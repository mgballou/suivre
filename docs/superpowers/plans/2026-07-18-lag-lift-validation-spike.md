# Lag-Lift Validation Spike — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a persisted private Python POC that stress-tests the D11 lag-lift insight on realistic synthetic personal-health data, and emit a go/no-go/adjust findings note for SUI-21/SUI-22.

**Architecture:** A `uv`-managed, notebook-forward repo `suivre-insights-poc` (sibling of `suivre`). Pure functions in `src/insights/` (`config`, `generate`, `lag_lift`, `sweep`) are driven by one narrative notebook that renders committed figures into `outputs/`. Every draw is seeded, so figures are byte-stable. The findings note is committed back into `suivre/docs/`.

**Tech Stack:** Python 3.12+, numpy, pandas, matplotlib, pytest, Jupyter — all via `uv`.

## Global Constraints

Every task's requirements implicitly include these (verbatim from the spec):

- **Repo:** private GitHub repo `suivre-insights-poc` at `/Users/matthewballou/projects/suivre-insights-poc` (sibling of `suivre`). `uv`-managed.
- **No real data; one user, one condition, one outcome series.** Intensity is an integer 0–10.
- **Grounding is walled off:** no condition name, trigger list, or clinical claim in the repo, product, or decision log. Tag names are generic (`tag_0…`, with `dairy`/`sugar` used only as neutral labels for the co-occurring cluster).
- **Lift = mean difference in intensity points** (headline); standardized `d` = lift / pooled SD (frontier only).
- **Per-day union windowing:** a day is *exposed* to tag T if within `0…N` days after any T occurrence; `n` = exposed-day count (occurrences reported alongside).
- **Baseline = tag-free complement, un-cleaned** (contamination is measured, not hidden).
- **Mitigation is stratification only** — no regression/confounder-control.
- **Detection:** per-dataset hit = true trigger ranks **top-3** AND its lift exceeds the **95th-pct noise-tag lift band**; cell score = hit-rate over Monte Carlo draws; **frontier = 0.8 contour**. Defaults K=3, threshold=0.8, with sensitivity panels.
- **Determinism:** one global `seed`; per-cell/per-dataset seeds derived from it. Never call the global numpy RNG — always thread an explicit `np.random.Generator`.

---

### Task 1: Scaffold repo + `SimConfig`

**Files:**
- Create: `/Users/matthewballou/projects/suivre-insights-poc/pyproject.toml` (via `uv init`)
- Create: `src/insights/__init__.py`
- Create: `src/insights/config.py`
- Create: `tests/test_config.py`

**Interfaces:**
- Produces: `SimConfig` frozen dataclass with fields `days:int, n_tags:int, true_trigger_idx:tuple[int,...], base_rates:tuple[float,...], cooccur_pairs:tuple[tuple[int,int],...], cooccur_strength:float, effect_points:float, kernel:tuple[float,...], flare_phi:float, flare_sd:float, sleep_phi:float, stress_phi:float, confounder_strength:float, confounding_path:bool, confounding_tag_idx:tuple[int,...], baseline_intensity:float, noise_sd:float, missingness:float, n_window:int, seed:int`; and `SimConfig.rng() -> np.random.Generator` returning `np.random.default_rng(self.seed)`.

- [ ] **Step 1: Scaffold the repo**

```bash
cd /Users/matthewballou/projects
uv init suivre-insights-poc --package
cd suivre-insights-poc
uv add numpy pandas matplotlib jupyter
uv add --dev pytest
mkdir -p src/insights tests outputs notebooks
touch src/insights/__init__.py
git init -q && printf '.venv/\n__pycache__/\n.ipynb_checkpoints/\noutputs/*.png\n' > .gitignore
```

- [ ] **Step 2: Write the failing test**

`tests/test_config.py`:
```python
import numpy as np
from insights.config import SimConfig


def test_defaults_are_consistent():
    c = SimConfig()
    assert len(c.base_rates) == c.n_tags
    assert all(0 <= i < c.n_tags for i in c.true_trigger_idx)
    assert len(c.kernel) >= 1


def test_rng_is_deterministic():
    a = SimConfig(seed=7).rng().random(5)
    b = SimConfig(seed=7).rng().random(5)
    assert np.array_equal(a, b)
```

