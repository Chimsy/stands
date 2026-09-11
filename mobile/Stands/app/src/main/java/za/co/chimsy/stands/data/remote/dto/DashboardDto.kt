package za.co.chimsy.stands.data.remote.dto

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

/**
 * Every money figure here is integer minor units, named `...Cents` to say so.
 */
@Serializable
data class DashboardDto(
    val from: String,
    val to: String,
    /** A branch code, or null when every branch is consolidated. */
    val branch: String? = null,
    val totals: DashboardTotalsDto,
    val branches: List<BranchPerformanceDto> = emptyList(),
    val monthly: List<MonthlyPointDto> = emptyList(),
    val mix: List<SaleMixDto> = emptyList(),
    val topAgents: List<AgentPerformanceDto> = emptyList(),
    val recentSales: List<RecentSaleDto> = emptyList(),
)

@Serializable
data class DashboardTotalsDto(
    val standsTotal: Int,
    val standsAvailable: Int,
    val standsSold: Int,
    val standsInProgress: Int,
    val salesCount: Int,
    val valueCents: Long,
    val costCents: Long,
    val grossProfitCents: Long,
    val collectedCents: Long,
    val outstandingCents: Long,
)

@Serializable
data class StandCountsDto(
    val total: Int,
    val available: Int,
    val sold: Int,
    @SerialName("in-progress") val inProgress: Int,
)

@Serializable
data class BranchPerformanceDto(
    val code: String,
    val name: String,
    val city: String,
    val stands: StandCountsDto,
    val salesCount: Int,
    val valueCents: Long,
    val costCents: Long,
    val collectedCents: Long,
    val outstandingCents: Long,
)

@Serializable
data class MonthlyPointDto(
    val month: String,
    val salesCount: Int,
    val valueCents: Long,
    val collectedCents: Long,
)

@Serializable
data class SaleMixDto(
    val type: String,
    val salesCount: Int,
    val valueCents: Long,
)

@Serializable
data class AgentPerformanceDto(
    val name: String,
    val branch: String? = null,
    val salesCount: Int,
    val valueCents: Long,
)

@Serializable
data class RecentSaleDto(
    val reference: String,
    val saleDate: String,
    val standNumber: String? = null,
    val buyerName: String? = null,
    val branch: String? = null,
    val type: String,
    val status: String,
    val valueCents: Long,
    val paidCents: Long,
    val outstandingCents: Long,
)
