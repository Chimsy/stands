package za.co.chimsy.stands.ui.statements

import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyListScope
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import za.co.chimsy.stands.core.asMoney
import za.co.chimsy.stands.data.repository.Statements
import za.co.chimsy.stands.domain.model.Branch
import za.co.chimsy.stands.ui.components.FeedScaffold
import za.co.chimsy.stands.ui.components.Panel
import za.co.chimsy.stands.ui.components.ValueRow

@Composable
fun StatementsScreen(
    branches: List<Branch>,
    modifier: Modifier = Modifier,
    viewModel: StatementsViewModel = viewModel(factory = StatementsViewModel.Factory),
) {
    val state by viewModel.uiState.collectAsStateWithLifecycle()

    FeedScaffold(
        state = state,
        branches = branches,
        onScopeSelected = viewModel::onScopeSelected,
        onRefresh = viewModel::onRefresh,
        emptyMessage = "No statements yet. Pull down to load them.",
        modifier = modifier,
    ) { statements -> statementsContent(statements) }
}

private fun LazyListScope.statementsContent(statements: Statements) {
    /**
     * The ledger failing to balance is not a display detail - it means
     * something wrote to the journal outside the posting rules, and every
     * figure below it is suspect until that is explained.
     */
    if (!statements.inBalance) {
        item {
            Panel(
                title = "The ledger does not balance",
                subtitle = "Stop and investigate before relying on these figures.",
                modifier = Modifier.padding(horizontal = 16.dp),
            ) {}
        }
    }

    item {
        val income = statements.incomeStatement

        Panel(
            title = "Income statement",
            subtitle = "${income.from} to ${income.to}",
            modifier = Modifier.padding(horizontal = 16.dp),
        ) {
            income.revenue.forEach { ValueRow(it.name, it.amountCents.asMoney()) }
            income.expenses.forEach { ValueRow(it.name, "(${it.amountCents.asMoney()})") }
            ValueRow("Net income", income.netIncomeCents.asMoney(), emphasised = true)
        }
    }

    item {
        val sheet = statements.balanceSheet

        Panel(
            title = "Balance sheet",
            subtitle = "As at ${sheet.asAt}",
            modifier = Modifier.padding(horizontal = 16.dp),
        ) {
            SectionLabel("Assets")
            sheet.assets.forEach { ValueRow(it.name, it.amountCents.asMoney()) }
            ValueRow("Total assets", sheet.assetsCents.asMoney(), emphasised = true)

            if (sheet.liabilities.isNotEmpty()) {
                SectionLabel("Liabilities")
                sheet.liabilities.forEach { ValueRow(it.name, it.amountCents.asMoney()) }
            }

            SectionLabel("Equity")
            sheet.equity.forEach { ValueRow(it.name, it.amountCents.asMoney()) }
            ValueRow(
                "Liabilities and equity",
                (sheet.liabilitiesCents + sheet.equityCents).asMoney(),
                emphasised = true,
            )
        }
    }

    item {
        val ageing = statements.ageing

        Panel(
            title = "Receivables ageing",
            subtitle = "${ageing.overdueCents.asMoney()} of ${ageing.totalCents.asMoney()} is overdue",
            modifier = Modifier.padding(horizontal = 16.dp),
        ) {
            ValueRow("Not yet due", ageing.buckets.notYetDue.asMoney())
            ValueRow("1 – 30 days", ageing.buckets.days1To30.asMoney())
            ValueRow("31 – 60 days", ageing.buckets.days31To60.asMoney())
            ValueRow("61 – 90 days", ageing.buckets.days61To90.asMoney())
            ValueRow("Over 90 days", ageing.buckets.over90Days.asMoney())
            ValueRow("Total", ageing.totalCents.asMoney(), emphasised = true)
        }
    }

    item {
        val trial = statements.trialBalance

        Panel(
            title = "Trial balance",
            subtitle = "As at ${trial.asAt}",
            modifier = Modifier.padding(horizontal = 16.dp),
        ) {
            trial.rows.forEach { row ->
                val amount = if (row.debitCents > 0) row.debitCents else row.creditCents
                val side = if (row.debitCents > 0) "Dr" else "Cr"

                ValueRow(
                    label = "${row.code} ${row.name}",
                    value = if (amount == 0L) "—" else "$side ${amount.asMoney()}",
                )
            }

            ValueRow("Total debits", trial.totalDebitCents.asMoney(), emphasised = true)
            ValueRow("Total credits", trial.totalCreditCents.asMoney(), emphasised = true)
        }
    }
}

@Composable
private fun SectionLabel(text: String) {
    Text(
        text = text.uppercase(),
        style = MaterialTheme.typography.labelSmall,
        color = MaterialTheme.colorScheme.onSurfaceVariant,
        modifier = Modifier.padding(top = 12.dp, bottom = 2.dp),
    )
}
