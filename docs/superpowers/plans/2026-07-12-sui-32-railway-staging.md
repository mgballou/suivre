# SUI-32 — Railway Staging + CI-Gated Deploy Pipeline Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stand up an always-reachable, disposable **staging** environment on Railway where every green merge to `main` auto-deploys the Inertia app + Filament backstage behind a queue worker and a `pg_trgm`-enabled Postgres.

**Architecture:** Single **FrankenPHP / Octane** container built from a repo `Dockerfile` (PHP 8.4 + built Inertia assets in one image). Railway hosts three resources: **web** (public URL), **postgres** (managed, `pg_trgm` via migration), **worker** (same image, `queue:work`). Deploy-gating uses Railway's **"Wait for CI"** (check-suites-must-pass) toggle — GitHub branch protection is unavailable on the free private repo (see D17), so Railway only deploys a `main` commit once its `quality` + `ci (8.4)` checks are green.

**Tech Stack:** Laravel 13 · PHP 8.4 · Laravel Octane + FrankenPHP · Inertia + React 19 + Vite · Filament 5 · Postgres 18 · Railway (Hobby) · `database` queue/cache/session drivers.

## Global Constraints

- **Toolchain: Herd only** for all local PHP/Composer/Artisan — `herd php artisan …`, `herd composer …`. Never bare `php`/`composer`.
- **PHP floor `^8.4`** (D17). CI matrix is `['8.4']`; required check contexts are exactly **`quality`** (lint.yml) and **`ci (8.4)`** (tests.yml).
- **Queue/cache/session = `database` driver.** No Redis for MVP.
- **Repo stays private on GitHub free plan.** Branch protection is impossible (403); the deploy gate is **Railway "Wait for CI"** + the existing `.githooks/pre-push` (blocks direct pushes to `main`) + PR workflow.
- **`DATABASE_URL` → `DB_URL`.** `config/database.php` reads `env('DB_URL')`; Railway injects `DATABASE_URL`. Map with a reference variable `DB_URL=${{Postgres.DATABASE_URL}}`.
- **No `route:cache`.** `routes/settings.php` has a closure route (`.well-known/passkey-endpoints`) that breaks route serialization. Cache `config` + `event` only.
- **Health endpoint is `/up`** (already wired in `bootstrap/app.php`) — Railway healthcheck target.
- **Flux Pro is NOT a dependency.** The Dockerfile must build with **no** `composer.fluxui.dev` auth. (The dead CI Flux step is SUI-33's problem, not this plan's.)
- **Staging data is disposable.** No backups/migration-safety concerns.
- Every `.php` change ends with `herd php vendor/bin/pint --dirty`. Keep `herd composer check` green.

---

## File Structure

- `composer.json` — add `laravel/octane` (prod dep).
- `config/octane.php` — published Octane config (FrankenPHP server).
- `Dockerfile` — 2-stage FrankenPHP build (build stage: composer + node + Vite; runtime stage: slim FrankenPHP + Octane).
- `.dockerignore` — keep `vendor/`, `node_modules/`, `.git/`, `.env` etc. out of build context.
- `docker/php.ini` — production opcache/php ini overlay.
- `railway.json` — web-service build + deploy config (Dockerfile builder, `/up` healthcheck, `migrate --force` preDeploy, cache-at-start start command).
- `database/migrations/XXXX_XX_XX_XXXXXX_enable_pg_trgm_extension.php` — `CREATE EXTENSION IF NOT EXISTS pg_trgm`.
- `database/seeders/StagingSeeder.php` — admin + throwaway user for staging.
- `docs/deployment.md` — staging runbook.
- `docs/decisions/decision-log.md` — append **D21**.

---

## Task 1: Add Laravel Octane + FrankenPHP

**Files:**
- Modify: `composer.json` (require `laravel/octane`)
- Create: `config/octane.php` (published)

**Interfaces:**
- Produces: an `octane:start --server=frankenphp` command available to the Dockerfile CMD (Task 2) and railway.json (Task 3).

- [ ] **Step 1: Require Octane**

```bash
herd composer require laravel/octane
```

- [ ] **Step 2: Install Octane for FrankenPHP (publishes config, does NOT download the binary — the Docker base image provides it)**

```bash
herd php artisan octane:install --server=frankenphp
```

