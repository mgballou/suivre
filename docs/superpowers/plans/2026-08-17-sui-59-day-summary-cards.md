# SUI-59 — The Day as Summary Cards Implementation Plan

- **Status:** Active
- **Ticket:** SUI-59, Linear project *Suivre v1*, milestone *V1 — Interface depth*
- **Spec:** `docs/superpowers/specs/2026-08-16-interface-depth-design.md` §5
- **Stack position:** branches off SUI-58 and is reviewed against it. SUI-60 branches off this one.

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the day page's single undifferentiated scroll with four summary cards that expand in place, one open at a time, each collapsed row stating what is recorded and which card opens decided by the server.

**Architecture:** `BuildDayView` gains a `sections` array and an `openSection` key, both computed server-side and carried on `DayView`. The page renders a `DaySections` container that owns which card is open and a `DaySection` card that animates its own height; each card's body is the existing component, moved inside unchanged. Height animates with a CSS grid `0fr → 1fr` transition rather than a measured pixel height, so no new dependency and no layout read.

**Tech Stack:** PHP 8.4, Laravel 13, Inertia 3, React 19, TypeScript, Tailwind v4, Pest 4, vitest.

## Global Constraints

- **Collapsed states what is recorded, not what is missing.** "3 items", "none", "not recorded" — flat description. **No completion count, no progress ring, no encouragement to finish.** "4 of 4 done ✓" is precisely the violation this ticket is most likely to commit by accident.
- **Summaries are computed server-side** and arrive as props already formatted. The client never derives a date and never re-derives a ramp step.
- **Which card opens is a prop**, never a client guess.
- **Height animates** over `--dur-base` with `--ease-quiet`; reduced motion collapses to a cross-fade with **no travel**.
- **Each card's body is the existing component, unchanged in behaviour** — `CheckinForm`, `ConditionRatings`, `MealLogger`, `FlareLogger`. This ticket restructures the container. The meal composer is rebuilt separately in SUI-55.
- **`day-cell`, `month-grid` and `intensity-picker` tests stay green** — the ramp is untouched.
- **Material comes from SUI-58's tokens.** `.elevation-raised`, `.panel-tint`. No bespoke shadow, blur or surface colour.
- **Every D20 commitment survives** (D28): structure yes, performance no.
- **Toolchain:** `herd php`, `herd composer`.

## One deliberate deviation from the spec's mock

The spec's ASCII sketch labels the cards `Check-in`, `Body`, `Food`, `Flares`. This plan ships **`Check-in`, `Conditions`, `Meals`, `Flares`** instead.

"Condition" and "meal" are the domain's words in the models, the enums, the settings screen, the onboarding flow and the backstage. Renaming them to "Body" and "Food" on one screen forks the vocabulary for no gain the sketch was arguing for — it was shorthand in a diagram, not settled copy. The change is one word in one file if the maintainer disagrees, so it is called out in the PR body rather than silently taken either way.

## File structure

| File | Responsibility |
|---|---|
| `app/Services/Journal/Data/DaySection.php` (create) | One card's server state: key, title, summary, whether it holds anything. |
| `app/Services/Journal/Data/DayView.php` (modify) | Carries `sections` and `openSection`. |
| `app/Services/Journal/Actions/BuildDayView.php` (modify) | Composes the four sections and picks the open one. |
| `resources/js/components/suivre/day-section.tsx` (create) | One card. Header button, animated body. Knows nothing about its siblings. |
| `resources/js/components/suivre/day-sections.tsx` (create) | The stack. Owns which card is open; that is its only state. |
| `resources/js/pages/day.tsx` (modify) | Header stays; the four-component scroll becomes four `DaySection` children. |
| `resources/js/components/suivre/condition-ratings.tsx` · `meal-logger.tsx` · `flare-logger.tsx` (modify) | Drop the inner `<h2>` now that the card header carries the title. Behaviour untouched. |
| `tests/Feature/Services/Journal/Actions/BuildDayViewTest.php` (modify) | Summaries and open-section selection. |
| `resources/js/components/suivre/day-section.test.tsx` · `day-sections.test.tsx` (create) | Render, expand, one-at-a-time, reduced motion. |

---

### Task 1: `DaySection` and the summaries

Server-side first, so the client has real props to render before it exists.

