<?php

declare(strict_types=1);

namespace App\Http\Requests\Conditions;

use App\Enums\ConditionHue;
use App\Models\Condition;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConditionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Condition::class) ?? false;
    }

    /**
     * Names are unique per user rather than globally: two people tracking
     * "Headache" is the normal case, one person tracking it twice is a mistake.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();

        return [
            'name' => [
                'required',
                'string',
                'max:60',
                Rule::unique('conditions', 'name')->where('user_id', $user->id),
            ],
            'color' => ['required', Rule::enum(ConditionHue::class)],
        ];
    }

    public function name(): string
    {
        return $this->string('name')->toString();
    }

    public function color(): ConditionHue
    {
        return ConditionHue::from($this->string('color')->toString());
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim($this->string('name')->toString())]);
    }
}