If it prompts to download the FrankenPHP binary, decline / ignore — the `dunglas/frankenphp` base image ships it. We only need `config/octane.php` written with `'server' => 'frankenphp'` default.

- [ ] **Step 3: Verify config landed and default server is frankenphp**

Run: `herd php artisan config:show octane.server`
Expected: prints `frankenphp` (or `env('OCTANE_SERVER', 'frankenphp')` resolving to it).

- [ ] **Step 4: Confirm the suite still boots (Octane service provider registered cleanly)**

Run: `herd php artisan test --compact`
Expected: PASS (same green count as before + no new failures).

- [ ] **Step 5: Pint + commit**

```bash
herd php vendor/bin/pint --dirty
git add composer.json composer.lock config/octane.php
git commit -m "Add Laravel Octane with FrankenPHP server for containerised deploy"
```

---

## Task 2: Dockerfile + .dockerignore + php.ini

**Files:**
- Create: `Dockerfile`
- Create: `.dockerignore`
- Create: `docker/php.ini`

**Interfaces:**
- Consumes: `octane:start --server=frankenphp` (Task 1).
- Produces: an image whose default `CMD` boots the web app on `$PORT`; worker service (Task 7) overrides the start command to `queue:work`.

- [ ] **Step 1: Create `.dockerignore`**

```
.git
.github
.githooks
node_modules
vendor
storage/framework/cache/*
storage/framework/sessions/*
storage/framework/views/*
storage/logs/*
.env
.env.*
!.env.example
docs
tests
.phpstan.cache
.code-review-graph
.claude
.ai
*.md
```

- [ ] **Step 2: Create `docker/php.ini`**

```ini
; Production PHP overlay for the FrankenPHP runtime
opcache.enable=1
opcache.enable_cli=1
opcache.jit=tracing
opcache.jit_buffer_size=64M
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
memory_limit=512M
expose_php=0
```

- [ ] **Step 3: Create `Dockerfile`**

```dockerfile
# syntax=docker/dockerfile:1

# ============================================================
# Stage 1 — build: composer deps + built Inertia assets.
# Wayfinder's Vite plugin shells out to `php artisan` during
# `npm run build`, so the asset build needs PHP present — hence
# a PHP base here rather than a plain node image.
# ============================================================
FROM dunglas/frankenphp:1-php8.4 AS build

# Runtime + build PHP extensions (pcntl required by Octane)
RUN install-php-extensions pdo_pgsql pgsql intl zip opcache pcntl

# Node 22 (matches CI) + tooling for the Vite build
RUN apt-get update && apt-get install -y --no-install-recommends \
        git curl unzip ca-certificates gnupg \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# 1) PHP deps first for layer caching. --no-scripts: no .git yet (git hooks
#    no-op anyway) and app code not copied, so discovery runs in step 4.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# 2) JS deps (separate cache layer)
COPY package.json package-lock.json ./
RUN npm ci

# 3) Application source
COPY . .

# 4) Optimised autoloader + package discovery + Filament static assets.
#    Dummy APP_KEY so any provider that touches the encrypter can boot at build.
RUN APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
    composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi \
    && php artisan filament:assets

# 5) Build the front-end (Wayfinder generates typed routes, then Vite bundles)
RUN npm run build

# Drop dev JS deps before copying the tree forward
RUN rm -rf node_modules

# ============================================================
# Stage 2 — runtime: slim FrankenPHP + Octane
# ============================================================
FROM dunglas/frankenphp:1-php8.4 AS runtime

RUN install-php-extensions pdo_pgsql pgsql intl zip opcache pcntl

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /app

# App + vendor + built public/build from the build stage
COPY --from=build /app /app

ENV APP_ENV=staging \
    OCTANE_SERVER=frankenphp

# FrankenPHP binds Railway's injected $PORT (defaults to 8000 locally)
EXPOSE 8000
CMD ["sh", "-c", "php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=${PORT:-8000}"]
```

- [ ] **Step 4: Build the image locally IF Docker is available (fast feedback loop)**

Run: `docker build -t suivre-staging . 2>&1 | tail -30`
Expected: `naming to docker.io/library/suivre-staging done` (build completes). If `docker` is not installed, skip — the build is verified for real by `railway up` in Task 7.

- [ ] **Step 5: Smoke-run the image locally IF Docker is available**