**Files:**
- Create: `app/Services/Journal/Data/DaySection.php`
- Modify: `app/Services/Journal/Data/DayView.php`
- Modify: `app/Services/Journal/Actions/BuildDayView.php`
- Test: `tests/Feature/Services/Journal/Actions/BuildDayViewTest.php`

**Interfaces:**
- Produces: `DaySection` with public readonly `string $key`, `string $title`, `string $summary`, `bool $recorded`, and `toArray(): array{key: string, title: string, summary: string, recorded: bool}`. `DayView` gains `public array $sections` and `public ?string $openSection`, serialised as `sections` and `openSection`. Section keys, in order: `checkin`, `conditions`, `meals`, `flares`. Task 3 renders exactly these.

#### The summary copy, settled

| Section | Recorded | Summary |
|---|---|---|
| Check-in | any of mood/sleep/stress/note on file | the sleep quality label when sleep is set, otherwise `Recorded` |
| Check-in | nothing on file | `Not recorded` |
| Conditions | ≥1 rating on file | `1 rated` / `2 rated` … |
| Conditions | conditions tracked, none rated | `Not rated` |
| Conditions | no active conditions at all | `None tracked` |
| Meals | ≥1 meal | `1 item` / `3 items` (counts **entries**, not meals — an entry is what the user typed) |
| Meals | none | `Nothing logged` |
| Flares | ≥1 flare | `1 flare` / `2 flares` |
| Flares | none | `None` |

Each of these states what is on file. None of them counts toward a total, and none of them is a nudge.

- [ ] **Step 1: Read the existing test so you extend rather than replace**

```bash
herd php artisan test --compact tests/Feature/Services/Journal/Actions/BuildDayViewTest.php
```

Expected: PASS. Read the file and reuse its existing user/condition setup helpers.

- [ ] **Step 2: Write the failing tests**

Append to `BuildDayViewTest.php`. Match the file's existing setup idiom for building a user with conditions rather than inventing a second one.

```php
it('describes an untouched day as what is not on file, never as what is left to do', function (): void {
    $user = User::factory()->tracking()->createQuietly();
    $date = CarbonImmutable::parse('2026-08-12');

    $view = app(BuildDayView::class)($user, $date, $date)->toArray();

    expect(array_column($view['sections'], 'key'))
        ->toBe(['checkin', 'conditions', 'meals', 'flares']);
    expect(array_column($view['sections'], 'summary'))
        ->toBe(['Not recorded', 'Not rated', 'Nothing logged', 'None']);
    expect(array_column($view['sections'], 'recorded'))
        ->toBe([false, false, false, false]);
});

it('opens the first section with nothing on file', function (): void {
    $user = User::factory()->tracking()->createQuietly();
    $date = CarbonImmutable::parse('2026-08-12');

    expect(app(BuildDayView::class)($user, $date, $date)->openSection)->toBe('checkin');
});

it('moves the open section past whatever the day already holds', function (): void {
    $user = User::factory()->tracking()->createQuietly();
    $date = CarbonImmutable::parse('2026-08-12');

    DailyCheckin::factory()->for($user)->createQuietly([
        'date' => $date->toDateString(),
        'sleep' => SleepQuality::Good,
    ]);

    $view = app(BuildDayView::class)($user, $date, $date);

    expect($view->openSection)->toBe('conditions');
    expect($view->sections[0]->summary)->toBe(SleepQuality::Good->getLabel());
    expect($view->sections[0]->recorded)->toBeTrue();
});

it('leaves a reviewed day entirely collapsed', function (): void {
    $user = User::factory()->tracking()->createQuietly();
    $condition = $user->conditions()->sole();
    $date = CarbonImmutable::parse('2026-08-12');

    DailyCheckin::factory()->for($user)->createQuietly([
        'date' => $date->toDateString(),
        'sleep' => SleepQuality::Good,
    ]);
    ConditionLog::factory()->for($user)->for($condition)->createQuietly([
        'date' => $date->toDateString(),
    ]);
    Meal::factory()->for($user)->createQuietly([
        'eaten_at' => $date->setTime(12, 0),
    ]);

    expect(app(BuildDayView::class)($user, $date, $date)->openSection)->toBeNull();
});

it('never opens the flare card, because an absent flare is a complete answer', function (): void {
    $user = User::factory()->tracking()->createQuietly();
    $condition = $user->conditions()->sole();
    $date = CarbonImmutable::parse('2026-08-12');

    DailyCheckin::factory()->for($user)->createQuietly(['date' => $date->toDateString()]);
    ConditionLog::factory()->for($user)->for($condition)->createQuietly(['date' => $date->toDateString()]);
    Meal::factory()->for($user)->createQuietly(['eaten_at' => $date->setTime(12, 0)]);

    $view = app(BuildDayView::class)($user, $date, $date);

    expect($view->openSection)->not->toBe('flares');
    expect($view->sections[3]->summary)->toBe('None');
});

it('counts what a meal card holds in entries, because an entry is what the user typed', function (): void {
    $user = User::factory()->tracking()->createQuietly();
    $date = CarbonImmutable::parse('2026-08-12');

    $meal = Meal::factory()->for($user)->createQuietly(['eaten_at' => $date->setTime(12, 0)]);
    FoodEntry::factory()->count(3)->for($meal)->createQuietly();

    $view = app(BuildDayView::class)($user, $date, $date);

    expect($view->sections[2]->summary)->toBe('3 items');
});

it('says a condition-less account tracks none rather than that it has failed to rate any', function (): void {
    $user = User::factory()->createQuietly();
    $date = CarbonImmutable::parse('2026-08-12');

    expect(app(BuildDayView::class)($user, $date, $date)->sections[1]->summary)->toBe('None tracked');
});
```

