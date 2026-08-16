# Suivre — Food Intelligence

- **Date:** 2026-08-16
- **Status:** Active
- **Tickets:** SUI-49 … SUI-57, Linear project *Suivre v1*, milestone *V1 — Food intelligence*
- **Amends:** D9 (one match per line → many), D11/D24 (a correlation subject may be a food item)
- **Retires:** `MealType`

---

## 1. The problem

Logging "lemon pepper wings" produces one catalog match and one set of tags. Logging
"carbonara" produces the same. Neither answer is wrong so much as shallow: the first is
two foods pretending to be one, and the second is a dish whose whole signal lives in
components the classifier never looks at.

Two mechanisms are missing, and one is missing more embarrassingly than the other.

**Composition is built and unused.** `food_item_compositions`, `FoodItem::components()`,
`FoodItem::dishes()` and `FoodItemType::Dish` have existed since 2026-07-20. Nothing
reads them. `ClassifyFoodEntry` resolves a match and returns `$foodItem->categories` —
its own row's categories, never a component's. `FoodItemType::Dish`'s own docblock
promises it "inherits further signal from the components linked to it", and no code
delivers that.

**Decomposition was never built.** One typed line resolves to at most one catalog row.
There is no path by which "lemon pepper wings" becomes two foods.

**Nobody can curate either.** There is no `FoodItemResource` in the backstage. An
operator can manage Categories and work the review queue, but cannot create a food, add
an alias, or link a dish to its components. The seeded catalog is the only catalog there
will ever be, because the only tool that writes to it is a seeder.

And the entry flow charges five steps for this: choose a meal type, type lines, press
"Check these", tick the boxes, save.

## 2. What stays fixed

D9's commitment holds: **no AI in the classification loop**. The vocabulary stays
curated, the classifier stays deterministic and inspectable, and the same text always
resolves the same way. Everything below is reachable with `pg_trgm`, a composition table
and a decent algorithm.

D9's *shape* changes in exactly one respect, and it should be written into the decision
log as an amendment: **a line resolves to a set of foods, not to a food.** The rest of
D9 — curated taxonomy, deterministic matching, human-in-the-loop review — is untouched.

## 3. Decomposition

### 3.1 Whole-line first

Normalize the line through `FoodItem::normalizeName()`. If the whole normalized string
matches the catalog above threshold, that match is the answer and decomposition stops.

This is load-bearing, not an optimization. "Carbonara" is a curated dish; splitting it
into "carbon" and "ara" would be worse than useless. A curated whole-line match always
beats anything assembled from its parts, and curating a dish is therefore how an operator
overrides the algorithm.

### 3.2 Spans

Otherwise, tokenize the normalized string and generate every contiguous span up to
`MAX_SPAN_TOKENS = 4`. For a line of `T` tokens that is at most `4T − 6` spans — a dozen
or so for anything a person actually types.

Match every span against the catalog in **one** query. Build the spans as a `VALUES`
list and union the name and alias trigram lookups against it, the same shape
`ClassifyFoodEntry::findBestCandidate()` already uses, so the existing `gin_trgm_ops`
indexes carry the work and a bare column reference keeps the query sargable.

### 3.3 Choosing a cover

Accept spans greedily under a **total** ordering, skipping any span that overlaps one
already accepted:

1. span length, longest first
2. match score, highest first
3. start position, leftmost first
4. `food_item_id`, lowest first

Every tie is broken, so the same input always yields the same decomposition. That is the
D9 property that makes the correlation data worth having, and it must be asserted in a
test rather than assumed.

Longest-first is what makes "lemon pepper wings" work: the two-token span "lemon pepper"
loses to nothing, but "chicken wings" — reached by alias from "wings" — is claimed before
"wings" alone can be.

### 3.4 Short spans lie

Trigram similarity is unstable on short strings; "and" scores respectably against
plenty of things. Two guards:

- A span of fewer than 5 characters must match a normalized name or alias **exactly**.
  No trigram matching at all.
