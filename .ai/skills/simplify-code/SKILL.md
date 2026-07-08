---
name: simplify-code
description: "Use ONLY when the user explicitly asks to simplify / clean up the changed code via the dual-simplifier consensus pass (e.g. runs /simplify-code). Do NOT invoke proactively, speculatively, or as a step inside other workflows — it dispatches multiple simplifier subagents and then edits the working tree, so it is slow and token-intensive. For correctness bugs use self-review, not this."
---

# Simplify Code

⚠️ **Cost gate — do not run casually.** This skill dispatches 5 simplifier subagents (4 cleanup lenses + the Laravel simplifier) over the changed code, then applies the reconciled fixes. It is slow and token-intensive. Run it ONLY on an explicit user request to simplify / clean up the changes (or `/simplify-code`). If you reached here proactively — bundled into finishing a branch, opening a PR, or another skill's flow — STOP and ask the user to confirm before dispatching any agents.

Quality-only consensus pass over the changed code: run the four cleanup lenses and the Laravel simplifier in parallel, reconcile their findings into one consensus, then apply the agreed cleanups. This improves the quality of the changed code — it does NOT hunt for correctness bugs (use self-review for that).

## 1. Gather the diff

```
git diff @{upstream}...HEAD    # fallbacks: git diff main...HEAD  /  git diff HEAD~1
```
If the working tree has uncommitted changes, or the `...HEAD` range is empty, also run `git diff HEAD` and fold those in. If a commit range, branch, path, or PR number was passed as an argument, scope to that. This diff is the review scope for every agent.

## 2. Dispatch 5 simplifier agents (parallel, read-only)

Dispatch all five in a single message so they run concurrently. Name each `simplify: <lens>` — `simplify: reuse`, `simplify: simplification`, `simplify: efficiency`, `simplify: altitude`, `simplify: laravel`.

Every agent is **read-only**: it does NOT edit files, and (in a git worktree) does NOT run `git checkout` / `git switch`. Each returns findings as `file`, `line`, a one-line summary, the concrete cost, and the simpler form. Tell every agent which decisions are known-intentional so deliberate trade-offs aren't re-flagged.

The four cleanup lenses (dispatch as read-only Explore / general-purpose agents):
- **simplify: reuse** — new code that re-implements something the codebase already has (a helper, an Action, an enum set-helper, a scope). Grep shared/utility modules and adjacent files; name the existing thing to call instead.
- **simplify: simplification** — unnecessary complexity: redundant or derivable state, copy-paste with slight variation, deep nesting, dead code, over-verbose closures/docblocks. Name the simpler form.
- **simplify: efficiency** — wasted work: redundant computation or repeated I/O, N+1 queries, sequential independent operations, blocking work on hot paths. Name the cheaper alternative.
- **simplify: altitude** — is each change at the right depth, not a fragile bandaid? Special cases layered on shared infrastructure signal the fix isn't deep enough; prefer generalizing the underlying mechanism.

The framework pass:
- **simplify: laravel** — dispatch the `laravel:laravel-simplifier` agent in **REPORT-ONLY** mode. Instruct it explicitly: do NOT edit files (and no `git checkout`/`switch`); return a written list of proposed simplifications (`file`, `line`, the change, why) — clarity/conciseness that preserves behavior and matches this project's Laravel/Filament conventions (`.ai/guidelines`).

## 3. Synthesize the consensus

Reconcile all five into one prioritized list: raised by multiple → high-confidence; raised by one → verify against the actual code before accepting or dismissing. Dedup findings pointing at the same line/mechanism. Resolve conflicts (one lens says "extract", another "keep inline") with a judgment call, stating which won and why. Spot-check the load-bearing `file:line` citations.

## 4. Apply, then summarize

Fix each surviving consensus finding directly. Skip any finding whose fix would change intended behavior, require changes well outside the reviewed diff, or that you judge a false positive — note the skip with its reason. Re-run `herd composer check` (Pint + PHPStan level 9 + tests) after applying. Finish with a brief summary: what was fixed, what was skipped (with reasons), or a plain confirmation the code was already clean.

## Common Mistakes

- **Applying before consensus.** The five agents are read-only; edits happen ONLY after reconciling all of them. Never let a single agent's report drive an edit.
- **Hunting for bugs.** Quality-only — a correctness concern is out of scope; note it and point to self-review.
- **Letting the Laravel agent edit.** It must run report-only.
- **Re-flagging intentional decisions.** Pass the known-intentional choices to every agent.
- **Not re-checking the branch after the agents return.** Confirm `git branch --show-current` before applying.
- **Reviewing the whole repo instead of the diff.** Anchor every agent to the gathered diff.
