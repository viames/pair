package dev.pair.mobile.android

import kotlinx.serialization.Serializable
import kotlinx.serialization.json.JsonElement

/** Standard Pair endpoint envelope for responses that return `data`. */
@Serializable
data class PairDataEnvelope<Value>(
    val data: Value
)

/** Standard mobile login and registration response. */
@Serializable
data class PairAuthSession<User>(
    val user: User,
    val token: String
)

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
    val session: PairAuthSession<User>,
    val defaultContext: JsonElement? = null
)

