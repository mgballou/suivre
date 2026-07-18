# SUI-36 — Lag-Lift Validation Spike (design)

- **Status:** Active
- **Ticket:** [SUI-36](https://linear.app/matthewbuiltthat/issue/SUI-36) — *Spike: validate lag-lift insight on synthetic data before E2/E3 build-out*
- **Feeds:** [SUI-21](https://linear.app/matthewbuiltthat/issue/SUI-21) (`ComputeCorrelations` action) · [SUI-22](https://linear.app/matthewbuiltthat/issue/SUI-22) (Insights UI: ranked trigger suspects)
- **Decisions in play:** D2 (correlation north star), D3 (time-lag model), D9 (curated tag taxonomy; correlation runs at the tag/category level), D11 (lag-lift ranking + defaults)

## Purpose

De-risk the north star (D2/D11) **before** committing to the E2 → E3 → E4 build-out. Answer one question cheaply: does the lag-lift ranking read as **signal** on realistic personal-scale data, or is it mush at small `n`? A fragile result is a **successful** spike — it reshapes E4 before we over-invest.

## Reframe vs. the original ticket

The ticket framed this as *fully disposable*. It is not. This is a **persisted POC / teaching artifact** the maintainer will return to and inspect over time. Two consequences:

- It lives in its **own private repo**, `suivre-insights-poc` (Python), a sibling of `suivre` — not in the PHP tree, which keeps `herd composer check` clean.
- Readability and reproducibility are first-class. The value is still the **findings**, but the code is kept because it is viable, inspectable, and accelerates SUI-21/22.

The POC informs SUI-21/22; it does **not** become E4 code.

## Non-goals

- No production `ComputeCorrelations`, no real user data, no UI.
- No changes to the `suivre` repo except the findings note (below).
- One condition, one user, one outcome series. No multi-condition or multi-user modelling.
- Mitigation analysis is limited to **stratification**; regularised regression / formal confounder-control stays deferred per D11.

## Grounding note (not baked into the product)

Parameter realism for this research session is grounded in a real chronic inflammatory condition whose dietary-trigger literature gives plausible base rates, effect sizes, and — critically — a **multi-day smeared lag** (one cohort: ~87% of new lesions appeared *within a week* of the trigger). This grounding shapes the synthetic knobs only; **no condition name, trigger list, or clinical claim is committed to the repo, the product, or the decision log.** D11's default lag window (0–2 days) is treated as an open question the spike re-evaluates, not a given.

---

## Repository & layout

Private GitHub repo `suivre-insights-poc`, `uv`-managed, single global seed. Notebook-forward:

```
suivre-insights-poc/
  README.md               # what it is, how to run, headline verdict
  pyproject.toml          # uv, pinned deps, seeded
  notebooks/
    01_lag_lift_spike.ipynb   # narrative: model -> math -> sweep -> heatmaps -> verdict
  src/insights/
    config.py             # dataclass holding every knob (no magic numbers in the notebook)
    generate.py           # synthetic daily-log generator
    lag_lift.py           # the D11 lift math + per-lag profile + stratified variant
    sweep.py              # parameter grid, Monte Carlo, detectability frontier
  outputs/                # committed figures + example tables
  tests/                  # light unit tests on the math (see Testing)
```

The notebook reads as prose and drives the modules; every figure is regenerable by a deterministic run-all.

---

## §1 · Generative model (`generate.py`)

Simulates one user over `D` days. Daily condition intensity is an integer 0–10.

**Tags.** `K` trigger tags (default 8), each present on a given day at its own base rate (non-uniform). A latent "dessert" factor co-fires **dairy + added sugar** together to plant deliberate **multicollinearity**. Only a designated subset (default 2) are *true* triggers with a nonzero effect; the remainder are **pure-noise tags**.

**Confounders.** `sleep_t` and `stress_t` are AR(1) latent series. Poor sleep / high stress raise intensity.

- **True-confounding toggle (ON in the headline "realistic" scenario):** `stress_t` also raises the probability of comfort-food tags (dairy/sugar). This creates genuine spurious food↔flare lift with **zero** real food effect — the hardest failure mode, and the one that most threatens the north star.

**True effect + lag kernel.** A true trigger occurring on day `t` adds `β · w_j` to intensity on days `t … t+L_true` via a decaying kernel `w` peaking at day 1–2 and tailing to ~7 (the "within a week" shape). `β` is the planted effect in intensity points.

**Sticky flares.** An AR(1) carryover `φ · flare_{t-1}` (plus innovation), **independent of tags** — the spurious-lift engine that any tag co-occurring with an ongoing flare will pick up.

**Assembly.**

```
latent_t   = μ0 + confounder_terms + Σ trigger_kernel_terms + flare_t + noise_t
intensity_t = round(clip(latent_t, 0, 10))
```

Floor/ceiling effects from clip+round are kept (they are real). Optional **MCAR missed-day** toggle drops a fraction of days (off by default) to probe robustness later.

All knobs live in `config.py`: `D`, `K`, base-rate vector, co-occurrence strength, `β` per true trigger, kernel shape / `L_true`, `φ`, confounder strengths, confounding-path toggle, noise SD, missingness rate, seed.

---

## §2 · Lag-lift math (`lag_lift.py`)

For an **analysis window** `N` (the operator setting from D11 — deliberately allowed to differ from the true `L_true`; the mismatch is measured):

- `exposed(T)` = days within `0…N` after **any** occurrence of tag `T` (per-day **union** labelling).
- `baseline(T)` = the tag-free complement — **not** cleaned of other tags or flare tails (so contamination is *measured*, not hidden).
- **Lift:** `lift_T = mean(intensity | exposed) − mean(intensity | baseline)`, on the native 0–10 scale (headline, interpretable as "points hotter").
- **Standardised effect:** `d_T = lift_T / pooled_sd` — used for the detectability frontier, not shown as the headline.
- **Sample size:** `n_T` = exposed-day count; occurrence count reported alongside.
- Rank tags by `lift_T` descending → the **suspects list**.

**Per-lag profile.** `lift_T(j)` computed at each individual lag `j = 0…7`. Recovers the true lag shape and directly evaluates candidate default windows `N` — this is the evidence behind the "is 0–2 the right default?" finding.

**Stratified / conditional lift (the one mitigation).** For a co-occurring pair `(A, B)`, recompute `lift_A` restricted to days where `B` is absent. Compare recovered attribution against the marginal result to answer: *does SUI-21 need more than marginal lift for MVP?*

---

## §3 · Sweep & detectability frontier (`sweep.py`)

**Grid axes:** `n` (days) × `β` (effect size, points) × base-rate × `N` (analysis window).

**Monte Carlo:** `R` simulated datasets per cell (default ~300), each a fresh draw from the generative model at that cell's parameters.

**Per-dataset hit:** the true trigger ranks **top-K** (default K=3) **AND** its lift exceeds the **noise-tag lift band** (95th percentile of the pure-noise tags' lifts in that dataset).

**Cell score:** hit-rate over the `R` datasets. **Frontier:** the **hit-rate ≥ 0.8** contour. Sensitivity panels vary K ∈ {1, 3} and the 0.8 threshold so the frontier's robustness is visible.

**Also reported per cell / scenario:**

- **False-positive rate** — how often a pure-noise tag lands top-3.
- **Confounding damage** — in the true-confounding scenario with `β_dairy = 0`, how often stress-driven dairy is still flagged top-3 (the scariest number in the study).

---

## §4 · Outputs & verdict

`outputs/` (all committed, all regenerable):

- Frontier heatmaps — `n × β` small-multiples across `N`.
- Per-lag profile plots (true kernel vs. recovered lift-by-lag).
- Example suspects tables at representative cells.
- Co-occurrence attribution: marginal vs. stratified, before/after.
- Confounding false-positive chart.

**Findings note** committed to `suivre/docs/` (Status-bannered, links to the POC repo) with the **go / no-go / adjust** verdict:

| Verdict | Trigger |
|---|---|
| **GO** | Realistic-scenario hit-rate ≥ 0.8 at personal-scale `n` (≤ ~90–180 days), moderate effects (≥ ~1.5 pts), realistic base rates, tolerable false-positive rate. |
| **ADJUST** | Legible only under narrower conditions → concrete guidance: recommended default `N`, min-`n` gating threshold for SUI-22, whether SUI-21 **must** stratify co-occurring tags. |
| **NO-GO** | Mush even under generous conditions → reshape E4 before building (defer insights, or change logging to boost signal). |

The note explicitly returns, for the downstream tickets:

- **SUI-21:** the lift definition (mean-difference, per-day union windows, tag-free baseline), the recommended default lag window `N`, and whether stratification is required for co-occurring tags.
- **SUI-22:** min-`n` flagging threshold, and false-positive-aware "suggestive, not proof" copy calibrated to the measured false-positive rate.

---

## Testing

Light unit tests on the deterministic core (`tests/`), not exhaustive:

- **Generator:** with a known seed and a single strong planted trigger, zero noise, zero confounding → the exposed mean exceeds baseline by the planted amount within tolerance; a pure-noise tag shows ~0 lift.
- **Lift math:** hand-constructed tiny series → `exposed/baseline` day sets and `lift`/`n` match expected values; union labelling dedupes overlapping windows.
- **Stratified lift:** a constructed co-occurring pair where the true effect is entirely on A → marginal lift flags both, stratified lift recovers A-only.

The heatmap/sweep layer is validated by eye in the notebook, not asserted.

---

## Reproducibility

`uv` with pinned dependencies; one global seed threaded through the config; Monte Carlo draws seeded per cell so figures are byte-stable across runs. `notebooks/01_lag_lift_spike.ipynb` run-all regenerates every artifact in `outputs/`.

---

## Definition of done

- `suivre-insights-poc` repo exists (private), runs clean via `uv`, notebook executes end-to-end deterministically.
- All `outputs/` figures generated, including the realistic-scenario frontier, the confounding-damage panel, and the co-occurrence marginal-vs-stratified comparison.
- Findings note committed to `suivre/docs/` with a clear GO/NO-GO/ADJUST verdict and the explicit SUI-21 / SUI-22 guidance above.
- SUI-36 linked to the POC repo; the ticket's original "fully disposable" AC is superseded by this design (already noted on the ticket).
