package za.co.chimsy.stands.ui

import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewmodel.CreationExtras
import za.co.chimsy.stands.StandsApplication
import za.co.chimsy.stands.di.AppContainer

/**
 * Reaches the single object graph from inside a ViewModel factory.
 *
 * Every factory in the app builds its ViewModel from this, so a ViewModel
 * itself never touches Android APIs or a service locator - it takes what it
 * needs through its constructor and a test can hand it fakes.
 */
val CreationExtras.appContainer: AppContainer
    get() = (this[ViewModelProvider.AndroidViewModelFactory.APPLICATION_KEY] as StandsApplication).container
