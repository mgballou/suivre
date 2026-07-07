---
name: self-review
description: "Use ONLY when the user explicitly asks for a full self-review / code review of the current branch (e.g. runs /self-review). Do NOT invoke proactively, speculatively, or as a step inside other workflows — it dispatches multiple review subagents and is slow and token-intensive."
---

# Self Review

⚠️ **Cost gate — do not run casually.** This skill dispatches several review subagents (2 general + 4 targeted + 1 claims) over the full branch diff; it is slow and token-intensive. Run it ONLY on an explicit user request to self-review / review the branch (or `/self-review`). If you reached here proactively — as a "good idea", or bundled into finishing a branch / opening a PR / another skill's flow — STOP and ask the user to confirm before dispatching any agents.

Multi-agent review of the current branch's diff: run a broad consensus pass plus focused single-concern passes, and reconcile everything into prioritized, actionable findings with fix proposals and trade-offs.

## 1. Determine the review mechanism

Use whatever review capability the active toolset provides — reference its review skill/command, not a hardcoded agent name (names change between versions).

- **superpowers** in use → run each review through its `requesting-code-review` flow (dispatch a general-purpose agent filled with the `requesting-code-review` reviewer template). Do NOT dispatch a named `code-reviewer` agent that may not exist in the installed version.
- A **feature-dev** review command / `code-reviewer` agent present → use it.
- If several are available and it's ambiguous, list the candidates and ask which to use.

All agents below are dispatched through the one chosen mechanism.

## 2. Establish the diff and the claims source

Determine the base (the branch this split from / the PR base) and head (current HEAD). Every agent reviews this same diff range. Tell each agent which decisions are known-intentional so they aren't re-flagged.

Establish where the work's behavioral claims live, for the claims agent (step 5), in priority order:
1. The PR description, if one exists.
2. Committed spec/plan docs (`docs/superpowers/specs/`, `docs/superpowers/plans/`) and the decision log (`docs/decisions/decision-log.md`).
3. Design-bearing comments and docblocks in the diff.
4. Claims known from the session — write them out explicitly for the agent.

If none exist, draft the PR description first and audit against the draft.

## 3. Two general review agents (consensus)

Dispatch two independent general review agents in parallel over the full diff. Each reviews broadly (correctness, conventions, performance, security, maintainability) and returns Strengths / Issues (severity + `file:line`) / Assessment.

Reconcile: raised by both → consensus, likely-real; raised by one → verify against the actual code before accepting or dismissing. Produce one reconciled list.

Dispatch naming (all agents, steps 3–5): `review: <lens>` — `review: general A`, `review: general B`, `review: correctness`, `review: conventions`, `review: performance`, `review: security`, `review: claims`.

## 4. Four targeted agents (one concern each)

Dispatch four independent agents in parallel, each focused on exactly ONE area over the same diff (the bullets are starting points, not exhaustive checklists):

- **Correctness** — logic bugs, edge cases, null-safety, data integrity; do the tests actually verify behavior?
- **Conventions** — adherence to `architectural-sensibility.md`, this project's `CLAUDE.md` / `.ai/guidelines`, naming, structure, idioms (Actions carry logic, enums as domain primitives, policies return `Response`, strict Eloquent, PHPStan level 9).
- **Performance** — N+1 queries, redundant work, missing indexes/eager-loads, lazy-loading violations under strict mode, hot paths.
- **Security** — authorization/policy scoping, per-user data isolation, injection, mass-assignment, data exposure.

## 5. One claims agent (adversarial, in parallel)

Dispatch one agent whose ONLY job is auditing claims against implementation. Give it the claims source from step 2 and instruct it to:
- Extract every behavioral claim (prose promises like "exception-safe", "idempotent", "no extra queries"; design-bearing code comments; captions).
- Audit implicit contracts: for each new public API in the diff, enumerate what a reasonable caller assumes from its name/shape and verdict those assumptions like written claims.
- For each claim, verdict it: point to the code that delivers it AND the test that proves it, or mark the gap (delivered/untested, not delivered, contradicted).
- Output a claims × verdict table — every claim and implicit-contract assumption gets a row; "couldn't verify" is a verdict, not an omission.

## 6. Synthesize

Merge the general consensus, the four targeted passes, and the claims table into one prioritized report. Claims verdicted *not delivered* / *contradicted* are findings by impact; *delivered, untested* are test-coverage gaps. For each real finding: severity, `file:line`, why it matters, a concrete fix proposal, trade-offs. Drop duplicates and known-intentional decisions. Keep it constructive and actionable.

## Common Mistakes

- Treating an agent's keyword starting-points as covered checkboxes — phrase hazards as behavior questions ("what happens IF nested?").
- Trusting reviewer `file:line` citations without spot-checking the load-bearing ones.
- Presenting an interpretation as a quote — inferred promises are labeled implicit.
- Treating one run's findings as a superset of another's — review sampling is stochastic; union independent runs.
- Adding steps that duplicate CI (formatting/lint/static analysis) — `herd composer check` and CI already verify those.
