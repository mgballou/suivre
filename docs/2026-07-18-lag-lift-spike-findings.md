# Lag-Lift Insight — Spike Findings (SUI-36)

- **Status:** Active
- **Verdict:** **ADJUST** — the lag-lift insight is real for moderate-to-strong triggers given ~90 days of data, but fragile against confounding and small-`n` noise in ways that reshape E4 and the product framing.
- **Spec / plan:** `docs/superpowers/specs/2026-07-18-lag-lift-validation-spike-design.md`, `docs/superpowers/plans/2026-07-18-lag-lift-validation-spike.md` (+ `…-deepdive.md`)
- **POC (all numbers reproducible):** [`mgballou/suivre-insights-poc`](https://github.com/mgballou/suivre-insights-poc) — Python, seeded, `.venv/bin/pytest` (28 tests) + `notebooks/01_lag_lift_spike.ipynb`.
- **Feeds:** [SUI-21](https://linear.app/matthewbuiltthat/issue/SUI-21) (`ComputeCorrelations`), [SUI-22](https://linear.app/matthewbuiltthat/issue/SUI-22) (Insights UI)

> Method: synthetic single-user daily logs with planted lagged tag→intensity effects, AR(1) sticky flares, sleep/stress confounders (with a true stress→comfort-food path), a multi-day smeared lag kernel, and deliberately co-occurring tags. Lift = mean intensity difference (exposed vs tag-free baseline days); tags ranked, sample size shown. Detection = a true trigger ranks top-3 **and** clears the 95th-pct noise band; cell score = hit-rate over 300 Monte Carlo draws.

## Verdict in one paragraph

The ranking **works** where the effect is real and the data is sufficient: a trigger worth ≥1.5 intensity points is reliably surfaced (hit-rate ≥0.8) by ~75–90 days. But three findings bound that hard: (1) at personal scale a single ranking is **noisy** — pure-noise tags routinely out-rank real ones; only the aggregate is trustworthy; (2) the dominant error is **confounding by co-occurrence** — an innocent food that travels with a real trigger (dessert = dairy + sugar) is flagged ~61% of the time, and this does **not** wash out with more data; (3) measuring and adjusting for sleep/stress fixes the *estimate* but **not** the ranked output at realistic `n`. Net: build the insight, but as an uncertainty-forward, data-volume-gated, descriptive nudge — never a confident "trigger list."

## Evidence

**1. Detectability frontier (`frontier.png`).** Hit-rate ≥0.8 requires roughly: effect ≥1.5 pts → ~75–90 days; 1.0 pt → ~120–180 days; 0.5 pt → not inside a year. Moderate real triggers are detectable at a few months of consistent logging.

**2. Small-`n` ranking is noisy (`suspects_example.csv`).** On a representative 90-day draw, the top two ranked tags were **pure noise**; the true triggers landed #3–#4. A single user's ranked list is not trustworthy on its own — the aggregate hit-rate is.

**3. Confounding is the core threat, and it's co-occurrence-driven (`confounding_damage.png`, `adjustment_payoff.png`).** A zero-effect, stress-correlated food (sugar) lands top-3 **61%** of the time. Decomposition shows the dominant cause is **co-occurrence with a real trigger** (sugar rides with dairy), not the lifestyle path: stress-stratified adjustment leaves the estimate for the innocent tag essentially unchanged in the full scenario (0.95 → 0.95), and leaves the ranked damage identical (0.613 → 0.613). Adjustment provably works when the confound is lifestyle-only (a co-occurrence-off check collapses it 0.18 → 0.15), but co-occurrence between two foods is not something lifestyle tracking can fix.

**4. More data is double-edged (`thirty_day.png`).** From 30 → 90 days, hit-rate rises 0.61 → 0.87 (good) but confounding-damage also rises 0.48 → 0.61 — more logging finds real triggers *and* more reliably indicts their innocent co-travelers.

**5. The lag effect smears past 0–2 days (`lag_profile.png`).** Observed lift peaks around lag 3 and stays elevated to day 7 (sticky flares contaminate). A window of 2 is adequate on the ensemble, but the effect genuinely spreads wider than D11's default assumes; returning the lag *profile* is more honest than one fixed `N`.

**6. A soft "have you noticed…?" nudge is viable — from ~90 days (`alert_precision.png`, `thirty_day_pushharder.png`).** Firing only above a conservative lift + noise-band threshold: at 90 days you can whisper rarely (~10–20% of the time) and be right ~85–90%. At 60 days it's marginal (precision ~0.7 only at a high bar); at 30 days it is not honestly achievable. Pushing to the *softest* surface — a single tentative hint — confirms it: 30-day precision@1 peaks at **0.58** even for a strong (3–4 pt) trigger, 60 days at **0.66**, and only 90 days clears 0.7 (0.81–0.84). Precision is non-monotonic in effect size (dips at effect 4) because a stronger true trigger amplifies its co-occurring partner's contaminated lift.

## Guidance for SUI-21 (`ComputeCorrelations`)

- **Keep** the lift definition (mean intensity difference, per-day union windowing, tag-free baseline, sample size shown).
- **Return the lag profile**, not a single `N`; default window ~0–3 with the profile visible. 0–2 is slightly narrow.
- **Confounding is not optional to handle.** Stratification helps only the lifestyle path; the dominant co-occurrence confound needs either (a) far more data, (b) joint/partial modelling across tags, or (c) surfacing at a coarser meal/pattern level. Decide this before building; marginal per-tag lift alone over-accuses.
- **Redefine the false-positive metric** — `fp_rate` as "any non-true tag in top-K" is degenerate (always 1.0 when true triggers < K). Use per-tag precision / confounding-damage-style measures instead.
- Gate compute output on a **minimum data volume** (see SUI-22).

## Guidance for SUI-22 (Insights UI)

- **Uncertainty-forward is mandatory, not stylistic.** Never present a small-`n` ranking as a confident trigger list. Frame as tentative "worth noticing" hints.
- **Gate on ~90 days of data** before surfacing correlation hints at all; below that, the honest precision is a coin-flip. Prefer a single conservative hint over a ranked list at the margin.
- **Never claim single-tag causality** for co-occurring foods — attribute to the pattern/meal or invite the user to disambiguate.

## Product-framing implications (beyond the two tickets)

- **The 30-day value proposition cannot be correlation** — even soft/tentative. Make the first month **habit + descriptive self-tracking** ("here's your month; here's what you logged"), explicitly *setting up* for insights that switch on around 90 days.
- **Co-occurrence disambiguation is a first-class product problem**, not an implementation detail: foods travel together, so per-food blame is unreliable at personal scale.
- **Sleep/stress are still worth logging** — for honest estimates and user context — but must not be sold as the fix for food-attribution error; they aren't.

## Why this is a successful spike

It was cheap, it ran before E2/E3/E4, and it reshapes both the engine (SUI-21/22) and the product framing (30-day promise, insight gating, co-occurrence) *before* a line of E4 is built — exactly what SUI-36 set out to de-risk. A fragile-at-realistic-`n` result was an acceptance criterion, not a failure.
