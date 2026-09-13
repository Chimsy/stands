package za.co.chimsy.stands.ui.theme

import androidx.compose.ui.graphics.Color

/**
 * Beyond Reality's two inks, as full scales. The same values back the web app's
 * `--color-brand-*` and `--color-marine-*` tokens, so a figure shown here and
 * on the dashboard is the same green.
 *
 * Light and dark pick different *steps* rather than redefining a hue: the green
 * that carries white text on paper is too dark to read against a near-black
 * surface, so dark mode moves up the scale and flips the label to ink.
 */

// Grass green - the wordmark, and the app's primary action.
val BrandGreen50 = Color(0xFFEEFBF2)
val BrandGreen100 = Color(0xFFD6F5E0)
val BrandGreen200 = Color(0xFFAEEAC4)
val BrandGreen300 = Color(0xFF76D99F)
val BrandGreen400 = Color(0xFF3CC275)
val BrandGreen500 = Color(0xFF18A84F)
val BrandGreen600 = Color(0xFF12873F)
val BrandGreen700 = Color(0xFF0D6B33)
val BrandGreen800 = Color(0xFF0D552B)
val BrandGreen900 = Color(0xFF0C4625)
val BrandGreen950 = Color(0xFF042713)

// Sky blue - the roundel, and everything secondary.
val MarineBlue50 = Color(0xFFEFF8FE)
val MarineBlue100 = Color(0xFFDAEEFC)
val MarineBlue200 = Color(0xFFBDE1FA)
val MarineBlue300 = Color(0xFF8FCEF5)
val MarineBlue400 = Color(0xFF56B3EC)
val MarineBlue500 = Color(0xFF2B98DC)
val MarineBlue600 = Color(0xFF1179BD)
val MarineBlue700 = Color(0xFF0F6098)
val MarineBlue800 = Color(0xFF11517C)
val MarineBlue900 = Color(0xFF134466)
val MarineBlue950 = Color(0xFF0C2B44)

// Surfaces: neutrals pulled a few degrees towards the green so the chrome sits
// with the brand instead of beside it.
val PaperLight = Color(0xFFF6F8F6)
val PaperLightRaised = Color(0xFFFFFFFF)
val PaperLightSunken = Color(0xFFF1F5F1)
val InkLight = Color(0xFF10231A)
val InkLightMuted = Color(0xFF40514A)
val OutlineLight = Color(0xFF70817A)
val OutlineLightVariant = Color(0xFFC2CFC7)

val PaperDark = Color(0xFF07100C)
val PaperDarkRaised = Color(0xFF0B1410)
val PaperDarkSunken = Color(0xFF111C16)
val InkDark = Color(0xFFE8F1EA)
val InkDarkMuted = Color(0xFFB6C7BC)
val OutlineDark = Color(0xFF7E8F85)
val OutlineDarkVariant = Color(0xFF3C4A42)

val ErrorLight = Color(0xFFB3261E)
val ErrorContainerLight = Color(0xFFF9DEDC)
val OnErrorContainerLight = Color(0xFF410E0B)

val ErrorDark = Color(0xFFF2B8B5)
val ErrorContainerDark = Color(0xFF8C1D18)
val OnErrorContainerDark = Color(0xFFF9DEDC)
