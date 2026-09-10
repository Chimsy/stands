<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What the stand is carried at in land inventory - the acquisition and
     * servicing cost the business has already sunk into it. Credited out of
     * inventory and charged to cost of sales when the stand sells.
     */
    public function up(): void
    {
        Schema::table('stands', function (Blueprint $table) {
            $table->unsignedBigInteger('cost_cents')->default(0)->after('price_cents');
        });
    }

    public function down(): void
    {
        Schema::table('stands', function (Blueprint $table) {
            $table->dropColumn('cost_cents');
        });
    }
};
