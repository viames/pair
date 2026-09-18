package dev.pair.mobile.android

import android.content.Context
import androidx.credentials.CreatePublicKeyCredentialRequest
import androidx.credentials.CreatePublicKeyCredentialResponse
import androidx.credentials.CredentialManager
import androidx.credentials.GetCredentialRequest
import androidx.credentials.GetPublicKeyCredentialOption
import androidx.credentials.PublicKeyCredential
import kotlinx.serialization.KSerializer
import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import kotlinx.serialization.SerializationException
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.jsonObject
import kotlinx.serialization.json.put

/** Standard Pair response for a native passkey authentication or registration challenge. */
@Serializable
data class PairPasskeyOptions(
    @SerialName("flow_id")
    val flowId: String,
    val publicKey: JsonObject
)

/** Privacy-minimal passkey metadata returned by Pair account management. */
@Serializable
data class PairPasskey(
    val id: Int,
    val label: String? = null,
    @SerialName("created_at")
    val createdAt: String? = null,
    @SerialName("last_used_at")
    val lastUsedAt: String? = null,
    val transports: List<String> = emptyList()
)

@Serializable
private data class PairPasskeyList(
    val items: List<PairPasskey>
)

@Serializable
private data class PairPasskeyRegistrationResult(
    val passkey: PairPasskey
)

/**
 * Runs Android passkey ceremonies through Credential Manager.
 *
 * The supplied context must be an Activity context so Credential Manager can present its UI.
 */
class PairPasskeyClient(
    private val context: Context,
    private val json: Json = PairJson.default
) {
    private val credentialManager = CredentialManager.create(context)

    /** Present a discoverable passkey assertion and return the WebAuthn JSON credential. */
    suspend fun authenticate(publicKeyOptions: JsonObject): JsonObject {
        val option = GetPublicKeyCredentialOption(
            requestJson = json.encodeToString(JsonObject.serializer(), publicKeyOptions)
        )
        val response = credentialManager.getCredential(
            context = context,
            request = GetCredentialRequest(listOf(option))
        )
        val credential = response.credential as? PublicKeyCredential
            ?: throw PairApiException.InvalidResponse

        return decodeCredential(credential.authenticationResponseJson)
    }

    /** Present passkey creation and return the WebAuthn JSON credential. */
    suspend fun register(publicKeyOptions: JsonObject): JsonObject {
        val response = credentialManager.createCredential(
            context = context,
            request = CreatePublicKeyCredentialRequest(
                requestJson = json.encodeToString(JsonObject.serializer(), publicKeyOptions)
            )
        ) as? CreatePublicKeyCredentialResponse ?: throw PairApiException.InvalidResponse

        return decodeCredential(response.registrationResponseJson)
    }

    /** Parse the credential provider response without rewriting cancellation or provider errors. */
    private fun decodeCredential(responseJson: String): JsonObject = try {
        json.parseToJsonElement(responseJson).jsonObject
    } catch (error: SerializationException) {
        throw PairApiException.Decoding(error)
    } catch (error: IllegalArgumentException) {
        throw PairApiException.Decoding(error)
    }
}

/** End-to-end client for Pair's standard native passkey and account-management endpoints. */
class PairPasskeyService<User>(
    private val client: PairApiClient,
    private val userSerializer: KSerializer<User>,
    private val passkeyClient: PairPasskeyClient
) {

    /** Authenticate with a discoverable passkey and store the returned Bearer token. */
    suspend fun login(deviceName: String? = null): PairAuthSession<User> {
        val options = client.sendData(
            path = "auth/passkey/options",
            method = "POST",
            body = JsonObject(emptyMap()),
            deserializer = PairPasskeyOptions.serializer()
        )
        val credential = passkeyClient.authenticate(options.publicKey)
        val payload = client.sendData(
            path = "auth/passkey/verify",
            method = "POST",
            body = buildJsonObject {
                put("flow_id", options.flowId)
                put("credential", credential)
                if (!deviceName.isNullOrBlank()) {
                    put("device_name", deviceName)
                }
            },
            deserializer = PairAuthSessionPayload.serializer(userSerializer)
        )
        val session = payload.toSession()

        client.setBearerToken(session.accessToken)
        return session
    }

    /** List the current Bearer-authenticated user's active passkeys. */
    suspend fun passkeys(): List<PairPasskey> = client.sendData(
        path = "auth/passkeys",
        deserializer = PairPasskeyList.serializer()
    ).items

    /** Create a discoverable passkey for the current Bearer-authenticated user. */
    suspend fun register(displayName: String, label: String? = null): PairPasskey {
        val options = client.sendData(
            path = "auth/passkeys/options",
            method = "POST",
            body = buildJsonObject {
                put("display_name", displayName)
            },
            deserializer = PairPasskeyOptions.serializer()
        )
        val credential = passkeyClient.register(options.publicKey)
        val result = client.sendData(
            path = "auth/passkeys/verify",
            method = "POST",
            body = buildJsonObject {
                put("flow_id", options.flowId)
                put("credential", credential)
                if (!label.isNullOrBlank()) {
                    put("label", label)
                }
            },
            deserializer = PairPasskeyRegistrationResult.serializer()
        )

        return result.passkey
    }

    /** Revoke one passkey owned by the current user. */
    suspend fun revoke(id: Int): PairEmptyResponse = client.sendData(
        path = "auth/passkeys/$id",
        method = "DELETE",
        deserializer = PairEmptyResponse.serializer()
    )
}
