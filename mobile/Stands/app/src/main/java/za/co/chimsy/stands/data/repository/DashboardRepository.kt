package za.co.chimsy.stands.data.repository

import kotlinx.coroutines.flow.Flow
import za.co.chimsy.stands.core.AppResult
import za.co.chimsy.stands.data.remote.StandsApi
import za.co.chimsy.stands.data.remote.dto.DashboardDto
import za.co.chimsy.stands.domain.model.Cached
import za.co.chimsy.stands.domain.model.Scope

class DashboardRepository(
    private val api: StandsApi,
    private val cache: ReportCache,
) {
    fun observe(scope: Scope): Flow<Cached<DashboardDto>?> =
        cache.observe(key(scope), DashboardDto.serializer())

    suspend fun refresh(scope: Scope): AppResult<Unit> =
        cache.refresh(key(scope), DashboardDto.serializer()) { api.dashboard(branch = scope.query).data }

    private fun key(scope: Scope) = "dashboard:${scope.query}"
}
