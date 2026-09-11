package za.co.chimsy.stands.data.repository

import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.map
import kotlinx.serialization.KSerializer
import kotlinx.serialization.json.Json
import za.co.chimsy.stands.core.AppResult
import za.co.chimsy.stands.core.map
import za.co.chimsy.stands.data.local.dao.ReportDao
import za.co.chimsy.stands.data.local.entity.CachedReportEntity
import za.co.chimsy.stands.data.remote.apiCall
import za.co.chimsy.stands.domain.model.Cached
import java.time.Instant

/**
 * The offline-first half of every report screen.
 *
 * Reads come from the database and writes come from the network, never the
 * other way round: a screen collects the cached flow, so whatever a refresh
 * fetches reaches the UI by being saved. That is what makes the app open
 * instantly, survive a dead connection, and keep one version of the truth.
 *
 * Serializers are passed in rather than reified, which keeps these functions
 * out of line and the dependencies private.
 */
class ReportCache(
    private val dao: ReportDao,
    private val json: Json,
) {
    /**
     * The cached report, decoded, or null if this device has never fetched it.
     *
     * A payload that no longer decodes - the API changed shape under an old
     * install - is reported as absent rather than crashing the screen it is on.
     */
    fun <T> observe(key: String, serializer: KSerializer<T>): Flow<Cached<T>?> =
        dao.observe(key).map { cached ->
            cached ?: return@map null

            runCatching { json.decodeFromString(serializer, cached.payload) }
                .map { Cached(it, Instant.ofEpochMilli(cached.fetchedAt)) }
                .getOrNull()
        }

    /** Fetches a report and saves it; the saved copy is what the screen then renders. */
    suspend fun <T> refresh(
        key: String,
        serializer: KSerializer<T>,
        fetch: suspend () -> T,
    ): AppResult<Unit> = apiCall(json) { fetch() }.map { fresh ->
        dao.upsert(
            CachedReportEntity(
                key = key,
                payload = json.encodeToString(serializer, fresh),
                fetchedAt = Instant.now().toEpochMilli(),
            ),
        )
    }

    /** Saves a value the app already has, without a round trip to fetch it again. */
    suspend fun <T> put(key: String, serializer: KSerializer<T>, value: T) {
        dao.upsert(
            CachedReportEntity(
                key = key,
                payload = json.encodeToString(serializer, value),
                fetchedAt = Instant.now().toEpochMilli(),
            ),
        )
    }

    suspend fun clear() = dao.clear()
}
