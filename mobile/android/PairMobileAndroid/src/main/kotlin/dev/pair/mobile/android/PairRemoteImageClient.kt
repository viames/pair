package dev.pair.mobile.android

import android.graphics.Bitmap
import android.graphics.BitmapFactory
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext

/** Remote image client that reuses the configured HTTP transport and its cache. */
class PairRemoteImageClient(
    private val transport: PairHttpTransport
) {

    /** Downloads image bytes through the shared cookie-free transport. */
    suspend fun bytes(url: String): ByteArray {
        val response = transport.perform(
            PairHttpRequest(
                url = url,
                method = "GET",
                headers = mapOf("Accept" to "image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8")
            )
        )

        if (response.statusCode !in 200..299) {
            throw PairApiException.Server(statusCode = response.statusCode, payload = null)
        }

        return response.body
    }

    /** Downloads and decodes an image as an Android Bitmap. */
    suspend fun bitmap(url: String): Bitmap {
        val data = bytes(url)

        return withContext(Dispatchers.Default) {
            BitmapFactory.decodeByteArray(data, 0, data.size) ?: throw PairApiException.InvalidResponse
        }
    }
}