- Single-token spans are dropped if the token is a stopword. Keep the list small and
  literal — `and`, `with`, `the`, `a`, `of`, `on`, `in`, `some`, `plus`, `side`, `fresh`.

Tune the threshold tiers against the tests in §8, not by intuition.

### 3.5 Residue

Tokens no accepted span covers are kept as residue text on the entry. They are not
discarded, and they are not matched loosely to make the output look complete. An honest
miss is the point — it reaches the review queue, and the queue is how the catalog grows.

## 4. Composition resolution

A matched food's categories are its own **union** its components', resolved recursively.

- Depth cap of 3. The database check constraint blocks a self-reference but not
  `A → B → A`, so carry a visited-id set and stop on revisit.
- Only `FoodItemType::Dish` may carry components; the resolver can lean on that to
  terminate early, and `FoodItemResource` (§6) must enforce it on write.
- The resolved set is deduplicated by category id.

This belongs in its own Action — `ResolveFoodItemCategories` — because the classifier,
the entry display, and the insights repository all need the same answer, and three
implementations of it would drift.

Worth stating plainly: composition and decomposition are different mechanisms and both
are needed. Decomposition splits text the catalog does not know as a whole.
Composition explains a food the catalog *does* know. "Carbonara" is composition; "lemon
pepper wings" is decomposition until somebody curates it as a dish, at which point it
becomes composition and gets better.

## 5. One entry, many foods

`FoodEntry.food_item_id` holds one match and cannot hold a decomposition.

Replace it with a `food_entry_food_item` pivot carrying, per link:

- `matched_text` — the span that produced this match, so the UI can show
  *wings → chicken wings* rather than asserting the user typed "chicken wings"
- `score` — the similarity that won, for the review queue's near-miss judgement

`FoodEntry.text` becomes non-nullable: it is always what the user typed. The existing
check constraint ("text or food_item_id must be present") is replaced by that, and
`isClassified()` becomes "has at least one linked food item".

The migration copies every existing `food_item_id` into a pivot row with the entry's own
text as `matched_text` and a null score, then drops the column. Sweep
`CorrelationDataRepository`, `BuildExposureMask`, `BuildExposureTimeline`, the
`FoodEntries` Filament resource, and `DayMealEntry`.

## 6. Curation backstage

A `FoodItemResource` in Filament, following the project's schema-class convention
(`FoodItemForm`, `FoodItemsTable`, `FoodItemInfolist` under `Schemas/`):

- name, type, source metadata read-only where imported
- aliases as a repeater, each independently trigram-indexed on save
- categories as a multi-select over the curated taxonomy
- **components** as a self-referencing multi-select, visible and writable **only** when
  type is `Dish`, excluding the record itself

This is operator reference data, not user-generated data, so it is writable — D27 bars
the backstage from mutating a member's journal, which the catalog is not. `Category` is
already writable on the same reasoning.

## 7. Entry flow

Five steps become one field.

**Type-ahead.** A single input queries a catalog search endpoint on a debounce and offers
matches with their resolved tags already visible. Selecting one commits a chip that is
already classified — the classify round trip stops being something the user performs.

**Free text still commits.** Text the catalog cannot resolve becomes a chip marked
unresolved, saved as typed, and queued for review. A miss must never block logging; that
rule predates this work and survives it.

**Meal type is retired.** The column, the enum, `MealTypeOption`, the request rule and
the picker all go. Correlation reads the *day* (D3/D11), so the field was carrying a
required tap and nothing else.

`ResolveMealMoment` loses its conventional-hour source with the enum. Logging today
stamps the actual time, as now. A back-filled day stamps **local noon** — far enough from
either boundary that no timezone reading moves it to a neighbouring day — and meals within
a back-filled day order by `id` behind the identical timestamp. The existing docblock
already concedes the hour is "a convention for ordering, not a claim about when the user
ate"; this makes the convention cheaper without changing what it claims.

## 8. Corrections teach the catalog

Without AI, the correction loop is the only way the system gets smarter, so it has to be
good.

On any logged entry the user can add or remove tags from the curated picker, and search
the catalog for a different match. The correction is applied to the entry immediately.

