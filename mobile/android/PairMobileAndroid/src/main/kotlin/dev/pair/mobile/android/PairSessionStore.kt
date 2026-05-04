package dev.pair.mobile.android

import android.content.Context
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import kotlinx.serialization.KSerializer
import kotlinx.serialization.SerializationException
import kotlinx.serialization.json.Json

/** Storage abstraction for persisted Pair mobile sessions. */
interface PairSessionStore<Snapshot> {

    /** Loads the persisted snapshot, returning null when none is available. */
    suspend fun load(): Snapshot?

    /** Saves or replaces the current snapshot. */
    suspend fun save(snapshot: Snapshot)

    /** Clears the persisted snapshot. */
    suspend fun clear()
}

/** App-private SharedPreferences store that can participate in Android backup and restore. */
class PairSharedPreferencesSessionStore<Snapshot>(
    context: Context,
    preferencesName: String = "pair_mobile_session",
    private val key: String = "primary-session",
    private val serializer: KSerializer<Snapshot>,
    private val json: Json = PairJson.default
) : PairSessionStore<Snapshot> {
    private val preferences = context.applicationContext.getSharedPreferences(preferencesName, Context.MODE_PRIVATE)

    /** Loads and decodes the saved snapshot from app-private preferences. */
    override suspend fun load(): Snapshot? = withContext(Dispatchers.IO) {
        val raw = preferences.getString(key, null) ?: return@withContext null

        try {
            json.decodeFromString(serializer, raw)
        } catch (_: SerializationException) {
            null
        } catch (_: IllegalArgumentException) {
            null
        }
    }

    /** Encodes and writes the snapshot synchronously so auth state is durable before returning. */
    override suspend fun save(snapshot: Snapshot) {
        withContext(Dispatchers.IO) {
            preferences.edit()
                .putString(key, json.encodeToString(serializer, snapshot))
                .commit()
        }
    }

    /** Removes the saved snapshot from app-private preferences. */
    override suspend fun clear() {
        withContext(Dispatchers.IO) {
            preferences.edit()
                .remove(key)
                .commit()
        }
    }
}

/** Lightweight store for tests, previews, and host apps that keep their own persistence. */
class PairInMemorySessionStore<Snapshot>(
    initialSnapshot: Snapshot? = null
) : PairSessionStore<Snapshot> {
    private var snapshot: Snapshot? = initialSnapshot

    /** Returns the in-memory snapshot. */
    override suspend fun load(): Snapshot? = snapshot

    /** Replaces the in-memory snapshot. */
    override suspend fun save(snapshot: Snapshot) {
        this.snapshot = snapshot
    }

    /** Clears the in-memory snapshot. */
    override suspend fun clear() {
        snapshot = null
    }
}

