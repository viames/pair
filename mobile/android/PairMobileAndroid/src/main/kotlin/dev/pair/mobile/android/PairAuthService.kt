package dev.pair.mobile.android

import kotlinx.serialization.KSerializer
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonObjectBuilder
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.put

/** Pair mobile auth service: `remember_me` is always enabled and not exposed to users. */
class PairAuthService<User>(
    private val client: PairApiClient,
    private val userSerializer: KSerializer<User>
) {

    /** Performs email/password login and stores the Bearer token in the client. */
    suspend fun login(
        email: String,
        password: String,
        extraPayload: JsonObject = JsonObject(emptyMap())
    ): PairAuthSession<User> {
        val session = client.sendData(
            path = "auth/login",
            method = "POST",
            body = loginPayload(email = email, password = password, extraPayload = extraPayload),
            deserializer = PairAuthSession.serializer(userSerializer)
        )

        client.setBearerToken(session.token)
        return session
    }

    /** Performs mobile registration and stores the Bearer token in the client. */
    suspend fun register(
        name: String,
        email: String,
        password: String,
        privacyAccepted: Boolean,
        extraPayload: JsonObject = JsonObject(emptyMap())
    ): PairAuthSession<User> {
        val session = client.sendData(
            path = "auth/register",
            method = "POST",
            body = registerPayload(
                name = name,
                email = email,
                password = password,
                privacyAccepted = privacyAccepted,
                extraPayload = extraPayload
            ),
            deserializer = PairAuthSession.serializer(userSerializer)
        )

        client.setBearerToken(session.token)
        return session
    }

    /** Reads the current `/auth/me` payload as a project-specific response. */
    suspend fun <Response> currentAuthentication(responseSerializer: KSerializer<Response>): Response =
        client.sendData(path = "auth/me", deserializer = responseSerializer)

    /** Reads the current `/auth/me` payload when it follows the default current-user shape. */
    suspend fun currentUser(): PairCurrentUserResponse<User> =
        currentAuthentication(PairCurrentUserResponse.serializer(userSerializer))

    /** Revokes the current token and clears the local Bearer token. */
    suspend fun logout(): PairEmptyResponse {
        val response = client.sendData(
            path = "auth/logout",
            method = "POST",
            body = buildJsonObject {},
            deserializer = PairEmptyResponse.serializer()
        )

        client.setBearerToken(null)
        return response
    }

    /** Builds login JSON while forcing `remember_me=true` after project-specific fields. */
    private fun loginPayload(email: String, password: String, extraPayload: JsonObject): JsonObject =
        buildJsonObject {
            addExtraPayload(extraPayload)
            put("email", email)
            put("password", password)
            put("remember_me", true)
        }

    /** Builds registration JSON while forcing `remember_me=true` after project-specific fields. */
    private fun registerPayload(
        name: String,
        email: String,
        password: String,
        privacyAccepted: Boolean,
        extraPayload: JsonObject
    ): JsonObject =
        buildJsonObject {
            addExtraPayload(extraPayload)
            put("name", name)
            put("email", email)
            put("password", password)
            put("privacy_accepted", privacyAccepted)
            put("remember_me", true)
        }

    /** Copies project-specific JSON fields except the protected `remember_me` key. */
    private fun JsonObjectBuilder.addExtraPayload(extraPayload: JsonObject) {
        for ((key, value) in extraPayload) {
            if (key != "remember_me") {
                put(key, value)
            }
        }
    }
}
