package za.co.chimsy.stands.ui.login

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.initializer
import androidx.lifecycle.viewmodel.viewModelFactory
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import za.co.chimsy.stands.core.AppError
import za.co.chimsy.stands.core.AppResult
import za.co.chimsy.stands.data.repository.AuthRepository
import za.co.chimsy.stands.ui.appContainer

data class LoginUiState(
    val email: String = "",
    val password: String = "",
    val isSubmitting: Boolean = false,
    val error: String? = null,
) {
    val canSubmit: Boolean get() = email.isNotBlank() && password.isNotBlank() && !isSubmitting
}

class LoginViewModel(
    private val authRepository: AuthRepository,
    private val deviceName: String,
) : ViewModel() {

    private val _uiState = MutableStateFlow(LoginUiState())
    val uiState: StateFlow<LoginUiState> = _uiState.asStateFlow()

    fun onEmailChange(value: String) = _uiState.update { it.copy(email = value, error = null) }

    fun onPasswordChange(value: String) = _uiState.update { it.copy(password = value, error = null) }

    fun onSubmit() {
        if (!_uiState.value.canSubmit) return

        _uiState.update { it.copy(isSubmitting = true, error = null) }

        viewModelScope.launch {
            val result = authRepository.signIn(
                email = _uiState.value.email.trim(),
                password = _uiState.value.password,
                deviceName = deviceName,
            )

            _uiState.update { state ->
                when (result) {
                    /** Success navigates by way of the session flow, so nothing to set here. */
                    is AppResult.Success -> state.copy(isSubmitting = false, password = "")
                    is AppResult.Failure -> state.copy(isSubmitting = false, error = result.error.readable())
                }
            }
        }
    }

    companion object {
        fun factory(deviceName: String) = viewModelFactory {
            initializer { LoginViewModel(appContainer.authRepository, deviceName) }
        }
    }
}

/** What to put in front of someone who just failed to sign in. */
private fun AppError.readable(): String = when (this) {
    AppError.Offline -> "Could not reach the server. Check your connection and try again."
    AppError.Unauthenticated -> "Those details were not accepted."
    is AppError.Forbidden -> message
    is AppError.Rejected -> fieldErrors["email"] ?: fieldErrors["password"] ?: message
    is AppError.Unexpected -> message
}
