package za.co.chimsy.stands.ui.components

import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.unit.dp
import za.co.chimsy.stands.core.asCompactMoney
import za.co.chimsy.stands.core.asMoney
import za.co.chimsy.stands.data.remote.dto.MonthlyPointDto

/**
 * Value signed against cash collected, month by month.
 *
 * Both series are money in the same currency, so they share one baseline -
 * that is the only reason the two are comparable by eye. Drawn with Canvas
 * rather than a charting library: two series of nine points is less code than
 * configuring someone else's chart, and it keeps the app free of a dependency
 * that would have to be themed to match anyway.
 */
@Composable
fun MonthlyBars(
    points: List<MonthlyPointDto>,
    signedColour: Color,
    collectedColour: Color,
    modifier: Modifier = Modifier,
) {
    if (points.isEmpty()) {
        Text("Nothing to plot yet.", style = MaterialTheme.typography.bodySmall)
        return
    }

    val ceiling = points.maxOf { maxOf(it.valueCents, it.collectedCents) }.coerceAtLeast(1L)
    val signedTotal = points.sumOf { it.valueCents }
    val collectedTotal = points.sumOf { it.collectedCents }

    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        Row(horizontalArrangement = Arrangement.spacedBy(16.dp)) {
            LegendKey("Signed", signedColour, signedTotal.asMoney())
            LegendKey("Collected", collectedColour, collectedTotal.asMoney())
        }

        Text(
            /** The axis the bars are measured against, stated rather than drawn. */
            "Tallest month ${ceiling.asCompactMoney()}",
            style = MaterialTheme.typography.labelSmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )

        Canvas(
            modifier = modifier
                .fillMaxWidth()
                .height(140.dp)
                .semantics {
                    contentDescription = points.joinToString(separator = ". ") {
                        "${it.month}: ${it.valueCents.asMoney()} signed, ${it.collectedCents.asMoney()} collected"
                    }
                },
        ) {
            drawMonthlyBars(points, ceiling, signedColour, collectedColour)
        }

        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
        ) {
            Text(points.first().month.monthLabel(), style = MaterialTheme.typography.labelSmall)
            Text(points.last().month.monthLabel(), style = MaterialTheme.typography.labelSmall)
        }
    }
}

private fun DrawScope.drawMonthlyBars(
    points: List<MonthlyPointDto>,
    ceiling: Long,
    signedColour: Color,
    collectedColour: Color,
) {
    val band = size.width / points.size
    /** A gap in the background does the separating, so no bar needs an outline. */
    val barWidth = ((band - 6.dp.toPx()) / 2).coerceAtLeast(1f)
    val radius = CornerRadius(barWidth.coerceAtMost(8.dp.toPx()) / 2)

    points.forEachIndexed { index, point ->
        val left = band * index + 2.dp.toPx()

        drawBar(left, point.valueCents, ceiling, barWidth, signedColour, radius)
        drawBar(left + barWidth + 2.dp.toPx(), point.collectedCents, ceiling, barWidth, collectedColour, radius)
    }
}

private fun DrawScope.drawBar(
    left: Float,
    cents: Long,
    ceiling: Long,
    width: Float,
    colour: Color,
    radius: CornerRadius,
) {
    if (cents <= 0) return

    val height = (cents.toFloat() / ceiling) * size.height

    drawRoundRect(
        color = colour,
        topLeft = Offset(left, size.height - height),
        size = Size(width, height),
        cornerRadius = radius,
    )
}

@Composable
private fun LegendKey(label: String, colour: Color, total: String) {
    Row(horizontalArrangement = Arrangement.spacedBy(6.dp), verticalAlignment = Alignment.CenterVertically) {
        Box(Modifier.size(10.dp).clip(RoundedCornerShape(2.dp)).background(colour))
        Text("$label $total", style = MaterialTheme.typography.labelSmall)
    }
}

/** `2026-03` reads as `Mar`; the year is carried by the panel's subtitle. */
private fun String.monthLabel(): String {
    val month = substringAfter('-', "").toIntOrNull() ?: return this
    val names = listOf("Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec")

    return names.getOrNull(month - 1) ?: this
}
