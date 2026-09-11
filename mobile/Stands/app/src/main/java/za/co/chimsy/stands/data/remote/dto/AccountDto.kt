package za.co.chimsy.stands.data.remote.dto

import kotlinx.serialization.Serializable

@Serializable
data class BranchDto(
    val code: String,
    val name: String,
    val city: String,
)

@Serializable
data class UserDto(
    val id: Long,
    val name: String,
    val email: String,
    val role: String,
    val isAdmin: Boolean,
    /** The office this request answered for; null only for an account with no branch. */
    val branch: BranchDto? = null,
    /** The offices this account may switch between - all of them for an administrator. */
    val branches: List<BranchDto> = emptyList(),
)

@Serializable
data class LoginRequestDto(
    val email: String,
    val password: String,
    /** Labels the token so the account holder can tell their devices apart. */
    val deviceName: String,
)

/** Login is the one response the API does not wrap in `data`. */
@Serializable
data class LoginResponseDto(
    val token: String,
    val user: UserDto,
)

/** Laravel API resources wrap their payload in a `data` key. */
@Serializable
data class EnvelopeDto<T>(val data: T)
