<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('food_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_id')->constrained()->cascadeOnDelete();

            /**
             * Points at the curated food catalog introduced by SUI-14. That
             * table does not exist yet, so the column carries no foreign key;
             * SUI-14 adds the constraint once `food_items` lands.
             */
            $table->unsignedBigInteger('food_item_id')->nullable()->index();

            $table->text('text')->nullable();
            $table->timestamps();
        });

        DB::statement(
            'ALTER TABLE food_entries ADD CONSTRAINT food_entries_text_or_food_item_check '
            . 'CHECK (food_item_id IS NOT NULL OR text IS NOT NULL)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('food_entries');
    }
};