Add whatever imports these need (`ConditionLog`, `FoodEntry`, `Meal`, `SleepQuality`) at the top, as `use` statements — never inline FQCNs.

- [ ] **Step 3: Run them and watch them fail**

```bash
herd php artisan test --compact tests/Feature/Services/Journal/Actions/BuildDayViewTest.php
```

Expected: FAIL — `Undefined array key "sections"`.

- [ ] **Step 4: Create the DTO**

```php
<?php

declare(strict_types=1);

namespace App\Services\Journal\Data;

use Illuminate\Contracts\Support\Arrayable;

/**
 * One collapsible section of the day, as the server sees it.
 *
 * `summary` states what is on file — "3 items", "None", "Not recorded" — and
 * never what is outstanding. A completion count is the one thing this line must
 * not become: it would turn a journal into a checklist, which is the reading
 * D20 exists to refuse.
 *
 * `recorded` drives which card the server opens, not a tick in the UI.
 *
 * @implements Arrayable<string, mixed>
 */
readonly class DaySection implements Arrayable
{
    public function __construct(
        public string $key,
        public string $title,
        public string $summary,
        public bool $recorded,
    ) {}

    /**
     * @return array{key: string, title: string, summary: string, recorded: bool}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'title' => $this->title,
            'summary' => $this->summary,
            'recorded' => $this->recorded,
        ];
    }
}
```

- [ ] **Step 5: Carry them on `DayView`**

Add two constructor properties after `$mealTypes`:

```php
        /** @param array<int, DaySection> $sections */
        public array $sections,
        public ?string $openSection,
```

Add to the `toArray()` return, and extend the method's `@return` array shape with:

```php
     *     sections: array<int, array{key: string, title: string, summary: string, recorded: bool}>,
     *     openSection: string|null,
```

```php
            'sections' => array_map(
                static fn (DaySection $section): array => $section->toArray(),
                $this->sections,
            ),
            'openSection' => $this->openSection,
```

Extend the class doc block with a sentence saying `openSection` is guidance without a wizard: the first thing not yet on file, or nothing at all once the day has been reviewed.

- [ ] **Step 6: Compose them in `BuildDayView`**

Build the sections after `$conditions`, `$flares` and `$meals` are resolved, then pass both new arguments to the `DayView` constructor. Keep every helper private, and keep `__invoke` the only public method.