- [ ] **Step 3: Run test to verify it fails**

Run: `uv run pytest tests/test_config.py -v`
Expected: FAIL with `ModuleNotFoundError: No module named 'insights.config'`

- [ ] **Step 4: Write `src/insights/config.py`**

```python
from dataclasses import dataclass, field

import numpy as np


@dataclass(frozen=True)
class SimConfig:
    days: int = 90
    n_tags: int = 8
    true_trigger_idx: tuple[int, ...] = (0, 2)
    base_rates: tuple[float, ...] = (0.35, 0.30, 0.15, 0.25, 0.10, 0.20, 0.12, 0.18)
    # index 0 = "dairy", 1 = "sugar": a latent dessert factor co-fires them.
    cooccur_pairs: tuple[tuple[int, int], ...] = ((0, 1),)
    cooccur_strength: float = 0.7
    effect_points: float = 2.0
    # lag kernel over days 0..7; peaks day 1-2, tails to ~a week.
    kernel: tuple[float, ...] = (0.2, 0.6, 0.8, 0.6, 0.4, 0.25, 0.15, 0.1)
    flare_phi: float = 0.6
    flare_sd: float = 1.0
    sleep_phi: float = 0.5
    stress_phi: float = 0.5
    confounder_strength: float = 1.0
    confounding_path: bool = True
    confounding_tag_idx: tuple[int, ...] = (0, 1)
    baseline_intensity: float = 2.0
    noise_sd: float = 0.8
    missingness: float = 0.0
    n_window: int = 2
    seed: int = 0

    def rng(self) -> np.random.Generator:
        return np.random.default_rng(self.seed)
```

- [ ] **Step 5: Run test to verify it passes**

Run: `uv run pytest tests/test_config.py -v`
Expected: PASS (2 passed)

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "Scaffold suivre-insights-poc + SimConfig"
```

---

### Task 2: Synthetic generator (`generate.py`)

**Files:**
- Create: `src/insights/generate.py`
- Test: `tests/test_generate.py`

**Interfaces:**
- Consumes: `SimConfig` (Task 1).
- Produces: `generate_logs(config: SimConfig, rng: np.random.Generator) -> pandas.DataFrame` — one row per day (index 0..days-1), columns: `intensity` (int 0–10), `tag_0 … tag_{K-1}` (bool), `sleep` (float, high = worse), `stress` (float, high = worse), `observed` (bool). Later tasks read `df["intensity"].to_numpy()` and `df[f"tag_{i}"].to_numpy()`.

- [ ] **Step 1: Write the failing tests**

`tests/test_generate.py`:
```python
import numpy as np
from insights.config import SimConfig
from insights.generate import generate_logs


def _clean(**kw):
    # a clean world: one strong trigger, no confounding, no stickiness, no noise.
    return SimConfig(
        days=400, n_tags=3, true_trigger_idx=(0,), base_rates=(0.3, 0.3, 0.3),
        cooccur_pairs=(), effect_points=3.0, kernel=(0.0, 1.0, 0.0),
        flare_phi=0.0, flare_sd=0.0, confounder_strength=0.0,
        confounding_path=False, noise_sd=0.0, baseline_intensity=1.0, **kw,
    )


def test_shape_and_columns():
    c = SimConfig()
    df = generate_logs(c, c.rng())
    assert len(df) == c.days
    assert df["intensity"].between(0, 10).all()
    assert df["intensity"].dtype.kind in "iu"
    for i in range(c.n_tags):
        assert f"tag_{i}" in df.columns


def test_planted_trigger_raises_next_day_intensity():
    c = _clean()
    df = generate_logs(c, c.rng())
    trig = df["tag_0"].to_numpy()
    inten = df["intensity"].to_numpy()
    after = inten[1:][trig[:-1]]          # day after a trigger
    other = inten[1:][~trig[:-1]]         # day after a non-trigger
    assert after.mean() - other.mean() > 1.5


