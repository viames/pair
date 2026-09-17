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
    ): Value = send(
        path = path,
        method = method,
        queryParameters = queryParameters,
        body = body,
        deserializer = deserializer,
        additionalHeaders = emptyMap()
    )

    /** Sends a JSON request with app-owned headers and decodes the expected response. */
    suspend fun <Value> send(
        path: String,
        method: String = "GET",
        queryParameters: Map<String, String?> = emptyMap(),
        body: JsonElement? = null,
        deserializer: KSerializer<Value>,
        additionalHeaders: Map<String, String>
    ): Value {
        val response = perform(
            path = path,
            method = method,
            queryParameters = queryParameters,
            body = body?.toString()?.toByteArray(StandardCharsets.UTF_8),
            contentType = body?.let { "application/json" },
            additionalHeaders = additionalHeaders
        )

        return decodeResponse(response, deserializer)
    }

    /** Sends a non-JSON request body such as multipart form data and decodes a JSON response. */
    suspend fun <Value> sendRawData(
        path: String,
        method: String,
        body: ByteArray,
        contentType: String,
        deserializer: KSerializer<Value>,
        additionalHeaders: Map<String, String> = emptyMap()
    ): Value {
        val response = perform(
            path = path,
            method = method,
            queryParameters = emptyMap(),
            body = body,
            contentType = contentType,
            additionalHeaders = additionalHeaders
        )
        return decodeResponse(response, PairDataEnvelope.serializer(deserializer)).data
    }

    /** Sends a request whose successful response is binary rather than a Pair JSON envelope. */
    suspend fun sendRawResponse(
        path: String,
        method: String = "GET",
        queryParameters: Map<String, String?> = emptyMap(),
        accept: String = "application/octet-stream",
        additionalHeaders: Map<String, String> = emptyMap()
    ): PairHttpResponse {
        val response = perform(
            path = path,
            method = method,
            queryParameters = queryParameters,
            body = null,
            contentType = null,
            additionalHeaders = additionalHeaders,
            accept = accept
        )
        requireSuccessfulResponse(response)
        return response
    }

    private suspend fun perform(
        path: String,
        method: String,
        queryParameters: Map<String, String?>,
        body: ByteArray?,
        contentType: String?,
        additionalHeaders: Map<String, String>,
        accept: String = "application/json"
    ): PairHttpResponse = transport.perform(
        PairHttpRequest(
            url = buildUrl(path = path, queryParameters = queryParameters),
            method = method,
            headers = buildHeaders(contentType = contentType, additionalHeaders = additionalHeaders, accept = accept),
            body = body
        )
    )

    private fun <Value> decodeResponse(response: PairHttpResponse, deserializer: KSerializer<Value>): Value {
        val responseBody = response.body.toString(StandardCharsets.UTF_8)
        requireSuccessfulResponse(response)

        return try {
            json.decodeFromString(deserializer, responseBody)
        } catch (error: SerializationException) {
            throw PairApiException.Decoding(error)
        } catch (error: IllegalArgumentException) {
            throw PairApiException.Decoding(error)
        }
    }

    private fun requireSuccessfulResponse(response: PairHttpResponse) {
        if (response.statusCode in 200..299) return
        val responseBody = response.body.toString(StandardCharsets.UTF_8)
        val apiError = PairApiException.Server(
            statusCode = response.statusCode,
            payload = runCatching {
                json.decodeFromString(PairApiErrorEnvelope.serializer(), responseBody).error
            }.getOrNull()
        )
        notifyAuthenticationInvalidationIfNeeded(apiError)
        throw apiError
    }

    /** Sends a JSON request and returns the Pair envelope `data` field directly. */
    suspend fun <Value> sendData(
        path: String,
        method: String = "GET",
        queryParameters: Map<String, String?> = emptyMap(),
        body: JsonElement? = null,
        deserializer: KSerializer<Value>
    ): Value = sendData(
        path = path,
        method = method,
        queryParameters = queryParameters,
        body = body,
        deserializer = deserializer,
        additionalHeaders = emptyMap()
    )

    /** Sends a JSON request with app-owned headers and returns the Pair envelope `data` field. */
    suspend fun <Value> sendData(
        path: String,
        method: String = "GET",
        queryParameters: Map<String, String?> = emptyMap(),
        body: JsonElement? = null,
        deserializer: KSerializer<Value>,
        additionalHeaders: Map<String, String>
    ): Value {
        val envelope: PairDataEnvelope<Value> = send(
            path = path,
            method = method,
            queryParameters = queryParameters,
            body = body,
            deserializer = PairDataEnvelope.serializer(deserializer),
            additionalHeaders = additionalHeaders
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

    /** Builds request headers while keeping authentication and representation headers client-owned. */
    private fun buildHeaders(
        contentType: String?,
        additionalHeaders: Map<String, String>,
        accept: String
    ): Map<String, String> {
        val acceptedType = accept.takeIf(String::isNotBlank) ?: "application/octet-stream"
        val headers = mutableMapOf("Accept" to acceptedType)

        currentToken?.let { token ->
            headers["Authorization"] = "Bearer $token"
        }

        contentType?.takeIf(String::isNotBlank)?.let { headers["Content-Type"] = it }

        additionalHeaders.forEach { (name, value) ->
            if (name.lowercase() !in protectedHeaderNames && value.isNotBlank()) {
                headers[name] = value
            }
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

    private companion object {
        val protectedHeaderNames = setOf("accept", "authorization", "content-type", "cookie")
    }
}
