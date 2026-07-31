<?php

declare(strict_types=1);

namespace App\Http\Requests\Conditions;

use App\Models\Condition;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Stopping and resuming a condition. There is no delete counterpart anywhere in
 * the app: history is the asset, so a condition is retired rather than removed.
 */
class UpdateConditionActivationRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->condition());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function condition(): Condition
    {
        $condition = $this->route('condition');

        abort_unless($condition instanceof Condition, 404);

        return $condition;
    }

    public function isActive(): bool
    {
        return $this->boolean('is_active');
    }
}
