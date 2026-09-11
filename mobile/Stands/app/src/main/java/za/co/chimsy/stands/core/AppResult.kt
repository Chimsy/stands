package za.co.chimsy.stands.core

/**
 * The outcome of something that talks to the network.
 *
 * Repositories hand this back instead of throwing, so a caller has to decide
 * what a failure means on screen rather than letting an exception escape into
 * a coroutine and take the process with it.
 */
sealed interface AppResult<out T> {
    data class Success<T>(val value: T) : AppResult<T>

    data class Failure(val error: AppError) : AppResult<Nothing>
}

/**
 * Why a call failed, in terms the UI can act on.
 *
 * Deliberately not an exception hierarchy: the screen needs to know whether to
 * show a message, sign the user out, or offer a retry - not which library threw.
 */
sealed interface AppError {
    /** The device could not reach the API at all. The cache, if any, is still worth showing. */
    data object Offline : AppError

    /** The token is gone or was revoked. The only answer is to sign in again. */
    data object Unauthenticated : AppError

    /** The account is not allowed to see this. */
    data class Forbidden(val message: String) : AppError

    /** The API rejected the request and said why - a wrong password, say. */
    data class Rejected(val message: String, val fieldErrors: Map<String, String> = emptyMap()) : AppError

    /** Anything else: a 500, a malformed body, a bug. */
    data class Unexpected(val message: String) : AppError
}

inline fun <T, R> AppResult<T>.map(transform: (T) -> R): AppResult<R> = when (this) {
    is AppResult.Success -> AppResult.Success(transform(value))
    is AppResult.Failure -> this
}
