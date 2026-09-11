package za.co.chimsy.stands.data.remote

import kotlinx.serialization.json.Json
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.jsonArray
import kotlinx.serialization.json.jsonObject
import kotlinx.serialization.json.jsonPrimitive
import retrofit2.HttpException
import za.co.chimsy.stands.core.AppError
import za.co.chimsy.stands.core.AppResult
import java.io.IOException

/**
 * Runs an API call and turns whatever comes back - or goes wrong - into an
 * [AppResult].
 *
 * Every repository goes through this so the mapping from HTTP status to
 * something the UI can act on is made once. In particular a 401 is not an
 * error to display: it means the token is dead and the session is over.
 */
suspend fun <T> apiCall(json: Json, block: suspend () -> T): AppResult<T> = try {
    AppResult.Success(block())
} catch (e: HttpException) {
    AppResult.Failure(e.toAppError(json))
} catch (e: IOException) {
    /** No route to the API: a flaky connection, a sleeping laptop, aeroplane mode. */
    AppResult.Failure(AppError.Offline)
} catch (e: Exception) {
    AppResult.Failure(AppError.Unexpected(e.message ?: "Something went wrong."))
}

private fun HttpException.toAppError(json: Json): AppError {
    val body = runCatching { response()?.errorBody()?.string() }.getOrNull()
    val parsed = body?.let { runCatching { json.parseToJsonElement(it).jsonObject }.getOrNull() }
    val message = parsed?.get("message")?.jsonPrimitive?.content

    return when (code()) {
        401 -> AppError.Unauthenticated
        403 -> AppError.Forbidden(message ?: "You are not allowed to see this.")
        422 -> AppError.Rejected(message ?: "That request was rejected.", parsed.fieldErrors())
        else -> AppError.Unexpected(message ?: "The server returned ${code()}.")
    }
}

/** Laravel reports validation failures as `errors: { field: [first, ...] }`. */
private fun JsonObject?.fieldErrors(): Map<String, String> {
    val errors = this?.get("errors")?.jsonObject ?: return emptyMap()

    return errors.mapNotNull { (field, messages) ->
        messages.jsonArray.firstOrNull()?.jsonPrimitive?.content?.let { field to it }
    }.toMap()
}
