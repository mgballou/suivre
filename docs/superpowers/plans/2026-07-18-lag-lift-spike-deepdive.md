# Lag-Lift Spike — Deep-Dive Extension Plan

> Extends `2026-07-18-lag-lift-validation-spike.md` after Task 5's findings prompted a product-direction question: *can consistent food logging carry a value prop at ~30 days, and what does the sleep/stress confound cost?* Same POC repo, same TDD discipline.

**Origin:** Task 5 showed `confounding_damage ≈ 0.61` — a zero-effect, stress-correlated food is flagged top-3 61% of the time. The lag-lift math never used sleep/stress, so **0.61 IS the food-only result**; not logging confounders doesn't remove them, it forecloses adjusting for them. These analyses quantify that and test an honest, small-scale "have you noticed…?" framing.

**Tech stack / constraints:** unchanged from the base plan (venv+pip, seeded, PYTHONPATH via editable install, never the global RNG, integer 0–10 intensity).

---

## Task 7: confounder-adjusted lift + soft-alert model

**Files:**
- Create: `src/insights/adjust.py`, `tests/test_adjust.py`
- Create: `src/insights/alerts.py`, `tests/test_alerts.py`

**Interfaces (produce):**
- `stress_adjusted_lift(intensity, tag_presence, confounder, n_window, n_strata=3) -> dict` — split days into `n_strata` quantile bins of `confounder`; within each bin compute exposed−baseline mean difference; return the **exposure-weighted** average of stratum lifts. Keys: `lift, n_exposed, n_strata_used`. This "controls for" the confounder: within a near-constant-stress stratum, tag-vs-no-tag difference removes stress's contribution.
- `rank_adjusted(intensity, tag_matrix, confounder, n_window, n_strata=3) -> DataFrame` — columns `tag, lift, n_exposed`, sorted by `lift` desc (adjusted-lift analogue of `rank_suspects`).
- `adjusted_damage(config, n_datasets=300, k=3, n_strata=3) -> float` — over Monte Carlo draws (seeded `config.seed+s`), share where a confounding-path-but-not-true tag lands top-`k` by **adjusted** rank. Directly comparable to `sweep.cell_metrics(...)["confounding_damage"]` (the marginal version).
- `alert_precision(config, days, alert_threshold, n_datasets=300) -> dict` — model conservative alerts: a tag "fires" when its **marginal** lift `>= alert_threshold` AND exceeds the 95th-pct noise band. Keys: `fire_rate` (share of datasets with ≥1 alert), `precision` (share of fired alerts that are true triggers), `n_alerts`, `true_alerts`.

**Key test intents (write real assertions, not tautologies):**
- `stress_adjusted_lift` collapses a purely-confounder-driven tag: construct a tag with **zero** real effect whose presence is perfectly tied to high `confounder`, where the confounder alone raises intensity → **marginal** `lift_for_tag` > 1 but `stress_adjusted_lift["lift"]` ≈ 0 (within tolerance).
- `stress_adjusted_lift` preserves a genuine effect: a tag that raises intensity independent of the confounder → adjusted lift ≈ the real effect.
- `alert_precision`: on data with one strong true trigger and pure-noise tags at a high threshold, `precision` == 1.0 and `fire_rate` > 0; at an impossibly high threshold, `n_alerts` == 0 (guard: precision defined as NaN when nothing fires).
- `adjusted_damage` runs and returns a float in [0,1] on the default confounded config.

**Steps:** TDD per function (write failing test → confirm fail → implement → confirm pass), then full-suite green, then commit. Reuse `exposed_mask`/`_pooled_sd` from `lag_lift.py` and `is_hit`-style noise-band logic from `sweep.py` (import, don't duplicate).

---

## Task 8: extend the notebook with the three deep-dive analyses

**Files:**
- Modify: `notebooks/01_lag_lift_spike.ipynb` (append a "Deep dive" section)
- Regenerate: `outputs/adjustment_payoff.png`, `outputs/thirty_day.png`, `outputs/alert_precision.png`

**Cells (call modules, plot; no inline stats):**
1. **30-day reality** — `cell_metrics` / `run_sweep` at `days ∈ {30, 60, 90}`: hit-rate + confounding-damage vs days. Narrate what is honestly surfaceable at 30 days.
2. **Confounder-adjustment payoff** — bar/line: marginal `confounding_damage` vs `adjusted_damage` (stress-stratified), and marginal vs adjusted lift for the spurious tag_1 (should collapse toward 0) and true tag_0 (should survive). This is the "what tracking sleep/stress buys you" figure.
3. **Soft-alert precision** — sweep `alert_threshold` at `days ∈ {30, 60, 90}`; plot **precision vs fire-rate** (coverage). Mark where precision clears a trust bar (e.g. ≥0.7). Narrate the "have you noticed…?" value prop and its floor set by confounding.
4. **Deep-dive verdict scratch (md)** — the numbers feeding the findings note.

**Steps:** author cells → execute headless (`--ExecutePreprocessor.timeout=1800`) → confirm clean run + three new figures → force-add pngs + commit. Report the actual numbers (30-day hit-rate/damage; adjusted vs marginal damage; the threshold/precision/fire-rate sweet spot) — they feed the note.

---

## Task 9: findings note + README + outward actions

Unchanged from base-plan Task 6, but the note now carries the deep-dive: the **ADJUST-plus** verdict, the sleep/stress-confound conclusion (why food-only can't escape it), the 30-day reality, and whether a conservative soft-alert framing is viable. Then README, private repo create + push, Linear attach (outward actions — user already approved).
