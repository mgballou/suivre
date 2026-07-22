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
     *
     * The curated-composition seam from D9: a composite dish ("chicken korma")
     * links to the catalog items it is made of, so the classifier can inherit
     * their categories instead of needing every dish tagged by hand. Only the
     * link is modelled here — quantities, optional components and the curation
     * workflow follow with the classifier work.
     */
    public function up(): void
    {
        Schema::create('food_item_compositions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('food_item_id')->constrained('food_items')->cascadeOnDelete();
            $table->foreignId('component_id')->constrained('food_items')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['food_item_id', 'component_id']);
        });

        DB::statement(
            'ALTER TABLE food_item_compositions '
            . 'ADD CONSTRAINT food_item_compositions_not_self_referential_check '
            . 'CHECK (food_item_id <> component_id)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('food_item_compositions');
    }
};
