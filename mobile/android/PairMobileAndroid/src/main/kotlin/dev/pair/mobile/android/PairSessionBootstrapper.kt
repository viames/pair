package dev.pair.mobile.android

/** Result of startup session restoration. */
sealed class PairSessionBootstrapResult<out Snapshot> {

    /** No saved session exists. */
    data object Missing : PairSessionBootstrapResult<Nothing>()

    /** The saved session was validated and refreshed. */
    data class Restored<Snapshot>(val snapshot: Snapshot) : PairSessionBootstrapResult<Snapshot>()

    /** The backend rejected the saved session. */
    data object Invalidated : PairSessionBootstrapResult<Nothing>()

    /** The saved session is available locally, but remote validation could not complete. */
    data class Offline<Snapshot>(val snapshot: Snapshot) : PairSessionBootstrapResult<Snapshot>()
}

/** Coordinates local storage and remote validation without imposing application models. */
class PairSessionBootstrapper<Snapshot>(
    private val store: PairSessionStore<Snapshot>
) {

    /** Restores the saved snapshot and validates it with a closure provided by the host app. */
    suspend fun bootstrap(
        validate: suspend (Snapshot) -> Snapshot
    ): PairSessionBootstrapResult<Snapshot> {
        val snapshot = store.load() ?: return PairSessionBootstrapResult.Missing

        return try {
            val validatedSnapshot = validate(snapshot)
            store.save(validatedSnapshot)
            PairSessionBootstrapResult.Restored(validatedSnapshot)
        } catch (error: PairApiException) {
            if (error.isAuthenticationFailure) {
                store.clear()
                PairSessionBootstrapResult.Invalidated
            } else {
                PairSessionBootstrapResult.Offline(snapshot)
            }
        } catch (_: Throwable) {
            PairSessionBootstrapResult.Offline(snapshot)
        }
    }
}

