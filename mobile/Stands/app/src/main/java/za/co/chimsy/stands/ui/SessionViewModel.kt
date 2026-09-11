package za.co.chimsy.stands.ui

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.initializer
import androidx.lifecycle.viewmodel.viewModelFactory
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.flow.stateIn
import kotlinx.coroutines.launch
import za.co.chimsy.stands.core.AppError
import za.co.chimsy.stands.core.AppResult
import za.co.chimsy.stands.data.repository.AuthRepository
import za.co.chimsy.stands.domain.model.AuthenticatedUser

/**
 * Whether anyone is signed in, and who.
 *
 * Held above the navigation graph because it decides which half of the app
 * exists at all: the token flow is the single authority, so a token cleared
 * anywhere - a sign-out, or a 401 on any screen - lands the app back on
 * sign-in without a screen having to navigate.
 */
class SessionViewModel(private val authRepository: AuthRepository) : ViewModel() {

    /** Read from the cache, so the branch picker survives a cold start with no connection. */
    val user: StateFlow<AuthenticatedUser?> = authRepository.observeUser()
        .stateIn(viewModelScope, SharingStarted.WhileSubscribed(5_000), null)

    val state: StateFlow<SessionState> = authRepository.isSignedIn
        .map { if (it) SessionState.SignedIn else SessionState.SignedOut }
        .stateIn(viewModelScope, SharingStarted.Eagerly, SessionState.Unknown)

    init {
        viewModelScope.launch {
            authRepository.isSignedIn.collect { signedIn ->
                if (signedIn) refreshUser()
            }
        }
    }

    fun signOut() {
        viewModelScope.launch { authRepository.signOut() }
    }

    private suspend fun refreshUser() {
        val result = authRepository.refreshUser()

        /**
         * Only the API rejecting the token ends the session. An unreachable
         * server must not: signing someone out because their train went into a
         * tunnel would throw away the cache at the very moment it is the only
         * thing they have.
         */
        if (result is AppResult.Failure && result.error is AppError.Unauthenticated) {
            authRepository.expire()
        }
    }

    companion object {
        val Factory = viewModelFactory {
            initializer { SessionViewModel(appContainer.authRepository) }
        }
    }
}

enum class SessionState {
    /** The stored token has not been read yet; showing either half now would flicker. */
    Unknown,
    SignedIn,
    SignedOut,
}
