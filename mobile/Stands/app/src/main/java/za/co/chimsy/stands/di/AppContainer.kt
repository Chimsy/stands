package za.co.chimsy.stands.di

import android.content.Context
import kotlinx.serialization.json.Json
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.kotlinx.serialization.asConverterFactory
import za.co.chimsy.stands.BuildConfig
import za.co.chimsy.stands.data.local.StandsDatabase
import za.co.chimsy.stands.data.remote.AuthInterceptor
import za.co.chimsy.stands.data.remote.DevHostDns
import za.co.chimsy.stands.data.remote.StandsApi
import za.co.chimsy.stands.data.repository.AuthRepository
import za.co.chimsy.stands.data.repository.DashboardRepository
import za.co.chimsy.stands.data.repository.ReportCache
import za.co.chimsy.stands.data.repository.SalesRepository
import za.co.chimsy.stands.data.repository.StatementsRepository
import za.co.chimsy.stands.data.session.SessionStore
import java.net.URI
import java.util.concurrent.TimeUnit

/**
 * Builds the object graph once, at the top of the app.
 *
 * Dependency injection is done by hand rather than with a framework: the graph
 * is small and entirely constructor-injected, which is what actually makes the
 * ViewModels testable - a test builds one with fakes and needs nothing from
 * here. Adding a code generator to wire twelve objects would cost build time
 * and a toolchain constraint without changing a line of the tests.
 *
 * Everything is lazy so that starting the app does not open a database or a
 * socket before a screen asks for one.
 */
class AppContainer(context: Context) {

    private val appContext = context.applicationContext

    val json: Json = Json {
        /** The API is free to add fields without breaking installed clients. */
        ignoreUnknownKeys = true
        explicitNulls = false
    }

    private val database: StandsDatabase by lazy { StandsDatabase.build(appContext) }

    val sessionStore: SessionStore by lazy { SessionStore(appContext) }

    private val httpClient: OkHttpClient by lazy {
        OkHttpClient.Builder()
            .addInterceptor(
                AuthInterceptor(
                    tokenProvider = sessionStore::currentToken,
                    branchProvider = sessionStore::currentBranch,
                ),
            )
            .apply {
                if (BuildConfig.DEBUG) {
                    dns(DevHostDns(URI(BuildConfig.API_BASE_URL).host, BuildConfig.DEV_HOST_ADDRESS))
                    /** Headers only: bodies would put bearer tokens and buyer details in logcat. */
                    addInterceptor(HttpLoggingInterceptor().apply { level = HttpLoggingInterceptor.Level.HEADERS })
                }
            }
            .connectTimeout(15, TimeUnit.SECONDS)
            .readTimeout(30, TimeUnit.SECONDS)
            .build()
    }

    private val api: StandsApi by lazy {
        Retrofit.Builder()
            .baseUrl(BuildConfig.API_BASE_URL)
            .client(httpClient)
            .addConverterFactory(json.asConverterFactory("application/json".toMediaType()))
            .build()
            .create(StandsApi::class.java)
    }

    private val reportCache: ReportCache by lazy { ReportCache(database.reportDao(), json) }

    val authRepository: AuthRepository by lazy {
        AuthRepository(api, sessionStore, reportCache, database.saleDao(), json)
    }

    val dashboardRepository: DashboardRepository by lazy { DashboardRepository(api, reportCache) }

    val statementsRepository: StatementsRepository by lazy { StatementsRepository(api, reportCache) }

    val salesRepository: SalesRepository by lazy {
        SalesRepository(api, database.saleDao(), database.reportDao(), json)
    }
}
