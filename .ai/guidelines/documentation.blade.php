# Documentation & decision hygiene

Suivre's recurring failure mode is **stale instructions**. A decision lands in the decision-log, but
the docs agents actually load each session — the woven `.ai/guidelines/*` → `CLAUDE.md` — keep
saying the old thing. Under an agent-driven workflow that stale line is then acted on confidently at
scale (e.g. building the `pg_trgm` abstraction D18 forbids, because a note still said tests run on
sqlite). Keeping the docs in sync is part of every change's **definition of done**, not a later cleanup.

Durable, always-loaded instructions to agents live in **`.ai/guidelines/*`** (woven into
`CLAUDE.md` / `AGENTS.md` by Boost) — never in `.claude/`, which holds machine-local settings and
Boost-generated symlinks, not authored guidance.

## Source-of-truth hierarchy

| Layer | Role | Drift rule |
|---|---|---|
| `.ai/guidelines/*` → woven `CLAUDE.md`/`AGENTS.md` | **The live authority.** Loaded every session; agents act on it directly. | Must contain **zero** statements a later decision contradicts. This is the layer that must never drift. |
| `docs/decisions/decision-log.md` | The **reasoning record** — each decision, why, and what it rules out. Newest appended at the bottom. | Append-only; never rewritten. Records what was true when written; newer entries win. |
| `docs/roadmap.md` | Current epic / build plan — what we build next. | Kept current: a stale stack, scope, or dependency line here misleads the next ticket. |
| `docs/superpowers/specs/*`, `docs/superpowers/plans/*` | **Dated, point-in-time artifacts.** | Not edited to stay current. Each carries a **Status banner** (below); when superseded, the banner points forward and the body is left as the historical record. |
| Linear label / project / ticket descriptions | Cross-references agents read when picking up work. | Swept alongside the docs when a decision invalidates them. |

The **real files in the repo** are the source of truth for configuration (`pint.json`, `phpstan.neon`,
`composer.json`, CI workflows). Never restate their contents in prose — a copy only drifts (D16).

## Authoring guidelines & skills — edit the source in `.ai/`, never the generated `.claude/` copy

Both the woven guidelines and the skills have a **source** in `.ai/` and a Boost-generated presence
under `.claude/`. Always author in `.ai/`; the `.claude/` side is output.

- **Guidelines** — author in `.ai/guidelines/*.blade.php`. Guideline files are **Blade** templates,
  even when the body is plain Markdown (the `.blade.php` extension is the convention — do not add
  `.md` guidelines). The `<laravel-boost-guidelines>` block in `CLAUDE.md` / `AGENTS.md` is
  **generated**; never hand-edit it — Boost's `GuidelineWriter` rewrites the block and drops stray
  edits (D14). After editing a blade, run `herd php artisan boost:update` and commit the regenerated
  `CLAUDE.md`.
- **Skills** — the source of truth is **`.ai/skills/<name>/`**; `.claude/skills/<name>` is a
  committed **symlink** into it. When you add a skill — or want to keep or modify one, **including a
  stock framework skill `boost:update` just pulled in** (those land as a real dir under
  `.claude/skills/`) — put its source in `.ai/skills/<name>/` and make `.claude/skills/<name>` a
  symlink: `ln -s ../../.ai/skills/<name> .claude/skills/<name>`. `boost:update` preserves the
  symlink. **Never commit an authored skill as a real directory under `.claude/skills/`** — that copy
  is not the source and won't survive a rebuild.

Rule of thumb: if a change must persist across `boost:update`, it belongs in `.ai/`. `.claude/` holds
generated output, symlinks, and machine-local settings — nothing authored.

## When a decision lands — the sweep

A decision is not done when it is written in the decision-log. In the **same PR** that enacts it,
sweep everything it invalidates. The decision-log entry's **"Rules out"** line is the checklist:

1. **`.ai/guidelines/*`** — correct every statement the decision changes, then run
   `herd php artisan boost:update` so the committed `CLAUDE.md` matches the blades. Hand-editing
   `CLAUDE.md` instead drifts it from its source and is overwritten on the next regenerate.
2. **`docs/roadmap.md`** — fix any epic whose stack, scope, or dependency changed.
3. **Spec / plan Status banners** — mark any spec or plan the decision supersedes (banner, not rewrite).
4. **Linear** — update the label / project / ticket descriptions the decision now makes wrong.

## Spec & plan Status banner

Every file under `docs/superpowers/specs/` and `docs/superpowers/plans/` opens with a status line so a
reader orients in one glance:

- `- **Status:** Active` — current, not yet superseded.
- `- **Status:** Superseded on <dimension> by <D-number / newer doc>` — e.g.
  `Superseded on stack (D19) & test DB (D18); see docs/…-design-system….md`. The body below is the
  historical record; do not act on the superseded dimensions.

A spec cited under **Authoritative references** in the `.ai/suivre` rules must either be **Active** or
carry a banner scoping exactly which parts still apply.

## Drift smell test — run before opening any PR

- Does any woven guideline sentence contradict the newest decisions? Fix the guideline.
- Did this change settle something a spec / plan / roadmap / Linear label still states the old way?
  Banner or fix it.
- Did you touch `.ai/guidelines/*` without re-running `boost:update`? The committed `CLAUDE.md` is stale.
