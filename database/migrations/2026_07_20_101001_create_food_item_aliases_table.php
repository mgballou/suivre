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
        Schema::create('food_item_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('food_item_id')->constrained()->cascadeOnDelete();
            $table->string('alias');
            $table->string('normalized_alias');
            $table->timestamps();

            $table->index('normalized_alias');
            $table->unique(['food_item_id', 'normalized_alias']);
        });

        /**
         * Aliases get their own trigram index rather than living in a JSON
         * column on `food_items`, so the classifier can fuzzy-match a synonym
         * with exactly the same indexed query it uses for a canonical name.
         */
        DB::statement(
            'CREATE INDEX food_item_aliases_normalized_alias_trigram_index '
            . 'ON food_item_aliases USING gin (normalized_alias gin_trgm_ops)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('food_item_aliases');
    }
};