```php
    /**
     * @param  array<int, DayCondition>  $conditions
     * @param  array<int, DayMeal>  $meals
     * @param  array<int, DayFlare>  $flares
     * @return array<int, DaySection>
     */
    private function sections(?DailyCheckin $checkin, array $conditions, array $meals, array $flares): array
    {
        $rated = count(array_filter(
            $conditions,
            static fn (DayCondition $condition): bool => $condition->intensity !== null,
        ));

        $items = array_sum(array_map(
            static fn (DayMeal $meal): int => count($meal->entries),
            $meals,
        ));

        return [
            new DaySection(
                key: 'checkin',
                title: 'Check-in',
                summary: $checkin instanceof DailyCheckin
                    ? ($checkin->sleep?->getLabel() ?? 'Recorded')
                    : 'Not recorded',
                recorded: $checkin instanceof DailyCheckin,
            ),
            new DaySection(
                key: 'conditions',
                title: 'Conditions',
                summary: match (true) {
                    $conditions === [] => 'None tracked',
                    $rated > 0 => "{$rated} rated",
                    default => 'Not rated',
                },
                recorded: $rated > 0,
            ),
            new DaySection(
                key: 'meals',
                title: 'Meals',
                summary: $items > 0
                    ? $items . ' ' . str('item')->plural($items)->value()
                    : 'Nothing logged',
                recorded: $meals !== [],
            ),
            new DaySection(
                key: 'flares',
                title: 'Flares',
                summary: $flares === []
                    ? 'None'
                    : count($flares) . ' ' . str('flare')->plural(count($flares))->value(),
                recorded: true,
            ),
        ];
    }

    /**
     * The card the day opens on: the first thing not yet on file.
     *
     * Guidance without a wizard — a fresh day lands on the check-in, a partly
     * filled one on the gap, and a reviewed one on nothing at all. Flares are
     * excluded because an absent flare is a complete answer, not a gap, and a
     * card that stayed open on every quiet day would read as a demand.
     *
     * @param  array<int, DaySection>  $sections
     */
    private function openSection(array $sections): ?string
    {
        foreach ($sections as $section) {
            if ($section->key !== 'flares' && ! $section->recorded) {
                return $section->key;
            }
        }

        return null;
    }
```

Note `recorded: true` on the flares section: it is never a gap, so it never opens and never reads as outstanding.

- [ ] **Step 7: Run the tests and watch them pass**

```bash
herd php artisan test --compact tests/Feature/Services/Journal/Actions/BuildDayViewTest.php tests/Feature/Http/Controllers/DayControllerTest.php
```

Expected: PASS. `DayControllerTest` asserts the Inertia prop shape — if it fails on the two new keys, extend it rather than loosening it.

- [ ] **Step 8: Static analysis and commit**

```bash
herd php vendor/bin/pint --dirty --format agent && herd php vendor/bin/phpstan analyse --memory-limit=2G
git add app/Services/Journal tests/Feature/Services/Journal
git commit --no-gpg-sign -m "Compute the day's section summaries and open card server-side"
```

---

### Task 2: The `DaySection` card

One card in isolation. It knows whether it is open and how to say so; it does not know its siblings exist.

**Files:**
- Create: `resources/js/components/suivre/day-section.tsx`
- Test: `resources/js/components/suivre/day-section.test.tsx`

**Interfaces:**
- Produces:

```ts
export type DaySectionSummary = {
    key: string;
    title: string;
    summary: string;
    recorded: boolean;
};

type DaySectionProps = {
    section: DaySectionSummary;
    open: boolean;
    onToggle: (key: string) => void;
    children: React.ReactNode;
};

export function DaySection(props: DaySectionProps): JSX.Element;
```

`DaySectionSummary` is exported from this file and imported by `day-sections.tsx` and `day.tsx`. It stays here until a third module needs it.

#### How the height animates

A CSS grid whose single row goes from `0fr` to `1fr` transitions smoothly without anyone measuring anything, which is what keeps this free of a layout read and free of a new dependency.

```
<div class="grid transition-[grid-template-rows]" style="grid-template-rows: 0fr | 1fr">
  <div class="overflow-hidden">…body…</div>
</div>
```

Reduced motion replaces the row transition with an opacity cross-fade, so the card still changes state visibly but nothing travels.

- [ ] **Step 1: Write the failing test**

