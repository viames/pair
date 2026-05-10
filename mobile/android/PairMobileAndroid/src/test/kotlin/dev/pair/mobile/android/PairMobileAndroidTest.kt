package dev.pair.mobile.android

import java.nio.charset.StandardCharsets
import kotlinx.coroutines.async
import kotlinx.coroutines.coroutineScope
import kotlinx.coroutines.delay
import kotlinx.coroutines.runBlocking
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.jsonObject
import kotlinx.serialization.json.put
import kotlinx.serialization.json.putJsonArray
import kotlinx.serialization.json.putJsonObject
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotEquals
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

class PairMobileAndroidTest {

    @Test
    fun loginForcesRememberMeAndStoresBearerToken() = runBlocking {
        val transport = RecordingTransport()
        val client = PairApiClient(apiBaseUrl = "https://example.test/api/v1", transport = transport)
        val auth = PairAuthService(client = client, userSerializer = TestUser.serializer())
        transport.enqueue(
            statusCode = 200,
            body = """{"data":{"user":{"id":7,"email":"mario@example.test","name":"Mario Rossi"},"access_token":"plain-token","refresh_token":"refresh-token","expires_in":900}}"""
        )

        val session = auth.login(
            email = "mario@example.test",
            password = "password",
            extraPayload = buildJsonObject {
                put("remember_me", false)
                put("tenant", "crotone")
            }
        )

        val request = transport.requests.single()
        val body = decodedBody(request)

        assertEquals("https://example.test/api/v1/auth/login", request.url)
        assertEquals("POST", request.method)
        assertEquals(JsonPrimitive(true), body["remember_me"])
        assertEquals(JsonPrimitive("crotone"), body["tenant"])
        assertEquals("plain-token", session.accessToken)
        assertEquals("refresh-token", session.refreshToken)
        assertTrue(session.expiresAtEpochSeconds > PairClock.epochSeconds())
        assertEquals("plain-token", client.currentBearerToken())
    }

    @Test
    fun registerForcesRememberMeAndKeepsPrivacyFlag() = runBlocking {
        val transport = RecordingTransport()
        val client = PairApiClient(apiBaseUrl = "https://example.test/api/v1", transport = transport)
        val auth = PairAuthService(client = client, userSerializer = TestUser.serializer())
        transport.enqueue(
            statusCode = 200,
            body = """{"data":{"user":{"id":8,"email":"luisa@example.test","name":"Luisa Bianchi"},"access_token":"register-token","expires_at":1811338500}}"""
        )

        val session = auth.register(
            name = "Luisa Bianchi",
            email = "luisa@example.test",
            password = "password",
            privacyAccepted = true,
            extraPayload = buildJsonObject {
                put("remember_me", false)
            }
        )

        val body = decodedBody(transport.requests.single())

        assertEquals(JsonPrimitive(true), body["remember_me"])
        assertEquals(JsonPrimitive(true), body["privacy_accepted"])
        assertEquals("register-token", session.accessToken)
        assertEquals(1_811_338_500L, session.expiresAtEpochSeconds)
        assertEquals("register-token", client.currentBearerToken())
    }

    @Test
    fun refreshPostsRefreshTokenAndStoresBearerToken() = runBlocking {
        val transport = RecordingTransport()
        val client = PairApiClient(apiBaseUrl = "https://example.test/api/v1", transport = transport)
        val auth = PairAuthService(client = client, userSerializer = TestUser.serializer())
        transport.enqueue(
            statusCode = 200,
            body = """{"data":{"user":{"id":9,"email":"refresh@example.test","name":"Refresh User"},"access_token":"new-access","refresh_token":"new-refresh","expires_in":900}}"""
        )

        val session = auth.refresh("old-refresh")

        val request = transport.requests.single()
        val body = decodedBody(request)

        assertEquals("https://example.test/api/v1/auth/refresh", request.url)
        assertEquals(JsonPrimitive("old-refresh"), body["refresh_token"])
        assertEquals("new-access", session.accessToken)
        assertEquals("new-refresh", session.refreshToken)
        assertEquals("new-access", client.currentBearerToken())
    }

