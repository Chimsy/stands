package za.co.chimsy.stands.ui.dashboard

import androidx.lifecycle.viewmodel.initializer
import androidx.lifecycle.viewmodel.viewModelFactory
import kotlinx.coroutines.flow.Flow
import za.co.chimsy.stands.core.AppResult
import za.co.chimsy.stands.data.remote.dto.DashboardDto
import za.co.chimsy.stands.data.repository.AuthRepository
import za.co.chimsy.stands.data.repository.DashboardRepository
import za.co.chimsy.stands.domain.model.Cached
import za.co.chimsy.stands.domain.model.Scope
import za.co.chimsy.stands.ui.ScopedFeedViewModel
import za.co.chimsy.stands.ui.appContainer

class DashboardViewModel(
    private val repository: DashboardRepository,
    onSessionExpired: suspend () -> Unit,
    initialScope: Scope = Scope.Group,
) : ScopedFeedViewModel<DashboardDto>(initialScope, onSessionExpired) {

    override fun observe(scope: Scope): Flow<Cached<DashboardDto>?> = repository.observe(scope)

    override suspend fun refresh(scope: Scope): AppResult<Unit> = repository.refresh(scope)

    companion object {
        val Factory = viewModelFactory {
            initializer {
                val container = appContainer

                DashboardViewModel(
                    repository = container.dashboardRepository,
                    onSessionExpired = container.authRepository::expire,
                )
            }
        }
    }
}
