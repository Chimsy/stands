<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The agreed repayment schedule for a payment-plan sale.
     *
     * `paid_cents` is a running allocation kept in step with payments by
     * App\Actions\RecordPayment. The ledger remains the record of the money;
     * this only says which instalment each receipt was applied to, so arrears
     * and ageing can be reported.
     */
    public function up(): void
    {
        Schema::create('instalments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->date('due_date');
            $table->unsignedBigInteger('amount_cents');
            $table->unsignedBigInteger('paid_cents')->default(0);
            $table->timestamps();

            $table->unique(['sale_id', 'sequence']);

            /** Arrears reporting scans by due date across every sale. */
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instalments');
    }
};
