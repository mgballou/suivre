<?php

declare(strict_types=1);

namespace App\Http\Requests\Meals;

use Illuminate\Foundation\Http\FormRequest;

class ClassifyMealDraftRequest extends FormRequest
{
    /**
     * Any signed-in user may ask what the classifier makes of some text —
     * nothing is read or written on their behalf, and the catalog is global.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1', 'max:30'],
            'lines.*' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * The typed lines, already trimmed and stripped of blanks.
     *
     * @return array<int, string>
     */
    public function lines(): array
    {
        /** @var array<int, string> $lines */
        $lines = $this->input('lines', []);

        return $lines;
    }

    /**
     * Blank lines are dropped before the rules run, not after.
     *
     * The client sends the textarea split on newlines, so a trailing return —
     * or a gap between items — produces an empty entry. Validating first would
     * fail the whole request on `lines.*` being required and reject a perfectly
     * ordinary way of typing a list.
     */
    protected function prepareForValidation(): void
    {
        $lines = $this->input('lines');

        if (! is_array($lines)) {
            return;
        }

        $this->merge([
            'lines' => array_values(array_filter(
                array_map(static fn (mixed $line): string => is_string($line) ? trim($line) : '', $lines),
                static fn (string $line): bool => $line !== '',
            )),
        ]);
    }
}