```bash
docker run --rm -e APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= -e PORT=8000 -p 8000:8000 suivre-staging &
sleep 8 && curl -sS -o /dev/null -w "%{http_code}\n" http://localhost:8000/up ; kill %1
```

Expected: `200`. (No DB is attached, so only `/up` is expected to work — that's enough to prove Octane boots and serves.) Skip if Docker absent.

- [ ] **Step 6: Commit**

```bash
git add Dockerfile .dockerignore docker/php.ini
git commit -m "Add FrankenPHP/Octane Dockerfile for Railway staging image"
```

---

## Task 3: railway.json (build + deploy config)

**Files:**
- Create: `railway.json`

**Interfaces:**
- Consumes: the `Dockerfile` (Task 2), the `pg_trgm` + app migrations (Task 4).
- Produces: the web service's build/deploy contract Railway reads on every deploy.

- [ ] **Step 1: Create `railway.json`**

```json
{
  "$schema": "https://railway.com/railway.schema.json",
  "build": {
    "builder": "DOCKERFILE",
    "dockerfilePath": "Dockerfile"
  },
  "deploy": {
    "startCommand": "php artisan config:cache && php artisan event:cache && php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=$PORT",
    "preDeployCommand": "php artisan migrate --force",
    "healthcheckPath": "/up",
    "healthcheckTimeout": 120,
    "restartPolicyType": "ON_FAILURE",
    "restartPolicyMaxRetries": 10
  }
}
```

Rationale baked into this file (do not "simplify" away):
- `preDeployCommand` runs in a throwaway one-off container, so only DB-persistent work belongs there → **`migrate --force` only**.
- `config:cache`/`event:cache` run in the **start command** so they populate the real runtime container. **No `route:cache`** (closure route in `settings.php`).
- Healthcheck hits `/up`; 120 s timeout covers first-boot cache warm.

- [ ] **Step 2: Validate JSON**

Run: `herd php -r "json_decode(file_get_contents('railway.json'), false, 512, JSON_THROW_ON_ERROR); echo 'valid';"`
Expected: `valid`

- [ ] **Step 3: Commit**

```bash
git add railway.json
git commit -m "Add railway.json: Dockerfile build, /up healthcheck, migrate preDeploy"
```

---

## Task 4: pg_trgm migration + staging seeder

**Files:**
- Create: `database/migrations/XXXX_XX_XX_XXXXXX_enable_pg_trgm_extension.php`
- Create: `database/seeders/StagingSeeder.php`
- Test: `tests/Feature/Database/PgTrgmExtensionTest.php`

**Interfaces:**
- Produces: `pg_trgm` available in every environment (local/CI/staging) via `migrate`; a `StagingSeeder` invocable by Task 7. Coordinate with **SUI-16** — it must NOT re-add this extension migration.

- [ ] **Step 1: Create the migration**

```bash
herd php artisan make:migration enable_pg_trgm_extension
```

Then set its body (keep the generated timestamped filename):

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
    }

    public function down(): void
    {
        DB::statement('DROP EXTENSION IF EXISTS pg_trgm');
    }
};
```

- [ ] **Step 2: Write the failing test**

`tests/Feature/Database/PgTrgmExtensionTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use Illuminate\Support\Facades\DB;

it('has the pg_trgm extension available for trigram queries', function () {
    $installed = DB::selectOne("SELECT 1 AS ok FROM pg_extension WHERE extname = 'pg_trgm'");

    expect($installed?->ok)->toBe(1);
});

