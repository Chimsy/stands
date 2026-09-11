package za.co.chimsy.stands

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.test.StandardTestDispatcher
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.setMain
import org.junit.rules.TestWatcher
import org.junit.runner.Description
import kotlin.coroutines.CoroutineContext

/**
 * Puts `viewModelScope` on a dispatcher the test controls.
 *
 * Without this a ViewModel's coroutines would try to reach the real main
 * looper, which does not exist in a JVM unit test.
 */
@OptIn(ExperimentalCoroutinesApi::class)
class MainDispatcherRule(
    val dispatcher: CoroutineContext = StandardTestDispatcher(),
) : TestWatcher() {

    override fun starting(description: Description) {
        Dispatchers.setMain(dispatcher as kotlinx.coroutines.CoroutineDispatcher)
    }

    override fun finished(description: Description) {
        Dispatchers.resetMain()
    }
}
