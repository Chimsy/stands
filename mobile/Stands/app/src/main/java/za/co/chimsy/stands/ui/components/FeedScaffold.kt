package za.co.chimsy.stands.ui.components

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyListScope
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import za.co.chimsy.stands.core.AppError
import za.co.chimsy.stands.domain.model.Branch
import za.co.chimsy.stands.domain.model.Scope
import za.co.chimsy.stands.ui.FeedUiState

/**
 * The frame every cached screen shares: a scope picker, when the figures were
 * last true, a pull to refresh, and whatever the screen itself draws.
 *
 * Having it in one place is what keeps the three screens honest about their
 * age - a screen cannot forget to say when it last heard from the server,
 * because saying so is not its job.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun <T> FeedScaffold(
    state: FeedUiState<T>,
    branches: List<Branch>,
    onScopeSelected: (Scope) -> Unit,
    onRefresh: () -> Unit,
    emptyMessage: String,
    modifier: Modifier = Modifier,
    content: LazyListScope.(T) -> Unit,
) {
    PullToRefreshBox(
        isRefreshing = state.isRefreshing,
        onRefresh = onRefresh,
        modifier = modifier.fillMaxSize(),
    ) {
        LazyColumn(
            modifier = Modifier.fillMaxSize(),
            contentPadding = PaddingValues(bottom = 32.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            item {
                Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    ScopeChips(
                        branches = branches,
                        selected = state.scope,
                        onSelect = onScopeSelected,
                        modifier = Modifier.padding(top = 8.dp),
                    )

                    LastRefreshedLabel(
                        fetchedAt = state.fetchedAt,
                        isRefreshing = state.isRefreshing,
                        modifier = Modifier.padding(horizontal = 16.dp),
                    )
                }
            }

            state.error?.let { error ->
                item { ErrorNotice(error, hasCachedData = state.data != null) }
            }

            val data = state.data

            when {
                data != null -> content(data)

                state.isLoading -> item {
                    LinearProgressIndicator(
                        modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp),
                    )
                }

                else -> item {
                    Box(Modifier.fillMaxWidth().padding(32.dp), contentAlignment = Alignment.Center) {
                        Text(emptyMessage, style = MaterialTheme.typography.bodyMedium)
                    }
                }
            }
        }
    }
}

/**
 * A failed refresh over cached figures is a note, not a takeover: the numbers
 * underneath are still the last thing the server said, and the label above
 * already dates them.
 */
@Composable
private fun ErrorNotice(error: AppError, hasCachedData: Boolean) {
    val message = when (error) {
        AppError.Offline ->
            if (hasCachedData) "Offline - showing the last figures this device received."
            else "Could not reach the server."
        AppError.Unauthenticated -> "Your session has expired."
        is AppError.Forbidden -> error.message
        is AppError.Rejected -> error.message
        is AppError.Unexpected -> error.message
    }

    Card(
        modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.errorContainer),
    ) {
        Text(
            text = message,
            modifier = Modifier.padding(12.dp),
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onErrorContainer,
        )
    }
}
