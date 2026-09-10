<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Money received against a sale. Every payment is a receipt: the receipt
     * number is raised when the payment is recorded and never reused.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('receipt_number')->unique();
            $table->date('paid_on');
            $table->unsignedBigInteger('amount_cents');
            $table->string('method');

            /** Bank or mobile-money transaction reference, when the buyer quotes one. */
            $table->string('external_reference')->nullable();

            $table->timestamps();

            $table->index(['branch_id', 'paid_on']);
            $table->index('sale_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
