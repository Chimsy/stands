package za.co.chimsy.stands.data.session

import android.content.Context
import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.map

private val Context.sessionDataStore: DataStore<Preferences> by preferencesDataStore(name = "session")

/**
 * What the app knows about who is signed in and which office they are looking
 * at, across process death.
 *
 * The token is encrypted by [TokenCipher]; the branch code is not, because it
 * is not a secret and the API re-checks entitlement on every request anyway.
 */
class SessionStore(
    context: Context,
    private val cipher: TokenCipher = TokenCipher(),
) {
    private val dataStore = context.applicationContext.sessionDataStore

    /** Emits null whenever there is no usable token, which is what drives the sign-in screen. */
    val token: Flow<String?> = dataStore.data.map { preferences ->
        preferences[TOKEN]?.let(cipher::decrypt)
    }

    val activeBranch: Flow<String?> = dataStore.data.map { it[ACTIVE_BRANCH] }

    suspend fun currentToken(): String? = token.first()

    suspend fun currentBranch(): String? = activeBranch.first()

    suspend fun start(token: String) {
        dataStore.edit { preferences ->
            preferences[TOKEN] = cipher.encrypt(token)
            /** A new sign-in must not inherit the last account's choice of office. */
            preferences.remove(ACTIVE_BRANCH)
        }
    }

    suspend fun switchBranch(code: String) {
        dataStore.edit { it[ACTIVE_BRANCH] = code }
    }

    suspend fun end() {
        dataStore.edit { it.clear() }
    }

    private companion object {
        val TOKEN = stringPreferencesKey("token")
        val ACTIVE_BRANCH = stringPreferencesKey("active_branch")
    }
}
