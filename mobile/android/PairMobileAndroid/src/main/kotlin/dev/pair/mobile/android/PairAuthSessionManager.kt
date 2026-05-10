package dev.pair.mobile.android

import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock

/** Bootstrap and token result for managed mobile authentication sessions. */
sealed class PairAuthSessionManagerResult<out Session> {

    /** The saved session was refreshed when needed and validated. */
    data class Valid<Session>(val session: Session) : PairAuthSessionManagerResult<Session>()

    /** No saved session exists. */
    data object Missing : PairAuthSessionManagerResult<Nothing>()

    /** The saved session is available locally, but remote validation could not complete. */
    data class Offline<Session>(val session: Session) : PairAuthSessionManagerResult<Session>()

    /** The backend rejected the saved session. */
    data object Invalidated : PairAuthSessionManagerResult<Nothing>()
}

/** Access-token result for callers that need an authorization header value. */
sealed class PairAccessTokenResult<out Session> {

    /** A usable access token is available. */
    data class Valid<Session>(val accessToken: String, val session: Session) : PairAccessTokenResult<Session>()

    /** No saved session exists. */
    data object Missing : PairAccessTokenResult<Nothing>()

    /** The saved session is available locally, but refresh could not complete. */
    data class Offline<Session>(val session: Session) : PairAccessTokenResult<Session>()

    /** The backend rejected the saved session. */
    data object Invalidated : PairAccessTokenResult<Nothing>()
}

/** Coordinates persistent mobile sessions, startup validation, and single-flight token refresh. */
class PairAuthSessionManager<User>(
    private val store: PairSessionStore<PairStoredAuthSession<User>>,
    private val refreshLeewaySeconds: Long = 60,
    private val nowEpochSeconds: () -> Long = PairClock::epochSeconds
) {
    private val refreshMutex = Mutex()

    /** Reads the persisted session snapshot. */
    suspend fun load(): PairStoredAuthSession<User>? = store.load()

    /** Saves a newly authenticated or externally migrated session snapshot. */
    suspend fun save(session: PairStoredAuthSession<User>) {
        store.save(session)
    }

    /** Clears the persisted session snapshot. */
    suspend fun clear() {
        store.clear()
    }

    /** Restores a session, refreshes stale tokens, and validates the result before app-private UI is shown. */
    suspend fun bootstrap(
        validate: suspend (PairStoredAuthSession<User>) -> PairStoredAuthSession<User>,
        refresh: suspend (PairStoredAuthSession<User>) -> PairStoredAuthSession<User>
    ): PairAuthSessionManagerResult<PairStoredAuthSession<User>> {
        val session = store.load() ?: return PairAuthSessionManagerResult.Missing

        return try {
            val tokenSession = sessionWithValidAccessToken(session, refresh)
            val validatedSession = validate(tokenSession)
            store.save(validatedSession)
            PairAuthSessionManagerResult.Valid(validatedSession)
        } catch (error: Throwable) {
            if (isDefinitiveAuthenticationFailure(error)) {
                store.clear()
                PairAuthSessionManagerResult.Invalidated
            } else {
                PairAuthSessionManagerResult.Offline(store.load() ?: session)
            }
        }
    }

    /** Returns a usable access token, refreshing once for all concurrent callers when needed. */
    suspend fun validAccessToken(
        refresh: suspend (PairStoredAuthSession<User>) -> PairStoredAuthSession<User>
    ): PairAccessTokenResult<PairStoredAuthSession<User>> {
        val session = store.load() ?: return PairAccessTokenResult.Missing

        return try {
            val tokenSession = sessionWithValidAccessToken(session, refresh)
            PairAccessTokenResult.Valid(tokenSession.accessToken, tokenSession)
        } catch (error: Throwable) {
            if (isDefinitiveAuthenticationFailure(error)) {
                store.clear()
                PairAccessTokenResult.Invalidated
            } else {
                PairAccessTokenResult.Offline(session)
            }
        }
    }

    /** Returns the input session when its token is usable or refreshes it through the shared lock. */
    private suspend fun sessionWithValidAccessToken(
        session: PairStoredAuthSession<User>,
        refresh: suspend (PairStoredAuthSession<User>) -> PairStoredAuthSession<User>
    ): PairStoredAuthSession<User> {
        if (session.hasUsableAccessToken(nowEpochSeconds(), refreshLeewaySeconds)) {
            return session
        }

        if (session.refreshToken.isNullOrBlank()) {
            throw MissingRefreshTokenException
        }

        return singleFlightRefresh(refresh)
    }

    /** Runs one refresh operation while concurrent callers wait and reuse the stored result. */
    private suspend fun singleFlightRefresh(
        refresh: suspend (PairStoredAuthSession<User>) -> PairStoredAuthSession<User>
    ): PairStoredAuthSession<User> = refreshMutex.withLock {
        val currentSession = store.load() ?: throw MissingRefreshTokenException

        if (currentSession.hasUsableAccessToken(nowEpochSeconds(), refreshLeewaySeconds)) {
            return@withLock currentSession
        }

        if (currentSession.refreshToken.isNullOrBlank()) {
            throw MissingRefreshTokenException
        }

        val refreshedSession = refresh(currentSession)
        store.save(refreshedSession)

        refreshedSession
    }

    /** Classifies only explicit auth failures as reasons to remove a local snapshot. */
    private fun isDefinitiveAuthenticationFailure(error: Throwable): Boolean =
        error is MissingRefreshTokenException || (error is PairApiException && error.isAuthenticationFailure)
}

private data object MissingRefreshTokenException : Exception("Missing refresh token.")