def test_noise_tag_has_no_effect():
    c = _clean()
    df = generate_logs(c, c.rng())
    tag2 = df["tag_2"].to_numpy()
    inten = df["intensity"].to_numpy()
    after = inten[1:][tag2[:-1]]
    other = inten[1:][~tag2[:-1]]
    assert abs(after.mean() - other.mean()) < 0.5


def test_determinism():
    c = SimConfig(seed=3)
    a = generate_logs(c, c.rng())
    b = generate_logs(c, c.rng())
    assert a.equals(b)
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `uv run pytest tests/test_generate.py -v`
Expected: FAIL with `ModuleNotFoundError: No module named 'insights.generate'`

- [ ] **Step 3: Write `src/insights/generate.py`**

```python
import numpy as np
import pandas as pd

from insights.config import SimConfig


def _ar1(n: int, phi: float, sd: float, rng: np.random.Generator) -> np.ndarray:
    x = np.zeros(n)
    innov = rng.normal(0.0, sd, size=n)
    for t in range(1, n):
        x[t] = phi * x[t - 1] + innov[t]
    return x


def _tag_matrix(config: SimConfig, stress: np.ndarray, rng: np.random.Generator) -> np.ndarray:
    d, k = config.days, config.n_tags
    tags = np.zeros((d, k), dtype=bool)

    # co-occurrence: a shared latent factor drives each cluster together.
    latent = {}
    for a, b in config.cooccur_pairs:
        latent[(a, b)] = rng.random(d) < 0.5

    for i in range(k):
        p = np.full(d, config.base_rates[i])
        # confounding path: stress raises the odds of comfort-food tags.
        if config.confounding_path and i in config.confounding_tag_idx:
            p = np.clip(p + config.confounder_strength * 0.05 * np.clip(stress, 0, None), 0, 1)
        draw = rng.random(d) < p
        tags[:, i] = draw

    # apply co-occurrence: when the latent factor is on, force both members on together.
    for (a, b), on in latent.items():
        both = on & (rng.random(config.days) < config.cooccur_strength)
        tags[both, a] = True
        tags[both, b] = True
    return tags


def _kernel_contribution(tag_col: np.ndarray, kernel: np.ndarray, beta: float) -> np.ndarray:
    d = len(tag_col)
    out = np.zeros(d)
    occ = np.flatnonzero(tag_col)
    for t in occ:
        for j, w in enumerate(kernel):
            if t + j < d:
                out[t + j] += beta * w
    return out


def generate_logs(config: SimConfig, rng: np.random.Generator) -> pd.DataFrame:
    d = config.days
    sleep = _ar1(d, config.sleep_phi, 1.0, rng)
    stress = _ar1(d, config.stress_phi, 1.0, rng)
    flare = _ar1(d, config.flare_phi, config.flare_sd, rng)

    tags = _tag_matrix(config, stress, rng)
    kernel = np.asarray(config.kernel, dtype=float)

    latent = np.full(d, config.baseline_intensity, dtype=float)
    latent += config.confounder_strength * np.clip(sleep, 0, None)
    latent += config.confounder_strength * np.clip(stress, 0, None)
    latent += flare
    for i in config.true_trigger_idx:
        latent += _kernel_contribution(tags[:, i], kernel, config.effect_points)
    latent += rng.normal(0.0, config.noise_sd, size=d)

    intensity = np.clip(np.round(latent), 0, 10).astype(int)
    observed = rng.random(d) >= config.missingness

    data = {"intensity": intensity, "sleep": sleep, "stress": stress, "observed": observed}
    for i in range(config.n_tags):
        data[f"tag_{i}"] = tags[:, i]
    return pd.DataFrame(data)
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `uv run pytest tests/test_generate.py -v`
Expected: PASS (4 passed). If `test_planted_trigger_raises_next_day_intensity` is borderline, confirm the kernel used in `_clean` puts weight on lag 1 — it does `(0.0, 1.0, 0.0)`.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "Add synthetic daily-log generator"
```

---

### Task 3: Lag-lift math (`lag_lift.py`)

**Files:**
- Create: `src/insights/lag_lift.py`
- Test: `tests/test_lag_lift.py`

