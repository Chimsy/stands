package za.co.chimsy.stands.data.remote

import za.co.chimsy.stands.data.remote.dto.BalanceSheetDto
import za.co.chimsy.stands.data.remote.dto.DashboardDto
import za.co.chimsy.stands.data.remote.dto.EnvelopeDto
import za.co.chimsy.stands.data.remote.dto.IncomeStatementDto
import za.co.chimsy.stands.data.remote.dto.LoginRequestDto
import za.co.chimsy.stands.data.remote.dto.LoginResponseDto
import za.co.chimsy.stands.data.remote.dto.PaginatedDto
import za.co.chimsy.stands.data.remote.dto.ReceivablesAgeingDto
import za.co.chimsy.stands.data.remote.dto.SaleDto
import za.co.chimsy.stands.data.remote.dto.TrialBalanceDto
import za.co.chimsy.stands.data.remote.dto.UserDto
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.POST
import retrofit2.http.Path
import retrofit2.http.Query

/**
 * The v1 API, exactly as the web client uses it.
 *
 * `branch` takes a branch code or "group"; leaving it off answers for whichever
 * office the request is being worked from, which `AuthInterceptor` sets.
 */
interface StandsApi {

    @POST("login")
    suspend fun login(@Body credentials: LoginRequestDto): LoginResponseDto

    @POST("logout")
    suspend fun logout()

    @GET("user")
    suspend fun user(): EnvelopeDto<UserDto>

    @GET("dashboard")
    suspend fun dashboard(
        @Query("branch") branch: String? = null,
        @Query("from") from: String? = null,
        @Query("to") to: String? = null,
    ): EnvelopeDto<DashboardDto>

    @GET("reports/income-statement")
    suspend fun incomeStatement(@Query("branch") branch: String? = null): EnvelopeDto<IncomeStatementDto>

    @GET("reports/balance-sheet")
    suspend fun balanceSheet(@Query("branch") branch: String? = null): EnvelopeDto<BalanceSheetDto>

    @GET("reports/trial-balance")
    suspend fun trialBalance(@Query("branch") branch: String? = null): EnvelopeDto<TrialBalanceDto>

    @GET("reports/receivables-ageing")
    suspend fun receivablesAgeing(@Query("branch") branch: String? = null): EnvelopeDto<ReceivablesAgeingDto>

    @GET("sales")
    suspend fun sales(
        @Query("branch") branch: String? = null,
        @Query("page") page: Int = 1,
    ): PaginatedDto<SaleDto>

    @GET("sales/{reference}")
    suspend fun sale(@Path("reference") reference: String): EnvelopeDto<SaleDto>
}
