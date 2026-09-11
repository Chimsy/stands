package za.co.chimsy.stands.ui

import za.co.chimsy.stands.core.AppError
import za.co.chimsy.stands.domain.model.Scope
import java.time.Instant

/**
 * What a cached screen shows, whatever it is showing.
 *
 * [data] and [error] are deliberately not exclusive: the most common state on
 * a phone is having yesterday's figures on screen and a failed refresh behind
 * them, and the screen should show both rather than throwing the figures away.
 */
data class FeedUiState<T>(
    val scope: Scope,
    val data: T? = null,
    /** When the server last answered. Null means this device has never had these figures. */
    val fetchedAt: Instant? = null,
    val isRefreshing: Boolean = false,
    val error: AppError? = null,
) {
    /** Nothing to draw yet and something is on its way: the only true spinner state. */
    val isLoading: Boolean get() = data == null && isRefreshing

    val isEmpty: Boolean get() = data == null && !isRefreshing
}
