package za.co.chimsy.stands.core

import java.text.NumberFormat
import java.util.Locale

/**
 * Money crosses the API as integer minor units and is only ever divided at the
 * edge, so nothing in between can round it. These are that edge.
 */
private val currency: NumberFormat = NumberFormat.getCurrencyInstance(Locale.US).apply {
    maximumFractionDigits = 0
}

fun Long.asMoney(): String = currency.format(this / 100.0)

/** Sales come back in major units already; the API divides those server-side. */
fun Double.asMoney(): String = currency.format(this)

private val magnitudes = listOf(1_000_000_000L to "B", 1_000_000L to "M", 1_000L to "K")

/** For axis ticks and tiles, where the exact cents would crowd out the shape of the data. */
fun Long.asCompactMoney(): String {
    val value = this / 100.0
    val magnitude = magnitudes.firstOrNull { kotlin.math.abs(value) >= it.first }
        ?: return currency.format(value)

    val scaled = value / magnitude.first
    val decimals = if (kotlin.math.abs(scaled) < 10 && scaled % 1.0 != 0.0) 1 else 0

    return "$%.${decimals}f%s".format(Locale.US, scaled, magnitude.second)
}
