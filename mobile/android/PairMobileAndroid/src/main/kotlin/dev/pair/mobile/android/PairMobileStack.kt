package dev.pair.mobile.android

import android.content.Context
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import kotlinx.serialization.KSerializer
import kotlinx.serialization.json.JsonElement
import kotlinx.serialization.json.JsonPrimitive
import okhttp3.OkHttpClient

/** Convenience facade that wires the default Android Pair mobile components. */
class PairMobileStack<User> private constructor(
    val client: PairApiClient,
    val auth: PairAuthService<User>,
    val sessionStore: PairSessionStore<PairStoredAuthSession<User>>,
    val sessionManager: PairAuthSessionManager<User>,
    val images: PairRemoteImageClient,
    private val okHttpClient: OkHttpClient
) {

    /** Builds a stored session snapshot with an optional default context. */
    fun storedSession(
        session: PairAuthSession<User>,
        context: JsonElement? = null
    ): PairStoredAuthSession<User> = PairStoredAuthSession(session = session, context = context)

    /** Builds a stored session snapshot with a string default context. */
    fun storedSession(
        session: PairAuthSession<User>,
        context: String
    ): PairStoredAuthSession<User> = storedSession(session = session, context = JsonPrimitive(context))

    /** Restores the saved session, refreshes stale tokens, and validates it with `/auth/me`. */
    suspend fun bootstrapWithCurrentUser(): PairAuthSessionManagerResult<PairStoredAuthSession<User>> =
        sessionManager.bootstrap(validate = { saved ->
            client.setBearerToken(saved.accessToken)
            val currentUser = auth.currentUser().user

            saved.copy(user = currentUser)
        }, refresh = { expired ->
            val refreshToken = expired.refreshToken ?: throw PairApiException.Server(statusCode = 401, payload = null)
            val refreshed = auth.refresh(refreshToken)

            PairStoredAuthSession(session = refreshed, context = expired.context)
        })

    /** Logs out remotely when possible, then clears local auth state and shared caches. */
    suspend fun logoutAndClear() {
        val snapshot = sessionStore.load()

        runCatching {
            auth.logout(refreshToken = snapshot?.refreshToken)
        }

        client.setBearerToken(null)
        sessionStore.clear()
        clearHttpCache()
    }

    /** Clears the HTTP cache used by API calls and the remote image client. */
    suspend fun clearHttpCache() {
        withContext(Dispatchers.IO) {
            okHttpClient.cache?.evictAll()
        }
    }

    companion object {

        /** Creates the default Android Pair mobile stack for common apps. */
        fun <User> create(
            context: Context,
            apiBaseUrl: String,
            userSerializer: KSerializer<User>,
            preferencesName: String = "pair_mobile_session",
            sessionKey: String = "primary-session",
            authenticationInvalidationHandler: ((PairApiException) -> Unit)? = null
        ): PairMobileStack<User> {
            val okHttpClient = PairOkHttpClientFactory.create(context)
            val transport = PairOkHttpTransport(okHttpClient)
            val client = PairApiClient(
                apiBaseUrl = apiBaseUrl,
                transport = transport,
                authenticationInvalidationHandler = authenticationInvalidationHandler
            )
            val auth = PairAuthService(client = client, userSerializer = userSerializer)
            val sessionStore = PairSharedPreferencesSessionStore(
                context = context,
                preferencesName = preferencesName,
                key = sessionKey,
                serializer = PairStoredAuthSession.serializer(userSerializer)
            )
            val sessionManager = PairAuthSessionManager(sessionStore)
            val images = PairRemoteImageClient(transport)

            return PairMobileStack(
                client = client,
                auth = auth,
                sessionStore = sessionStore,
                sessionManager = sessionManager,
                images = images,
                okHttpClient = okHttpClient
            )
        }

        /** Creates a stack from explicit components for custom storage or transport needs. */
        fun <User> create(
            client: PairApiClient,
            userSerializer: KSerializer<User>,
            sessionStore: PairSessionStore<PairStoredAuthSession<User>>,
            imageTransport: PairHttpTransport,
            okHttpClient: OkHttpClient = PairOkHttpClientFactory.create()
        ): PairMobileStack<User> {
            val auth = PairAuthService(client = client, userSerializer = userSerializer)

            return PairMobileStack(
                client = client,
                auth = auth,
                sessionStore = sessionStore,
                sessionManager = PairAuthSessionManager(sessionStore),
                images = PairRemoteImageClient(imageTransport),
                okHttpClient = okHttpClient
            )
        }
    }
}
