# Deployment — Railway staging

Disposable **staging** environment. Data is throwaway; there are no backup or
migration-safety guarantees. Rationale is recorded in the decision log as **D21**.

## Topology

Railway project **`suivre-staging`** (workspace *My Projects*), environment
`production`, three services — all in one repo, one image:

| Service | Role | Source | Notes |
|---|---|---|---|
| `web` | Octane/FrankenPHP HTTP server | this repo's `Dockerfile` | public URL, `CONTAINER_ROLE` unset |
| `worker` | `php artisan queue:work` | same image | `CONTAINER_ROLE=worker`, no domain |
| `Postgres` | managed Postgres 18 | Railway template | `pg_trgm` enabled via migration |

Public URL: **https://web-production-1abde.up.railway.app**

Seeded accounts (staging only, `docker/entrypoint.sh`): `admin@suivre.staging`
and `user@suivre.staging`, both password **`<redacted>`**, pre-verified.

## Build & release model

- **Image** (`Dockerfile`): 2 stages. The build stage has PHP *and* Node because
  Wayfinder's Vite plugin shells out to `php artisan` during `npm run build`.
  It runs `composer install` + `npm run build` + publishes Filament assets. The
  runtime stage is slim FrankenPHP + Octane.
- **`railway.json`** — build via Dockerfile; `preDeployCommand` is
  `php artisan migrate --force` (runs in-network before traffic switches). No
  HTTP healthcheck (the worker has no HTTP server; the web app is curl-verified).
- **Start** is the Dockerfile `CMD` → `docker/entrypoint.sh`, which branches on
  `CONTAINER_ROLE`:
  - **web:** `config:cache` → `event:cache` → seed staging accounts → `octane:start`.
  - **worker:** `queue:work --tries=3 --max-time=3600`.

### Gotchas baked into the config (do not "simplify" away)

- **Railway execs `railway.json`'s `startCommand`/`preDeployCommand` without a
  shell** — no `$VAR` expansion, no `&&` chaining. So the real start logic lives
  in `docker/entrypoint.sh` (a shell script), *not* in `railway.json`. This is why
  seeding and cache-warming are in the entrypoint, and `preDeployCommand` is a
  single `migrate` with no `&&`.
- **No `route:cache`** — `routes/settings.php` has a closure route
  (`.well-known/passkey-endpoints`) that can't be serialised.
- **`DB_URL=${{Postgres.DATABASE_URL}}`** — `config/database.php` reads `DB_URL`;
  Railway injects `DATABASE_URL`. `DB_SSLMODE=disable` on the private network.
- **`PORT=8080`** and the public domain targets 8080.
- **`OCTANE_HTTPS=true`** (web service) — Railway terminates TLS at its edge and
  forwards HTTP to the container, so without this Octane generates `http://` asset
  URLs and the browser blocks them as mixed content (blank page). Set per-service,
  not in the image (local runs must stay HTTP).
- `public/frankenphp-worker.php` is committed (the image doesn't run `octane:install`).

## CI-gated auto-deploy (GitHub Actions)

Branch protection is unavailable on the free private repo (D17), and Railway's
GitHub App could not be connected to this repo (the Railway account's GitHub link
wouldn't attach `mgballou/suivre`). So deploy runs from **GitHub Actions**, not
Railway's GitHub integration.

`.github/workflows/deploy.yml` triggers via `workflow_run` after the **`tests`**
workflow completes, and deploys only when that run **succeeded**, on a **push to
`main`**. So a red `main` never reaches `railway up` — that's the CI gate. It
deploys with a Railway **project token** (`RAILWAY_TOKEN` secret), running
`railway up --service web` then `--service worker`; Railway builds the Dockerfile
and runs the `migrate` preDeploy. The `.githooks/pre-push` block on direct pushes
to `main` + the PR workflow supply the merge discipline. (Pint/`lint` gates PRs
and pre-commit; it is not a deploy blocker.)

**One-time setup:** create a project token in the Railway dashboard →
`suivre-staging` → **Settings → Tokens** (scope: `production` environment), then
add it to GitHub → repo **Settings → Secrets and variables → Actions** as
**`RAILWAY_TOKEN`**.

## Operations

```bash
# Deploy the local working tree (manual / pre-merge)
railway up --service web
railway up --service worker

# Tail logs
railway logs -d --service web
railway logs -d --service worker

# Open a DB shell
railway connect Postgres

# Re-seed (also runs automatically on every web boot)
#   the web entrypoint runs StagingSeeder; to force it manually, redeploy web.

# Env vars
railway variables --service web
```

Postgres is reachable off-network via its **public TCP proxy**
(`DATABASE_PUBLIC_URL` on the `Postgres` service) for one-off admin from a laptop;
the internal `DATABASE_URL` only resolves inside Railway.
