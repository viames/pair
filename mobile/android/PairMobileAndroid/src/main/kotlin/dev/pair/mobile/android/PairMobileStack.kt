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
    val bootstrapper: PairSessionBootstrapper<PairStoredAuthSession<User>>,
    val images: PairRemoteImageClient,
    private val okHttpClient: OkHttpClient
) {

    /** Builds a stored session snapshot with an optional default context. */
    fun storedSession(
        session: PairAuthSession<User>,
        defaultContext: JsonElement? = null
    ): PairStoredAuthSession<User> = PairStoredAuthSession(session = session, defaultContext = defaultContext)

    /** Builds a stored session snapshot with a string default context. */
    fun storedSession(
        session: PairAuthSession<User>,
        defaultContext: String
    ): PairStoredAuthSession<User> = storedSession(session = session, defaultContext = JsonPrimitive(defaultContext))

    /** Restores the saved session and validates it with the default `/auth/me` response shape. */
    suspend fun bootstrapWithCurrentUser(): PairSessionBootstrapResult<PairStoredAuthSession<User>> =
        bootstrapper.bootstrap { saved ->
            client.setBearerToken(saved.session.token)
            val currentUser = auth.currentUser().user

            saved.copy(session = saved.session.copy(user = currentUser))
        }

    /** Logs out remotely when possible, then clears local auth state and shared caches. */
    suspend fun logoutAndClear() {
        runCatching {
            auth.logout()
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
            val bootstrapper = PairSessionBootstrapper(sessionStore)
            val images = PairRemoteImageClient(transport)

            return PairMobileStack(
                client = client,
                auth = auth,
                sessionStore = sessionStore,
                bootstrapper = bootstrapper,
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
                bootstrapper = PairSessionBootstrapper(sessionStore),
                images = PairRemoteImageClient(imageTransport),
                okHttpClient = okHttpClient
            )
        }
    }
}

