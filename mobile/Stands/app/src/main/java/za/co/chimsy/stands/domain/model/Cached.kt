package za.co.chimsy.stands.domain.model

import java.time.Duration
import java.time.Instant

/**
 * A value the device already had, and when it was last known to be true.
 *
 * Every cached read carries its own age so the screen can say when it last
 * heard from the server instead of presenting stale figures as current.
 */
data class Cached<T>(
    val value: T,
    val fetchedAt: Instant,
) {
    fun age(now: Instant = Instant.now()): Duration = Duration.between(fetchedAt, now)

    fun isStale(now: Instant = Instant.now()): Boolean = age(now) > STALE_AFTER

    companion object {
        /**
         * How old a cached answer may be before the app refreshes it on its own.
         *
         * Long enough that moving between screens does not hammer the API,
         * short enough that a figure on a dashboard is never quietly wrong for
         * a working session. A pull-to-refresh always overrides it.
         */
        val STALE_AFTER: Duration = Duration.ofMinutes(5)
    }
}
