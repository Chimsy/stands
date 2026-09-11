package za.co.chimsy.stands.ui

import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.launch
import za.co.chimsy.stands.core.AppError
import za.co.chimsy.stands.core.AppResult
import za.co.chimsy.stands.domain.model.Cached
import za.co.chimsy.stands.domain.model.Scope
import java.time.Instant

/**
 * A stand-in for a repository, built by hand rather than mocked.
 *
 * A fake states the contract the ViewModel depends on - a cache you can read
 * and a refresh that writes to it - so the tests exercise the real interaction
 * instead of asserting that certain methods were called.
 */
class FakeFeedRepository(
    private val clock: () -> Instant = Instant::now,
) {
    private val cache = MutableStateFlow<Map<String, Cached<String>>>(emptyMap())

    var nextResult: AppResult<Unit> = AppResult.Success(Unit)
    var refreshCount: Int = 0
        private set
    val refreshedScopes = mutableListOf<Scope>()

    fun observe(scope: Scope): Flow<Cached<String>?> = cache.map { it[scope.query] }

    suspend fun refresh(scope: Scope): AppResult<Unit> {
        refreshCount++
        refreshedScopes += scope

        if (nextResult is AppResult.Success) {
            cache.value = cache.value + (scope.query to Cached("payload-${scope.query}-$refreshCount", clock()))
        }

        return nextResult
    }

    /** Seeds the cache as though a previous run had fetched it at [fetchedAt]. */
    fun seed(scope: Scope, value: String, fetchedAt: Instant) {
        cache.value = cache.value + (scope.query to Cached(value, fetchedAt))
    }

    fun failWith(error: AppError) {
        nextResult = AppResult.Failure(error)
    }
}

/**
 * A ViewModel that collects its own state the moment it is constructed, the way
 * `Dispatchers.Main.immediate` does on a device. Anything the base class does
 * during construction therefore happens here too, rather than being deferred by
 * a queueing dispatcher.
 */
class EagerlyCollectedViewModel(
    repository: FakeFeedRepository,
) : TestFeedViewModel(repository) {

    init {
        CoroutineScope(Dispatchers.Unconfined).launch { uiState.collect { } }
    }
}

/** The ViewModel under test, wired to the fake. */
open class TestFeedViewModel(
    private val repository: FakeFeedRepository,
    initialScope: Scope = Scope.Group,
    onSessionExpired: suspend () -> Unit = {},
) : ScopedFeedViewModel<String>(initialScope, onSessionExpired) {

    override fun observe(scope: Scope): Flow<Cached<String>?> = repository.observe(scope)

    override suspend fun refresh(scope: Scope): AppResult<Unit> = repository.refresh(scope)
}