it('can run a trigram similarity query', function () {
    $row = DB::selectOne("SELECT similarity('banana', 'bananas') AS score");

    expect($row->score)->toBeGreaterThan(0.0);
});
```

- [ ] **Step 3: Run it — expect PASS (migrations run against `suivre_test` Postgres)**

Run: `herd php artisan test --compact --filter=PgTrgmExtension`
Expected: PASS. (If the extension can't be created, the migration privilege issue surfaces here, not on staging.)

- [ ] **Step 4: Create the staging seeder**

`database/seeders/StagingSeeder.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class StagingSeeder extends Seeder
{
    /**
     * Idempotent staging accounts: an admin (Filament backstage) and a
     * throwaway user for real-world app use. Passwords are staging-only.
     */
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@suivre.staging'],
            [
                'name' => 'Staging Admin',
                'password' => bcrypt('staging-admin-password'),
                'email_verified_at' => now(),
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'user@suivre.staging'],
            [
                'name' => 'Throwaway User',
                'password' => bcrypt('staging-user-password'),
                'email_verified_at' => now(),
            ],
        );
    }
}
```

> Note: if Filament admin access is gated by a policy/`FilamentUser` contract rather than "any user", check `app/Models/User.php` for `canAccessPanel()` and set whatever attribute it requires on the admin row. Read the model before finalising this seeder.

- [ ] **Step 5: Verify the seeder runs**

Run: `herd php artisan db:seed --class=StagingSeeder --no-interaction`
Expected: `Database seeding completed successfully.` Re-run once more — still succeeds (idempotent, no duplicate-email error).

- [ ] **Step 6: Pint + commit**

```bash
herd php vendor/bin/pint --dirty
git add database/migrations database/seeders/StagingSeeder.php tests/Feature/Database/PgTrgmExtensionTest.php
git commit -m "Enable pg_trgm via migration; add idempotent StagingSeeder"
```

---

## Task 5: Provision the Railway project + Postgres

**Files:** none (Railway CLI state).

**Interfaces:**
- Consumes: authenticated `railway` CLI (already logged in as `matthewbuiltthat`).
- Produces: a fresh project `suivre-staging` with a Postgres service exposing `${{Postgres.DATABASE_URL}}`.

> All commands run from the repo root `/Users/matthewballou/projects/suivre`. Do **not** touch the unrelated `handy-agreement` project.

- [ ] **Step 1: Create a fresh project and link this directory**

```bash
railway init --name suivre-staging
railway status
```

Expected: `Project: suivre-staging` linked, environment `production`.

- [ ] **Step 2: Provision managed Postgres**

```bash
railway add --database postgres
```

Expected: a `Postgres` service appears. Confirm with `railway status` (or `railway variables --service Postgres` shows `DATABASE_URL`).

- [ ] **Step 3: Note the exact Postgres service name**

Run: `railway status`
Record the DB service's name (Railway usually names it `Postgres`). The reference variable in Task 6 must match it exactly (`${{Postgres.DATABASE_URL}}`).

> `pg_trgm` is enabled by the migration in Task 4 running under `preDeployCommand`, so **no manual `CREATE EXTENSION` is needed here.** (If Railway's default DB role somehow lacks the privilege, fall back to: `railway connect Postgres` then `CREATE EXTENSION IF NOT EXISTS pg_trgm;` — but Railway's managed PG role is a superuser, so this should not be necessary.)

---

## Task 6: Configure environment variables

**Files:** none (Railway service variables).

**Interfaces:**
- Consumes: the linked project + Postgres service (Task 5).
- Produces: a fully-configured env for the web + worker services.

- [ ] **Step 1: Generate a staging APP_KEY**

```bash
herd php artisan key:generate --show
```

Copy the `base64:...` value for the next step.

- [ ] **Step 2: Set the web service variables** (replace `<APP_KEY>`; set `APP_URL` after the domain exists in Task 7 — re-run for that one)

```bash
railway variables \
  --set "APP_ENV=staging" \
  --set "APP_DEBUG=false" \
  --set "APP_KEY=<APP_KEY>" \
  --set "APP_NAME=Suivre" \
  --set "LOG_CHANNEL=stderr" \
  --set "DB_CONNECTION=pgsql" \
  --set "DB_URL=\${{Postgres.DATABASE_URL}}" \
  --set "DB_SSLMODE=disable" \
  --set "SESSION_DRIVER=database" \
  --set "CACHE_STORE=database" \
  --set "QUEUE_CONNECTION=database" \
  --set "MAIL_MAILER=log" \
  --set "OCTANE_SERVER=frankenphp"
