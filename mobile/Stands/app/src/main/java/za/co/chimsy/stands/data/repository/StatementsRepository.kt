package za.co.chimsy.stands.data.repository

import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.combine
import za.co.chimsy.stands.core.AppResult
import za.co.chimsy.stands.data.remote.StandsApi
import za.co.chimsy.stands.data.remote.dto.BalanceSheetDto
import za.co.chimsy.stands.data.remote.dto.IncomeStatementDto
import za.co.chimsy.stands.data.remote.dto.ReceivablesAgeingDto
import za.co.chimsy.stands.data.remote.dto.TrialBalanceDto
import za.co.chimsy.stands.domain.model.Cached
import za.co.chimsy.stands.domain.model.Scope

/** The four statements a screen shows together, and the moment they were read. */
data class Statements(
    val incomeStatement: IncomeStatementDto,
    val balanceSheet: BalanceSheetDto,
    val trialBalance: TrialBalanceDto,
    val ageing: ReceivablesAgeingDto,
) {
    /** False means something wrote to the ledger outside the posting rules. */
    val inBalance: Boolean get() = balanceSheet.inBalance && trialBalance.inBalance
}

class StatementsRepository(
    private val api: StandsApi,
    private val cache: ReportCache,
) {
    /**
     * The four statements as one value, so a screen can never show an income
     * statement from one refresh beside a balance sheet from another. The set
     * is dated by its oldest member for the same reason.
     */
    fun observe(scope: Scope): Flow<Cached<Statements>?> = combine(
        cache.observe(key("income-statement", scope), IncomeStatementDto.serializer()),
        cache.observe(key("balance-sheet", scope), BalanceSheetDto.serializer()),
        cache.observe(key("trial-balance", scope), TrialBalanceDto.serializer()),
        cache.observe(key("receivables-ageing", scope), ReceivablesAgeingDto.serializer()),
    ) { income, sheet, trial, ageing ->
        if (income == null || sheet == null || trial == null || ageing == null) return@combine null

        Cached(
            value = Statements(income.value, sheet.value, trial.value, ageing.value),
            fetchedAt = minOf(income.fetchedAt, sheet.fetchedAt, trial.fetchedAt, ageing.fetchedAt),
        )
    }

    /**
     * All four are refreshed together and the first failure stops the rest, so
     * a half-updated set never reaches the cache.
     */
    suspend fun refresh(scope: Scope): AppResult<Unit> {
        val branch = scope.query

        val steps = listOf<suspend () -> AppResult<Unit>>(
            { cache.refresh(key("income-statement", scope), IncomeStatementDto.serializer()) { api.incomeStatement(branch).data } },
            { cache.refresh(key("balance-sheet", scope), BalanceSheetDto.serializer()) { api.balanceSheet(branch).data } },
            { cache.refresh(key("trial-balance", scope), TrialBalanceDto.serializer()) { api.trialBalance(branch).data } },
            { cache.refresh(key("receivables-ageing", scope), ReceivablesAgeingDto.serializer()) { api.receivablesAgeing(branch).data } },
        )

        steps.forEach { step ->
            val result = step()
            if (result is AppResult.Failure) return result
        }

        return AppResult.Success(Unit)
    }

    private fun key(report: String, scope: Scope) = "$report:${scope.query}"
}