    @Test
    fun logoutCanPostRefreshTokenAndClearBearerToken() = runBlocking {
        val transport = RecordingTransport()
        val client = PairApiClient(
            apiBaseUrl = "https://example.test/api/v1",
            transport = transport,
            bearerToken = "access-token"
        )
        val auth = PairAuthService(client = client, userSerializer = TestUser.serializer())
        transport.enqueue(statusCode = 200, body = """{"data":{}}""")

        auth.logout(refreshToken = "refresh-token")

        val request = transport.requests.single()
        val body = decodedBody(request)

        assertEquals("https://example.test/api/v1/auth/logout", request.url)
        assertEquals("Bearer access-token", request.headers["Authorization"])
        assertEquals(JsonPrimitive("refresh-token"), body["refresh_token"])
        assertNull(client.currentBearerToken())
    }

    @Test
    fun apiClientBuildsQueryAuthorizationAndDoesNotSetCookies() = runBlocking {
        val transport = RecordingTransport()
        val client = PairApiClient(
            apiBaseUrl = "https://example.test/api/v1",
            transport = transport,
            bearerToken = "existing-token"
        )
        transport.enqueue(statusCode = 200, body = """{"data":{"id":42}}""")

        val response = client.sendData(
            path = "/items/search",
            queryParameters = mapOf("q" to "city hall", "page" to "2"),
            deserializer = TestIdentifier.serializer()
        )

        val request = transport.requests.single()

        assertEquals(42, response.id)
        assertEquals("Bearer existing-token", request.headers["Authorization"])
        assertFalse(request.headers.keys.any { it.equals("Cookie", ignoreCase = true) })
        assertEquals("https://example.test/api/v1/items/search?q=city+hall&page=2", request.url)
    }

    @Test
    fun clientInvalidatesBearerTokenOnUnauthorizedResponse() = runBlocking {
        val transport = RecordingTransport()
        val recorder = InvalidationRecorder()
        val client = PairApiClient(
            apiBaseUrl = "https://example.test/api/v1",
            transport = transport,
            bearerToken = "expired-token",
            authenticationInvalidationHandler = recorder::record
        )
        transport.enqueue(
            statusCode = 401,
            body = """{"error":{"code":"unauthorized","message":"Token expired"}}"""
        )

        val result = runCatching {
            client.sendData(path = "auth/me", deserializer = PairCurrentUserResponse.serializer(TestUser.serializer()))
        }

        assertTrue(result.exceptionOrNull() is PairApiException.Server)
        assertNull(client.currentBearerToken())
        assertEquals(1, recorder.count)
    }

    @Test
    fun pairJsonPayloadSupportsNestedExtraFields() {
        val payload = pairJsonPayload {
            put("tenant", "crotone")
            putJsonObject("metadata") {
                put("source", "android")
                putJsonArray("tags") {
                    add(JsonPrimitive("mobile"))
                    add(JsonPrimitive("pair"))
                }
            }
        }

        val metadata = payload["metadata"]!!.jsonObject

        assertEquals(JsonPrimitive("crotone"), payload["tenant"])
        assertEquals(JsonPrimitive("android"), metadata["source"])
    }

    @Test
    fun authSessionManagerBootstrapHandlesMissingValidOfflineAndInvalidatedStates() = runBlocking {
        val missingStore = PairInMemorySessionStore<PairStoredAuthSession<TestUser>>()
        val missingManager = PairAuthSessionManager(store = missingStore, nowEpochSeconds = { managedNow })

        val missing = missingManager.bootstrap(validate = { it }, refresh = { it })
        assertTrue(missing is PairAuthSessionManagerResult.Missing)

        val snapshot = managedSnapshot(accessToken = "valid-token", expiresAtEpochSeconds = managedNow + 600)
        val store = PairInMemorySessionStore(snapshot)
        val manager = PairAuthSessionManager(store = store, nowEpochSeconds = { managedNow })

        val valid = manager.bootstrap(validate = { saved ->
            saved.copy(accessToken = "validated-token")
        }, refresh = {
            throw AssertionError("Refresh should not run for a valid token.")
        })

        assertTrue(valid is PairAuthSessionManagerResult.Valid)
        assertEquals("validated-token", (valid as PairAuthSessionManagerResult.Valid).session.accessToken)
        assertEquals("validated-token", store.load()?.accessToken)

        store.save(snapshot)
        val offline = manager.bootstrap(validate = {
            throw PairApiException.Transport(java.io.IOException("offline"))
        }, refresh = { it })

        assertTrue(offline is PairAuthSessionManagerResult.Offline)
        assertEquals("valid-token", (offline as PairAuthSessionManagerResult.Offline).session.accessToken)
        assertEquals("valid-token", store.load()?.accessToken)

        store.save(snapshot)
        val invalidated = manager.bootstrap(validate = {
            throw PairApiException.Server(statusCode = 401, payload = null)
        }, refresh = { it })

        assertTrue(invalidated is PairAuthSessionManagerResult.Invalidated)
        assertNull(store.load())
    }

