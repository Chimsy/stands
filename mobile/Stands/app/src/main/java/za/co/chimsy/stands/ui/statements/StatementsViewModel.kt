package za.co.chimsy.stands.ui.statements

import androidx.lifecycle.viewmodel.initializer
import androidx.lifecycle.viewmodel.viewModelFactory
import kotlinx.coroutines.flow.Flow
import za.co.chimsy.stands.core.AppResult
import za.co.chimsy.stands.data.repository.Statements
import za.co.chimsy.stands.data.repository.StatementsRepository
import za.co.chimsy.stands.domain.model.Cached
import za.co.chimsy.stands.domain.model.Scope
import za.co.chimsy.stands.ui.ScopedFeedViewModel
import za.co.chimsy.stands.ui.appContainer

class StatementsViewModel(
    private val repository: StatementsRepository,
    onSessionExpired: suspend () -> Unit,
    initialScope: Scope = Scope.Group,
) : ScopedFeedViewModel<Statements>(initialScope, onSessionExpired) {

    override fun observe(scope: Scope): Flow<Cached<Statements>?> = repository.observe(scope)

    override suspend fun refresh(scope: Scope): AppResult<Unit> = repository.refresh(scope)

    companion object {
        val Factory = viewModelFactory {
            initializer {
                val container = appContainer

                StatementsViewModel(
                    repository = container.statementsRepository,
                    onSessionExpired = container.authRepository::expire,
                )
            }
        }
    }
}
