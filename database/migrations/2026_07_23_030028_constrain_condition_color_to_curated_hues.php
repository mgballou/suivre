<?php

declare(strict_types=1);

use App\Enums\ConditionHue;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * `conditions.color` held a free hex before D20 settled that condition
     * colour is chosen from a curated set. The column stays a string — the
     * constraint is the ConditionHue cast plus validation, so an eighth hue
     * later costs a case rather than a schema change — but any value left over
     * from the free-hex era would fail that cast on read.
     *
     * Rows are spread across the curated hues by id rather than collapsed onto
     * one, so a user who had distinct colours keeps distinct colours.
     */
    public function up(): void
    {
        $hues = ConditionHue::cases();

        DB::table('conditions')
            ->whereNotIn('color', array_column($hues, 'value'))
            ->orderBy('id')
            ->each(function (object $condition) use ($hues): void {
                /** @var int $id */
                $id = $condition->id;

                DB::table('conditions')
                    ->where('id', $id)
                    ->update(['color' => $hues[$id % count($hues)]->value]);
            });
    }

    public function down(): void
    {
        // The original free hexes are not recoverable, and a curated value is a
        // legal value for the column as it was, so there is nothing to reverse.
    }
};
