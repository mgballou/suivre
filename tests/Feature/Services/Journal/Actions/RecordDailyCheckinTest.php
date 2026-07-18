<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Journal\Actions;

use App\Enums\MoodLevel;
use App\Enums\SleepQuality;
use App\Enums\StressLevel;
use App\Events\Journal\DailyCheckinRecorded;
use App\Models\DailyCheckin;
use App\Models\User;
use App\Services\Journal\Actions\RecordDailyCheckin;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;

it('upserts a single row for the same user and date, with the latest values winning', function (): void {
    $user = User::factory()->createQuietly();
    $date = CarbonImmutable::parse('2026-07-07');
    $record = app(RecordDailyCheckin::class);

    $record($user, $date, MoodLevel::Low, SleepQuality::Poor, StressLevel::High, 'first');
    $checkin = $record($user, $date, MoodLevel::Good, SleepQuality::Good, StressLevel::Low, 'second');

    expect(DailyCheckin::query()->where('user_id', $user->id)->count())->toBe(1);
    expect($checkin->mood)->toBe(MoodLevel::Good);
    expect($checkin->sleep)->toBe(SleepQuality::Good);
    expect($checkin->stress)->toBe(StressLevel::Low);
    expect($checkin->note)->toBe('second');
});

it('returns a persisted daily check-in', function (): void {
    $user = User::factory()->createQuietly();

    $checkin = app(RecordDailyCheckin::class)(
        $user,
        CarbonImmutable::parse('2026-07-07'),
        MoodLevel::Neutral,
        SleepQuality::Poor,
        StressLevel::Moderate,
        'a note',
    );

    expect($checkin->exists)->toBeTrue();
    expect($checkin->user_id)->toBe($user->id);
    expect($checkin->date->toDateString())->toBe('2026-07-07');
});

it('dispatches the DailyCheckinRecorded event', function (): void {
    Event::fake();

    $user = User::factory()->createQuietly();

    $checkin = app(RecordDailyCheckin::class)(
        $user,
        CarbonImmutable::parse('2026-07-07'),
        null,
        null,
        null,
        null,
    );

    Event::assertDispatched(DailyCheckinRecorded::class, function (DailyCheckinRecorded $event) use ($checkin) {
        expect($event->checkin->id)->toBe($checkin->id);

        return true;
    });
});
