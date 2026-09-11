package za.co.chimsy.stands.ui.dashboard

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
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
import za.co.chimsy.stands.data.remote.dto.DashboardDto
import za.co.chimsy.stands.domain.model.Branch
import za.co.chimsy.stands.domain.model.Scope
import za.co.chimsy.stands.ui.components.FeedScaffold
import za.co.chimsy.stands.ui.components.MonthlyBars
import za.co.chimsy.stands.ui.components.Panel
import za.co.chimsy.stands.ui.components.ValueRow

@Composable
fun DashboardScreen(
    branches: List<Branch>,
    modifier: Modifier = Modifier,
    viewModel: DashboardViewModel = viewModel(factory = DashboardViewModel.Factory),
) {
    val state by viewModel.uiState.collectAsStateWithLifecycle()

    FeedScaffold(
        state = state,
        branches = branches,
        onScopeSelected = viewModel::onScopeSelected,
        onRefresh = viewModel::onRefresh,
        emptyMessage = "No figures yet. Pull down to load them.",
        modifier = modifier,
    ) { dashboard -> dashboardContent(dashboard, state.scope) }
}

private fun LazyListScope.dashboardContent(dashboard: DashboardDto, scope: Scope) {
    val totals = dashboard.totals

    item {
        Panel(
            title = "Value signed",
            subtitle = "${dashboard.from} to ${dashboard.to}",
            modifier = Modifier.padding(horizontal = 16.dp),
        ) {
            Text(totals.valueCents.asMoney(), style = MaterialTheme.typography.headlineMedium)
            Text(
                "${totals.salesCount} ${if (totals.salesCount == 1) "sale" else "sales"} in the period",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )

            Column(modifier = Modifier.padding(top = 12.dp)) {
                ValueRow("Cash collected", totals.collectedCents.asMoney())
                ValueRow("Still owed", totals.outstandingCents.asMoney())
                ValueRow("Gross profit", totals.grossProfitCents.asMoney())
            }
        }
    }

    item {
        Panel(
            title = "Stock",
            subtitle = "${totals.standsAvailable} of ${totals.standsTotal} still available",
            modifier = Modifier.padding(horizontal = 16.dp),
        ) {
            ValueRow("Sold", totals.standsSold.toString())
            ValueRow("In progress", totals.standsInProgress.toString())
            ValueRow("Available", totals.standsAvailable.toString())
        }
    }

    item {
        Panel(
            title = "Signings and collections",
            subtitle = "Revenue is recognised at signing, so the two rarely land in the same month",
            modifier = Modifier.padding(horizontal = 16.dp),
        ) {
            MonthlyBars(
                points = dashboard.monthly,
                signedColour = MaterialTheme.colorScheme.primary,
                collectedColour = MaterialTheme.colorScheme.tertiary,
            )
        }
    }

    if (scope is Scope.Group && dashboard.branches.size > 1) {
        item {
            Panel(
                title = "Branch performance",
                subtitle = "Value signed in the period",
                modifier = Modifier.padding(horizontal = 16.dp),
            ) {
                dashboard.branches.sortedByDescending { it.valueCents }.forEach { branch ->
                    ValueRow(
                        label = branch.name,
                        value = branch.valueCents.asMoney(),
                        caption = "${branch.salesCount} sales · ${branch.collectedCents.asMoney()} collected",
                    )
                }
            }
        }
    }

    item {
        Panel(
            title = "How it was sold",
            subtitle = "Value signed in the period",
            modifier = Modifier.padding(horizontal = 16.dp),
        ) {
            dashboard.mix.forEach { entry ->
                ValueRow(
                    label = entry.type.saleTypeLabel(),
                    value = entry.valueCents.asMoney(),
                    caption = "${entry.salesCount} ${if (entry.salesCount == 1) "sale" else "sales"}",
                )
            }
        }
    }

    if (dashboard.topAgents.isNotEmpty()) {
        item {
            Panel(
                title = "Leading agents",
                subtitle = "By value signed in the period",
                modifier = Modifier.padding(horizontal = 16.dp),
            ) {
                dashboard.topAgents.forEach { agent ->
                    ValueRow(
                        label = agent.name,
                        value = agent.valueCents.asMoney(),
                        caption = listOfNotNull(agent.branch, "${agent.salesCount} sales").joinToString(" · "),
                    )
                }
            }
        }
    }

    if (dashboard.recentSales.isNotEmpty()) {
        item {
            Panel(
                title = "Latest signings",
                subtitle = "The most recent sales, whenever they were booked",
                modifier = Modifier.padding(horizontal = 16.dp),
            ) {
                dashboard.recentSales.forEach { sale ->
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                    ) {
                        ValueRow(
                            label = sale.reference,
                            value = sale.valueCents.asMoney(),
                            caption = listOfNotNull(
                                sale.standNumber?.let { "Stand $it" },
                                sale.buyerName,
                                sale.branch,
                            ).joinToString(" · "),
                        )
                    }
                }
            }
        }
    }
}

private fun String.saleTypeLabel() = when (this) {
    "cash" -> "Cash"
    "payment-plan" -> "Payment plan"
    else -> replaceFirstChar(Char::uppercase)
}
