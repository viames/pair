package dev.pair.mobile.android

import kotlinx.serialization.json.Json
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonObjectBuilder
import kotlinx.serialization.json.buildJsonObject

/** Shared JSON configuration for Pair mobile clients. */
object PairJson {

    /** Default JSON instance that accepts additive backend fields. */
    val default: Json = Json {
        ignoreUnknownKeys = true
        encodeDefaults = true
    }
}

/** Builds a JSON object for project-specific Pair payload extensions. */
fun pairJsonPayload(builder: JsonObjectBuilder.() -> Unit): JsonObject = buildJsonObject(builder)

