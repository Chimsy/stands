<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One balanced double-entry transaction. Entries are append-only: nothing
     * amends a posted entry, and a mistake is corrected by posting a reversing
     * entry against it.
     */
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('reference')->unique();
            $table->date('entry_date');
            $table->string('description');

            /** The sale or payment that gave rise to the entry, for drill-down. */
            $table->nullableMorphs('source');

            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            /** Statements are always filtered by branch and date. */
            $table->index(['branch_id', 'entry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
