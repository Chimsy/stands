package za.co.chimsy.stands.ui.sales

import androidx.lifecycle.viewmodel.initializer
import androidx.lifecycle.viewmodel.viewModelFactory
import kotlinx.coroutines.flow.Flow
import za.co.chimsy.stands.core.AppResult
import za.co.chimsy.stands.data.repository.SalesRepository
import za.co.chimsy.stands.domain.model.Cached
import za.co.chimsy.stands.domain.model.SaleSummary
import za.co.chimsy.stands.domain.model.Scope
import za.co.chimsy.stands.ui.ScopedFeedViewModel
import za.co.chimsy.stands.ui.appContainer

class SalesViewModel(
    private val repository: SalesRepository,
    onSessionExpired: suspend () -> Unit,
    initialScope: Scope = Scope.Group,
) : ScopedFeedViewModel<List<SaleSummary>>(initialScope, onSessionExpired) {

    override fun observe(scope: Scope): Flow<Cached<List<SaleSummary>>?> = repository.observe(scope)

    override suspend fun refresh(scope: Scope): AppResult<Unit> = repository.refresh(scope)

    /** How many sales the whole ledger holds, so the screen can say what it is showing a slice of. */
    fun totalFor(scope: Scope): Flow<Int?> = repository.observeTotal(scope)

    companion object {
        val Factory = viewModelFactory {
            initializer {
                val container = appContainer

                SalesViewModel(
                    repository = container.salesRepository,
                    onSessionExpired = container.authRepository::expire,
                )
            }
        }
    }
}
