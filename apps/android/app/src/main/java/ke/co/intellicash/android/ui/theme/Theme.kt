package ke.co.intellicash.android.ui.theme

import android.os.Build
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext

val IntelliGreen = Color(0xFF0D7C3D)
val IntelliGreenLight = Color(0xFF4CAF50)
val IntelliGreenDark = Color(0xFF005A1F)
val IntelliGold = Color(0xFFF9A825)
val IntelliBlue = Color(0xFF1565C0)

private val LightColors = lightColorScheme(
    primary = IntelliGreen,
    onPrimary = Color.White,
    primaryContainer = Color(0xFFB8E6C8),
    onPrimaryContainer = IntelliGreenDark,
    secondary = IntelliGold,
    onSecondary = Color.Black,
    secondaryContainer = Color(0xFFFFF3C4),
    tertiary = IntelliBlue,
    onTertiary = Color.White,
    background = Color(0xFFFAFAFA),
    surface = Color.White,
    surfaceVariant = Color(0xFFF0F4F0),
    error = Color(0xFFD32F2F)
)

private val DarkColors = darkColorScheme(
    primary = IntelliGreenLight,
    onPrimary = Color.Black,
    primaryContainer = IntelliGreenDark,
    onPrimaryContainer = Color(0xFFB8E6C8),
    secondary = IntelliGold,
    onSecondary = Color.Black,
    tertiary = Color(0xFF64B5F6),
    background = Color(0xFF121212),
    surface = Color(0xFF1E1E1E),
    surfaceVariant = Color(0xFF2A2A2A),
    error = Color(0xFFEF5350)
)

@Composable
fun IntelliCashTheme(
    darkTheme: Boolean = isSystemInDarkTheme(),
    content: @Composable () -> Unit
) {
    val colorScheme = when {
        Build.VERSION.SDK_INT >= Build.VERSION_CODES.S -> {
            val context = LocalContext.current
            if (darkTheme) dynamicDarkColorScheme(context)
            else dynamicLightColorScheme(context)
        }
        darkTheme -> DarkColors
        else -> LightColors
    }

    MaterialTheme(
        colorScheme = colorScheme,
        typography = Typography(),
        content = content
    )
}
