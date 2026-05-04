package dev.pair.mobile.android

import kotlinx.serialization.Serializable

/** Normalized error payload returned by Pair API endpoints. */
@Serializable
data class PairApiErrorPayload(
    val code: String? = null,
    val message: String? = null,
    val details: Map<String, List<String>>? = null
)

/** Common client errors for Pair-based Android apps. */
sealed class PairApiException(message: String? = null, cause: Throwable? = null) : Exception(message, cause) {

    /** The configured API base URL or a request URL is invalid. */
    data object InvalidUrl : PairApiException("Invalid Pair API URL.")

    /** The transport returned a response that cannot be consumed safely. */
    data object InvalidResponse : PairApiException("Invalid Pair API response.")

    /** The backend returned a non-successful HTTP status code. */
    data class Server(
        val statusCode: Int,
        val payload: PairApiErrorPayload?
    ) : PairApiException(payload?.message ?: "Pair API request failed with HTTP $statusCode.")

    /** The response body does not match the expected JSON contract. */
    class Decoding(cause: Throwable) : PairApiException("Pair API response decoding failed.", cause)

    /** The underlying HTTP transport failed before a valid HTTP response was available. */
    class Transport(cause: Throwable) : PairApiException("Pair API transport failed.", cause)

    /** Indicates whether the backend rejected the current Bearer session. */
    val isAuthenticationFailure: Boolean
        get() = this is Server && statusCode == 401
}

@Serializable
internal data class PairApiErrorEnvelope(
    val error: PairApiErrorPayload? = null
)

