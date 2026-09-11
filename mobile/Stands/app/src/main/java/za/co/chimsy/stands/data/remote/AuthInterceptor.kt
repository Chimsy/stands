package za.co.chimsy.stands.data.remote

import kotlinx.coroutines.runBlocking
import okhttp3.Interceptor
import okhttp3.Response

/**
 * Attaches the credentials every authenticated call needs.
 *
 * Both live here rather than on each request so a screen can never forget one:
 * the bearer token identifies the account, and `X-Branch` settles which office
 * the API answers for. Reading them per request - rather than caching them - is
 * what makes signing out and switching branch take effect immediately.
 */
class AuthInterceptor(
    private val tokenProvider: suspend () -> String?,
    private val branchProvider: suspend () -> String?,
) : Interceptor {

    override fun intercept(chain: Interceptor.Chain): Response {
        val original = chain.request()

        /**
         * OkHttp interceptors are blocking by contract and run on a background
         * dispatcher, so bridging to the suspending session store here is safe;
         * the alternative is threading a token through every call site.
         */
        val token = runBlocking { tokenProvider() }
        val branch = runBlocking { branchProvider() }

        val request = original.newBuilder()
            .header("Accept", "application/json")
            .apply {
                token?.let { header("Authorization", "Bearer $it") }
                branch?.let { header("X-Branch", it) }
            }
            .build()

        return chain.proceed(request)
    }
}
