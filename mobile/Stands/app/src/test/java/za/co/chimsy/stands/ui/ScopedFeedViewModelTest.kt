package za.co.chimsy.stands.ui

import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.test.advanceUntilIdle
import kotlinx.coroutines.test.runTest
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Rule
import org.junit.Test
import za.co.chimsy.stands.MainDispatcherRule
import za.co.chimsy.stands.core.AppError
import za.co.chimsy.stands.domain.model.Cached
import za.co.chimsy.stands.domain.model.Scope
import java.time.Instant

/**
 * The offline-first loop, which is the part of this app most worth pinning
 * down: what it shows, when it decides to go to the network, and what happens
 * to the figures already on screen when that fails.
 */
@OptIn(ExperimentalCoroutinesApi::class)
class ScopedFeedViewModelTest {

    @get:Rule
    val mainDispatcherRule = MainDispatcherRule()

    /**
     * A regression test for a crash on the device that the other tests missed.
     *
     * The base class used to kick off its first fetch from an `init` block,
     * which runs before the subclass has assigned its repository. Under a
     * queueing test dispatcher the coroutine ran later, by which time the field
     * was set, so every test passed; on `Dispatchers.Main.immediate` it ran
     * during construction and dereferenced null. Collecting eagerly from the
     * constructor reproduces the device's timing.
     */
    @Test
    fun `does not touch the repository before the subclass is constructed`() = runTest {
        val repository = FakeFeedRepository()
        val viewModel = EagerlyCollectedViewModel(repository)

        advanceUntilIdle()

        assertEquals("payload-group-1", viewModel.uiState.value.data)
    }

    @Test
    fun `fetches on open when the device has nothing cached`() = runTest {
        val repository = FakeFeedRepository()
        val viewModel = TestFeedViewModel(repository)

        collectInBackground(viewModel.uiState)
        advanceUntilIdle()

        assertEquals(1, repository.refreshCount)
        assertEquals("payload-group-1", viewModel.uiState.value.data)
    }

    @Test
    fun `serves a fresh cache without going to the network`() = runTest {
        val repository = FakeFeedRepository()
        repository.seed(Scope.Group, "cached", Instant.now())

        val viewModel = TestFeedViewModel(repository)
        collectInBackground(viewModel.uiState)
        advanceUntilIdle()

        assertEquals(0, repository.refreshCount)
        assertEquals("cached", viewModel.uiState.value.data)
    }

    @Test
    fun `refreshes a cache that has gone stale`() = runTest {
        val repository = FakeFeedRepository()
        repository.seed(Scope.Group, "old", Instant.now().minus(Cached.STALE_AFTER).minusSeconds(1))

        val viewModel = TestFeedViewModel(repository)
        collectInBackground(viewModel.uiState)
        advanceUntilIdle()

        assertEquals(1, repository.refreshCount)
        assertEquals("payload-group-1", viewModel.uiState.value.data)
    }

    @Test
    fun `a pull to refresh asks the server however fresh the cache is`() = runTest {
        val repository = FakeFeedRepository()
        repository.seed(Scope.Group, "cached", Instant.now())

        val viewModel = TestFeedViewModel(repository)
        collectInBackground(viewModel.uiState)
        advanceUntilIdle()
        assertEquals(0, repository.refreshCount)

        viewModel.onRefresh()
        advanceUntilIdle()

        assertEquals(1, repository.refreshCount)
    }

    /**
     * The whole point of caching: a failed refresh must not take the figures
     * off the screen, because dated figures beat a blank page.
     */
    @Test
    fun `keeps the cached figures on screen when a refresh fails`() = runTest {
        val repository = FakeFeedRepository()
        repository.seed(Scope.Group, "last known", Instant.now().minus(Cached.STALE_AFTER).minusSeconds(1))
        repository.failWith(AppError.Offline)

        val viewModel = TestFeedViewModel(repository)
        collectInBackground(viewModel.uiState)
        advanceUntilIdle()

        val state = viewModel.uiState.value
        assertEquals("last known", state.data)
        assertEquals(AppError.Offline, state.error)
        assertFalse(state.isRefreshing)
    }

    @Test
    fun `reports when the device has never held these figures`() = runTest {
        val repository = FakeFeedRepository()
        repository.failWith(AppError.Offline)

        val viewModel = TestFeedViewModel(repository)
        collectInBackground(viewModel.uiState)
        advanceUntilIdle()

        val state = viewModel.uiState.value
        assertNull(state.data)
        assertNull(state.fetchedAt)
        assertTrue(state.isEmpty)
    }

    @Test
    fun `switching branch shows that branch and fetches it`() = runTest {
        val repository = FakeFeedRepository()
        val viewModel = TestFeedViewModel(repository)

        collectInBackground(viewModel.uiState)
        advanceUntilIdle()

        viewModel.onScopeSelected(Scope.Branch("BYO"))
        advanceUntilIdle()

        assertEquals(Scope.Branch("BYO"), viewModel.uiState.value.scope)
        assertEquals("payload-BYO-2", viewModel.uiState.value.data)
        assertEquals(listOf(Scope.Group, Scope.Branch("BYO")), repository.refreshedScopes)
    }

    @Test
    fun `selecting the branch already shown does nothing`() = runTest {
        val repository = FakeFeedRepository()
        val viewModel = TestFeedViewModel(repository)

        collectInBackground(viewModel.uiState)
        advanceUntilIdle()

        viewModel.onScopeSelected(Scope.Group)
        advanceUntilIdle()

        assertEquals(1, repository.refreshCount)
    }

    /** A dead token is not a message to show - it is the end of the session. */
    @Test
    fun `ends the session when the API rejects the token`() = runTest {
        val repository = FakeFeedRepository()
        repository.failWith(AppError.Unauthenticated)

        var expired = false
        val viewModel = TestFeedViewModel(repository, onSessionExpired = { expired = true })

        collectInBackground(viewModel.uiState)
        advanceUntilIdle()

        assertTrue(expired)
    }

    @Test
    fun `does not end the session merely because the device is offline`() = runTest {
        val repository = FakeFeedRepository()
        repository.failWith(AppError.Offline)

        var expired = false
        val viewModel = TestFeedViewModel(repository, onSessionExpired = { expired = true })

        collectInBackground(viewModel.uiState)
        advanceUntilIdle()

        assertFalse(expired)
    }
}
