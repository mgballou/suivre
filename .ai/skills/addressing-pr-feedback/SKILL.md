---
name: addressing-pr-feedback
description: "Use when review feedback needs picking up on an existing pull request — phrasings like \"feedback has come in on this PR\", \"apply the review comments\", \"address the feedback on SUI-12\", \"see what's on PR 5\", or a bare ticket id, PR number, or PR URL offered as the target. Covers unresolved inline review threads, review summaries, and conversation comments on a branch that has not yet merged. This is responding to a human's review of existing work — not generating a fresh review of your own diff (use self-review), and not setting up a branch to begin work (use starting-work)."
---

# Addressing PR Feedback

## Overview

Pick up review comments on an open PR, work each one to a resolution, and reply in-thread saying what
happened. Feedback on an unmerged branch is **pre-deployment**: edit the original migration/file in
place, no follow-up migration, no compatibility shims (see the root `CLAUDE.md`).

**Not every comment is a change request.** A question wants an answer. A suggestion may warrant a
reasoned decline. Only defects want a diff. Deciding which is the work.

Run all PHP/Composer/Artisan through **Herd** (`herd php`, `herd composer`).

## When to Use

- A human has left comments on an open PR and wants them addressed.
- The user names a ticket (`SUI-3`), a PR number (`#5`), or pastes a PR URL, and asks for the feedback.

Do NOT use when: reviewing your own uncommitted diff (**self-review**), starting a new ticket
(**starting-work**), or the PR is already merged.

## Flow

### 1. Resolve the target to a PR number

Given a PR number or URL, use it directly. Given a Linear ticket id, match on the **branch name** —
never `gh pr list --search`, which also matches the ticket id where it appears in a PR *body* and
happily returns the wrong PR:

```bash
REPO=$(gh repo view --json nameWithOwner -q .nameWithOwner)
gh pr list --state all --json number,headRefName,state \
  --jq '.[] | select(.headRefName | test("(^|/)[a-z-]*sui-3(-|$)")) | "\(.number) \(.state) \(.headRefName)"'
```

Confirm the PR (number, title, branch) with the user before touching anything.

### 2. Work in the branch's checkout

The branch usually has a worktree from **starting-work**. Find it with `bin/worktree-list`; if one
exists, every later command, edit, and spawned agent uses that path as cwd. Otherwise check the
branch out. Confirm a clean tree, and `git pull` so you are not editing a stale head.

### 3. Gather feedback from all three sources

`gh pr view --json comments` **does not return inline review comments.** On a PR whose only feedback
is an inline comment it returns the bot linkback and an empty review stub — it will look like there
is no feedback. Query each source:

```bash
# a. Inline review threads, with resolution state — the source that matters most.
gh api graphql -f query='
query($owner:String!, $repo:String!, $pr:Int!) {
  repository(owner:$owner, name:$repo) { pullRequest(number:$pr) {
    reviewThreads(first:50) { nodes { isResolved isOutdated
      comments(first:10) { nodes { databaseId path line body author { login } } } } } } }
}' -f owner=OWNER -f repo=REPO -F pr=N

# b. Review summary bodies. Empty bodies are noise from an inline-only review — skip them.
gh api repos/OWNER/REPO/pulls/N/reviews --jq '.[] | select(.body != "") | {user: .user.login, state, body}'

# c. Conversation comments. Filter bots (Linear linkback, CI).
gh api repos/OWNER/REPO/issues/N/comments --jq '.[] | select(.user.type != "Bot") | {user: .user.login, body}'
```

Skip threads where `isResolved` is true. Surface `isOutdated` threads and threads that already carry
a reply, rather than silently reworking them.

### 4. Triage each thread before touching code

| Comment is | Resolution |
|---|---|
| A defect or bug | Fix it. Behavior change ⇒ **superpowers:test-driven-development** — failing test first. |
| A question | Investigate, answer with evidence. Often **no code change**. |
| A suggestion you accept | Apply it. |
| A suggestion you decline | Reply with the reasoning. Declining is a valid outcome. |
| Ambiguous | Ask the user. Do not guess. |

Never edit code merely to look responsive to a question. If verifying the answer needs an
experiment, run it — change the code, run the gate, then revert and confirm `git diff` is empty.

### 5. Verify

`herd composer check` (Pint + PHPStan level 9 + Pest) must be green before you reply. Quote the real
counts. If nothing changed, say so.

### 6. Reply in each thread

```bash
gh api repos/OWNER/REPO/pulls/N/comments/COMMENT_ID/replies -f body="..."
```

State what you did, the evidence, and — when nothing changed — say `No code change.` and why.

**Do not resolve threads.** The reviewer resolves their own.

### 7. Hand off

Report per-thread outcomes. **Commit and push only when the user asks** (per the global `CLAUDE.md`,
no AI attribution in commit messages). A run that ends with an empty diff is a normal, successful
outcome — not a failure to be papered over with a cosmetic edit.

## Quick Reference

| Action | Command |
|---|---|
| Resolve repo slug | `gh repo view --json nameWithOwner -q .nameWithOwner` |
| Ticket → PR | `gh pr list --state all --json number,headRefName --jq '...test("sui-N(-\|$)")...'` |
| Inline threads + resolution | `gh api graphql` (query above) |
| Inline comments (flat) | `gh api repos/O/R/pulls/N/comments` |
| Review summaries | `gh api repos/O/R/pulls/N/reviews` |
| Conversation comments | `gh api repos/O/R/issues/N/comments` |
| Reply in thread | `gh api repos/O/R/pulls/N/comments/ID/replies -f body=...` |
| Find the worktree | `bin/worktree-list` |
| Quality gate | `herd composer check` |

## Common Mistakes

- **Trusting `gh pr view --json comments`.** It omits inline review comments — the usual place
  feedback lives. Reporting "no feedback found" from it is a false negative.
- **`gh pr list --search sui-3`.** Fuzzy-matches PR bodies; returns other PRs that merely mention the
  ticket. Match on `headRefName`.
- **Treating every comment as a change request.** A question answered with a needless refactor loses
  information and wastes review cycles.
- **Reading bot noise as feedback.** Linear's linkback comment and empty `COMMENTED` review stubs are
  not review comments.
- **Adding a follow-up migration.** The branch is unmerged; edit the original migration in place.
- **Editing the main checkout** when the branch lives in a worktree.
- **Resolving the thread yourself**, or replying before the gate is green.
