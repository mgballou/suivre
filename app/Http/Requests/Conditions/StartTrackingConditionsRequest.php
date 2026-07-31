<?php

declare(strict_types=1);

namespace App\Http\Requests\Conditions;

use App\Enums\ConditionHue;
use App\Models\Condition;
use App\Models\User;
use App\Services\Conditions\Data\SuggestedCondition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartTrackingConditionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Condition::class) ?? false;
    }

    /**
     * Onboarding submits the whole selection at once, so uniqueness is enforced
     * across the payload (`distinct`) as well as against the table — a user can
     * reach this screen with conditions already on file by re-visiting it.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();

        return [
            'conditions' => ['required', 'array', 'min:1', 'max:12'],
            'conditions.*.name' => [
                'required',
                'string',
                'max:60',
                'distinct:ignore_case',
                Rule::unique('conditions', 'name')->where('user_id', $user->id),
            ],
            'conditions.*.color' => ['required', Rule::enum(ConditionHue::class)],
        ];
    }

    /**
     * @return array<int, SuggestedCondition>
     */
    public function conditions(): array
    {
        /** @var array<int, array{name: string, color: string}> $conditions */
        $conditions = $this->validated('conditions');

        return array_map(
            static fn (array $condition): SuggestedCondition => new SuggestedCondition(
                name: $condition['name'],
                hue: ConditionHue::from($condition['color']),
            ),
            $conditions,
        );
    }

    protected function prepareForValidation(): void
    {
        $conditions = $this->input('conditions');

        if (! is_array($conditions)) {
            return;
        }

        $this->merge([
            'conditions' => array_values(array_map(
                static fn (mixed $condition): mixed => is_array($condition) && isset($condition['name']) && is_string($condition['name'])
                    ? [...$condition, 'name' => trim($condition['name'])]
                    : $condition,
                $conditions,
            )),
        ]);
    }
}
