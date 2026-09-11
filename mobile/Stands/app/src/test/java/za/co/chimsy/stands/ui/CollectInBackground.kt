package za.co.chimsy.stands.ui

import kotlinx.coroutines.CoroutineStart
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch
import kotlinx.coroutines.test.TestScope
import kotlinx.coroutines.test.UnconfinedTestDispatcher

/**
 * Keeps a `WhileSubscribed` flow hot for the length of a test.
 *
 * Without a collector the ViewModel's state would never leave its initial
 * value and every assertion would pass for the wrong reason.
 */
@OptIn(ExperimentalCoroutinesApi::class)
fun <T> TestScope.collectInBackground(flow: StateFlow<T>) {
    backgroundScope.launch(UnconfinedTestDispatcher(testScheduler), CoroutineStart.UNDISPATCHED) {
        flow.collect { }
    }
}
