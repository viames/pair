package dev.pair.mobile.android

/** Transport-level request used by the Pair API client. */
data class PairHttpRequest(
    val url: String,
    val method: String,
    val headers: Map<String, String> = emptyMap(),
    val body: ByteArray? = null
)

/** Transport-level response returned to the Pair API client. */
data class PairHttpResponse(
    val statusCode: Int,
    val headers: Map<String, String> = emptyMap(),
    val body: ByteArray = ByteArray(0)
)

/** HTTP transport that host apps and tests can replace. */
fun interface PairHttpTransport {

    /** Performs the HTTP request and returns the raw HTTP response. */
    suspend fun perform(request: PairHttpRequest): PairHttpResponse
}

