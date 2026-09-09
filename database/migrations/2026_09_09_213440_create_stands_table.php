<?php

use App\Enums\StandStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_plan_id')->constrained()->cascadeOnDelete();

            /** The surveyed stand number. It is the identifier buyers quote, so the API routes on it. */
            $table->string('stand_number')->unique();

            $table->string('status')->default(StandStatus::Available->value);
            $table->string('block');
            $table->string('road');
            $table->unsignedInteger('area_sqm');

            /** Money is stored in minor units to keep arithmetic exact. */
            $table->unsignedBigInteger('price_cents');

            /**
             * Plan-space geometry in metres, origin at the plan's top-left corner.
             * The centroid lives in real columns so proximity searches can use an
             * index; the polygon itself is only ever read back whole.
             */
            $table->decimal('centroid_x', 8, 2);
            $table->decimal('centroid_y', 8, 2);
            $table->json('points');

            $table->timestamps();

            /** The legend and the status filter both count stands per status. */
            $table->index(['site_plan_id', 'status']);

            /** Bounding-box prefilter for "stands near this point" lookups. */
            $table->index(['centroid_x', 'centroid_y']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stands');
    }
};