```

Notes:
- `DB_URL=${{Postgres.DATABASE_URL}}` is a Railway **reference variable** — it resolves to the internal connection string. The `\$` escaping is for the shell; the stored value must be the literal `${{Postgres.DATABASE_URL}}`.
- `DB_SSLMODE=disable` — Railway internal networking (`*.railway.internal`) is a private network; SSL is not required and `prefer` can add latency/errors.
- `LOG_CHANNEL=stderr` so logs land in Railway's log stream (no file volume).

- [ ] **Step 3: Verify the stored reference variable**

Run: `railway variables`
Expected: `DB_URL` shows `${{Postgres.DATABASE_URL}}` (literal reference, not expanded), `APP_ENV=staging`, `APP_DEBUG=false`.

---

## Task 7: Deploy web + worker services, connect GitHub, enable Wait-for-CI

**Files:** none (Railway services).

**Interfaces:**
- Consumes: everything above.
- Produces: a running web service on a public URL, a worker service processing the DB queue, and auto-deploy from `main` gated on green CI.

> The branch must be merged to `main` before GitHub-triggered auto-deploy is meaningful. For the **first** deploy, use `railway up` (deploys the local working tree) to prove the image and env work; wire GitHub auto-deploy after.

- [ ] **Step 1: First deploy of the web service from local (proves the Dockerfile builds on Railway's builder)**

```bash
railway up --service <web-service> 2>&1 | tail -40
```

If no web service exists yet, create one first: `railway add --service web` (or Railway auto-creates on first `up`). Expected: build succeeds, deploy goes live, `preDeployCommand` runs `migrate --force`.

- [ ] **Step 2: Generate a public domain for the web service**

```bash
railway domain
```

Expected: prints a `*.up.railway.app` URL. Record it.

- [ ] **Step 3: Set `APP_URL` to the generated domain and redeploy**

```bash
railway variables --set "APP_URL=https://<generated>.up.railway.app"
railway up --service <web-service> 2>&1 | tail -20
```

- [ ] **Step 4: Add the worker service (same repo/image, overridden start command, no domain/healthcheck)**

```bash
railway add --service worker
railway variables --service worker \
  --set "APP_ENV=staging" \
  --set "APP_KEY=<APP_KEY>" \
  --set "DB_CONNECTION=pgsql" \
  --set "DB_URL=\${{Postgres.DATABASE_URL}}" \
  --set "DB_SSLMODE=disable" \
  --set "QUEUE_CONNECTION=database" \
  --set "CACHE_STORE=database" \
  --set "LOG_CHANNEL=stderr"
```

Then set the worker's start command to `php artisan queue:work --tries=3 --max-time=3600` and disable healthcheck. If the CLI can't set a per-service start command / disable healthcheck, **do it in the Railway dashboard** (Service → Settings → Deploy → Custom Start Command) and note it in the runbook.

- [ ] **Step 5: Connect the GitHub repo for auto-deploy + enable "Wait for CI"** *(likely dashboard — surface to the human)*

In the Railway dashboard for the **web** service → Settings → Source: connect `mgballou/suivre`, branch `main`. Then Settings → Deploy → enable **"Wait for CI"** (a.k.a. check-suites-must-pass). Do the same source connection for the **worker** service. Attempt via `railway service` CLI first; if unavailable, this is a human step — flag it clearly.

- [ ] **Step 6: Seed staging accounts**

```bash
railway run --service <web-service> php artisan db:seed --class=StagingSeeder --force
```

Expected: seeding completes; `admin@suivre.staging` + `user@suivre.staging` exist.

---

## Task 8: Verify acceptance criteria on staging

**Files:** none (operational verification).

**Interfaces:** consumes the live staging URL + services.

- [ ] **Step 1: Health + app shell over HTTPS**

```bash
curl -sS -o /dev/null -w "up=%{http_code}\n" https://<domain>/up
curl -sS -o /dev/null -w "home=%{http_code}\n" https://<domain>/
curl -sS -o /dev/null -w "admin=%{http_code}\n" https://<domain>/admin
```

Expected: `up=200`, `home=200`, `admin=200` (or `admin=302` redirect to login). Then load `https://<domain>/login` in a browser, sign in as `user@suivre.staging`, and confirm the authenticated landing route renders. **Note:** the AC says `login → /calendar`, but no `/calendar` route exists yet (`routes/web.php` currently redirects auth users to `/dashboard`). Verify the *reachable* authenticated page (`/dashboard`) and record that `/calendar` lands with its product ticket, not here.

- [ ] **Step 2: Worker processes a dispatched job**

```bash
railway run --service <web-service> php artisan tinker --execute "dispatch(function () { \Illuminate\Support\Facades\Log::info('staging-queue-smoke'); });"
railway logs --service worker | tail -20
```

Expected: worker log shows the job processed (`staging-queue-smoke` in the web/worker log stream), no failed_jobs row.

- [ ] **Step 3: pg_trgm trigram query succeeds against staging DB**

