<?php

declare(strict_types=1);

namespace App\Http\Requests\Conditions;

use App\Enums\ConditionHue;
use App\Models\Condition;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateConditionRequest extends FormRequest
{
    /**
     * Returning the policy's Response rather than a bool carries its deny
     * message through to the UI.
     */
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->condition());
    }

    /**
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
                Rule::unique('conditions', 'name')
                    ->where('user_id', $user->id)
                    ->ignore($this->condition()->id),
            ],
            'color' => ['required', Rule::enum(ConditionHue::class)],
        ];
    }

    public function condition(): Condition
    {
        $condition = $this->route('condition');

        abort_unless($condition instanceof Condition, 404);

        return $condition;
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