```tsx
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { DaySection } from './day-section';

const section = {
    key: 'meals',
    title: 'Meals',
    summary: '3 items',
    recorded: true,
};

describe('DaySection', () => {
    it('states what is on file in the collapsed row', () => {
        render(
            <DaySection section={section} open={false} onToggle={vi.fn()}>
                <p>body</p>
            </DaySection>,
        );

        expect(screen.getByText('Meals')).toBeInTheDocument();
        expect(screen.getByText('3 items')).toBeInTheDocument();
    });

    it('reports its open state to assistive technology', () => {
        const { rerender } = render(
            <DaySection section={section} open={false} onToggle={vi.fn()}>
                <p>body</p>
            </DaySection>,
        );

        expect(screen.getByRole('button')).toHaveAttribute('aria-expanded', 'false');

        rerender(
            <DaySection section={section} open onToggle={vi.fn()}>
                <p>body</p>
            </DaySection>,
        );

        expect(screen.getByRole('button')).toHaveAttribute('aria-expanded', 'true');
    });

    it('takes a closed body out of the tab order rather than merely hiding it', () => {
        const { container } = render(
            <DaySection section={section} open={false} onToggle={vi.fn()}>
                <button type="button">inside</button>
            </DaySection>,
        );

        expect(container.querySelector('[inert]')).not.toBeNull();
    });

    it('hands its key back on activation so the parent can decide what closes', async () => {
        const onToggle = vi.fn();
        render(
            <DaySection section={section} open={false} onToggle={onToggle}>
                <p>body</p>
            </DaySection>,
        );

        await userEvent.click(screen.getByRole('button'));

        expect(onToggle).toHaveBeenCalledWith('meals');
    });

    it('carries no completion state of any kind', () => {
        const { container } = render(
            <DaySection section={section} open={false} onToggle={vi.fn()}>
                <p>body</p>
            </DaySection>,
        );

        // D20/D28: a card describes what is on file and never scores it.
        expect(container.textContent).not.toMatch(/\bof\b|\bdone\b|✓|complete/i);
    });
});
```

- [ ] **Step 2: Run it and watch it fail**

```bash
npx vitest run resources/js/components/suivre/day-section.test.tsx
```

Expected: FAIL — cannot resolve `./day-section`.

- [ ] **Step 3: Implement it**

```tsx
import { ChevronDown } from 'lucide-react';
import { useId } from 'react';
import { cn } from '@/lib/utils';

export type DaySectionSummary = {
    key: string;
    title: string;
    summary: string;
    recorded: boolean;
};

type DaySectionProps = {
    section: DaySectionSummary;
    open: boolean;
    onToggle: (key: string) => void;
    children: React.ReactNode;
};

/**
 * One section of the day: a row stating what is on file, and a body that
 * expands in place beneath it.
 *
 * The summary is a server prop and is deliberately flat — "3 items", "None",
 * "Not recorded". It never counts toward a total and never carries a tick,
 * because a journal that scores the day stops being a journal (D20/D28).
 *
 * Height comes from a grid row going 0fr to 1fr, which the browser can
 * interpolate without anyone measuring the body. The alternative — reading
 * scrollHeight and animating a pixel value — costs a layout read on every
 * toggle and breaks the moment the body's own content resizes.
 */
export function DaySection({ section, open, onToggle, children }: DaySectionProps) {
    const bodyId = useId();

    return (
        <div className="border-b border-border last:border-b-0">
            <button
                type="button"
                aria-expanded={open}
                aria-controls={bodyId}
                onClick={() => onToggle(section.key)}
                className="flex min-h-14 w-full items-center gap-3 px-4 text-left transition-colors duration-[var(--dur-micro)] ease-quiet hover:bg-accent/40"
            >
                <span className="flex-1 text-sm font-medium text-foreground">
                    {section.title}
                </span>
                <span className="text-sm text-muted-foreground tabular-nums">
                    {section.summary}
                </span>
                <ChevronDown
                    aria-hidden
                    className={cn(
                        'size-4 shrink-0 text-muted-foreground',
                        'transition-transform duration-[var(--dur-base)] ease-quiet',
                        'motion-reduce:transition-none',
                        open && 'rotate-180',
                    )}
                />
            </button>

            <div
                id={bodyId}
                className={cn(
                    'grid',
                    'transition-[grid-template-rows,opacity] duration-[var(--dur-base)] ease-quiet',
                    'motion-reduce:transition-[opacity]',
                    open ? 'opacity-100' : 'opacity-0',
                )}
                style={{ gridTemplateRows: open ? '1fr' : '0fr' }}
            >
                <div className="overflow-hidden" inert={!open}>
                    <div className="panel-tint rounded-md px-4 pt-2 pb-6">
                        {children}
                    </div>
                </div>
            </div>
        </div>
    );
}
```

