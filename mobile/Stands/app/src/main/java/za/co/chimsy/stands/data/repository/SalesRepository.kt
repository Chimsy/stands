package za.co.chimsy.stands.data.repository

import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.flow.map
import kotlinx.serialization.json.Json
import za.co.chimsy.stands.core.AppResult
import za.co.chimsy.stands.core.map
import za.co.chimsy.stands.data.local.dao.ReportDao
import za.co.chimsy.stands.data.local.dao.SaleDao
import za.co.chimsy.stands.data.local.entity.CachedReportEntity
import za.co.chimsy.stands.data.local.entity.CachedSaleEntity
import za.co.chimsy.stands.data.remote.StandsApi
import za.co.chimsy.stands.data.remote.apiCall
import za.co.chimsy.stands.data.remote.dto.SaleDto
import za.co.chimsy.stands.domain.model.Cached
import za.co.chimsy.stands.domain.model.SaleSummary
import za.co.chimsy.stands.domain.model.Scope
import java.time.Instant

/**
 * The most recent sales for a scope.
 *
 * Unlike the reports, these are rows rather than a computed answer, so they get
 * real columns and are queried as a list. Only the first page is kept: this is
 * an at-a-glance view of what has just been signed, not an archive to scroll -
 * the full ledger is a job for the web app on a big screen.
 */
class SalesRepository(
    private val api: StandsApi,
    private val saleDao: SaleDao,
    private val reportDao: ReportDao,
    private val json: Json,
) {
    fun observe(scope: Scope): Flow<Cached<List<SaleSummary>>?> = combine(
        saleDao.observe(scope.query),
        reportDao.observe(syncKey(scope)),
    ) { rows, sync ->
        sync ?: return@combine null

        Cached(rows.map(CachedSaleEntity::toDomain), Instant.ofEpochMilli(sync.fetchedAt))
    }

    suspend fun refresh(scope: Scope): AppResult<Unit> =
        apiCall(json) { api.sales(branch = scope.query, page = 1) }.map { page ->
            saleDao.replace(
                scope = scope.query,
                sales = page.data.mapIndexed { index, sale -> sale.toEntity(scope.query, index) },
            )

            /**
             * The window's own timestamp lives beside the reports so that "last
             * refreshed" means the same thing on every screen, and so an empty
             * branch is still recorded as fetched rather than as never loaded.
             */
            reportDao.upsert(
                CachedReportEntity(
                    key = syncKey(scope),
                    payload = page.meta?.total?.toString() ?: "0",
                    fetchedAt = Instant.now().toEpochMilli(),
                ),
            )
        }

    /** How many sales the whole ledger holds for this scope, as at the last refresh. */
    fun observeTotal(scope: Scope): Flow<Int?> =
        reportDao.observe(syncKey(scope)).map { it?.payload?.toIntOrNull() }

    private fun syncKey(scope: Scope) = "sales-window:${scope.query}"
}

private fun SaleDto.toEntity(scope: String, position: Int) = CachedSaleEntity(
    scope = scope,
    reference = reference,
    position = position,
    saleDate = saleDate,
    standNumber = standNumber,
    buyerName = buyer?.name,
    branchCode = branch?.code,
    type = type,
    status = status,
    price = price,
    paid = paid,
    outstanding = outstanding,
)

private fun CachedSaleEntity.toDomain() = SaleSummary(
    reference = reference,
    saleDate = saleDate,
    standNumber = standNumber,
    buyerName = buyerName,
    branchCode = branchCode,
    type = type,
    status = status,
    price = price,
    paid = paid,
    outstanding = outstanding,
)
