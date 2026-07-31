<?php

declare(strict_types=1);

namespace App\Http\Requests\Meals;

use App\Enums\MealType;
use App\Services\Meals\Data\MealEntryDraft;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMealRequest extends FormRequest
{
    /**
     * A meal is created against the signed-in user, never a chosen one, so
     * there is no record to authorise against — ownership is assigned by
     * `LogMeal`, not accepted from the request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `entries.*.food_item_id` is the catalog match the user confirmed. It is
     * nullable because rejecting a suggestion is a legitimate outcome — the
     * line saves as free text and goes to the review queue — and `exists`
     * because a client may not invent a catalog reference.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'meal_type' => ['required', Rule::enum(MealType::class)],
            'entries' => ['required', 'array', 'min:1', 'max:30'],
            'entries.*.text' => ['required', 'string', 'max:255'],
            'entries.*.food_item_id' => ['nullable', 'integer', 'exists:food_items,id'],
        ];
    }

    public function mealDate(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string('date')->toString());
    }

    public function mealType(): MealType
    {
        return MealType::from($this->string('meal_type')->toString());
    }

    /**
     * The confirmed lines, blanks dropped.
     *
     * @return array<int, MealEntryDraft>
     */
    public function entries(): array
    {
        /** @var array<int, array{text: string, food_item_id?: int|string|null}> $entries */
        $entries = $this->input('entries', []);

        $drafts = [];

        foreach ($entries as $entry) {
            $text = trim($entry['text']);

            if ($text === '') {
                continue;
            }

            $foodItemId = $entry['food_item_id'] ?? null;

            $drafts[] = new MealEntryDraft(
                text: $text,
                foodItemId: $foodItemId === null ? null : (int) $foodItemId,
            );
        }

        return $drafts;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['date' => $this->route('date')]);
    }
}
