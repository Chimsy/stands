package za.co.chimsy.stands.data.local.entity

import androidx.room.Entity
import androidx.room.PrimaryKey

/**
 * A row of the sales ledger, cached for the scope it was fetched under.
 *
 * [scope] is part of the identity rather than a filter after the fact: the same
 * sale appears under both its own branch and the group, and without it
 * switching scope would leave the previous office's rows on screen.
 *
 * [position] preserves the server's ordering, which is by sale date and then by
 * id - an ordering the device cannot reproduce from the columns alone once two
 * sales share a date.
 */
@Entity(tableName = "cached_sales", primaryKeys = ["scope", "reference"])
data class CachedSaleEntity(
    val scope: String,
    val reference: String,
    val position: Int,
    val saleDate: String,
    val standNumber: String?,
    val buyerName: String?,
    val branchCode: String?,
    val type: String,
    val status: String,
    val price: Double,
    val paid: Double,
    val outstanding: Double,
)
