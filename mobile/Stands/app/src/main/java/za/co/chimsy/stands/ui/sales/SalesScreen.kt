package za.co.chimsy.stands.ui.sales

import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyListScope
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import za.co.chimsy.stands.core.asMoney
import za.co.chimsy.stands.domain.model.Branch
import za.co.chimsy.stands.domain.model.SaleSummary
import za.co.chimsy.stands.ui.components.FeedScaffold
import za.co.chimsy.stands.ui.components.Panel
import za.co.chimsy.stands.ui.components.ValueRow

@Composable
fun SalesScreen(
    branches: List<Branch>,
    modifier: Modifier = Modifier,
    viewModel: SalesViewModel = viewModel(factory = SalesViewModel.Factory),
) {
    val state by viewModel.uiState.collectAsStateWithLifecycle()
    val total by viewModel.totalFor(state.scope).collectAsStateWithLifecycle(initialValue = null)

    FeedScaffold(
        state = state,
        branches = branches,
        onScopeSelected = viewModel::onScopeSelected,
        onRefresh = viewModel::onRefresh,
        emptyMessage = "No sales yet. Pull down to load them.",
        modifier = modifier,
    ) { sales -> salesContent(sales, total) }
}

private fun LazyListScope.salesContent(sales: List<SaleSummary>, total: Int?) {
    item {
        Panel(
            title = "Latest sales",
            /** Says plainly that this is a window on the ledger, not the whole of it. */
            subtitle = total?.let { "Showing ${sales.size} of $it" } ?: "Showing ${sales.size}",
            modifier = Modifier.padding(horizontal = 16.dp),
        ) {
            sales.forEach { sale ->
                ValueRow(
                    label = sale.reference,
                    value = sale.price.asMoney(),
                    caption = listOfNotNull(
                        sale.saleDate,
                        sale.standNumber?.let { "Stand $it" },
                        sale.buyerName,
                        sale.branchCode,
                        if (sale.isSettled) "Settled" else "${sale.outstanding.asMoney()} owing",
                    ).joinToString(" · "),
                )
            }
        }
    }
}
