# Suivre — Interface Depth

- **Date:** 2026-08-16
- **Status:** Active
- **Tickets:** SUI-58 … SUI-60, Linear project *Suivre v1*, milestone *V1 — Interface depth*
- **Amends:** D20 (*quiet instrument*) — adds a material layer; changes none of its commitments

---

## 1. The problem

Two problems, and they are not the same problem.

**The day page is one undifferentiated scroll.** Check-in, condition ratings, meals and
flares stack in a single column separated by `<Separator />`, with no navigation, no
sense of what is done, and no way to reach the third thing without passing the first two.
The day is where a user spends nearly all their time in this app, and it is the least
designed screen in it.

**The surface is flat to the point of being unfinished.** D20 stripped ornament
deliberately and correctly, and what remains reads less like restraint than like an
unstyled form. Borders and background are doing all the work of separating a control from
the page.

## 2. What D20 keeps

Every commitment in D20 survives this work intact, and a reviewer should treat any
violation as a bug:

- **No red, ever.** Condition intensity stays the single-hue petrol ramp, deepening in
  light and lightening in dark.
- **No streaks, rings, guilt notifications or praise copy.** A missed day is data.
- **No celebratory or bouncy motion.** One easing token, four durations, zero overshoot.
- **Tabular figures, 8px radius, tight grid.**
- The one moment of whimsy remains the only one: a logged day's colour *arrives* over
  600ms.

## 3. What changes

D20 says "no ornament" and means no *decoration that rewards*. It does not settle what a
surface is made of, because the question never came up — the answer was implicitly "flat".
This spec settles it, and needs a decision-log entry saying so, or the next agent reads
"no ornament" and reverts the lot on sight.

**Depth is material, not celebration.** A panel that sits above the page, a sheet with a
translucent edge, a tinted field behind a group of controls: these say *this is a distinct
thing* and *this is above that*. They are the same category of statement as a border, made
better. None of them congratulate anybody.

The test to apply to any new visual treatment:

> Does it say something about **structure**, or something about the **user's performance**?

Structure is in scope. Performance is D20's prohibition and stays prohibited.

## 4. The material layer

### 4.1 Elevation

A three-step scale, no more. Page (flat), raised (a card on the page), floating (a sheet,
popover or the tab bar over content). Each step is a shadow token plus a background token
— never a shadow alone, which reads as grime in dark mode.

### 4.2 Glass

Translucency plus `backdrop-filter: blur()` plus a hairline top border, used **only** on
elements that genuinely overlay content: the bottom tab bar, sheets, popovers, the sticky
day header. Glass on something that overlays nothing is a lie about depth and looks like
one.

Two hard constraints:

- Text on glass must clear **WCAG AA against the worst backdrop it can sit over**, not
  against its nominal background. The `ConditionHue` contrast test already establishes
  the pattern of proving contrast rather than eyeballing it (D25 exists because eyeballing
  shipped two defects); extend it to cover glass surfaces over both schemes.
- `backdrop-filter` needs a fallback. Where it is unsupported the surface falls back to
  opaque at the same elevation. It must never fall back to unreadable.

### 4.3 Panel tint

Form sections sit on a faint tint rather than on the page. This is what makes a group of
controls read as a group without a box drawn around it, and it is the cheapest single
improvement available to the current screens.

### 4.4 Gooey

An SVG filter primitive — `feGaussianBlur` into `feColorMatrix` alpha contrast — applied
where two shapes meet and should read as one substance. Candidates: the tab bar's active
indicator as it travels, chips merging as they commit in the meal composer.

Tuned soft. The technique is capable of a lot of personality and this app wants almost
none of it: the blur radius stays low enough that the effect is felt at the boundary and
invisible at rest. If it reads as playful it is turned up too far.

`prefers-reduced-motion` removes the travel; the filter itself is static and may stay.

### 4.5 Tokens, not one-offs

All of the above are tokens in `app.css` beside the existing `--dur-*`, `--ease-quiet` and
radius scale. A component reaching for a raw blur value or a bespoke shadow is a review
failure — the point of a system is that the fifth screen costs less than the first.

## 5. The day page

Four summary cards, expanding in place. One open at a time.

```
┌────────────────────────────┐
│ ‹  Tue 12 Aug        ▓     │
│ ┌────────────────────────┐ │
│ │ Check-in    slept ok ✓ │ │
│ ├────────────────────────┤ │
│ │ Body        2 rated  ▾ │ │
│ │   Eczema   ▓▓▓▓░   7   │ │
│ │   Joints   ▓▓░░░   3   │ │
│ ├────────────────────────┤ │
│ │ Food        3 items  › │ │
│ ├────────────────────────┤ │
│ │ Flares      none     › │ │
│ └────────────────────────┘ │
└────────────────────────────┘
```

**Collapsed states what is recorded, not what is missing.** "3 items", "none", "not
recorded" — flat description. No completion count, no progress ring, no encouragement to
finish. This is the point in the design where D20 is easiest to violate by accident, and
"4 of 4 done ✓" is precisely the violation.

**Summaries are computed server-side.** Dates, counts and labels arrive as props already
formatted, per the standing rule that the client never derives a date and never re-derives
a ramp step.

**Which card opens** is a prop, so the server can open the first unrecorded section on a
fresh day and leave everything collapsed on a reviewed one. That is guidance without a
wizard, and it is why this shape beat a stepped flow.

**Height animates** over `--dur-base` with `--ease-quiet`; reduced motion collapses to a
cross-fade with no travel, per D20.

Each card's body is the existing component, unchanged in behaviour: `CheckinForm`,
`ConditionRatings`, `MealLogger`, `FlareLogger`. This ticket restructures the container
and does not rewrite what is inside it — the meal composer is rebuilt separately in A7.

## 6. Everything else

The material layer then sweeps calendar, insights, settings, onboarding and auth. No
structural change to those screens; they inherit elevation, tint and glass, and any
bespoke shadow or border found along the way is replaced by a token.

The calendar's day cells are explicitly **out of scope** for glass. They carry the petrol
ramp, which is a data encoding — putting translucency over a value the user is meant to
read comparatively would corrupt the reading.

## 7. Tests

- Contrast: every glass surface, both schemes, against its worst backdrop, at AA. Extend
  the existing `ConditionHueTest` pattern rather than inventing a second one.
- The day page renders four cards; the collapsed summary of each reflects server state.
- Opening one card closes the others.
- Reduced motion: no height travel, no indicator travel, feedback still present.
- Existing `day-cell`, `month-grid` and `intensity-picker` tests stay green — the ramp is
  untouched.

## 8. Sequencing

| | Ticket | Blocked by |
|---|---|---|
| SUI-58 | Add the material layer: elevation, glass, tint, gooey | — |
| SUI-59 | Rebuild the day as summary cards that expand in place | SUI-58 |
| SUI-60 | Sweep the material layer across the remaining surfaces | SUI-59 |

Tokens and the decision-log entry first; both later tickets consume them. The day page
before the sweep, so the sweep applies a system that has already survived one real screen.
