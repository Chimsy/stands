package za.co.chimsy.stands.ui

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.flatMapLatest
import kotlinx.coroutines.flow.onStart
import kotlinx.coroutines.flow.stateIn
import kotlinx.coroutines.launch
import za.co.chimsy.stands.core.AppError
import za.co.chimsy.stands.core.AppResult
import za.co.chimsy.stands.domain.model.Cached
import za.co.chimsy.stands.domain.model.Scope

/**
 * The behaviour every cached screen shares: show what we have, then quietly go
 * and check.
 *
 * This is the offline-first loop in one place. The screen only ever renders the
 * database; the network's job is to update the database. A refresh that fails
 * leaves the last good figures on screen with an explanation attached, because
 * a stale number the reader can date is far more useful than a blank page.
 */
abstract class ScopedFeedViewModel<T>(
    initialScope: Scope,
    private val onSessionExpired: suspend () -> Unit,
) : ViewModel() {

    private val scope = MutableStateFlow(initialScope)
    private val refreshing = MutableStateFlow(false)
    private val error = MutableStateFlow<AppError?>(null)

    /** The cached value for this scope, and every later version of it. */
    protected abstract fun observe(scope: Scope): Flow<Cached<T>?>

    /** Fetches from the API and writes to the cache. */
    protected abstract suspend fun refresh(scope: Scope): AppResult<Unit>

    @OptIn(ExperimentalCoroutinesApi::class)
    val uiState: StateFlow<FeedUiState<T>> =
        combine(scope, scope.flatMapLatest(::observe), refreshing, error) { scope, cached, isRefreshing, error ->
            FeedUiState(
                scope = scope,
                data = cached?.value,
                fetchedAt = cached?.fetchedAt,
                isRefreshing = isRefreshing,
                error = error,
            )
        }
            /**
             * The first look at the screen is what triggers the first fetch.
             *
             * Deliberately not the constructor: this class calls `observe` and
             * `refresh`, which subclasses implement, and a superclass
             * constructor runs before the subclass has assigned its
             * repository - so an `init` block here would reach a field that is
             * still null. Hanging it off subscription is also the better
             * behaviour, because a screen returned to after a while re-checks
             * on its own.
             */
            .onStart { refreshIfStale() }
            .stateIn(
                scope = viewModelScope,
                /** Survives a rotation without refetching, and stops collecting when the screen is gone. */
                started = SharingStarted.WhileSubscribed(5_000),
                initialValue = FeedUiState(initialScope),
            )

    val currentScope: StateFlow<Scope> = scope.asStateFlow()

    fun onScopeSelected(selected: Scope) {
        if (selected == scope.value) return

        scope.value = selected
        error.value = null
        refreshIfStale()
    }

    /** A deliberate pull-to-refresh always asks the server, however fresh the cache is. */
    fun onRefresh() = load(force = true)

    fun onErrorShown() {
        error.value = null
    }

    private fun refreshIfStale() = load(force = false)

    private fun load(force: Boolean) {
        val target = scope.value

        viewModelScope.launch {
            if (!force) {
                val cached = observe(target).firstOrNullValue()
                if (cached != null && !cached.isStale()) return@launch
            }

            refreshing.value = true

            when (val result = refresh(target)) {
                is AppResult.Success -> error.value = null
                is AppResult.Failure -> {
                    error.value = result.error

                    /** A dead token is not a message to show; it is the end of the session. */
                    if (result.error is AppError.Unauthenticated) onSessionExpired()
                }
            }

            refreshing.value = false
        }
    }
}

/** The value already in the cache, without waiting for a later one. */
private suspend fun <T> Flow<Cached<T>?>.firstOrNullValue(): Cached<T>? = first()
