package za.co.chimsy.stands

import android.app.Application
import za.co.chimsy.stands.di.AppContainer

class StandsApplication : Application() {

    /** The single object graph; every ViewModel factory reaches it through this. */
    lateinit var container: AppContainer
        private set

    override fun onCreate() {
        super.onCreate()
        container = AppContainer(this)
    }
}
