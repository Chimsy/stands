package za.co.chimsy.stands.data.local.entity

import androidx.room.Entity
import androidx.room.PrimaryKey

/**
 * A report exactly as the API computed it, kept so the screen has something to
 * draw the instant it opens.
 *
 * Reports are stored as their own JSON rather than shredded into columns on
 * purpose. A dashboard or a balance sheet is a server-computed answer, not an
 * entity the device queries across - normalising it would buy no query power
 * and would mean a migration every time a figure is added to the API. Rows the
 * app really does query, like sales, get real columns instead.
 *
 * [fetchedAt] is what the UI reports as the last refresh, and what decides
 * whether the cache is stale enough to refresh on its own.
 */
@Entity(tableName = "cached_reports")
data class CachedReportEntity(
    /** Report and scope together, e.g. `dashboard:group` or `balance-sheet:HRE`. */
    @PrimaryKey val key: String,
    val payload: String,
    val fetchedAt: Long,
)
