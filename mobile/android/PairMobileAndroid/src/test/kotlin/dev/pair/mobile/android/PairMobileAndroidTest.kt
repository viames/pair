package dev.pair.mobile.android

import java.nio.charset.StandardCharsets
import kotlinx.coroutines.runBlocking
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.jsonObject
import kotlinx.serialization.json.put
import kotlinx.serialization.json.putJsonArray
import kotlinx.serialization.json.putJsonObject
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
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
            body = """{"data":{"user":{"id":7,"email":"mario@example.test","name":"Mario Rossi"},"token":"plain-token"}}"""
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
        assertEquals("plain-token", session.token)
        assertEquals("plain-token", client.currentBearerToken())
    }

    @Test
    fun registerForcesRememberMeAndKeepsPrivacyFlag() = runBlocking {
        val transport = RecordingTransport()
        val client = PairApiClient(apiBaseUrl = "https://example.test/api/v1", transport = transport)
        val auth = PairAuthService(client = client, userSerializer = TestUser.serializer())
        transport.enqueue(
            statusCode = 200,
            body = """{"data":{"user":{"id":8,"email":"luisa@example.test","name":"Luisa Bianchi"},"token":"register-token"}}"""
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
        assertEquals("register-token", session.token)
        assertEquals("register-token", client.currentBearerToken())
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
    fun sessionBootstrapperHandlesRestoredInvalidatedAndOfflineStates() = runBlocking {
        val snapshot = PairStoredAuthSession(
            session = PairAuthSession(
                user = TestUser(id = 7, email = "mario@example.test", name = "Mario Rossi"),
                token = "boot-token"
            ),
            defaultContext = JsonPrimitive("crotone")
        )
        val store = PairInMemorySessionStore(snapshot)
        val bootstrapper = PairSessionBootstrapper(store)

        val restored = bootstrapper.bootstrap { saved ->
            saved.copy(session = saved.session.copy(token = "fresh-token"))
        }
        assertTrue(restored is PairSessionBootstrapResult.Restored)
        assertEquals("fresh-token", (restored as PairSessionBootstrapResult.Restored).snapshot.session.token)

        store.save(snapshot)
        val invalidated = bootstrapper.bootstrap {
            throw PairApiException.Server(statusCode = 401, payload = null)
        }
        assertTrue(invalidated is PairSessionBootstrapResult.Invalidated)
        assertNull(store.load())

        store.save(snapshot)
        val offline = bootstrapper.bootstrap {
            throw PairApiException.Transport(java.io.IOException("offline"))
        }
        assertTrue(offline is PairSessionBootstrapResult.Offline)
        assertEquals("boot-token", (offline as PairSessionBootstrapResult.Offline).snapshot.session.token)
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
