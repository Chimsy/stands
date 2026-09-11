package za.co.chimsy.stands.ui.components

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.FilterChip
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import za.co.chimsy.stands.domain.model.Branch
import za.co.chimsy.stands.domain.model.Scope

/** A titled card. Every block on every screen is one of these, so they line up. */
@Composable
fun Panel(
    title: String,
    subtitle: String? = null,
    modifier: Modifier = Modifier,
    content: @Composable () -> Unit,
) {
    Card(
        modifier = modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceContainerLow),
    ) {
        Column(
            modifier = Modifier.padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(4.dp),
        ) {
            Text(title, style = MaterialTheme.typography.titleSmall)
            subtitle?.let {
                Text(it, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
            Column(modifier = Modifier.padding(top = 8.dp)) { content() }
        }
    }
}

/** A label on the left and a figure on the right, which is most of a statement. */
@Composable
fun ValueRow(
    label: String,
    value: String,
    modifier: Modifier = Modifier,
    caption: String? = null,
    emphasised: Boolean = false,
) {
    Row(
        modifier = modifier.fillMaxWidth().padding(vertical = 4.dp),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Column(modifier = Modifier.weight(1f)) {
            Text(
                label,
                style = if (emphasised) MaterialTheme.typography.bodyMedium else MaterialTheme.typography.bodyMedium,
                color = if (emphasised) MaterialTheme.colorScheme.onSurface else MaterialTheme.colorScheme.onSurfaceVariant,
                maxLines = 2,
                overflow = TextOverflow.Ellipsis,
            )
            caption?.let {
                Text(it, style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
        }

        Text(
            value,
            style = if (emphasised) MaterialTheme.typography.titleSmall else MaterialTheme.typography.bodyMedium,
        )
    }
}

/**
 * Which office the screen is showing.
 *
 * Built from the branches the signed-in account may actually read, so the app
 * can never offer a scope the API would then refuse.
 */
@Composable
fun ScopeChips(
    branches: List<Branch>,
    selected: Scope,
    onSelect: (Scope) -> Unit,
    modifier: Modifier = Modifier,
) {
    LazyRow(
        modifier = modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.spacedBy(8.dp),
        contentPadding = androidx.compose.foundation.layout.PaddingValues(horizontal = 16.dp),
    ) {
        item {
            FilterChip(
                selected = selected is Scope.Group,
                onClick = { onSelect(Scope.Group) },
                label = { Text("Group") },
            )
        }

        items(branches.size) { index ->
            val branch = branches[index]

            FilterChip(
                selected = selected == Scope.Branch(branch.code),
                onClick = { onSelect(Scope.Branch(branch.code)) },
                label = { Text(branch.name) },
            )
        }
    }
}