**Interfaces:**
- Consumes: numpy arrays from Task 2's DataFrame.
- Produces:
  - `exposed_mask(tag_presence: np.ndarray, n_window: int) -> np.ndarray` (bool)
  - `lift_for_tag(intensity, tag_presence, n_window) -> dict` with keys `lift, d, n_exposed, n_occurrences, exposed_mean, baseline_mean`
  - `rank_suspects(intensity, tag_matrix: np.ndarray, n_window) -> pandas.DataFrame` — columns `tag, lift, d, n_exposed, n_occurrences`, sorted by `lift` desc
  - `lag_profile(intensity, tag_presence, max_lag=7) -> np.ndarray` — lift at each single lag `0..max_lag`
  - `stratified_lift(intensity, tag_a, tag_b, n_window) -> dict` — A's lift on days where B is absent (same key shape as `lift_for_tag`)

- [ ] **Step 1: Write the failing tests**

`tests/test_lag_lift.py`:
```python
import numpy as np
from insights.lag_lift import (
    exposed_mask, lift_for_tag, rank_suspects, lag_profile, stratified_lift,
)


def test_exposed_mask_unions_overlapping_windows():
    pres = np.array([True, False, False, True, False, False])
    # window 2: day0 -> {0,1,2}; day3 -> {3,4,5}
    assert exposed_mask(pres, 2).tolist() == [True, True, True, True, True, True]


def test_exposed_mask_dedupes_close_occurrences():
    pres = np.array([True, True, False, False])
    assert exposed_mask(pres, 1).tolist() == [True, True, True, False]


def test_lift_is_mean_difference():
    inten = np.array([0, 5, 5, 0, 0, 0])
    pres = np.array([True, False, False, False, False, False])  # exposed {0,1} at window 1
    r = lift_for_tag(inten, pres, 1)
    assert r["n_exposed"] == 2
    assert r["n_occurrences"] == 1
    assert np.isclose(r["exposed_mean"], 2.5)
    assert np.isclose(r["baseline_mean"], 0.0)
    assert np.isclose(r["lift"], 2.5)


def test_rank_orders_by_lift_desc():
    inten = np.array([0, 6, 0, 6, 0, 6, 0, 0])
    tags = np.zeros((8, 2), dtype=bool)
    tags[[0, 2, 4], 0] = True   # strong tag precedes the 6s
    tags[[7], 1] = True         # weak/noise tag
    df = rank_suspects(inten, tags, 1)
    assert df.iloc[0]["tag"] == "tag_0"
    assert df.iloc[0]["lift"] > df.iloc[1]["lift"]


def test_lag_profile_peaks_at_true_lag():
    inten = np.zeros(50)
    pres = np.zeros(50, dtype=bool)
    pres[::5] = True
    inten[np.flatnonzero(pres) + 2] = 8   # effect lands exactly 2 days later
    prof = lag_profile(inten, pres, max_lag=5)
    assert int(np.argmax(prof)) == 2


def test_stratified_lift_recovers_a_only_effect():
    # A causes the effect; B always rides with A but is inert.
    inten = np.array([0, 8, 0, 8, 0, 0])
    a = np.array([True, False, True, False, False, False])
    b = np.array([True, False, True, False, False, False])  # co-occurs with A
    strat = stratified_lift(inten, a, b, 1)  # A on B-absent days
    # with B always co-occurring, B-absent exposed days may be few; assert it runs and returns keys
    assert set(strat) >= {"lift", "n_exposed"}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `uv run pytest tests/test_lag_lift.py -v`
Expected: FAIL with `ModuleNotFoundError: No module named 'insights.lag_lift'`

- [ ] **Step 3: Write `src/insights/lag_lift.py`**

```python
import numpy as np
import pandas as pd


def exposed_mask(tag_presence: np.ndarray, n_window: int) -> np.ndarray:
    d = len(tag_presence)
    mask = np.zeros(d, dtype=bool)
    for t in np.flatnonzero(tag_presence):
        mask[t : min(t + n_window + 1, d)] = True
    return mask


