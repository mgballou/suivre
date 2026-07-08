---
name: starting-work
description: "Use when beginning a new Linear ticket, feature, or task and you need to set up to write code — phrasings like \"starting on SUI-…\", \"kick off this ticket\", \"get me a branch going\", or \"set me up\". Creates a branch off up-to-date main using Linear's suggested branch name, by default in an isolated git worktree (its own Postgres database and Herd URL) via bin/worktree-create. This is environment/branch setup to begin work — not deep ticket analysis (use investigate-ticket), and not for work already in progress on an existing branch."
---

# Starting Work

## Overview

Standardizes how new work begins: never start on top of uncommitted changes or a stale base, always create a branch using **Linear's suggested branch name** (which auto-links it to the issue), and — by default — set up an isolated worktree so parallel work never collides. Worktrees get their own Postgres database and `<ticket>.suivre.test` Herd URL, so the main checkout is never touched.

Run all PHP/Composer/Artisan through **Herd** (`herd php`, `herd composer`).

## When to Use

- Starting a new Linear ticket, feature, or piece of work.
- The user references a Linear ticket (e.g. `SUI-123`) and intends to begin.
- Before creating a branch or editing code for new work.

Do NOT use when: continuing on an existing branch/worktree, only reading/exploring code, or the user is working directly on main.

## Worktree by default — your call when not

Create an isolated worktree via `bin/worktree-create` unless there's a reason not to. Use a worktree when the work will run the app, touch the database, or run in parallel with other branches (most real work). Skip it and just create a branch (step 5b) when the change is small and self-contained (docs, config, a one-line fix) and no isolated database or URL is needed. When unsure, prefer the worktree. Confirm the choice with the user before creating anything.

## Flow

### 1. Pre-flight — guard the starting point

Run `git status` and `git branch --show-current`.
- If there are uncommitted or untracked changes, surface them and confirm before proceeding.
- If the current branch isn't the base you'd branch from (normally `main`), point it out and confirm.

### 2. Sync the base branch

```
git fetch origin
git -C <main checkout> switch main && git -C <main checkout> pull --ff-only
```
If `main` can't fast-forward, stop and surface it rather than forcing. (No remote yet? Skip the pull — just branch off local `main`.)

### 3. Identify the work

Ask for a Linear ticket id/URL (preferred) or, if there's no ticket, a short slug (e.g. `calendar-shell`).

If a ticket is given and the Linear MCP is available, fetch a brief summary (read-only) and report 2–3 lines: title, state, one-line intent. Don't do deep investigation here (that's **investigate-ticket**), and never write to Linear.

### 4. Derive the branch name (Linear convention)

- With a ticket: **use Linear's suggested branch name** — read `gitBranchName` from the issue via the Linear MCP `get_issue` (e.g. `matthewbuiltthat/sui-12-add-daily-checkin`). This is what Linear auto-links to the issue.
- Without a ticket: a plain `<slug>`.

The worktree directory (a flat sibling named after the branch's last segment, e.g. `sui-12-add-daily-checkin`), database (`suivre_sui_<n>`), and URL (`sui-<n>.suivre.test`) are all derived from the `sui-<n>` identifier in the branch — so keep that identifier intact. Show the final branch name and confirm before creating anything.

### 5. Create the worktree (default)

Ensure Herd's services (incl. Postgres) are running (`herd start`). Then run from the main checkout:

```
bin/worktree-create <branch>
```

The script is self-contained and handles everything:
- Sibling worktree directory `../<branch>/`, branched off `main` (or checked out if the branch exists).
- Isolated Postgres database `suivre_<ticket>` (created via `herd php` + PDO) and `.env` rewrite (`APP_URL`, `DB_DATABASE`).
- `herd composer install`, `npm ci`, `npm run build`.
- `herd php artisan migrate:fresh --seed --force` and `storage:link`.
- `herd link --secure` on the resolved PHP version, serving `https://<ticket>.suivre.test`.

Do NOT re-run migrations, seeders, composer install, npm ci, or npm run build afterward — they already ran, and re-running `migrate:fresh` wipes the seed data.

### 5b. No-worktree path

If a worktree isn't warranted, create just the branch on the main checkout:

```
git -C <main checkout> switch --create <branch>
```

### 6. Orient (light) and hand off

After a worktree is created, surface and hand off:
- **Worktree path** — `../<branch>/` (compute and show the absolute path).
- **URL** — `https://<ticket>.suivre.test`.

**Critical:** every subsequent Bash, Edit, Read, and spawned agent must use the worktree path as the working directory — not the main checkout. The skill ends but the context carries forward; default to the worktree path for the rest of the session.

Give a 2–3 line summary of what's being started. For a full investigation, offer **investigate-ticket** — don't do deep analysis here.

## Quick Reference

| Action | Command |
|---|---|
| Check working tree | `git status` |
| Update base | `git fetch origin && git switch main && git pull --ff-only` |
| Create worktree | `bin/worktree-create <branch>` |
| List worktrees | `bin/worktree-list` |
| Remove worktree | `bin/worktree-remove <branch> [--force]` |
| Branch only (no worktree) | `git switch --create <branch>` |
| Worktree path | `../<branch>/` |
| Worktree URL | `https://<ticket>.suivre.test` |

## Common Mistakes

- **Re-running bootstrap after the create script.** It already ran composer, npm, build, migrate, and seed. Re-running wastes time and `migrate:fresh` blows away the seed data.
- **Operating on the main checkout after creating a worktree.** Subsequent tool calls — especially spawned agents — must use the worktree path as cwd.
- **Branching off a stale or dirty base.** Sync main and confirm a clean tree first.
- **Not using Linear's branch name.** With a ticket, use its `gitBranchName` (contains `sui-<n>`) so the script can derive the DB/URL and Linear auto-links the branch to the issue.
- **Herd not running.** The script creates the Postgres DB via `herd php`; ensure `herd start` first.
- **Doing deep ticket investigation inline.** That's investigate-ticket's job; keep this skill to setup.
- **Writing to Linear.** Reads only — never create, comment, assign, or change status during setup.
