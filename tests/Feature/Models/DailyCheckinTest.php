<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Enums\MoodLevel;
use App\Enums\SleepQuality;
use App\Enums\StressLevel;
use App\Models\DailyCheckin;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;

it('persists a check-in belonging to a user', function (): void {
    $user = User::factory()->createQuietly();

    $checkin = DailyCheckin::factory()->for($user)->createQuietly();

    expect($checkin->exists)->toBeTrue();
    $this->assertDatabaseHas('daily_checkins', [
        'id' => $checkin->id,
        'user_id' => $user->id,
    ]);
});

it('round-trips the signal enums through the database', function (): void {
    $checkin = DailyCheckin::factory()->createQuietly([
        'sleep' => SleepQuality::Poor,
        'mood' => MoodLevel::Good,
        'stress' => StressLevel::High,
    ]);

    $fresh = $checkin->fresh();

    expect($fresh->sleep)->toBe(SleepQuality::Poor);
    expect($fresh->mood)->toBe(MoodLevel::Good);
    expect($fresh->stress)->toBe(StressLevel::High);
});

it('stores the signal enums as their integer backing values', function (): void {
    $checkin = DailyCheckin::factory()->createQuietly([
        'sleep' => SleepQuality::Poor,
        'mood' => MoodLevel::Good,
        'stress' => StressLevel::High,
    ]);

    $this->assertDatabaseHas('daily_checkins', [
        'id' => $checkin->id,
        'sleep' => 1,
        'mood' => 3,
        'stress' => 3,
    ]);
});

it('leaves the signals null when the day is logged without them', function (): void {
    $checkin = DailyCheckin::factory()->blank()->createQuietly();

    $fresh = $checkin->fresh();

    expect($fresh->sleep)->toBeNull();
    expect($fresh->mood)->toBeNull();
    expect($fresh->stress)->toBeNull();
    expect($fresh->note)->toBeNull();
});

it('hydrates the date as an immutable date', function (): void {
    $checkin = DailyCheckin::factory()->on(CarbonImmutable::parse('2026-07-06'))->createQuietly();

    $fresh = $checkin->fresh();

    expect($fresh->date)->toBeInstanceOf(CarbonImmutable::class);
    expect($fresh->date->toDateString())->toBe('2026-07-06');
});

it('resolves the user it belongs to', function (): void {
    $user = User::factory()->createQuietly();

    $checkin = DailyCheckin::factory()->for($user)->createQuietly();

    expect($checkin->fresh()->user)->toBeInstanceOf(User::class);
    expect($checkin->fresh()->user->id)->toBe($user->id);
});

it('resolves its morph alias from the enforced morph map', function (): void {
    expect((new DailyCheckin())->getMorphClass())->toBe('daily_checkins');
});

it('allows two users to check in on the same date', function (): void {
    $date = CarbonImmutable::parse('2026-07-06');

    DailyCheckin::factory()->for(User::factory())->on($date)->createQuietly();
    DailyCheckin::factory()->for(User::factory())->on($date)->createQuietly();

    expect(DailyCheckin::count())->toBe(2);
});

it('allows one user to check in on different dates', function (): void {
    $user = User::factory()->createQuietly();

    DailyCheckin::factory()->for($user)->on(CarbonImmutable::parse('2026-07-06'))->createQuietly();
    DailyCheckin::factory()->for($user)->on(CarbonImmutable::parse('2026-07-07'))->createQuietly();

    expect(DailyCheckin::count())->toBe(2);
});

it('rejects a second check-in for the same user and date', function (): void {
    $user = User::factory()->createQuietly();
    $date = CarbonImmutable::parse('2026-07-06');

    DailyCheckin::factory()->for($user)->on($date)->createQuietly();

    expect(fn () => DailyCheckin::factory()->for($user)->on($date)->createQuietly())
        ->toThrow(UniqueConstraintViolationException::class);
});