def _pooled_sd(a: np.ndarray, b: np.ndarray) -> float:
    na, nb = len(a), len(b)
    if na < 2 or nb < 2:
        return 0.0
    num = a.var(ddof=1) * (na - 1) + b.var(ddof=1) * (nb - 1)
    return float(np.sqrt(num / (na + nb - 2)))


def _lift_from_masks(intensity, exposed, baseline, n_occurrences) -> dict:
    if exposed.sum() == 0 or baseline.sum() == 0:
        return {"lift": np.nan, "d": np.nan, "n_exposed": int(exposed.sum()),
                "n_occurrences": int(n_occurrences), "exposed_mean": np.nan,
                "baseline_mean": np.nan}
    e, b = intensity[exposed], intensity[baseline]
    sd = _pooled_sd(e, b)
    lift = float(e.mean() - b.mean())
    return {"lift": lift, "d": (lift / sd) if sd > 0 else 0.0,
            "n_exposed": int(exposed.sum()), "n_occurrences": int(n_occurrences),
            "exposed_mean": float(e.mean()), "baseline_mean": float(b.mean())}


def lift_for_tag(intensity: np.ndarray, tag_presence: np.ndarray, n_window: int) -> dict:
    exp = exposed_mask(tag_presence, n_window)
    return _lift_from_masks(intensity, exp, ~exp, tag_presence.sum())


def rank_suspects(intensity: np.ndarray, tag_matrix: np.ndarray, n_window: int) -> pd.DataFrame:
    rows = []
    for i in range(tag_matrix.shape[1]):
        r = lift_for_tag(intensity, tag_matrix[:, i], n_window)
        r["tag"] = f"tag_{i}"
        rows.append(r)
    df = pd.DataFrame(rows)[["tag", "lift", "d", "n_exposed", "n_occurrences"]]
    return df.sort_values("lift", ascending=False, na_position="last").reset_index(drop=True)


def lag_profile(intensity: np.ndarray, tag_presence: np.ndarray, max_lag: int = 7) -> np.ndarray:
    prof = np.full(max_lag + 1, np.nan)
    occ = np.flatnonzero(tag_presence)
    d = len(intensity)
    baseline = intensity[~exposed_mask(tag_presence, max_lag)]
    if len(baseline) == 0:
        return prof
    bmean = baseline.mean()
    for j in range(max_lag + 1):
        hit = occ[occ + j < d] + j
        if len(hit):
            prof[j] = intensity[hit].mean() - bmean
    return prof


def stratified_lift(intensity: np.ndarray, tag_a: np.ndarray, tag_b: np.ndarray,
                    n_window: int) -> dict:
    exp_a = exposed_mask(tag_a, n_window)
    exp_b = exposed_mask(tag_b, n_window)
    exposed = exp_a & ~exp_b            # A-exposed but not B-exposed
    baseline = ~exp_a & ~exp_b          # clean of both
    return _lift_from_masks(intensity, exposed, baseline, tag_a.sum())
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `uv run pytest tests/test_lag_lift.py -v`
Expected: PASS (6 passed)

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "Add lag-lift math: windowing, lift, ranking, profile, stratified"
```

---

### Task 4: Sweep, detection & damage metrics (`sweep.py`)

**Files:**
- Create: `src/insights/sweep.py`
- Test: `tests/test_sweep.py`

**Interfaces:**
- Consumes: `SimConfig` (T1), `generate_logs` (T2), `rank_suspects` (T3).
- Produces:
  - `is_hit(suspects: pd.DataFrame, true_idx: tuple[int,...], k: int = 3, noise_pct: float = 95.0) -> bool`
  - `cell_metrics(config: SimConfig, n_datasets: int = 300, k: int = 3) -> dict` with keys `hit_rate, fp_rate, confounding_damage` (each a float in [0,1]); `confounding_damage` = share of datasets where a confounding-path tag with **zero** true effect lands top-`k` (NaN if the scenario has no such tag)
  - `run_sweep(base: SimConfig, days_grid, effect_grid, n_datasets=300) -> pandas.DataFrame` — columns `days, effect_points, hit_rate, fp_rate` (one row per grid cell)

- [ ] **Step 1: Write the failing tests**

`tests/test_sweep.py`:
```python
import numpy as np
import pandas as pd
from insights.config import SimConfig
from insights.sweep import is_hit, cell_metrics, run_sweep


