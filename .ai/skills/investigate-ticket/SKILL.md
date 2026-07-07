---
name: investigate-ticket
description: "Use when the user mentions a Linear issue (e.g. SUI-123), pastes a Linear URL, or asks to investigate, look at, dig into, scope, or discover a Linear ticket before implementing. Reads all ticket context (description, comments, links, sub-issues), cross-references the codebase for the probable fix, assesses validity, and presents a thorough analysis. This is deep read-only investigation — not branch setup to start work (use starting-work), and it never writes to Linear."
---

# Investigate Ticket

You are a senior engineer performing deep ticket investigation. Goal: fully understand a Linear ticket, cross-reference it against the codebase, and produce a thorough analysis the user can act on.

Arguments: $ARGUMENTS

The phase numbers below are internal scaffolding — don't surface "Phase N" labels to the user.

**Read-only.** Gather, analyze, present. Never create, update, comment on, move, or otherwise write to any Linear ticket during investigation, and don't modify code or switch git branches unless the user explicitly picks an action in the final step.

## Prerequisites

This skill needs the **Linear MCP server** (see the project-root `.mcp.json`). If the Linear tools aren't available, tell the user to authenticate it (`/mcp` → `linear` → complete the OAuth login) and retry. If still unavailable, stop.

## Phase 1: Identify the Ticket

Parse `$ARGUMENTS` for a Linear identifier:
- A bare issue key like `SUI-123` (case-insensitive — normalize to uppercase).
- A Linear URL like `https://linear.app/matthewbuiltthat/issue/SUI-123/...` — extract the `SUI-123` key.

If none is found, ask which ticket to investigate (key or URL).

## Phase 2: Gather All Ticket Context

Retrieve everything about the ticket in as few round-trips as possible, using the Linear MCP tools (e.g. `get_issue`, `list_comments`, and the issue's links/attachments). Read in parallel where possible.

- **Issue** — title, description (markdown), state, priority, labels, assignee, project, parent, sub-issues, and any linked/related issues.
- **Comments** — read every comment; they frequently hold reproduction steps and clarifications missing from the description.
- **Links & attachments** — note any URLs (designs, docs, PRs) and fetch materially relevant ones with WebFetch; screenshots/diagrams often carry load-bearing detail.

After gathering, write a brief summary of what the ticket is asking for — reproduction steps and key constraints from comments. Classify it: **bug**, **feature/enhancement**, or **verification/QA**. A ticket with no comments, links, or sub-issues is self-contained — say so and move on.

## Phase 3: Investigate the Codebase

Find the relevant code (Glob/Grep/Read). This app's layering (see `architectural-sensibility.md`): business logic lives in **invokable Actions** under `app/Services/{Domain}/Actions/`; the operator UI is **Filament 5** (`app/Filament/`); the end-user UI is **bespoke Livewire 4 / Flux** components + Blade; models in `app/Models/`.

- **Identify the affected area** — the Actions, models, Filament resources/pages, Livewire components, routes, and migrations that match the ticket's surface.
- **Trace the execution path** — follow from entry point to the described behavior. Read the actual code; don't guess. Controllers/models are thin — the logic usually sits in an Action, an enum, an observer/event, or a Filament/Livewire class.
- **Find the root cause** (bug), the **insertion point** (feature), or check each stated requirement against the code (verification/QA), specific to `file:line`.
- **Assess blast radius** — other callers of the changed method/class, tests covering it, related UI surfaces (Filament + Livewire can both expose the same data), and schema implications. `Model::shouldBeStrict()` is on, so watch relationship access in accessors.

## Phase 4: Present the Analysis

Output four sections.

**Ticket Summary** — concise restatement in your own words.

**Validity Assessment** — is it actually a bug or working as designed? Feasible within the current architecture? Contradictions, ambiguities, or missing info? If gaps exist, state what's unclear and what to ask the reporter. If a load-bearing artifact was unreadable, mark the assessment blocked on that input.

**Probable Fix** —
- **Files to change** — exact paths.
- **What the change is** — concrete ("add a null check on `$service->config` before `->link` in `OnboardingController@show:42`", not "update the controller").
- **Why this is the right fix** — root cause and why it addresses it rather than masking it.
- **Why this path is least risky** — alternatives considered and why this minimizes blast radius.
- **Tests to write/update** — specific happy-path, failure-path, and edge cases (Pest, mirroring source paths).

**Risk Assessment** — what could go wrong, what existing functionality might be affected, edge cases to watch.

## Phase 5: Offer Next Steps

Under a `## Next Steps` heading, present options and let the user pick:
- Open the ticket in a browser (print the Linear URL).
- Add a clarifying comment to the ticket (Linear MCP `save_comment`) — only if the user chooses it.
- Hand off to **starting-work** (set up a branch/worktree to begin), **superpowers:brainstorming** (design the fix), or **superpowers:writing-plans** (implementation plan) — pass the ticket key + one-line summary so the next step doesn't re-fetch.
- Stop here.

## Tone

Direct and specific — exact file paths, line numbers, method names. If you've read the code, state what it does rather than hedging. If genuinely uncertain, say so and explain what would resolve it.