```bash
railway run --service <web-service> php artisan tinker --execute "echo \Illuminate\Support\Facades\DB::selectOne(\"SELECT similarity('banana','bananas') AS s\")->s;"
```

Expected: a float `> 0` (e.g. `0.5`). Confirms the migration enabled the extension on staging.

- [ ] **Step 4: CI-gated auto-deploy end-to-end**

Open a trivial PR (e.g. a README touch) → confirm `quality` + `ci (8.4)` run → merge → confirm Railway starts a deploy **only after** the checks are green (watch the web service's deploy log / `railway logs`). Expected: deploy triggered post-green, new revision live.

---

## Task 9: Documentation + decision log

**Files:**
- Create: `docs/deployment.md`
- Modify: `docs/decisions/decision-log.md` (append D21)

- [ ] **Step 1: Write `docs/deployment.md`** — the staging runbook: the three Railway services and their roles; the env-var table (incl. `DB_URL=${{Postgres.DATABASE_URL}}` and why `DB_SSLMODE=disable`); build/release model (Dockerfile, `migrate --force` preDeploy, cache-at-start, **why no `route:cache`**); the "Wait for CI" gate + local pre-push hook + PR flow (and the D17 reason branch protection is absent); how to redeploy, tail logs, open a DB shell (`railway connect Postgres`), and re-seed; the staging account credentials location.

- [ ] **Step 2: Append D21 to the decision log**

```markdown
## D21 — Staging on Railway; CI-gated deploy via "Wait for CI" (branch protection unavailable)

- **Decision:** Host disposable **staging** on **Railway** (Hobby, ~$5/mo) as three services — **web** (FrankenPHP/Octane single-container Dockerfile), **postgres** (managed, `pg_trgm` via migration), **worker** (`queue:work`, `database` driver, no Redis). Build runs `composer install` + `npm run build` in one image; release runs `migrate --force` (preDeploy) with `config`/`event` cache at container start.
- **Deploy gate:** GitHub branch protection is impossible on the free private repo (D17). Instead, Railway's **"Wait for CI"** deploys a `main` commit only after `quality` + `ci (8.4)` are green; the existing `.githooks/pre-push` block on direct `main` pushes + PR workflow supply the merge discipline. Net effect matches SUI-32's AC intent at $0 extra.
- **Why Railway (over Laravel Cloud / Supabase):** explicit, legible deploy mechanics for a solo dev learning deployments; Supabase is a DB/BaaS, not an app host (500 MB free DB + 7-day idle pause are a poor fit for an OFF catalog + intermittent staging box).
- **Key mechanics recorded:** `DATABASE_URL`→`DB_URL` reference var; `DB_SSLMODE=disable` on Railway's private network; **no `route:cache`** (closure route in `settings.php`); Octane needs `pcntl`; Filament assets published at build; Wayfinder's Vite plugin forces PHP into the asset-build stage.
- **Rules out (for now):** GitHub-server-side branch protection; Redis; custom domain; production-grade backups (data is disposable).
- **Coordinate:** SUI-16 must not re-add the `pg_trgm` extension migration (this ticket added it). The stale CLAUDE.md "tests run on sqlite / pg_trgm needs an abstraction" note is wrong (D18) — fix in SUI-33.
```

- [ ] **Step 3: Commit docs**

```bash
git add docs/deployment.md docs/decisions/decision-log.md
git commit -m "Document Railway staging runbook; record D21"
```

- [ ] **Step 4: Open the PR** (per the finish-work convention — push branch, open PR, never merge to main locally)

```bash
git push -u origin matthewbuiltthat/sui-32-provision-railway-staging-ci-gated-deploy-pipeline
gh pr create --fill
```

---

## Self-Review Notes

- **Spec coverage:** service topology (T2/T5/T7), Dockerfile w/ composer+npm build (T2), migrate/cache release (T3), env & secrets (T6), pg_trgm (T4/T8), worker (T7/T8), Wait-for-CI gate (T7/T8), seeded accounts (T4/T7), docs + decision log (T9). All AC items mapped.
- **Known deviation surfaced, not hidden:** `login → /calendar` — `/calendar` doesn't exist yet; T8 verifies `/dashboard` and defers `/calendar` to its product ticket.
- **Human-only steps flagged:** GitHub-source connection + "Wait for CI" toggle (T7 Step 5) may be dashboard-only; per-service worker start command / healthcheck-disable (T7 Step 4) may be dashboard-only.