If `inert={!open}` trips the TypeScript definitions, widen React's types rather than casting to `any` — React 19 ships `inert?: boolean` on `HTMLAttributes`, so a failure here means the `@types/react` version is behind and should be checked before working around it.

- [ ] **Step 4: Run it and watch it pass**

```bash
npx vitest run resources/js/components/suivre/day-section.test.tsx
```

Expected: PASS, 5 tests.

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/suivre/day-section.tsx resources/js/components/suivre/day-section.test.tsx
git commit --no-gpg-sign -m "Add the DaySection card, expanding in place over a grid row"
```

---

### Task 3: The stack, and the day page

**Files:**
- Create: `resources/js/components/suivre/day-sections.tsx`
- Test: `resources/js/components/suivre/day-sections.test.tsx`
- Modify: `resources/js/pages/day.tsx`
- Modify: `resources/js/components/suivre/condition-ratings.tsx`, `meal-logger.tsx`, `flare-logger.tsx`

**Interfaces:**
- Consumes: `DaySection`, `DaySectionSummary` (Task 2); `sections` and `openSection` props (Task 1).
- Produces:

```ts
type DaySectionsProps = {
    sections: DaySectionSummary[];
    openSection: string | null;
    children: Record<string, React.ReactNode>;
};

export function DaySections(props: DaySectionsProps): JSX.Element;
```

`children` is keyed by section key so the page states which body belongs to which card without relying on array order.

- [ ] **Step 1: Write the failing test**

```tsx
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { DaySections } from './day-sections';

const sections = [
    { key: 'checkin', title: 'Check-in', summary: 'Not recorded', recorded: false },
    { key: 'conditions', title: 'Conditions', summary: '2 rated', recorded: true },
    { key: 'meals', title: 'Meals', summary: '3 items', recorded: true },
    { key: 'flares', title: 'Flares', summary: 'None', recorded: true },
];

const bodies = {
    checkin: <p>checkin body</p>,
    conditions: <p>conditions body</p>,
    meals: <p>meals body</p>,
    flares: <p>flares body</p>,
};

describe('DaySections', () => {
    it('renders one card per section, in server order', () => {
        render(<DaySections sections={sections} openSection={null}>{bodies}</DaySections>);

        expect(screen.getAllByRole('button').map((button) => button.textContent)).toEqual([
            expect.stringContaining('Check-in'),
            expect.stringContaining('Conditions'),
            expect.stringContaining('Meals'),
            expect.stringContaining('Flares'),
        ]);
    });

    it('opens the card the server chose', () => {
        render(<DaySections sections={sections} openSection="checkin">{bodies}</DaySections>);

        expect(screen.getByRole('button', { name: /Check-in/ })).toHaveAttribute('aria-expanded', 'true');
        expect(screen.getByRole('button', { name: /Conditions/ })).toHaveAttribute('aria-expanded', 'false');
    });

    it('leaves every card closed when the server chose none', () => {
        render(<DaySections sections={sections} openSection={null}>{bodies}</DaySections>);

        for (const button of screen.getAllByRole('button')) {
            expect(button).toHaveAttribute('aria-expanded', 'false');
        }
    });

    it('closes the open card when another is opened', async () => {
        render(<DaySections sections={sections} openSection="checkin">{bodies}</DaySections>);

        await userEvent.click(screen.getByRole('button', { name: /Meals/ }));

        expect(screen.getByRole('button', { name: /Meals/ })).toHaveAttribute('aria-expanded', 'true');
        expect(screen.getByRole('button', { name: /Check-in/ })).toHaveAttribute('aria-expanded', 'false');
    });

    it('closes the open card when it is activated again', async () => {
        render(<DaySections sections={sections} openSection="checkin">{bodies}</DaySections>);

        await userEvent.click(screen.getByRole('button', { name: /Check-in/ }));

        expect(screen.getByRole('button', { name: /Check-in/ })).toHaveAttribute('aria-expanded', 'false');
    });

    it('adopts the server choice again when the day changes underneath it', () => {
        const { rerender } = render(
            <DaySections sections={sections} openSection="checkin">{bodies}</DaySections>,
        );

        rerender(<DaySections sections={sections} openSection="meals">{bodies}</DaySections>);

        expect(screen.getByRole('button', { name: /Meals/ })).toHaveAttribute('aria-expanded', 'true');
    });
});
```

- [ ] **Step 2: Run it and watch it fail**

```bash
npx vitest run resources/js/components/suivre/day-sections.test.tsx
```

Expected: FAIL — cannot resolve `./day-sections`.

- [ ] **Step 3: Implement it**

The last test is the interesting one. Inertia re-renders the day page in place when moving between days, so a `useState` seeded once from the prop would carry yesterday's open card into today. Keying off the server value and resetting when it changes is what the existing components solve with `key={date}` — here it is cheaper to track the prop.

```tsx
import { useState } from 'react';
import {
    DaySection,
    type DaySectionSummary,
} from '@/components/suivre/day-section';