Every correction also raises a `ReviewItem` against that entry carrying what the
classifier said and what the user chose. `review_items` already has the right shape — a
polymorphic `reviewable`, the copied `text`, a nullable `score`, and a unique index that
makes a re-correction update the open question rather than queue a second one.

The review queue gains an action that promotes a correction into the catalog: create the
missing food, attach the chosen categories, or add the typed text as an alias of an
existing row. One operator decision, and every user's next match is right.

## 9. Food items as insight subjects

Ranking food items alongside categories is a real risk and needs a guard, not enthusiasm.

The lag-lift spike (SUI-36, D24) found that small-`n` rankings are noise — pure-noise
tags out-rank real ones — and that co-occurrence confounding *worsens* with more data.
Food items are far sparser than categories: a user eats dairy on a third of days and this
specific chicken wings row perhaps four times a year. Ranking them naively reproduces
exactly the failure the spike warned about, with a longer list to draw false confidence
from.

Three guards, all mandatory:

1. **An exposure floor.** A food item is rankable only after it appears on at least
   `MIN_EXPOSURE_DAYS` distinct days. Start at 14 and treat it as tunable; it becomes a
   runtime setting when SUI-25 lands.
2. **A subject cap.** Rank at most the top `K` food items by exposure count. An unbounded
   subject list is an unbounded multiple-comparisons problem.
3. **Categories underneath.** Food-item results never replace category results. They are
   the finer grain over the same ranking, and a food item that fails the floor still
   contributes its categories, which is where its signal was always safest.

`SuspectTag` grows a `kind` discriminator (`category` | `food_item`) and a
`fromFoodItem()` constructor beside `fromCategory()`. D24's co-occurrence clustering
applies to food-item subjects unchanged — an item that cannot be separated from what it
travels with falls back to the coarse pattern, exactly as a category does.

## 10. Seed

None of this demonstrates anything against a catalog of atomic imported products. Seed
roughly forty composite dishes with their components — carbonara, korma, pad thai, lemon
pepper wings, burrito, margherita, pho, shakshuka — plus the component foods they need
and the aliases that reach them ("wings" → chicken wings).

Idempotent on `normalized_name` and additive on categories, matching `CommonFoodsSeeder`,
so a re-run never strips curation (D26).

## 11. Tests

Beyond the usual coverage, these carry the design and must exist:

- `lemon pepper wings` resolves to lemon and chicken wings, and to neither "lemon pepper"
  nor a bare "wings" row
- `carbonara` resolves as one dish and carries pasta, egg and dairy from components
- a curated whole-line dish beats the decomposition of its own name
- the same input yields the same decomposition across repeated runs and across a shuffled
  catalog insert order
- `A → B → A` composition terminates
- a 3-character span does not trigram-match
- residue text survives onto the entry and reaches the review queue
- a food item below the exposure floor is absent from the ranking while its categories
  are present
- the pivot migration preserves every existing classified entry

## 12. Sequencing

| | Ticket | Blocked by |
|---|---|---|
| SUI-49 | Decompose a typed line into many catalog matches | — |
| SUI-50 | Resolve a dish's categories through its components | — |
| SUI-51 | Link a food entry to many catalog foods | SUI-49 |
| SUI-52 | Retire `MealType` | — |
| SUI-53 | Add a `FoodItem` resource so the catalog can be curated | — |
| SUI-54 | Seed composite dishes and the foods they are made of | SUI-53 |
| SUI-55 | Replace the meal composer with type-ahead chips | SUI-51, SUI-52 |
| SUI-56 | Let a correction teach the catalog | SUI-53, SUI-55 |
| SUI-57 | Rank food items as insight subjects, behind an exposure floor | SUI-51 |

SUI-49 and SUI-50 are independent of each other and both land before SUI-51, which
everything downstream reads. SUI-52 can go any time. SUI-53 unblocks SUI-54, which is
what makes the first two demonstrable. SUI-57 is last, because it is the one that
benefits from real logged data.
