package za.co.chimsy.stands

import android.os.Build
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import za.co.chimsy.stands.ui.StandsApp
import za.co.chimsy.stands.ui.theme.StandsTheme

/**
 * The only Activity. Everything above it is Compose and everything below it is
 * the object graph built in [StandsApplication].
 */
class MainActivity : ComponentActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()

        setContent {
            StandsTheme {
                /** Labels the API token so the account holder can tell their devices apart. */
                StandsApp(deviceName = "${Build.MANUFACTURER} ${Build.MODEL}")
            }
        }
    }
}
