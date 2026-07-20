# Front-end Conventions

How React + TypeScript under `resources/js` is written: commenting density, typing, and where a
doc block belongs. Stack, layering and page conventions live in the `.ai/architecture` rules; for
Inertia client patterns activate the `inertia-react-development` skill. This file covers only what
review has actually had to correct.

The failure mode here is **over-commenting**. Prop-by-prop JSDoc that restates the prop name, prose
describing a string format a type could enforce, and doc blocks attached to the wrong symbol — all
appeared in the calendar work and all had to be removed. Default to fewer comments and stronger
types.

## Prefer a type to a comment

If a comment describes the **shape** of a value, encode the shape instead. Template literal types
for formatted strings, literal unions for closed sets.

@verbatim
<code-snippet name="Encode the format, don't describe it" lang="ts">
// ✅ The format is enforced, and a month cannot be passed as a date
export type IsoDate = `${number}-${number}-${number}`;
export type IsoMonth = `${number}-${number}`;

type MonthGridProps = {
    month: IsoMonth;
    previousMonth: IsoMonth;
};

// ❌ Prose the compiler cannot check
type MonthGridProps = {
    /** `YYYY-MM`. */
    month: string;
    previousMonth: string;
};
</code-snippet>
@endverbatim

The payoff is not tidiness. `date` and `month` were both `string`, so the compiler would happily
accept one where the other belonged; typing them distinctly made them mutually unassignable.

Be honest about the limit: a template literal constrains **shape, not validity** — `IsoDate` will
accept `2026-13-99`. It stops a category error, not bad data. Validity is the server's job.

## No prop-by-prop JSDoc

Do not interleave comments through a prop list. If a prop needs explaining, the name or the type is
wrong — fix that first. Context that genuinely survives goes on **the type, the component, or the
constant it describes**, never mid-list.

@verbatim
<code-snippet name="Context on the type, not the prop" lang="ts">
// ✅ The level's meaning belongs to the level type
/** Step 0 is an unlogged day; 1–5 climb the ramp. */
export type IntensityLevel = 0 | 1 | 2 | 3 | 4 | 5;

type DayCellProps = {
    date: IsoDate;
    level: IntensityLevel;
    isToday?: boolean;
    hasCheckin?: boolean;
};

// ❌ Restating the name and the type, one prop at a time
type DayCellProps = {
    /** ISO date, `YYYY-MM-DD`. */
    date: string;
    /** 0 = no entry; 1–5 climb the ramp. */
    level: IntensityLevel;
    /** A `DailyCheckin` exists for this date. */
    hasCheckin?: boolean;
};
</code-snippet>
@endverbatim

## Comment *why*, not *what*

A comment earns its place only when it records something the reader cannot recover from the code:
a constraint from outside the file, a cross-boundary contract, or a non-obvious reason for a
choice. Real examples from this codebase:

- Ramp classes are listed literally rather than interpolated, **so Tailwind's JIT emits them**.
- Weekdays are Monday-first, and **the server computes `leadingBlanks` from the same convention**,
  so the two must change together.
- Pan direction is read from the previous render, **because Inertia re-renders the component in
  place rather than remounting it**.

Delete anything that narrates the next line (`// Set up listener`, `// Components`).

## Attach the doc to the symbol it describes

A JSDoc block sits immediately above the thing it documents. A component doc attached to the
constant above the component never surfaces on hover — it is worse than no comment, because it
looks maintained. When you move code, move its doc with it and re-check what it is now attached to.

## Types

- Types used by more than one module live in `resources/js/types/` and are re-exported through
  `resources/js/types/index.ts` (`export type * from './dates';`). Import from `@/types`.
- A type owned by one component stays with that component and is exported from it — `IntensityLevel`
  from `day-cell.tsx`, `CalendarDay` from `month-grid.tsx`. Do not promote it until a second module
  needs it.
- Never widen a prop to `string` or `unknown` to silence an error; narrow the source instead.
- A type assertion (`as IsoDate`) is a last resort and is the one place a comment reliably earns its
  keep — say what widened the type and why the assertion is safe.

## Dates come from the server

The client **never derives a date**. `new Date()` reads the device timezone, not the user's
configured one, so every date, month and label arrives as a server prop already formatted. There is
no client-side date maths in the user-facing app; if a view needs a new date, the controller or
Action computes it.
