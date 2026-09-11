package za.co.chimsy.stands.data.remote.dto

import kotlinx.serialization.Serializable

@Serializable
data class BuyerDto(
    val id: Long,
    val name: String,
    val email: String? = null,
    val phone: String? = null,
    val nationalId: String? = null,
)

/**
 * Unlike the reports, the sales resource divides money at the edge, so these
 * are major units already.
 */
@Serializable
data class SaleDto(
    val reference: String,
    val saleDate: String,
    val type: String,
    val status: String,
    val price: Double,
    val cost: Double = 0.0,
    val deposit: Double = 0.0,
    val paid: Double = 0.0,
    val outstanding: Double = 0.0,
    val instalmentCount: Int = 0,
    val standNumber: String? = null,
    val buyer: BuyerDto? = null,
    val soldBy: String? = null,
    val branch: BranchDto? = null,
    val instalments: List<InstalmentDto>? = null,
    val payments: List<PaymentDto>? = null,
)

@Serializable
data class InstalmentDto(
    val sequence: Int,
    val dueDate: String,
    val amount: Double,
    val paid: Double,
    val outstanding: Double,
    val isSettled: Boolean,
    val isOverdue: Boolean,
)

@Serializable
data class PaymentDto(
    val receiptNumber: String,
    val paidOn: String,
    val amount: Double,
    val method: String,
    val externalReference: String? = null,
)

/** A paginated collection carries Laravel's `meta` block alongside the rows. */
@Serializable
data class PaginatedDto<T>(
    val data: List<T>,
    val meta: PageMetaDto? = null,
)

@Serializable
data class PageMetaDto(
    val current_page: Int = 1,
    val last_page: Int = 1,
    val per_page: Int = 0,
    val total: Int = 0,
)
