package za.co.chimsy.stands.data

import kotlinx.coroutines.runBlocking
import kotlinx.serialization.json.Json
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.mockwebserver.MockResponse
import okhttp3.mockwebserver.MockWebServer
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test
import retrofit2.Retrofit
import retrofit2.converter.kotlinx.serialization.asConverterFactory
import za.co.chimsy.stands.core.AppError
import za.co.chimsy.stands.core.AppResult
import za.co.chimsy.stands.data.remote.AuthInterceptor
import za.co.chimsy.stands.data.remote.StandsApi
import za.co.chimsy.stands.data.remote.apiCall

/**
 * The networking layer against a real HTTP server, so the contract with the
 * Laravel API is checked rather than assumed: the headers that go out, and the
 * meaning the app takes from each status that comes back.
 */
class StandsApiTest {

    private lateinit var server: MockWebServer
    private lateinit var api: StandsApi

    private val json = Json { ignoreUnknownKeys = true; explicitNulls = false }
    private var token: String? = "test-token"
    private var branch: String? = "BYO"

    @Before
    fun setUp() {
        server = MockWebServer().also { it.start() }

        val client = OkHttpClient.Builder()
            .addInterceptor(AuthInterceptor(tokenProvider = { token }, branchProvider = { branch }))
            .build()

        api = Retrofit.Builder()
            .baseUrl(server.url("/api/v1/"))
            .client(client)
            .addConverterFactory(json.asConverterFactory("application/json".toMediaType()))
            .build()
            .create(StandsApi::class.java)
    }

    @After
    fun tearDown() = server.shutdown()

    @Test
    fun `sends the bearer token and the branch the app is working from`() = runBlocking {
        server.enqueue(MockResponse().setResponseCode(200).setBody(DASHBOARD_BODY))

        apiCall(json) { api.dashboard(branch = "group") }

        val request = server.takeRequest()
        assertEquals("Bearer test-token", request.getHeader("Authorization"))
        assertEquals("BYO", request.getHeader("X-Branch"))
        assertEquals("application/json", request.getHeader("Accept"))
        assertEquals("/api/v1/dashboard?branch=group", request.path)
    }

    @Test
    fun `omits the headers when there is no session`() = runBlocking {
        token = null
        branch = null
        server.enqueue(MockResponse().setResponseCode(200).setBody(DASHBOARD_BODY))

        apiCall(json) { api.dashboard() }

        val request = server.takeRequest()
        assertEquals(null, request.getHeader("Authorization"))
        assertEquals(null, request.getHeader("X-Branch"))
    }

    @Test
    fun `reads the dashboard, including the hyphenated stand status`() = runBlocking {
        server.enqueue(MockResponse().setResponseCode(200).setBody(DASHBOARD_BODY))

        val result = apiCall(json) { api.dashboard(branch = "group").data }

        val dashboard = (result as AppResult.Success).value
        assertEquals(590_220_000L, dashboard.totals.valueCents)
        assertEquals(2, dashboard.monthly.size)
        assertEquals(164, dashboard.branches.first().stands.inProgress)
    }

    /** A 401 is the end of the session, not a message to put on screen. */
    @Test
    fun `maps 401 to an expired session`() = runBlocking {
        server.enqueue(MockResponse().setResponseCode(401).setBody("""{"message":"Unauthenticated."}"""))

        val result = apiCall(json) { api.dashboard() }

        assertEquals(AppError.Unauthenticated, (result as AppResult.Failure).error)
    }

    @Test
    fun `carries the API's own wording through a 403`() = runBlocking {
        server.enqueue(
            MockResponse().setResponseCode(403)
                .setBody("""{"message":"This is available to administrators only."}"""),
        )

        val result = apiCall(json) { api.dashboard() }

        val error = (result as AppResult.Failure).error
        assertEquals("This is available to administrators only.", (error as AppError.Forbidden).message)
    }

    @Test
    fun `pulls the first message per field out of a validation failure`() = runBlocking {
        server.enqueue(
            MockResponse().setResponseCode(422).setBody(
                """{"message":"The given data was invalid.","errors":{"email":["These credentials do not match our records.","second"]}}""",
            ),
        )

        val result = apiCall(json) { api.login(LOGIN) }

        val error = (result as AppResult.Failure).error as AppError.Rejected
        assertEquals("These credentials do not match our records.", error.fieldErrors["email"])
    }

    @Test
    fun `reports an unreachable server as offline rather than as a failure to explain`() = runBlocking {
        server.shutdown()

        val result = apiCall(json) { api.dashboard() }

        assertEquals(AppError.Offline, (result as AppResult.Failure).error)
    }

    /** The API is free to add fields; an installed build must not break on them. */
    @Test
    fun `ignores fields it does not know about`() = runBlocking {
        server.enqueue(
            MockResponse().setResponseCode(200)
                .setBody(DASHBOARD_BODY.replace(""""from":""", """"somethingNew":true,"from":""")),
        )

        val result = apiCall(json) { api.dashboard().data }

        assertTrue(result is AppResult.Success)
    }

    private companion object {
        val LOGIN = za.co.chimsy.stands.data.remote.dto.LoginRequestDto("a@b.test", "secret", "Pixel")

        val DASHBOARD_BODY = """
        {"data":{
          "from":"2026-01-01","to":"2026-09-11","branch":null,
          "totals":{"standsTotal":2446,"standsAvailable":1345,"standsSold":758,"standsInProgress":343,
                    "salesCount":499,"valueCents":590220000,"costCents":354132000,"grossProfitCents":236088000,
                    "collectedCents":523922571,"outstandingCents":204359017},
          "branches":[{"code":"BYO","name":"Bulawayo Branch","city":"Bulawayo",
                       "stands":{"total":1223,"available":679,"sold":380,"in-progress":164},
                       "salesCount":241,"valueCents":285750000,"costCents":171450000,
                       "collectedCents":260958690,"outstandingCents":96859854}],
          "monthly":[{"month":"2026-01","salesCount":56,"valueCents":66990000,"collectedCents":52089497},
                     {"month":"2026-02","salesCount":77,"valueCents":90650000,"collectedCents":75678700}],
          "mix":[{"type":"cash","salesCount":313,"valueCents":370100000}],
          "topAgents":[{"name":"Harare Branch Agent","branch":"HRE","salesCount":258,"valueCents":304470000}],
          "recentSales":[]
        }}
        """.trimIndent()
    }
}
