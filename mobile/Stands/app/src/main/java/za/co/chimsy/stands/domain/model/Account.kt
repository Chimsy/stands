package za.co.chimsy.stands.domain.model

data class Branch(
    val code: String,
    val name: String,
    val city: String,
)

data class AuthenticatedUser(
    val name: String,
    val email: String,
    val isAdmin: Boolean,
    /** The office this account is currently working from. */
    val branch: Branch?,
    /** The offices it may switch between: one for an agent, all of them for an administrator. */
    val branches: List<Branch>,
)