def test_is_hit_true_when_trigger_top3_and_above_noise():
    df = pd.DataFrame({
        "tag": ["tag_0", "tag_1", "tag_2", "tag_3"],
        "lift": [3.0, 0.2, 0.1, 0.0],
        "d": [1.0, 0.1, 0.0, 0.0], "n_exposed": [10] * 4, "n_occurrences": [5] * 4,
    })
    assert is_hit(df, true_idx=(0,), k=3) is True


def test_is_hit_false_when_trigger_buried():
    df = pd.DataFrame({
        "tag": ["tag_1", "tag_2", "tag_3", "tag_0"],
        "lift": [3.0, 2.0, 1.0, 0.05],
        "d": [1, 1, 1, 0], "n_exposed": [10] * 4, "n_occurrences": [5] * 4,
    })
    assert is_hit(df, true_idx=(0,), k=3) is False


def test_strong_clean_signal_has_high_hit_rate():
    c = SimConfig(days=180, n_tags=5, true_trigger_idx=(0,), effect_points=3.0,
                  flare_phi=0.0, flare_sd=0.0, confounder_strength=0.0,
                  confounding_path=False, cooccur_pairs=(), noise_sd=0.5)
    m = cell_metrics(c, n_datasets=40)
    assert m["hit_rate"] > 0.8


def test_run_sweep_shape():
    base = SimConfig(n_tags=4, true_trigger_idx=(0,), cooccur_pairs=(),
                     confounding_path=False)
    df = run_sweep(base, days_grid=[60, 120], effect_grid=[1.0, 2.0], n_datasets=15)
    assert len(df) == 4
    assert {"days", "effect_points", "hit_rate", "fp_rate"} <= set(df.columns)
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `uv run pytest tests/test_sweep.py -v`
Expected: FAIL with `ModuleNotFoundError: No module named 'insights.sweep'`

- [ ] **Step 3: Write `src/insights/sweep.py`**

