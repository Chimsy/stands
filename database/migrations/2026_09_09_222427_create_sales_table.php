<?php

use App\Enums\SaleStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();

            /** A stand can only be sold once, so the ledger cannot double-count it. */
            $table->foreignId('stand_id')->unique()->constrained()->restrictOnDelete();

            $table->foreignId('buyer_id')->constrained()->restrictOnDelete();
            $table->foreignId('sold_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference')->unique();
            $table->date('sale_date');
            $table->string('type');
            $table->string('status')->default(SaleStatus::Outstanding->value);

            /** The agreed selling price, which may differ from the stand's list price. */
            $table->unsignedBigInteger('price_cents');

            /**
             * The stand's carrying cost at the moment of sale, copied here so the
             * cost of sales already posted cannot drift if the stand is re-costed.
             */
            $table->unsignedBigInteger('cost_cents');

            $table->unsignedBigInteger('deposit_cents')->default(0);
            $table->unsignedSmallInteger('instalment_count')->default(0);
            $table->timestamps();

            $table->index(['branch_id', 'sale_date']);
            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
