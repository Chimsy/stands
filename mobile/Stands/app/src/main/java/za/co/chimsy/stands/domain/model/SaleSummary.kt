package za.co.chimsy.stands.domain.model

/** One line of the sales ledger, as the list shows it. Money is in major units. */
data class SaleSummary(
    val reference: String,
    val saleDate: String,
    val standNumber: String?,
    val buyerName: String?,
    val branchCode: String?,
    val type: String,
    val status: String,
    val price: Double,
    val paid: Double,
    val outstanding: Double,
) {
    val isSettled: Boolean get() = outstanding <= 0.0
}
