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
        Schema::create('food_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            /**
             * The lowercased, transliterated, punctuation-stripped form of
             * `name`. Derived rather than entered, and the only column the
             * classifier matches against — trigram similarity is meaningless
             * against inconsistently cased and accented display names.
             */
            $table->string('normalized_name');

            $table->string('kind')->default('item');

            /**
             * Provenance of an imported row (D10): `source` names the dataset
             * ('open_food_facts'), `source_ref` its identifier in that dataset.
             * Both null for hand-curated entries.
             */
            $table->string('source')->nullable();
            $table->string('source_ref')->nullable();

            $table->timestamps();

            $table->index('kind');
            $table->index('normalized_name');

            /**
             * Makes a re-run of the catalog import idempotent. Postgres treats
             * NULLs as distinct, so curated rows — which carry no provenance —
             * are unaffected.
             */
            $table->unique(['source', 'source_ref']);
        });

        /**
         * The substrate SUI-16's classifier queries with `similarity()` and the
         * `%` operator. `pg_trgm` itself is enabled by an earlier migration.
         */
        DB::statement(
            'CREATE INDEX food_items_normalized_name_trigram_index '
            . 'ON food_items USING gin (normalized_name gin_trgm_ops)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('food_items');
    }
};
