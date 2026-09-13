package za.co.chimsy.stands.ui.theme

import android.os.Build
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.dynamicDarkColorScheme
import androidx.compose.material3.dynamicLightColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.platform.LocalContext

private val LightColorScheme = lightColorScheme(
    primary = BrandGreen600,
    onPrimary = PaperLightRaised,
    primaryContainer = BrandGreen100,
    onPrimaryContainer = BrandGreen950,

    secondary = MarineBlue600,
    onSecondary = PaperLightRaised,
    secondaryContainer = MarineBlue100,
    onSecondaryContainer = MarineBlue950,

    tertiary = MarineBlue800,
    onTertiary = PaperLightRaised,
    tertiaryContainer = MarineBlue200,
    onTertiaryContainer = MarineBlue950,

    background = PaperLight,
    onBackground = InkLight,
    surface = PaperLightRaised,
    onSurface = InkLight,
    surfaceVariant = BrandGreen50,
    onSurfaceVariant = InkLightMuted,
    surfaceContainerLowest = PaperLightRaised,
    surfaceContainerLow = PaperLightSunken,
    surfaceContainer = PaperLightSunken,

    outline = OutlineLight,
    outlineVariant = OutlineLightVariant,

    error = ErrorLight,
    onError = PaperLightRaised,
    errorContainer = ErrorContainerLight,
    onErrorContainer = OnErrorContainerLight,
)

private val DarkColorScheme = darkColorScheme(
    primary = BrandGreen400,
    onPrimary = BrandGreen950,
    primaryContainer = BrandGreen700,
    onPrimaryContainer = BrandGreen100,

    secondary = MarineBlue400,
    onSecondary = MarineBlue950,
    secondaryContainer = MarineBlue800,
    onSecondaryContainer = MarineBlue100,

    tertiary = MarineBlue300,
    onTertiary = MarineBlue950,
    tertiaryContainer = MarineBlue900,
    onTertiaryContainer = MarineBlue100,

    background = PaperDark,
    onBackground = InkDark,
    surface = PaperDarkRaised,
    onSurface = InkDark,
    surfaceVariant = OutlineDarkVariant,
    onSurfaceVariant = InkDarkMuted,
    surfaceContainerLowest = PaperDark,
    surfaceContainerLow = PaperDarkSunken,
    surfaceContainer = PaperDarkSunken,

    outline = OutlineDark,
    outlineVariant = OutlineDarkVariant,

    error = ErrorDark,
    onError = OnErrorContainerLight,
    errorContainer = ErrorContainerDark,
    onErrorContainer = OnErrorContainerDark,
)

/**
 * The app wears Beyond Reality's colours, not the handset's wallpaper, so
 * [dynamicColor] is off by default - Material You would otherwise repaint the
 * whole head-office view whenever someone changes their home screen, and the
 * green a branch figure is shown in is part of the brand.
 */
@Composable
fun StandsTheme(
    darkTheme: Boolean = isSystemInDarkTheme(),
    dynamicColor: Boolean = false,
    content: @Composable () -> Unit,
) {
    val colorScheme = when {
        dynamicColor && Build.VERSION.SDK_INT >= Build.VERSION_CODES.S -> {
            val context = LocalContext.current
            if (darkTheme) dynamicDarkColorScheme(context) else dynamicLightColorScheme(context)
        }

        darkTheme -> DarkColorScheme
        else -> LightColorScheme
    }

    MaterialTheme(
        colorScheme = colorScheme,
        typography = Typography,
        content = content,
    )
}
