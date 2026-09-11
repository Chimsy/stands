package za.co.chimsy.stands.domain.model

/**
 * Whose figures a screen is showing.
 *
 * The API takes a branch code or the word "group", and only an administrator
 * may ask for the group - so this doubles as the cache key, keeping one
 * branch's cached answers from ever being shown under another's name.
 */
sealed interface Scope {

    /** Every branch consolidated. */
    data object Group : Scope

    data class Branch(val code: String) : Scope

    val query: String
        get() = when (this) {
            Group -> "group"
            is Branch -> code
        }

    companion object {
        /** The API reports a consolidated answer by returning a null branch. */
        fun of(branchCode: String?): Scope = branchCode?.let(::Branch) ?: Group
    }
}
