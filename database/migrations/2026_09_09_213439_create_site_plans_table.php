<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A site plan is the surveyed layout a township's stands are drawn on.
     *
     * Roads, zones, blocks and the estate boundary are stored as JSON rather
     * than in their own tables: they are pure geometry, are only ever read as
     * part of the whole plan document, and are never queried or updated
     * individually. Stands get a real table because they are filtered,
     * searched and updated on their own.
     */
    public function up(): void
    {
        Schema::create('site_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('subtitle');
            $table->string('authority');
            $table->text('note');
            $table->decimal('north_rotation', 5, 2)->default(0);
            $table->json('bounds');
            $table->json('boundary');
            $table->json('roads');
            $table->json('zones');
            $table->json('blocks');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_plans');
    }
};
