package dev.pair.mobile.android

import java.text.SimpleDateFormat
import java.util.Locale
import java.util.TimeZone
import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.JsonElement
import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.doubleOrNull
import kotlinx.serialization.json.jsonPrimitive
import kotlinx.serialization.json.longOrNull

/** Standard Pair endpoint envelope for responses that return `data`. */
@Serializable
data class PairDataEnvelope<Value>(
    val data: Value
)

/** Standard mobile login, registration, and refresh response. */
@Serializable
data class PairAuthSession<User>(
    val user: User,
    @SerialName("access_token")
    val accessToken: String,
    @SerialName("refresh_token")
    val refreshToken: String? = null,
    @SerialName("expires_at_epoch_seconds")
    val expiresAtEpochSeconds: Long
) {

    /** Returns true when the access token remains outside the refresh leeway window. */
    fun hasUsableAccessToken(nowEpochSeconds: Long = PairClock.epochSeconds(), refreshLeewaySeconds: Long = 60): Boolean =
        expiresAtEpochSeconds - nowEpochSeconds > refreshLeewaySeconds
}

/** Standard `/auth/me` response when the project exposes only the current user. */
@Serializable
data class PairCurrentUserResponse<User>(
    val user: User
)

/** Minimal response for logout or mutations without application payload. */
@Serializable
data object PairEmptyResponse

/** Full snapshot to store locally, with optional context defined by the host app. */
@Serializable
data class PairStoredAuthSession<User>(
    val user: User,
    @SerialName("access_token")
    val accessToken: String,
    @SerialName("refresh_token")
    val refreshToken: String? = null,
    @SerialName("expires_at_epoch_seconds")
    val expiresAtEpochSeconds: Long,
    val context: JsonElement? = null
) {

    /** Builds a stored snapshot from a freshly returned auth session. */
    constructor(session: PairAuthSession<User>, context: JsonElement? = null) : this(
        user = session.user,
        accessToken = session.accessToken,
        refreshToken = session.refreshToken,
        expiresAtEpochSeconds = session.expiresAtEpochSeconds,
        context = context
    )

    /** Returns true when the access token remains outside the refresh leeway window. */
    fun hasUsableAccessToken(nowEpochSeconds: Long = PairClock.epochSeconds(), refreshLeewaySeconds: Long = 60): Boolean =
        expiresAtEpochSeconds - nowEpochSeconds > refreshLeewaySeconds
}

@Serializable
internal data class PairAuthSessionPayload<User>(
    val user: User,
    @SerialName("access_token")
    val accessToken: String,
    @SerialName("refresh_token")
    val refreshToken: String? = null,
    @SerialName("expires_at")
    val expiresAt: JsonElement? = null,
    @SerialName("expires_in")
    val expiresIn: JsonElement? = null
) {

    /** Converts backend token metadata into the local session model. */
    fun toSession(nowEpochSeconds: Long = PairClock.epochSeconds()): PairAuthSession<User> {
        val expiresAtEpochSeconds = parseExpiresAt(expiresAt)
            ?: parseDurationSeconds(expiresIn)?.let { nowEpochSeconds + it }
            ?: throw PairApiException.Decoding(IllegalArgumentException("Missing access-token expiration."))

        return PairAuthSession(
            user = user,
            accessToken = accessToken,
            refreshToken = refreshToken,
            expiresAtEpochSeconds = expiresAtEpochSeconds
        )
    }

    /** Reads `expires_at` values encoded as epoch seconds, epoch milliseconds, or ISO 8601 strings. */
    private fun parseExpiresAt(value: JsonElement?): Long? {
        val primitive = value?.jsonPrimitive ?: return null

        parseNumericSeconds(primitive)?.let { return it }

        val content = primitive.content.trim()

        return parseIso8601(content)
    }

    /** Reads `expires_in` values encoded as seconds. */
    private fun parseDurationSeconds(value: JsonElement?): Long? {
        val primitive = value?.jsonPrimitive ?: return null

        return parseNumericSeconds(primitive)
    }

    /** Normalizes numeric timestamp or duration values. */
    private fun parseNumericSeconds(primitive: JsonPrimitive): Long? {
        primitive.longOrNull?.let { value ->
            return if (value > 10_000_000_000L) value / 1_000 else value
        }

        return primitive.doubleOrNull?.let { value ->
            val seconds = value.toLong()
            if (seconds > 10_000_000_000L) seconds / 1_000 else seconds
        }
    }

    /** Parses common ISO 8601 date strings without requiring java.time on old Android devices. */
    private fun parseIso8601(value: String): Long? {
        val patterns = listOf(
            "yyyy-MM-dd'T'HH:mm:ss.SSSXXX",
            "yyyy-MM-dd'T'HH:mm:ssXXX",
            "yyyy-MM-dd'T'HH:mm:ss'Z'"
        )

        for (pattern in patterns) {
            val formatter = SimpleDateFormat(pattern, Locale.US)
            formatter.timeZone = TimeZone.getTimeZone("UTC")
            val parsed = runCatching { formatter.parse(value) }.getOrNull()

            if (parsed != null) {
                return parsed.time / 1_000
            }
        }

        return null
    }
}

internal object PairClock {

    /** Returns the current Unix timestamp in seconds. */
    fun epochSeconds(): Long = System.currentTimeMillis() / 1_000
}
