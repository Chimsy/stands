package za.co.chimsy.stands.data.remote.dto

import kotlinx.serialization.Serializable

@Serializable
data class StatementLineDto(
    val code: String,
    val name: String,
    val amountCents: Long,
)

@Serializable
data class IncomeStatementDto(
    val from: String,
    val to: String,
    val branch: String? = null,
    val revenue: List<StatementLineDto> = emptyList(),
    val expenses: List<StatementLineDto> = emptyList(),
    val revenueCents: Long,
    val expensesCents: Long,
    val netIncomeCents: Long,
)

@Serializable
data class BalanceSheetDto(
    val asAt: String,
    val branch: String? = null,
    val assets: List<StatementLineDto> = emptyList(),
    val liabilities: List<StatementLineDto> = emptyList(),
    val equity: List<StatementLineDto> = emptyList(),
    val assetsCents: Long,
    val liabilitiesCents: Long,
    val equityCents: Long,
    val retainedEarningsCents: Long,
    /** False means something wrote to the ledger outside the posting rules. */
    val inBalance: Boolean,
)

@Serializable
data class TrialBalanceRowDto(
    val code: String,
    val name: String,
    val type: String,
    val debitCents: Long,
    val creditCents: Long,
)

@Serializable
data class TrialBalanceDto(
    val asAt: String,
    val branch: String? = null,
    val rows: List<TrialBalanceRowDto> = emptyList(),
    val totalDebitCents: Long,
    val totalCreditCents: Long,
    val inBalance: Boolean,
)

@Serializable
data class AgeingBucketsDto(
    val notYetDue: Long,
    val days1To30: Long,
    val days31To60: Long,
    val days61To90: Long,
    val over90Days: Long,
)

@Serializable
data class ReceivablesAgeingDto(
    val asAt: String,
    val branch: String? = null,
    val buckets: AgeingBucketsDto,
    val totalCents: Long,
    val overdueCents: Long,
)
