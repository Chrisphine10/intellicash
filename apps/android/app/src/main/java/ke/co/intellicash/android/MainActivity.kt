package ke.co.intellicash.android

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import dagger.hilt.android.AndroidEntryPoint
import ke.co.intellicash.android.ui.navigation.IntelliCashNavHost
import ke.co.intellicash.android.ui.theme.IntelliCashTheme

@AndroidEntryPoint
class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        setContent {
            IntelliCashTheme {
                IntelliCashNavHost()
            }
        }
    }
}
