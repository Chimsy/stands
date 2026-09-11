package za.co.chimsy.stands.ui.components

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.size
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.produceState
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.delay
import java.time.Duration
import java.time.Instant

/**
 * Says when the figures on screen were last true.
 *
 * A cached screen that does not date itself is indistinguishable from a live
 * one, which is how someone ends up quoting last week's number in a meeting.
 * The label re-renders on a timer so "just now" does not stay on screen for an
 * hour while the app sits open on a desk.
 */
@Composable
fun LastRefreshedLabel(
    fetchedAt: Instant?,
    isRefreshing: Boolean,
    modifier: Modifier = Modifier,
) {
    val now by produceState(initialValue = Instant.now(), fetchedAt) {
        while (true) {
            value = Instant.now()
            delay(TICK_MILLIS)
        }
    }

    Row(
        modifier = modifier,
        horizontalArrangement = Arrangement.spacedBy(6.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        if (isRefreshing) {
            CircularProgressIndicator(modifier = Modifier.size(12.dp), strokeWidth = 1.5.dp)
        }

        Text(
            text = when {
                isRefreshing && fetchedAt == null -> "Loading…"
                isRefreshing -> "Refreshing…"
                fetchedAt == null -> "Not loaded yet"
                else -> "Updated ${relativeTime(fetchedAt, now)}"
            },
            style = MaterialTheme.typography.labelMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
    }
}

/**
 * How long ago, in the coarsest unit that is still honest. Anything older than
 * a day is reported in days rather than pretending to an hour's precision.
 */
fun relativeTime(fetchedAt: Instant, now: Instant = Instant.now()): String {
    val elapsed = Duration.between(fetchedAt, now)

    return when {
        elapsed.isNegative -> "just now"
        elapsed < Duration.ofMinutes(1) -> "just now"
        elapsed < Duration.ofHours(1) -> "${elapsed.toMinutes()} min ago"
        elapsed < Duration.ofDays(1) -> plural(elapsed.toHours(), "hour")
        else -> plural(elapsed.toDays(), "day")
    }
}

private fun plural(count: Long, unit: String) = "$count $unit${if (count == 1L) "" else "s"} ago"

private const val TICK_MILLIS = 30_000L
