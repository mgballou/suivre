<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Closes the seam SUI-18 left open: `food_entries.food_item_id` shipped as a
     * bare indexed column because `food_items` did not exist yet.
     *
     * The delete behaviour is `restrict`, not `null`. A table check constraint
     * already requires every entry to carry text or a food item, so nulling the
     * column on a catalog delete could violate it for entries the classifier
     * resolved without keeping the raw text. Retiring a catalog row therefore
     * has to reassign its entries first — the journal is the user's record and
     * must not be silently mutated by operator catalog maintenance.
     */
    public function up(): void
    {
        Schema::table('food_entries', function (Blueprint $table) {
            $table->foreign('food_item_id')
                ->references('id')
                ->on('food_items')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('food_entries', function (Blueprint $table) {
            $table->dropForeign(['food_item_id']);
        });
    }
};
