<?php

declare(strict_types=1);

namespace App\Http\Requests\Conditions;

use App\Enums\FlareIntensity;
use App\Models\Condition;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class LogFlareEventRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('record', $this->condition());
    }

    /**
     * Only the intensity is required. A flare is logged mid-flare, so duration
     * and note are the details a user adds later if at all — demanding them
     * would make the fast path the slow one.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'intensity' => ['required', Rule::enum(FlareIntensity::class)],
            'duration_minutes' => ['nullable', 'integer', 'between:1,1440'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function condition(): Condition
    {
        $condition = $this->route('condition');

        abort_unless($condition instanceof Condition, 404);

        return $condition;
    }

    public function flareDate(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string('date')->toString());
    }

    public function intensity(): FlareIntensity
    {
        return FlareIntensity::from($this->integer('intensity'));
    }

    public function durationMinutes(): ?int
    {
        return $this->input('duration_minutes') === null
            ? null
            : $this->integer('duration_minutes');
    }

    public function note(): ?string
    {
        $note = trim($this->string('note')->toString());

        return $note === '' ? null : $note;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['date' => $this->route('date')]);
    }
}