type DaySectionsProps = {
    sections: DaySectionSummary[];
    openSection: string | null;
    children: Record<string, React.ReactNode>;
};

/**
 * The day's four cards. One open at a time — this component's only job is
 * deciding which, so a card never has to know its siblings exist.
 *
 * The server picks the card the day opens on. That choice is re-adopted
 * whenever it changes, because Inertia re-renders this page in place when
 * navigating between days: state seeded once would carry yesterday's open card
 * into today.
 */
export function DaySections({ sections, openSection, children }: DaySectionsProps) {
    const [open, setOpen] = useState<string | null>(openSection);
    const [chosen, setChosen] = useState<string | null>(openSection);

    if (chosen !== openSection) {
        setChosen(openSection);
        setOpen(openSection);
    }

    return (
        <div className="elevation-raised overflow-hidden rounded-lg border border-border">
            {sections.map((section) => (
                <DaySection
                    key={section.key}
                    section={section}
                    open={open === section.key}
                    onToggle={(key) => setOpen((current) => (current === key ? null : key))}
                >
                    {children[section.key]}
                </DaySection>
            ))}
        </div>
    );
}
```

- [ ] **Step 4: Run it and watch it pass**

```bash
npx vitest run resources/js/components/suivre/day-sections.test.tsx
```

Expected: PASS, 6 tests.

- [ ] **Step 5: Drop the now-duplicated inner headings**

The card header carries the title, so the components' own `<h2>` would print it twice.

- `condition-ratings.tsx:70` — remove `<h2 className="…">Conditions</h2>`.
- `condition-ratings.tsx:51` — the empty state's `<h2>Conditions</h2>` goes too; keep the "You are not tracking any conditions right now" paragraph and its link exactly as they are.
- `meal-logger.tsx:120` — remove `<h2 …>Meals</h2>`.
- `flare-logger.tsx:92` — remove `<h2 …>Flare</h2>`.

Change nothing else in these files. If removing the heading leaves a wrapper element with a single child and no styling of its own, leave the wrapper — collapsing it is a separate change and this ticket is already touching four files it does not own.

- [ ] **Step 6: Rebuild the day page**

Keep the header — back link, label, "Today", and the arriving colour swatch — exactly as it is. Replace only the `<div className="flex flex-col gap-8">` block and its `<Separator />`s.

```tsx
                    <DaySections sections={sections} openSection={openSection}>
                        {{
                            checkin: (
                                <CheckinForm
                                    key={date}
                                    date={date}
                                    values={checkin}
                                    scales={scales}
                                />
                            ),
                            conditions: (
                                <ConditionRatings
                                    key={`conditions-${date}`}
                                    date={date}
                                    conditions={conditions}
                                />
                            ),
                            meals: (
                                <MealLogger
                                    key={`meals-${date}`}
                                    date={date}
                                    meals={meals}
                                    mealTypes={mealTypes}
                                />
                            ),
                            flares: (
                                <FlareLogger
                                    key={`flares-${date}`}
                                    date={date}
                                    conditions={conditions}
                                    intensities={flareIntensities}
                                    flares={flares}
                                />
                            ),
                        }}
                    </DaySections>
