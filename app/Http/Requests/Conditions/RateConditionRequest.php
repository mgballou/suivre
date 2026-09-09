<?php

declare(strict_types=1);

namespace App\Http\Requests\Conditions;

use App\Models\Condition;
use App\Models\User;
use App\Rules\WithinJournalBounds;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class RateConditionRequest extends FormRequest
{
    /**
     * `record` also rejects a deactivated condition, so a stale tab cannot
     * write a rating against something the user has since stopped tracking.
     */
    public function authorize(): Response
    {
        return Gate::inspect('record', $this->condition());
    }

    /**
     * The date is folded in from the route so a value matching the route's
     * YYYY-MM-DD pattern but naming no real day (2026-02-31) is rejected here
     * rather than rolling over into the next month inside Carbon.
     *
     * `WithinJournalBounds` then rejects a day the user cannot have lived
     * through — after their own today, or before their account began. The
     * route allows four digits of year, and the insights read walks first
     * rating to last rating one day at a time.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();

        return [
            'date' => ['bail', 'required', 'date_format:Y-m-d', new WithinJournalBounds($user)],
            'intensity' => ['required', 'integer', 'between:0,10'],
        ];
    }

    public function condition(): Condition
    {
        $condition = $this->route('condition');

        abort_unless($condition instanceof Condition, 404);

        return $condition;
    }

    /** Named around `Request::date()`, which is a different method entirely. */
    public function ratingDate(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string('date')->toString());
    }

    public function intensity(): int
    {
        return $this->integer('intensity');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['date' => $this->route('date')]);
    }
}
