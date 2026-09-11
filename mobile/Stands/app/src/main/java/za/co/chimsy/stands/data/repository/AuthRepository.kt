package za.co.chimsy.stands.data.repository

import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.map
import kotlinx.serialization.json.Json
import za.co.chimsy.stands.core.AppError
import za.co.chimsy.stands.core.AppResult
import za.co.chimsy.stands.data.local.dao.SaleDao
import za.co.chimsy.stands.data.remote.StandsApi
import za.co.chimsy.stands.data.remote.apiCall
import za.co.chimsy.stands.data.remote.dto.BranchDto
import za.co.chimsy.stands.data.remote.dto.LoginRequestDto
import za.co.chimsy.stands.data.remote.dto.UserDto
import za.co.chimsy.stands.data.session.SessionStore
import za.co.chimsy.stands.domain.model.AuthenticatedUser
import za.co.chimsy.stands.domain.model.Branch

/**
 * Signing in, signing out, and which office the account is working from.
 *
 * Ending a session clears the cache as well as the token: the figures on this
 * device belong to whoever was signed in, and leaving them for the next person
 * to open the app would show them another account's books.
 */
class AuthRepository(
    private val api: StandsApi,
    private val session: SessionStore,
    private val reportCache: ReportCache,
    private val saleDao: SaleDao,
    private val json: Json,
) {
    /** Null whenever there is no usable token, which is what sends the app to sign-in. */
    val isSignedIn: Flow<Boolean> = session.token.map { it != null }

    /**
     * The signed-in account, from the cache.
     *
     * Cached like everything else so that opening the app without a connection
     * still knows who is signed in and which branches they may look at - the
     * branch picker would otherwise be empty exactly when the cached figures
     * behind it are the only ones available.
     */
    fun observeUser(): Flow<AuthenticatedUser?> =
        reportCache.observe(USER_KEY, UserDto.serializer()).map { it?.value?.toDomain() }

    suspend fun refreshUser(): AppResult<Unit> =
        reportCache.refresh(USER_KEY, UserDto.serializer()) { api.user().data }

    suspend fun signIn(email: String, password: String, deviceName: String): AppResult<AuthenticatedUser> {
        val response = apiCall(json) {
            api.login(LoginRequestDto(email = email, password = password, deviceName = deviceName))
        }

        return when (response) {
            is AppResult.Failure -> response
            is AppResult.Success -> {
                val user = response.value.user.toDomain()

                /**
                 * This app is the head-office view. An agent has a perfectly
                 * good account, just not one this build has screens for, so
                 * they are told plainly rather than shown an empty dashboard.
                 */
                if (!user.isAdmin) {
                    return AppResult.Failure(
                        AppError.Forbidden("This app is for administrators. Please use the web app instead."),
                    )
                }

                clearLocalData()
                /** The account is known already; caching it now saves a round trip on first paint. */
                reportCache.put(USER_KEY, UserDto.serializer(), response.value.user)
                session.start(response.value.token)

                AppResult.Success(user)
            }
        }
    }

    /**
     * Revoking the token is best-effort: if the API has already forgotten it
     * the account is signed out either way, so a failure here must not leave
     * the app stuck on a session it cannot use.
     */
    suspend fun signOut() {
        apiCall(json) { api.logout() }
        clearLocalData()
        session.end()
    }

    /**
     * Ends the session without calling the API, for when the API is what told
     * us the token is dead. Calling logout with a rejected token would only
     * earn another 401.
     */
    suspend fun expire() {
        clearLocalData()
        session.end()
    }

    private suspend fun clearLocalData() {
        reportCache.clear()
        saleDao.clearAll()
    }

    private companion object {
        const val USER_KEY = "user"
    }
}

private fun UserDto.toDomain() = AuthenticatedUser(
    name = name,
    email = email,
    isAdmin = isAdmin,
    branch = branch?.toDomain(),
    branches = branches.map(BranchDto::toDomain),
)

private fun BranchDto.toDomain() = Branch(code = code, name = name, city = city)