```

Add `sections: DaySectionSummary[]` and `openSection: string | null` to `DayProps`, import `DaySections` and `DaySectionSummary`, and drop the now-unused `Separator` import. Reduce the header's `mb-8` to `mb-6`: the cards carry their own edges now, so the old gap reads as a hole.

- [ ] **Step 7: Build, typecheck, and run everything the ramp depends on**

The day page is code-split per page, so a page missing from the Vite manifest 500s its route — build before Pest.

```bash
herd php artisan wayfinder:generate --with-form && npx tsc --noEmit
npm run build
npx vitest run
herd php artisan test --compact --filter='day-cell|month-grid|intensity-picker|Day'
```

Expected: all PASS. The `day-cell`, `month-grid` and `intensity-picker` suites must be untouched — if any of them moved, the ramp was touched and it should not have been.

- [ ] **Step 8: Commit**

```bash
git add resources/js
git commit --no-gpg-sign -m "Rebuild the day as four cards that expand in place"
```

---

### Task 4: Prove the motion contract, then gate and open the PR

**Files:**
- Modify: `tests/Browser/DayCheckinTest.php` (only if the restructure broke it — check first)

- [ ] **Step 1: Check the browser test still passes**

`DayCheckinTest` drives the real check-in on a real page. Its check-in controls now start inside a card, and on a fresh day the server opens that card — so it should still reach them. Verify rather than assume:

```bash
herd php artisan test tests/Browser/DayCheckinTest.php
```

If it fails because a control is inside a closed card, click the card's header first. Do not weaken the assertion.

- [ ] **Step 2: Run the whole gate**

```bash
herd composer check
```

- [ ] **Step 3: Look at it, at three widths and both schemes**

Capture the day page at 390px (phone), 768px (tablet) and 1280px (desktop), in light and dark, on three states: an untouched day, a partly filled one, and a fully reviewed one. Use the **capture-screenshots** skill and heed its ordering rule — the colour scheme is a browser-context option, so `->inLightMode()` / `->inDarkMode()` must be chained on the *pending* page, before `->on()` resolves it into a live one.

What to check in the images, specifically:

- the open card's body is fully visible and not clipped at 390px;
- the collapsed rows' summaries are right-aligned and do not collide with the title at 390px;
- the card stack's elevation reads as *above* the page in dark mode and is not merely a black rectangle;
- the panel tint behind a body is faint enough to read as grouping and not as a second card;
- no card shows a tick, a count toward a total, or anything else that scores the day.

- [ ] **Step 4: Push as the bot, and open the PR based on the SUI-58 branch**

```bash
GH_TOKEN="$(cat ~/.config/suivre/agent.token)" git push -u origin HEAD
```

Invoke **create-pr**. **The base is the SUI-58 branch, not `main`** — this is a stacked PR, and basing it on `main` would show SUI-58's diff inside it. Say in the body that it stacks on SUI-58, and call out the `Conditions`/`Meals` naming deviation from the spec's mock so the maintainer can overrule it in one word.

- [ ] **Step 5: Attach the screenshots** via **capture-screenshots**.

---

## Self-review

**Spec coverage.** §5's four cards → Task 3. Collapsed states what is recorded → Task 1's copy table, enforced by the "carries no completion state" test in Task 2. Summaries computed server-side → Task 1. Which card opens is a prop → Task 1's `openSection`, proven by four tests. Height animates over `--dur-base` with `--ease-quiet`, reduced motion cross-fades → Task 2's grid transition plus `motion-reduce:`. Bodies unchanged in behaviour → Task 3 Step 5 removes headings only. §7's "opening one closes the others" and "each collapsed summary reflects server state" → Task 3's tests. §7's "day-cell, month-grid, intensity-picker stay green" → Task 3 Step 7.

**Type consistency.** `DaySectionSummary` (Task 2) is what Task 3 imports and what `DayProps` declares; its four fields match `DaySection::toArray()`'s shape in Task 1 exactly. `onToggle: (key: string) => void` is called with `section.key` in Task 2 and consumed by `setOpen` in Task 3. Section keys `checkin`/`conditions`/`meals`/`flares` are the same four strings in the PHP action, the PHP test, the React tests and the page's `children` record.

**Known risk, stated rather than discovered.** `MealLogger` is a tall component with its own internal disclosure state. Inside a card that animates `grid-template-rows`, its content growing while the row is mid-transition can produce a single frame of clipping. If that shows up in the Step 3 screenshots, the fix is to let the transition finish before the body reflows — not to abandon the grid technique for a measured height, which has the same problem and costs a layout read.
