<?php

declare(strict_types=1);

namespace App\Http\Requests\Journal;

use App\Enums\MoodLevel;
use App\Enums\SleepQuality;
use App\Enums\StressLevel;
use App\Models\DailyCheckin;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordDailyCheckinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', DailyCheckin::class) ?? false;
    }

    /**
     * Every scale is nullable: a partial check-in is a legitimate record, and
     * the whole point of the two-tap flow is that one tap already persists.
     *
     * The date is folded in from the route so a value that matches the route's
     * YYYY-MM-DD pattern but is not a real day (2026-02-31) is rejected here
     * rather than silently rolling over into the next month inside Carbon.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'mood' => ['nullable', Rule::enum(MoodLevel::class)],
            'sleep' => ['nullable', Rule::enum(SleepQuality::class)],
            'stress' => ['nullable', Rule::enum(StressLevel::class)],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** Named around `Request::date()`, which is a different method entirely. */
    public function checkinDate(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string('date')->toString());
    }

    public function mood(): ?MoodLevel
    {
        return $this->enum('mood', MoodLevel::class);
    }

    public function sleep(): ?SleepQuality
    {
        return $this->enum('sleep', SleepQuality::class);
    }

    public function stress(): ?StressLevel
    {
        return $this->enum('stress', StressLevel::class);
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