    @Test
    fun authSessionManagerValidTokenDoesNotCallRefresh() = runBlocking {
        val snapshot = managedSnapshot(accessToken = "still-valid", expiresAtEpochSeconds = managedNow + 600)
        val store = PairInMemorySessionStore(snapshot)
        val manager = PairAuthSessionManager(store = store, nowEpochSeconds = { managedNow })
        val recorder = RefreshRecorder()

        val result = manager.validAccessToken { saved ->
            recorder.refresh(saved, accessToken = "unused-token")
        }

        assertTrue(result is PairAccessTokenResult.Valid)
        assertEquals("still-valid", (result as PairAccessTokenResult.Valid).accessToken)
        assertEquals(0, recorder.calls)
    }

    @Test
    fun authSessionManagerExpiredTokenCallsRefresh() = runBlocking {
        val snapshot = managedSnapshot(accessToken = "expired-token", expiresAtEpochSeconds = managedNow - 1)
        val store = PairInMemorySessionStore(snapshot)
        val manager = PairAuthSessionManager(store = store, nowEpochSeconds = { managedNow })
        val recorder = RefreshRecorder()

        val result = manager.validAccessToken { saved ->
            recorder.refresh(saved, accessToken = "refreshed-token", refreshToken = "rotated-refresh")
        }

        assertTrue(result is PairAccessTokenResult.Valid)
        assertEquals("refreshed-token", (result as PairAccessTokenResult.Valid).accessToken)
        assertEquals("rotated-refresh", result.session.refreshToken)
        assertEquals(1, recorder.calls)
        assertEquals("refreshed-token", store.load()?.accessToken)
    }

    @Test
    fun authSessionManagerConcurrentRefreshesAreCoalesced() = runBlocking {
        val snapshot = managedSnapshot(accessToken = "expired-token", expiresAtEpochSeconds = managedNow - 1)
        val store = PairInMemorySessionStore(snapshot)
        val manager = PairAuthSessionManager(store = store, nowEpochSeconds = { managedNow })
        val recorder = RefreshRecorder(delayMillis = 50)

        coroutineScope {
            val first = async {
                manager.validAccessToken { saved -> recorder.refresh(saved, accessToken = "shared-token") }
            }
            val second = async {
                manager.validAccessToken { saved -> recorder.refresh(saved, accessToken = "shared-token") }
            }
            val third = async {
                manager.validAccessToken { saved -> recorder.refresh(saved, accessToken = "shared-token") }
            }

            listOf(first.await(), second.await(), third.await()).forEach { result ->
                assertTrue(result is PairAccessTokenResult.Valid)
                assertEquals("shared-token", (result as PairAccessTokenResult.Valid).accessToken)
            }
        }

        assertEquals(1, recorder.calls)
        assertEquals("shared-token", store.load()?.accessToken)
    }

    @Test
    fun authSessionManagerRefreshNetworkFailureKeepsSnapshot() = runBlocking {
        val snapshot = managedSnapshot(accessToken = "expired-token", expiresAtEpochSeconds = managedNow - 1)
        val store = PairInMemorySessionStore(snapshot)
        val manager = PairAuthSessionManager(store = store, nowEpochSeconds = { managedNow })

        val result = manager.validAccessToken {
            throw PairApiException.Transport(java.io.IOException("offline"))
        }

        assertTrue(result is PairAccessTokenResult.Offline)
        assertEquals("expired-token", (result as PairAccessTokenResult.Offline).session.accessToken)
        assertEquals("expired-token", store.load()?.accessToken)
    }

