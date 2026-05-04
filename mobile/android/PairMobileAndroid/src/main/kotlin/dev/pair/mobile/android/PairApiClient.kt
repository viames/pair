package dev.pair.mobile.android

import java.net.URLEncoder
import java.nio.charset.StandardCharsets
import kotlinx.serialization.KSerializer
import kotlinx.serialization.SerializationException
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.JsonElement
import okhttp3.HttpUrl.Companion.toHttpUrlOrNull

/** Base API client for Pair mobile projects with Bearer tokens and `data` response envelopes. */
class PairApiClient(
    apiBaseUrl: String,
    private val transport: PairHttpTransport,
    private val json: Json = PairJson.default,
    bearerToken: String? = null,
    private val authenticationInvalidationHandler: ((PairApiException) -> Unit)? = null
) {
    private val normalizedApiBaseUrl = apiBaseUrl.trimEnd('/')

    @Volatile
    private var currentToken: String? = bearerToken

    /** Updates the Bearer token used by subsequent requests. */
    fun setBearerToken(token: String?) {
        currentToken = token
    }

    /** Reads the current Bearer token. */
    fun currentBearerToken(): String? = currentToken

    /** Sends a JSON request and decodes the expected response. */
    suspend fun <Value> send(
        path: String,
        method: String = "GET",
        queryParameters: Map<String, String?> = emptyMap(),
        body: JsonElement? = null,
        deserializer: KSerializer<Value>
    ): Value {
        val response = transport.perform(
            PairHttpRequest(
                url = buildUrl(path = path, queryParameters = queryParameters),
                method = method,
                headers = buildHeaders(hasBody = body != null),
                body = body?.toString()?.toByteArray(StandardCharsets.UTF_8)
            )
        )

        val responseBody = response.body.toString(StandardCharsets.UTF_8)

        if (response.statusCode !in 200..299) {
            val apiError = PairApiException.Server(
                statusCode = response.statusCode,
                payload = runCatching {
                    json.decodeFromString(PairApiErrorEnvelope.serializer(), responseBody).error
                }.getOrNull()
            )
            notifyAuthenticationInvalidationIfNeeded(apiError)
            throw apiError
        }

        return try {
            json.decodeFromString(deserializer, responseBody)
        } catch (error: SerializationException) {
            throw PairApiException.Decoding(error)
        } catch (error: IllegalArgumentException) {
            throw PairApiException.Decoding(error)
        }
    }

    /** Sends a JSON request and returns the Pair envelope `data` field directly. */
    suspend fun <Value> sendData(
        path: String,
        method: String = "GET",
        queryParameters: Map<String, String?> = emptyMap(),
        body: JsonElement? = null,
        deserializer: KSerializer<Value>
    ): Value {
        val envelope: PairDataEnvelope<Value> = send(
            path = path,
            method = method,
            queryParameters = queryParameters,
            body = body,
            deserializer = PairDataEnvelope.serializer(deserializer)
        )

        return envelope.data
    }

    /** Builds the final request URL while preserving any base path such as `/api/v1`. */
    private fun buildUrl(path: String, queryParameters: Map<String, String?>): String {
        if (normalizedApiBaseUrl.toHttpUrlOrNull() == null) {
            throw PairApiException.InvalidUrl
        }

        val cleanPath = path.trim('/')
        val url = buildString {
            append(normalizedApiBaseUrl)
            if (cleanPath.isNotEmpty()) {
                append('/')
                append(cleanPath.split('/').joinToString("/") { encodePathSegment(it) })
            }
        }

        val query = queryParameters
            .filterValues { it != null }
            .map { (name, value) -> "${encodeQuery(name)}=${encodeQuery(value.orEmpty())}" }
            .joinToString("&")

        return if (query.isEmpty()) url else "$url?$query"
    }

    /** Builds JSON request headers with Bearer authorization when available. */
    private fun buildHeaders(hasBody: Boolean): Map<String, String> {
        val headers = mutableMapOf("Accept" to "application/json")

        currentToken?.let { token ->
            headers["Authorization"] = "Bearer $token"
        }

        if (hasBody) {
            headers["Content-Type"] = "application/json"
        }

        return headers
    }

    /** Notifies the host app when the backend invalidates the Bearer session. */
    private fun notifyAuthenticationInvalidationIfNeeded(error: PairApiException) {
        if (error.isAuthenticationFailure) {
            setBearerToken(null)
            authenticationInvalidationHandler?.invoke(error)
        }
    }

    private fun encodePathSegment(value: String): String =
        URLEncoder.encode(value, StandardCharsets.UTF_8.name()).replace("+", "%20")

    private fun encodeQuery(value: String): String =
        URLEncoder.encode(value, StandardCharsets.UTF_8.name())
}
