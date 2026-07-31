<?php

declare(strict_types=1);

namespace App\Services\Food\Actions;

use App\Enums\FoodClassificationOutcome;
use App\Models\FoodItem;
use App\Services\Food\Data\FoodClassificationResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The deterministic classifier D9 mandates in place of AI/LLM tagging:
 * normalize → resolve synonyms/aliases → trigram-fuzzy-match → assign the
 * matched FoodItem's curated categories.
 *
 * Takes raw text rather than a `FoodEntry` model. This ticket (SUI-16)
 * delivers pure classification only — SUI-19 owns wiring a result back onto a
 * `FoodEntry` (persisting `food_item_id`, dispatching classification events)
 * and SUI-17 owns the review queue a `LowConfidence` outcome feeds. Taking
 * text keeps the Action usable anywhere free text needs resolving, not just
 * from a persisted entry.
 */
class ClassifyFoodEntry
{
    /**
     * The minimum trigram similarity a candidate must clear to count as a
     * match rather than `LowConfidence`. Doubles as the Postgres
     * `pg_trgm.similarity_threshold` for the query itself, so a row the
     * database returns is, by construction, confident.
     *
     * A named constant for now; becomes a Spatie runtime setting in E5
     * (SUI-25) so an operator can tune it without a deploy. Do not build that
     * integration here.
     */
    private const float CONFIDENCE_THRESHOLD = 0.4;

    public function __invoke(string $text): FoodClassificationResult
    {
        $normalized = FoodItem::normalizeName($text);

        if ($normalized === '') {
            return $this->unmatched();
        }

        $candidate = $this->findBestCandidate($normalized);

        if ($candidate === null) {
            return $this->unmatched();
        }

        /** @var FoodItem $foodItem */
        $foodItem = FoodItem::query()->with('categories')->findOrFail($candidate['food_item_id']);

        return new FoodClassificationResult(
            outcome: FoodClassificationOutcome::Matched,
            foodItem: $foodItem,
            categories: $foodItem->categories,
            score: $candidate['score'],
        );
    }

    /**
     * Finds the single best trigram match across canonical names and aliases
     * in one indexed query, so an alias hit resolves straight to its parent
     * `FoodItem` rather than needing a second lookup.
     *
     * `SET LOCAL pg_trgm.similarity_threshold` is scoped to this transaction:
     * setting it to `CONFIDENCE_THRESHOLD` means the `%` operator — the form
     * that can use the `gin_trgm_ops` GIN indexes SUI-14 created — only ever
     * returns candidates that already clear the bar, and a bare column
     * reference (never function-wrapped) keeps the query sargable.
     *
     * Ties are broken by `food_item_id` then source, never left to whatever
     * order Postgres happens to return rows in, so the same input always
     * yields the same match (D9).
     *
     * @return array{food_item_id: int, score: float}|null
     */
    private function findBestCandidate(string $normalized): ?array
    {
        return DB::transaction(function () use ($normalized): ?array {
            DB::statement('SET LOCAL pg_trgm.similarity_threshold = ' . self::CONFIDENCE_THRESHOLD);

            $nameMatches = DB::table('food_items')
                ->selectRaw('id as food_item_id, similarity(normalized_name, ?) as score, 0 as source_rank', [$normalized])
                ->whereRaw('normalized_name % ?', [$normalized]);

            $aliasMatches = DB::table('food_item_aliases')
                ->selectRaw('food_item_id, similarity(normalized_alias, ?) as score, 1 as source_rank', [$normalized])
                ->whereRaw('normalized_alias % ?', [$normalized]);

            $row = DB::query()
                ->fromSub($nameMatches->unionAll($aliasMatches), 'candidates')
                ->orderByDesc('score')
                ->orderBy('food_item_id')
                ->orderBy('source_rank')
                ->first();

            if ($row === null) {
                return null;
            }

            /** @var array{food_item_id: int|string, score: int|string|float} $data */
            $data = (array) $row;

            return [
                'food_item_id' => (int) $data['food_item_id'],
                'score' => (float) $data['score'],
            ];
        });
    }

    private function unmatched(): FoodClassificationResult
    {
        return new FoodClassificationResult(
            outcome: FoodClassificationOutcome::LowConfidence,
            foodItem: null,
            categories: new Collection(),
            score: 0.0,
        );
    }
}
