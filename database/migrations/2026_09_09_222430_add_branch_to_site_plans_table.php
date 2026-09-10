<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_plans', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('id')->constrained()->restrictOnDelete();

            /** Matches the fixture directory under database/data. */
            $table->string('slug')->nullable()->after('branch_id')->unique();
        });
    }

    public function down(): void
    {
        Schema::table('site_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn('slug');
        });
    }
};
