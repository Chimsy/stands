package za.co.chimsy.stands.data.local.dao

import androidx.room.Dao
import androidx.room.Insert
import androidx.room.OnConflictStrategy
import androidx.room.Query
import androidx.room.Transaction
import kotlinx.coroutines.flow.Flow
import za.co.chimsy.stands.data.local.entity.CachedReportEntity
import za.co.chimsy.stands.data.local.entity.CachedSaleEntity

@Dao
interface ReportDao {

    /**
     * Emits the cached report and every later version of it. The screen
     * collects this rather than the network call, so a refresh updates the UI
     * by writing to the database - one source of truth, not two.
     */
    @Query("SELECT * FROM cached_reports WHERE `key` = :key")
    fun observe(key: String): Flow<CachedReportEntity?>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsert(report: CachedReportEntity)

    @Query("DELETE FROM cached_reports")
    suspend fun clear()
}

@Dao
interface SaleDao {

    @Query("SELECT * FROM cached_sales WHERE scope = :scope ORDER BY position ASC")
    fun observe(scope: String): Flow<List<CachedSaleEntity>>

    @Query("DELETE FROM cached_sales WHERE scope = :scope")
    suspend fun clear(scope: String)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertAll(sales: List<CachedSaleEntity>)

    /**
     * Replaces a scope's window in one transaction, so a collector never sees
     * the list empty between the delete and the insert.
     */
    @Transaction
    suspend fun replace(scope: String, sales: List<CachedSaleEntity>) {
        clear(scope)
        insertAll(sales)
    }

    @Query("DELETE FROM cached_sales")
    suspend fun clearAll()
}
