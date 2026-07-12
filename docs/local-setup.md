# Local Setup

How to bring a fresh checkout of Suivre up on macOS. Everything runs through
**Herd** — never bare `php` / `composer`.

## Prerequisites

- **[Laravel Herd](https://herd.laravel.com/) (Pro)** — provides PHP 8.4, and manages
  local services (Postgres, Redis, …). Pro is required for the `services:*` commands used below.
- **[GitHub CLI](https://cli.github.com/)** (`gh`) — repo access and PR flow. Run `gh auth login` once.
- **Node** (for the Vite/Tailwind front-end build). Herd bundles a Node; `herd isolate-node` pins a version per site if needed.

> **macOS gotcha:** the project lives under `~/Documents`, which macOS protects with
> TCC. If tools suddenly fail with `Operation not permitted` (git `getcwd`, file reads),
> grant your terminal (e.g. iTerm) **Full Disk Access** in
> *System Settings → Privacy & Security*, then fully quit and reopen it.

## 1. Install dependencies

```bash
herd composer install
cp .env.example .env      # if .env does not exist
herd php artisan key:generate
npm install
```

## 2. Postgres (via Herd services)

Suivre uses **PostgreSQL** (chosen for `pg_trgm` fuzzy matching in the food classifier).
Herd does **not** create a Postgres service by default — you must add one. `psql` is not on
the PATH; databases are managed in **TablePlus** or via `herd php` + PDO.

```bash
# One-time: create + start a Postgres 18 service on the default port 5432
herd services:create postgresql --service-version=18 --no-interaction
herd services:list          # confirm Status = running
```

Herd's default superuser is **`root`** with an **empty password** — matching `.env`:

```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=suivre
DB_USERNAME=root
DB_PASSWORD=
```

Create the `suivre` database (Postgres has no `CREATE DATABASE IF NOT EXISTS`, and `psql`
is not on PATH, so create it via PDO against the maintenance `postgres` db — or just make it
in TablePlus):

```bash
herd php -r '$p=new PDO("pgsql:host=127.0.0.1;port=5432;dbname=postgres","root","");
$p->exec("CREATE DATABASE suivre");'
```

Run migrations:

```bash
herd php artisan migrate
```

The `pg_trgm` extension ships with Herd's Postgres (migrations that need it should
`CREATE EXTENSION IF NOT EXISTS pg_trgm`).

### The test database

The Pest suite runs against Postgres, not sqlite — `phpunit.xml` pins it to a dedicated
`suivre_test` database so a test run can never touch your dev data. Create it once:

```bash
herd php -r '$p=new PDO("pgsql:host=127.0.0.1;port=5432;dbname=postgres","root","");
$p->exec("CREATE DATABASE suivre_test");'
```

`RefreshDatabase` migrates it on first run and wraps each test in a transaction thereafter.
Worktrees share this one database, so don't run two suites concurrently.

## 3. MCP servers

`.mcp.json` registers three servers:

- **laravel-boost** — Laravel-aware docs/schema/query tools. No setup; runs via artisan.
- **linear** — task tracking (HTTP transport). On first use, approve the OAuth prompt to
  authenticate against the `matthewbuiltthat` workspace (team **SUI**).
- **code-review-graph** — Tree-sitter knowledge graph for token-efficient reviews.
  Requires the CLI to be installed and the graph built locally:

  ```bash
  # install the code-review-graph CLI (see its own README), then, from the repo root:
  code-review-graph build      # one-time full build
  code-review-graph status     # verify node/edge counts
  ```

  A `SessionStart` hook runs `status` and a `PostToolUse` hook keeps the graph updated on
  edits (see `.claude/settings.json`). The graph's data dir (`.code-review-graph/`) is gitignored.

## 4. Worktrees (isolated branches)

`bin/worktree-create` sets up an isolated git worktree with its **own** Postgres database and
Herd URL, using Linear's suggested branch name. It provisions/drops the per-worktree DB through
`herd php` + PDO (no `psql`).

```bash
bin/worktree-create SUI-123          # branch + worktree + fresh DB + migrate --seed
bin/worktree-list
bin/worktree-remove SUI-123          # tears down the worktree and drops its DB
```

### Git hooks (and protecting main)

Hooks live in the tracked `.githooks/` dir. `herd composer install` wires them automatically
(via `post-install-cmd` → `bin/install-git-hooks`, which sets `core.hooksPath`); `bin/worktree-create`
installs them into each worktree too. To wire them by hand (or if hooks stop firing):

```bash
bin/install-git-hooks     # sets: git config core.hooksPath .githooks
```

- **pre-commit** — runs Pint on staged PHP files and re-stages the result (staged files only).
- **pre-push** — blocks direct pushes to `main`, then runs PHPStan + the changed classes' mirror
  tests. The full gate (`herd composer check`) is left to you / CI.

`main` is protected **by convention**, not server-side rules (GitHub's free plan has no branch
protection or rulesets on private repos). The pre-push hook refuses pushes to `main`, so branch
off, push the branch, and open a PR (`gh pr create`); CI (`linter` + `tests`) runs on every PR.
Emergency bypass, discouraged: `git push --no-verify`.

## 5. Quality gate

`herd composer check` runs the full gate — Pint, PHPStan (**level 9, no baseline**), and the
Pest suite. Keep all three green; fix causes, never suppress.

```bash
herd composer check
```

The test suite runs on a dedicated **Postgres** database (`suivre_test`), not sqlite; Postgres-only
features like `pg_trgm` work in both local (Herd) and CI (`postgres:18`) and need only
`CREATE EXTENSION IF NOT EXISTS pg_trgm` in a migration — not an abstraction.
