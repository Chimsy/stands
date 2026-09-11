package za.co.chimsy.stands.data.local

import android.content.Context
import androidx.room.Database
import androidx.room.Room
import androidx.room.RoomDatabase
import za.co.chimsy.stands.data.local.dao.ReportDao
import za.co.chimsy.stands.data.local.dao.SaleDao
import za.co.chimsy.stands.data.local.entity.CachedReportEntity
import za.co.chimsy.stands.data.local.entity.CachedSaleEntity

/**
 * The device's copy of what the API last said.
 *
 * Everything in here is a cache of a server-owned record, never the original,
 * which is why destructive migration is the right call: throwing the file away
 * and refetching costs one request and cannot corrupt anything, whereas a
 * hand-written migration for cached rows is risk without a reward.
 */
@Database(
    entities = [CachedReportEntity::class, CachedSaleEntity::class],
    version = 1,
    exportSchema = false,
)
abstract class StandsDatabase : RoomDatabase() {

    abstract fun reportDao(): ReportDao

    abstract fun saleDao(): SaleDao

    companion object {
        fun build(context: Context): StandsDatabase =
            Room.databaseBuilder(context.applicationContext, StandsDatabase::class.java, "stands-cache.db")
                .fallbackToDestructiveMigration(dropAllTables = true)
                .build()
    }
}
