---
name: triage-backlog
description: "Use when reviewing, grooming, or triaging the Linear board rather than working a single ticket — phrasings like \"what should I pick up next\", \"triage the backlog\", \"review the tickets\", \"is the board accurate\", \"what's actually unblocked\", or at the start of a session when choosing work. Recomputes which tickets are genuinely unblocked, promotes them from Backlog to Todo, repairs the blocks/blockedBy graph, refines stubs, and files gaps. This is board-level project management — not deep analysis of one ticket (use investigate-ticket), and not branch setup to begin coding (use starting-work)."
---

# Triage Backlog

## Overview

Keeps the Linear board honest so that "what do I pick up next" is answerable in one glance instead of re-derived by reading twenty tickets. The board earns its keep only if **Todo means pickable** — otherwise every session starts by rebuilding the dependency graph from ticket prose.

Run this at the start of a session when choosing work, after a batch of tickets closes, or after a spike or decision that reshapes the plan.

## The contract this skill enforces

| Status | Meaning |
|---|---|
| **Todo** (`unstarted`) | **Pickable right now.** Every blocker is Done/Canceled. An agent could take it end-to-end without waiting on anything. |
| **Backlog** | Real work, but something must land first. Its `blockedBy` edges say what. |
| In Progress / In Review | Being worked. Untouched by triage. |

Two invariants follow, and triage exists to restore them:

1. **Nothing in Backlog is unblocked.** An unblocked ticket sitting in Backlog is invisible work.
2. **Nothing in Todo is blocked.** A blocked ticket in Todo is a trap — an agent picks it up and stalls.

**Priority orders within a status; it does not gate pickup.** Todo may hold several tickets; priority says which to take first. Do not keep a pickable ticket in Backlog merely because it is low priority — that is what `Low` is for.

| Priority | Use for |
|---|---|
| Urgent (1) | On the critical path — the whole plan waits on it. |
| High (2) | Next real value; head of an epic; unblocks several things. |
| Medium (3) | Genuine work, not urgent. |
| Low (4) | Polish, deferred, or human-gated (needs credentials, a purchase, a device). |

## Procedure

### 1. Load the board

Fetch all issues for the team in one call. `list_issues` output is large — if it spills to a file, parse it with a short `python3 -c` rather than reading it back in full.

Then fetch `get_issue` with `includeRelations: true` for every **open** ticket. Relations are the whole point and are not in the list payload.

Also load `docs/roadmap.md` and the project milestones so epic sequencing is in view.

### 2. Build the graph and compute the unblocked set

A ticket is **unblocked** when every `blockedBy` edge points at something Done, Canceled, or Duplicate.

Do not trust the edges alone — read the ticket's Dependencies prose too. Tickets are frequently written with "blockedBy SUI-x" in the body while the actual Linear relation was never created. **The prose is the intent; the relation is what tooling can see.** Reconcile toward the relation.

### 3. Repair the graph

This is usually where the real value is. Look for:

- **Missing edges** — whole epics with zero relations. Later epics (insights, backstage, PWA) are typically filed as stubs and never wired up.
- **Edges implied by prose** but never created.
- **Over-constraint** — do not add an edge that merely reflects preferred order. `blockedBy` means *cannot be built*, not *would rather do later*. Rely on transitivity: if A blocks B and B blocks C, do not also add A blocks C.
- **`relatedTo` for real coupling** that is not a blocker — shared surface, a decision one informs, a superseded sibling.

Prefer stating dependencies as `blocks` from the upstream ticket — it reads naturally and Linear mirrors the inverse automatically.

### 4. Promote and demote

Move every unblocked Backlog ticket to **Todo**; move any blocked Todo ticket back to **Backlog**. Set priority across the whole open set so both lists are ordered, not just the active one.

### 5. Refine what triage exposes

Triage legitimately surfaces work needing more than a status change. While the graph is in view:

- **Stubs** — tickets saying "refine on pickup" are debt. An agent that picks one up starts by guessing. Write real Goal / Scope / Dependencies / Acceptance criteria / Tests, grounded in the decision-log, the roadmap, and the actual code.
- **Findings not yet reflected** — if a spike or decision landed, its conclusions must reach the tickets it reshapes. A findings doc nobody's tickets cite has not landed.
- **Gaps** — a conclusion that implies work with no ticket. File it.
- **Doc drift** — per the `documentation` rules, sweep Linear label/project descriptions a decision has invalidated. The Linear MCP **cannot update an existing label's description**; surface those for the human to fix in the UI.

### 6. Report

Summarize: what became pickable, what edges were added, what was refined, what was filed. Then raise decisions — batched at the end, never interrupting the sweep.

## Conventions

- Ticket bodies are **notes, not chapters** — dense and skimmable. Scope and acceptance criteria earn their length; preamble does not.
- Agent-authored Linear content **says so** (`*Refined by Claude Code during backlog triage*`). Commits never carry AI attribution.
- Reference decisions by D-number and specs by path so a ticket stays traceable.
- Convert relative dates to absolute.

## Judgment calls

**Take without asking:** status promotion/demotion, priority, relations, stub refinement, filing a ticket for a clear gap, correcting stale stack references.

**Batch for the human at the end:** cancelling or merging a ticket, changing agreed scope or sequencing, anything implying a new D-number, and open product questions a refined ticket surfaced.

**Never:** delete tickets, or silently rewrite a ticket's intent while claiming to have refined it.

## Anti-patterns

- Treating Todo as a WIP limit — it is a *pickability* signal, not a commitment. Priority handles ordering.
- Adding `blockedBy` for preferred sequencing, which fossilizes an ordering nobody agreed to.
- Refining a stub by restating its title in more words. If you cannot ground Scope in the decision-log, roadmap, or code, say so rather than inventing plausible detail.
- Interrupting mid-sweep with questions. Finish, then ask once.
