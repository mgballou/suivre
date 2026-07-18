---
name: create-pr
description: "Use ONLY when the user explicitly asks to open a pull request — \"create the PR\", \"open a PR for this\", \"write the PR description\". Opens a draft PR whose body fills .github/PULL_REQUEST_TEMPLATE.md with the Linear ticket link and a Technical-description written in the global CLAUDE.md `Verb X preposition Y` bullet style. Resolves the ticket from the branch name via the Linear MCP. Never invoke unsolicited or as a side effect of finishing other work."
---

# Create a PR

**Never open a PR unless the user explicitly asks.** When they do, create it as a **draft** unless they
say ready-for-review (CI still runs on drafts here). One PR per Linear ticket; do not merge to `main`
locally.

Run all PHP/Composer/Artisan through **Herd** (`herd php`, `herd composer`). **Requires** an authenticated
`gh` CLI and the Linear MCP.

## When to Use

- The user says "create the PR", "open a PR", "write the PR description", or similar, on a branch with
  committed work.

Do NOT use when: applying review feedback (**addressing-pr-feedback**), reviewing your own diff
(**self-review**), or merely committing.

## Flow

### 1. Resolve the ticket (required)

Suivre branches follow Linear's convention `matthewbuiltthat/sui-<id>-<slug>` (from **starting-work**).

```bash
git branch --show-current
```

Extract the `sui-<id>` segment and fetch the issue via the Linear MCP `get_issue` (`SUI-<id>`). Read its
`title` (the PR title text) and `url` (the Ticket link).

**If you cannot infer a `SUI-<id>`, stop and ask the user for the ticket.** Every PR is tracked by a Linear
issue; a ticket-less PR is a rare exception the user must approve explicitly — never invent a title to work
around a missing ticket.

### 2. Confirm the branch is pushable

```bash
git status            # clean tree; nothing uncommitted you meant to include
git log --oneline main..HEAD   # the commits this PR will carry
```

Diff the branch against `main`, not the iterative back-and-forth — only the start-vs-end contrast matters.

### 3. Build the PR

- **Title:** the Linear ticket `title`, used essentially verbatim (Suivre PR titles are plain and
  descriptive — no `SUI-x` prefix; the ticket link in the body carries the association, and Linear
  auto-links via the branch name). Lightly tighten only if the ticket title is awkward as a PR title.
- **Body:** fill **`.github/PULL_REQUEST_TEMPLATE.md`** — keep every section and its order:
  - **Ticket** — the Linear URL (`https://linear.app/matthewbuiltthat/issue/SUI-<id>`).
  - **Technical description** — the section that needs writing well. Follow the global `CLAUDE.md` bullet
    style: `Verb X preposition Y (optionally) clause Z`. Aim for 5–8 bullets (1–2 for a tiny PR); one
    bullet per cohesive idea, not per file/method; group related changes; call out non-obvious decisions
    or trade-offs a reviewer can't get from the diff. Do **not** restate the diff file-by-file.
  - **Types of changes** — check the boxes that apply (`[x]`).
  - **Screenshots** — for a UI change, offer **capture-screenshots** to generate and embed them; otherwise
    leave empty.
  - **Deployment steps** — check `None` unless there genuinely is a command to run or an ENV key to add.

### 4. Open it

1. Push the branch to `origin` first (confirm before any force-push):
   ```bash
   git push -u origin <branch>
   ```
2. Write the filled template to a temp file and create the PR with `--body-file` (never inline `--body` —
   the shell mangles the multi-line template's backticks and checkboxes):
   ```bash
   gh pr create --draft --base main --title "<ticket title>" --body-file <tmp>
   ```
   Drop `--draft` only when the user explicitly asked for ready-for-review.
3. Print the PR URL.

Commit messages never carry AI attribution (global `CLAUDE.md`). Do not move the Linear ticket or attach
the PR here unless asked — that's the status-update workflow's job.

## Quick Reference

| Action | Command |
|---|---|
| Current branch | `git branch --show-current` |
| Ticket → issue | Linear MCP `get_issue` with `SUI-<id>` |
| Commits in PR | `git log --oneline main..HEAD` |
| Push branch | `git push -u origin <branch>` |
| Open draft PR | `gh pr create --draft --base main --title "…" --body-file <tmp>` |

## Common Mistakes

- **Opening a PR unprompted.** This skill runs only on an explicit request.
- **Inline `--body`.** Backticks and `- [ ]` checkboxes get mangled; always `--body-file`.
- **Restating the diff.** The Technical description is the *why* and the non-obvious *what*, not a file
  inventory.
- **Guessing a title with no ticket.** Stop and ask; every PR maps to a Linear issue.
- **Adding a `SUI-x` title prefix.** Suivre titles are plain; the body link + branch name carry the link.
- **Merging to `main` locally.** Open the PR and hand off.