    @Test
    fun authSessionManagerRefreshAuthFailureClearsSnapshot() = runBlocking {
        val snapshot = managedSnapshot(accessToken = "expired-token", expiresAtEpochSeconds = managedNow - 1)
        val store = PairInMemorySessionStore(snapshot)
        val manager = PairAuthSessionManager(store = store, nowEpochSeconds = { managedNow })

        val result = manager.validAccessToken {
            throw PairApiException.Server(statusCode = 401, payload = null)
        }

        assertTrue(result is PairAccessTokenResult.Invalidated)
        assertNull(store.load())
    }

    @Test
    fun authSessionDecodesIsoExpiresAt() = runBlocking {
        val transport = RecordingTransport()
        val client = PairApiClient(apiBaseUrl = "https://example.test/api/v1", transport = transport)
        val auth = PairAuthService(client = client, userSerializer = TestUser.serializer())
        transport.enqueue(
            statusCode = 200,
            body = """{"data":{"user":{"id":10,"email":"iso@example.test","name":"ISO User"},"access_token":"iso-token","refresh_token":"iso-refresh","expires_at":"2027-05-10T10:00:00Z"}}"""
        )

        val session = auth.login(email = "iso@example.test", password = "password")

        assertEquals("iso-token", session.accessToken)
        assertEquals("iso-refresh", session.refreshToken)
        assertNotEquals(0L, session.expiresAtEpochSeconds)
    }

    private fun decodedBody(request: PairHttpRequest): JsonObject {
        val body = requireNotNull(request.body)

        return Json.parseToJsonElement(body.toString(StandardCharsets.UTF_8)).jsonObject
    }
}

@Serializable
private data class TestUser(
    val id: Int,
    val email: String,
    val name: String
)

@Serializable
private data class TestIdentifier(
    val id: Int
)

private const val managedNow = 1_811_337_600L

private fun managedSnapshot(
    accessToken: String,
    refreshToken: String? = "refresh-token",
    expiresAtEpochSeconds: Long = managedNow + 600
): PairStoredAuthSession<TestUser> =
    PairStoredAuthSession(
        user = TestUser(id = 15, email = "managed@example.test", name = "Managed"),
        accessToken = accessToken,
        refreshToken = refreshToken,
        expiresAtEpochSeconds = expiresAtEpochSeconds,
        context = JsonPrimitive("crotone")
    )

private class RefreshRecorder(private val delayMillis: Long = 0) {
    var calls = 0
        private set

    /** Records a refresh and returns a rotated session snapshot. */
    suspend fun refresh(
        session: PairStoredAuthSession<TestUser>,
        accessToken: String,
        refreshToken: String? = "refresh-token"
    ): PairStoredAuthSession<TestUser> {
        calls++

        if (delayMillis > 0) {
            delay(delayMillis)
        }

        return session.copy(
            accessToken = accessToken,
            refreshToken = refreshToken,
            expiresAtEpochSeconds = managedNow + 900
        )
    }
}

private class RecordingTransport : PairHttpTransport {
    val requests = mutableListOf<PairHttpRequest>()
    private val responses = ArrayDeque<PairHttpResponse>()

    /** Queues a fake HTTP response for the next request. */
    fun enqueue(statusCode: Int, body: String) {
        responses.addLast(PairHttpResponse(statusCode = statusCode, body = body.toByteArray(StandardCharsets.UTF_8)))
    }

    /** Records the request and returns the queued response. */
    override suspend fun perform(request: PairHttpRequest): PairHttpResponse {
        requests.add(request)

        return responses.removeFirstOrNull() ?: throw PairApiException.InvalidResponse
    }
}

private class InvalidationRecorder {
    private val errors = mutableListOf<PairApiException>()

    val count: Int
        get() = errors.count()

    /** Records a session invalidation error. */
    fun record(error: PairApiException) {
        errors.add(error)
    }
}