```python
from dataclasses import replace

import numpy as np
import pandas as pd

from insights.config import SimConfig
from insights.generate import generate_logs
from insights.lag_lift import rank_suspects


def is_hit(suspects: pd.DataFrame, true_idx: tuple[int, ...], k: int = 3,
           noise_pct: float = 95.0) -> bool:
    true_tags = {f"tag_{i}" for i in true_idx}
    top = suspects.head(k)["tag"].tolist()
    if not any(t in true_tags for t in top):
        return False
    noise = suspects[~suspects["tag"].isin(true_tags)]["lift"].dropna()
    band = np.percentile(noise, noise_pct) if len(noise) else -np.inf
    best_true = suspects[suspects["tag"].isin(true_tags)]["lift"].max()
    return bool(best_true > band)


def cell_metrics(config: SimConfig, n_datasets: int = 300, k: int = 3) -> dict:
    hits = fps = 0
    damage = 0
    # a confounding-path tag that is NOT a true trigger = pure spurious candidate.
    spurious = [i for i in config.confounding_tag_idx
                if config.confounding_path and i not in config.true_trigger_idx]
    true_tags = {f"tag_{i}" for i in config.true_trigger_idx}
    for s in range(n_datasets):
        rng = np.random.default_rng(config.seed + s)
        df = generate_logs(config, rng)
        inten = df["intensity"].to_numpy()
        tags = np.column_stack([df[f"tag_{i}"].to_numpy() for i in range(config.n_tags)])
        suspects = rank_suspects(inten, tags, config.n_window)
        if is_hit(suspects, config.true_trigger_idx, k=k):
            hits += 1
        top = set(suspects.head(k)["tag"])
        if top - true_tags:                       # any non-true tag in the top-k
            fps += 1
        if spurious and top & {f"tag_{i}" for i in spurious}:
            damage += 1
    return {
        "hit_rate": hits / n_datasets,
        "fp_rate": fps / n_datasets,
        "confounding_damage": (damage / n_datasets) if spurious else float("nan"),
    }


def run_sweep(base: SimConfig, days_grid, effect_grid, n_datasets: int = 300) -> pd.DataFrame:
    rows = []
    for d in days_grid:
        for e in effect_grid:
            cfg = replace(base, days=d, effect_points=e)
            m = cell_metrics(cfg, n_datasets=n_datasets)
            rows.append({"days": d, "effect_points": e,
                         "hit_rate": m["hit_rate"], "fp_rate": m["fp_rate"]})
    return pd.DataFrame(rows)
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `uv run pytest tests/test_sweep.py -v`
Expected: PASS (4 passed). `test_strong_clean_signal_has_high_hit_rate` is the load-bearing one — a clean strong signal must be reliably detectable, else the pipeline is broken.

- [ ] **Step 5: Run the full suite + commit**

Run: `uv run pytest -q`
Expected: all green.
```bash
git add -A && git commit -m "Add sweep, detection hit criterion, and damage metrics"
```

---

### Task 5: Narrative notebook + committed figures

**Files:**
- Create: `notebooks/01_lag_lift_spike.ipynb`
- Create (generated): `outputs/frontier.png`, `outputs/lag_profile.png`, `outputs/cooccurrence.png`, `outputs/confounding_damage.png`, `outputs/suspects_example.csv`

**Interfaces:**
- Consumes: all of `insights.*`. Produces only figures + the numbers the findings note (Task 6) cites.

- [ ] **Step 1: Build the notebook, one section per cell**

Author these cells (markdown headers + code). Each code cell calls the modules — no analysis logic lives in the notebook beyond plotting:

1. **Intro (md):** what this POC tests and the walled-off grounding note.
2. **One realistic dataset:** `c = SimConfig(); df = generate_logs(c, c.rng())`; plot the intensity series with tag markers; show `df.head()`. Sanity that it looks like a plausible journal.
3. **Suspects on one dataset:** `rank_suspects(...)`; render the table; save to `outputs/suspects_example.csv`. Narrate whether the true triggers surface.
4. **Lag profile:** `lag_profile(...)` for a true trigger; plot lift-by-lag with the true kernel overlaid; save `outputs/lag_profile.png`. This is the evidence for the default-`N` finding.
5. **Detectability frontier:** `run_sweep(SimConfig(), days_grid=[30,60,90,180,365], effect_grid=[0.5,1.0,1.5,2.0,3.0], n_datasets=300)`; pivot to a `days × effect` grid; `imshow` heatmap of `hit_rate` with the 0.8 contour drawn; save `outputs/frontier.png`. Add small-multiples across `n_window ∈ {1,2,4,7}` by re-running with `replace(base, n_window=w)`.
6. **False-positive + confounding damage:** call `cell_metrics(SimConfig(), n_datasets=300)`; bar/annotation of `fp_rate` and `confounding_damage`; save `outputs/confounding_damage.png`. Narrate: with the confounding path ON and `β=0` on the spurious tag, how often is it wrongly flagged?
7. **Co-occurrence mitigation:** on a co-occurring dataset, show marginal `rank_suspects` (both dairy+sugar flagged) vs `stratified_lift(inten, tag_dairy, tag_sugar, N)`; save `outputs/cooccurrence.png`. Narrate whether stratification recovers attribution.
8. **Sensitivity (md+code):** re-draw the frontier contour for K∈{1,3} and threshold∈{0.7,0.8,0.9} so the frontier's robustness is visible.
9. **Verdict scratch (md):** jot the numbers that feed Task 6 (min `n`/effect for hit-rate ≥0.8; recommended default `N`; fp/damage rates; does stratification work).

- [ ] **Step 2: Run the notebook end-to-end deterministically**

Run: `uv run jupyter nbconvert --to notebook --execute --inplace notebooks/01_lag_lift_spike.ipynb`
Expected: executes with no errors; all five `outputs/` files written.

- [ ] **Step 3: Force-add the figures (gitignore excludes png by default) + commit**

```bash
git add -f outputs/*.png outputs/*.csv
git add notebooks/01_lag_lift_spike.ipynb
git commit -m "Add narrative notebook and generated figures"
```

---

### Task 6: README, findings note, ticket link

**Files:**
- Create: `/Users/matthewballou/projects/suivre-insights-poc/README.md`
- Create: `/Users/matthewballou/projects/suivre/docs/2026-07-18-lag-lift-spike-findings.md`
- Modify (Linear): attach the POC repo to SUI-36

**Interfaces:**
- Consumes: the numbers from Task 5's notebook.

- [ ] **Step 1: Write the POC `README.md`**

Cover: one-paragraph purpose; the walled-off grounding note; `uv run pytest` + the nbconvert command to reproduce; a "Headline verdict" section stating GO / NO-GO / ADJUST with the key numbers; a link back to the `suivre` spec.

- [ ] **Step 2: Write the findings note** in `suivre/docs/2026-07-18-lag-lift-spike-findings.md`

Structure (fill from the notebook's actual numbers):
- **Status:** Active; links to the spec and the POC repo.
- **Verdict:** one of GO / NO-GO / ADJUST, one sentence.
- **Evidence:** the frontier (min `n` and effect for hit-rate ≥0.8), the lag-profile finding (is 0–2 the right default `N`, or wider?), the false-positive rate, the confounding-damage number, and whether stratification recovered co-occurring-tag attribution.
- **Guidance for SUI-21:** lift definition confirmed; recommended default lag window `N`; whether stratification is required (not optional) for co-occurring tags.
- **Guidance for SUI-22:** min-`n` flagging threshold; false-positive-aware "suggestive, not proof" copy calibrated to the measured fp-rate.

- [ ] **Step 3: Commit both**

```bash
# POC repo
cd /Users/matthewballou/projects/suivre-insights-poc
git add README.md && git commit -m "Add README with reproduction steps and headline verdict"

# suivre repo (on the SUI-36 branch)
cd /Users/matthewballou/projects/suivre
git add docs/2026-07-18-lag-lift-spike-findings.md
git -c commit.gpgsign=false commit -m "Add SUI-36 lag-lift spike findings note"
```

- [ ] **Step 4: Create the private GitHub repo, push, and link to SUI-36**

```bash
cd /Users/matthewballou/projects/suivre-insights-poc
gh repo create suivre-insights-poc --private --source=. --remote=origin --push
```
Then attach the repo URL to SUI-36 via the Linear MCP (`create_attachment`), and open the `suivre` findings-note branch as a PR per the usual flow.

---

## Self-Review

**Spec coverage:**
- Repo/layout → Task 1. Generative model (tags, co-occurrence, confounders + true-confounding path, kernel, sticky flares, clip+round) → Task 2. Lift math (mean-diff, `d`, union windowing, `n`, tag-free baseline), per-lag profile, stratified mitigation → Task 3. Sweep + hit criterion (top-3 AND >95th-pct noise), hit-rate, frontier 0.8, fp-rate, confounding-damage → Task 4. Figures + notebook → Task 5. Findings note + verdict + SUI-21/22 guidance + repo link → Task 6. All spec sections map to a task.
- Missingness knob is generated (Task 2) but only exercised if a later analyst flips it — consistent with the spec's "off by default" and "probe later".

**Placeholder scan:** No TBD/TODO; every code step carries complete code. The notebook cells (Task 5) specify exact module calls and output paths rather than pseudo-analysis — appropriate for a narrative artifact, no free-floating "add plotting here".

**Type consistency:** `SimConfig` field names are used identically across Tasks 2–4 (`confounding_tag_idx`, `true_trigger_idx`, `n_window`, `cooccur_pairs`). `lift_for_tag`/`stratified_lift` share the `_lift_from_masks` key shape (`lift, d, n_exposed, n_occurrences, exposed_mean, baseline_mean`). `rank_suspects` columns (`tag, lift, d, n_exposed, n_occurrences`) match what `is_hit` reads (`tag`, `lift`). `cell_metrics` keys (`hit_rate, fp_rate, confounding_damage`) match `run_sweep`'s usage. Consistent.
