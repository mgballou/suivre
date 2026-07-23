<?php

declare(strict_types=1);

namespace Tests\Support\Insights;

use App\Models\Category;
use App\Models\Condition;
use App\Models\FoodItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A deterministic port of the SUI-36 spike's synthetic generator
 * (`src/insights/generate.py`), writing real journal rows instead of a numpy
 * frame.
 *
 * The scenarios are structurally equivalent to the spike's, not numerically
 * identical: numpy's Mersenne draws and the linear congruential generator below
 * produce different numbers from the same seed, so tests assert on properties
 * that survive a redraw — ordering, sign, clearing the noise band, the
 * separability verdict — rather than on exact floats. The seed is fixed so a
 * given test is reproducible, and the generator is written out here rather than
 * using `fake()` so it cannot drift with faker's internals.
 *
 * What is reproduced from the spike: a lag kernel that peaks a day or two after
 * the tag and tails to a week, AR(1) sticky flares, and a latent factor that
 * co-fires a pair of tags (the "dessert" confound).
 */
final class SyntheticJournal
{
    /** The spike's lag kernel over days 0…7 (`SimConfig.kernel`). */
    private const KERNEL = [0.2, 0.6, 0.8, 0.6, 0.4, 0.25, 0.15, 0.1];

    private const BASELINE_INTENSITY = 2.0;

    private const FLARE_PHI = 0.6;

    private const FLARE_SD = 1.0;

    private const NOISE_SD = 0.8;

    private int $state;

    /** @var array<string, array{rate: float, effect: float}> */
    private array $tags = [];

    /** @var array<int, array{0: string, 1: string, strength: float}> */
    private array $coOccurrences = [];

    public function __construct(private readonly int $days, int $seed)
    {
        $this->state = abs($seed) % 2147483647 + 1;
    }

    /**
     * Add a trigger category to the vocabulary. `effect` is the intensity
     * points the kernel spreads over the days that follow it; zero makes the
     * tag inert.
     */
    public function tag(string $name, float $rate, float $effect = 0.0): self
    {
        $this->tags[$name] = ['rate' => $rate, 'effect' => $effect];

        return $this;
    }

    /**
     * Drive two tags from a shared latent factor, as the spike's dessert factor
     * co-fires dairy and sugar.
     */
    public function coOccur(string $first, string $second, float $strength): self
    {
        $this->coOccurrences[] = [$first, $second, 'strength' => $strength];

        return $this;
    }

    /**
     * Write the scenario as meals, classified food entries and daily condition
     * ratings, ending on `$endingOn` in the user's timezone.
     *
     * @return array<string, Category> the created categories, keyed by tag name
     */
    public function plant(User $user, Condition $condition, CarbonImmutable $endingOn): array
    {
        $presence = $this->drawPresence();
        $intensities = $this->drawIntensities($presence);

        $categories = [];
        $foodItems = [];

        foreach (array_keys($this->tags) as $name) {
            $category = Category::factory()->createQuietly([
                'name' => Str::headline($name),
                'slug' => Str::slug($name),
            ]);

            $categories[$name] = $category;
            $foodItems[$name] = FoodItem::factory()
                ->named(Str::headline($name) . ' food')
                ->withCategories([$category])
                ->createQuietly();
        }

        $start = $endingOn->startOfDay()->subDays($this->days - 1);
        $mealRows = [];
        $logRows = [];
        $now = CarbonImmutable::now()->toDateTimeString();

        for ($day = 0; $day < $this->days; $day++) {
            $date = $start->addDays($day);

            $mealRows[] = [
                'user_id' => $user->id,
                'eaten_at' => CarbonImmutable::parse($date->toDateString(), $user->timezone)
                    ->setTime(12, 0)
                    ->utc()
                    ->toDateTimeString(),
                'meal_type' => 'lunch',
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $logRows[] = [
                'user_id' => $user->id,
                'condition_id' => $condition->id,
                'date' => $date->toDateString(),
                'intensity' => $intensities[$day],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('meals')->insert($mealRows);
        DB::table('condition_logs')->insert($logRows);

        /** @var array<int, int> $mealIds */
        $mealIds = DB::table('meals')
            ->where('user_id', $user->id)
            ->orderBy('eaten_at')
            ->pluck('id')
            ->all();

        $entryRows = [];

        foreach ($this->tags as $name => $tag) {
            foreach ($presence[$name] as $day => $present) {
                if ($present) {
                    $entryRows[] = [
                        'meal_id' => $mealIds[$day],
                        'food_item_id' => $foodItems[$name]->id,
                        'text' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        DB::table('food_entries')->insert($entryRows);

        return $categories;
    }

    /**
     * @return array<string, array<int, bool>>
     */
    private function drawPresence(): array
    {
        $presence = [];

        foreach ($this->tags as $name => $tag) {
            $days = [];

            for ($day = 0; $day < $this->days; $day++) {
                $days[$day] = $this->uniform() < $tag['rate'];
            }

            $presence[$name] = $days;
        }

        foreach ($this->coOccurrences as $pair) {
            for ($day = 0; $day < $this->days; $day++) {
                $latentOn = $this->uniform() < 0.5;
                $fires = $this->uniform() < $pair['strength'];

                if ($latentOn && $fires) {
                    $presence[$pair[0]][$day] = true;
                    $presence[$pair[1]][$day] = true;
                }
            }
        }

        return $presence;
    }

    /**
     * @param  array<string, array<int, bool>>  $presence
     * @return array<int, int>
     */
    private function drawIntensities(array $presence): array
    {
        $flare = $this->autoRegressive(self::FLARE_PHI, self::FLARE_SD);
        $latent = array_fill(0, $this->days, self::BASELINE_INTENSITY);

        foreach ($this->tags as $name => $tag) {
            if ($tag['effect'] === 0.0) {
                continue;
            }

            foreach ($presence[$name] as $day => $present) {
                if (! $present) {
                    continue;
                }

                foreach (self::KERNEL as $lag => $weight) {
                    if ($day + $lag < $this->days) {
                        $latent[$day + $lag] += $tag['effect'] * $weight;
                    }
                }
            }
        }

        $intensities = [];

        for ($day = 0; $day < $this->days; $day++) {
            $value = $latent[$day] + $flare[$day] + $this->normal() * self::NOISE_SD;
            $intensities[$day] = (int) max(0, min(10, round($value)));
        }

        return $intensities;
    }

    /**
     * @return array<int, float>
     */
    private function autoRegressive(float $phi, float $sd): array
    {
        $series = [0.0];

        for ($day = 1; $day < $this->days; $day++) {
            $series[$day] = $phi * $series[$day - 1] + $this->normal() * $sd;
        }

        return $series;
    }

    /**
     * Box-Muller, so the noise is normal without pulling in a dependency.
     */
    private function normal(): float
    {
        $first = max($this->uniform(), 1.0e-12);
        $second = $this->uniform();

        return sqrt(-2.0 * log($first)) * cos(2.0 * M_PI * $second);
    }

    /**
     * A linear congruential generator: identical draws on every platform and
     * every PHP build, unlike `mt_rand()` seeding.
     */
    private function uniform(): float
    {
        $this->state = ($this->state * 1103515245 + 12345) % 2147483648;

        return $this->state / 2147483648;
    }
}
