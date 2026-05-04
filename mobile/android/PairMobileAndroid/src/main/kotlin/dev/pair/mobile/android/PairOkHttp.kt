package dev.pair.mobile.android

import android.content.Context
import java.io.File
import java.util.concurrent.TimeUnit
import kotlin.coroutines.resume
import kotlin.coroutines.resumeWithException
import kotlinx.coroutines.suspendCancellableCoroutine
import okhttp3.Cache
import okhttp3.Call
import okhttp3.Callback
import okhttp3.CookieJar
import okhttp3.Interceptor
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import okhttp3.Response

/** Factory for the default Android OkHttp client used by Pair mobile apps. */
object PairOkHttpClientFactory {

    /** Creates a cookie-free OkHttp client using app cache storage. */
    fun create(
        context: Context,
        cacheDirectoryName: String = "pair-http-cache",
        cacheSizeBytes: Long = 160L * 1024L * 1024L
    ): OkHttpClient {
        val cacheDirectory = File(context.applicationContext.cacheDir, cacheDirectoryName)

        return create(cacheDirectory = cacheDirectory, cacheSizeBytes = cacheSizeBytes)
    }

    /** Creates a cookie-free OkHttp client using an optional explicit cache directory. */
    fun create(
        cacheDirectory: File? = null,
        cacheSizeBytes: Long = 160L * 1024L * 1024L
    ): OkHttpClient {
        val builder = OkHttpClient.Builder()
            .cookieJar(CookieJar.NO_COOKIES)
            .addInterceptor(PairNoCookieInterceptor)
            .connectTimeout(30, TimeUnit.SECONDS)
            .readTimeout(30, TimeUnit.SECONDS)
            .writeTimeout(30, TimeUnit.SECONDS)
            .callTimeout(90, TimeUnit.SECONDS)

        if (cacheDirectory != null) {
            builder.cache(Cache(cacheDirectory, cacheSizeBytes))
        }

        return builder.build()
    }
}

/** OkHttp-backed transport for Pair API requests. */
class PairOkHttpTransport(
    private val client: OkHttpClient
) : PairHttpTransport {

    /** Performs a Pair transport request with JSON defaults and without cookies. */
    override suspend fun perform(request: PairHttpRequest): PairHttpResponse {
        val requestBuilder = Request.Builder().url(request.url)

        for ((name, value) in request.headers) {
            if (!name.equals("Cookie", ignoreCase = true)) {
                requestBuilder.header(name, value)
            }
        }

        val requestBody = request.body?.toRequestBody("application/json".toMediaType())
        val okHttpRequest = requestBuilder
            .removeHeader("Cookie")
            .method(request.method, requestBody)
            .build()

        return try {
            client.newCall(okHttpRequest).await().use { response ->
                PairHttpResponse(
                    statusCode = response.code,
                    headers = response.headers.toMultimap().mapValues { (_, values) -> values.joinToString(",") },
                    body = response.body?.bytes() ?: ByteArray(0)
                )
            }
        } catch (error: PairApiException) {
            throw error
        } catch (error: Throwable) {
            throw PairApiException.Transport(error)
        }
    }
}

private object PairNoCookieInterceptor : Interceptor {

    /** Removes web cookie headers from requests and responses. */
    override fun intercept(chain: Interceptor.Chain): Response {
        val request = chain.request().newBuilder()
            .removeHeader("Cookie")
            .build()

        return chain.proceed(request).newBuilder()
            .removeHeader("Set-Cookie")
            .build()
    }
}

private suspend fun Call.await(): Response = suspendCancellableCoroutine { continuation ->
    enqueue(
        object : Callback {

            /** Propagates transport failures to the suspended caller. */
            override fun onFailure(call: Call, e: java.io.IOException) {
                if (!continuation.isCancelled) {
                    continuation.resumeWithException(e)
                }
            }

            /** Resumes the suspended caller with the HTTP response. */
            override fun onResponse(call: Call, response: Response) {
                if (continuation.isCancelled) {
                    response.close()
                } else {
                    continuation.resume(response)
                }
            }
        }
    )

    continuation.invokeOnCancellation {
        cancel()
    }
}

