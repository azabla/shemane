# Robit Handloom — progress log

Newest first. Each entry follows the execution guide's milestone format (§C).

## 2026-11-12 — M2 closed: environments and the `/health` fix

**Objective.** Write staging and production guidance; explain the serve check's HTTP 400.

**Finding.** `/` answered 400 because `TrustHosts` rejects `127.0.0.1` (only `APP_URL` and channel
domains are trusted), which is correct. `/health` is meant to be exempt, but under Octane it also answered
400: Symfony keeps the trusted-host list in a static, so once a worker had served any other request
(Docker's own health probe is enough) every `/health` by IP failed. A probe on a pod address would have
marked a healthy app as down.

**Changes.**
- `app/Http/Middleware/TrustHosts.php` — `/health` clears the trusted-host list before it runs.
- `tests/Feature/TrustedHostsTest.php` — reproduces a worker that kept a previous request's list.
- `.github/scripts/smoke-serve.sh` — also requires `/` on a trusted host to answer below 500, and
  `/health` by IP to return its JSON eight times in a row.
- `docs/robit/ENVIRONMENTS.md` (RH-M2-04).

**Tests and results.**
- New test failed before the fix (400), passed after; `TrustedHostsTest` and `ChannelResolutionTest`
  16 passed.
- `smoke-serve.sh`: pre-fix image fails ("/health by IP answered: <!DOCTYPE html>…"); fixed image passes
  (`/` 404 on an empty database, `/health` 503 `degraded` with no database file).

## 2026-11-12 — M2 remote and CI

**Objective.** Own remote; every PR shows build, test and image results.

**Changes.**
- Remotes: `origin` = `azabla/shemane` (public by owner choice), `upstream` fetch-only. Security findings
  kept in gitignored `docs/robit/private/`.
- RoadRunner packages locked by the `composer-require` workflow (`9e5f2a3`); PRs #13 and #12 merged.
- `.github/workflows/main.yml` — Docker job runs on PRs, no Docker Hub login, GHA build cache, new serve
  check; push to `ghcr.io/azabla/shemane` only when repository variable `PUBLISH_IMAGE=true`.
- `.github/scripts/smoke-serve.sh` — starts the image, waits for health and HTTP on 8000, checks `rr` runs.
- `.github/dependabot.yml` — weekly, one grouped PR per ecosystem.

**Tests and results.**
- CI on #12 and #13: all 14 checks pass; `docker` skipped on PRs before this change.
- `smoke-serve.sh` locally: production image healthy, `rr` serving on 8000 in 138 s; against
  `php:8.5-cli-alpine` it fails ("container exited"), as it should.
- `sync-packages.yml` unchanged: its job is guarded to `liberusoftware/project-ecommerce-laravel-filament`.

## 2026-11-11 — M2 repeatable development environment (in progress)

**Objective.** Edit code without rebuilding the image; keep data across restarts; tests safe to run in
the container.

**Baseline verified.** Production-style image with no source mount and
`opcache.validate_timestamps = 0` (`.docker/php.ini`); entrypoint caches config/routes/events
(`.docker/start-container`); no Vite service; DB/Redis/Mailpit on `0.0.0.0` (`docker-compose.yml`).

**Changes.**
- `compose.dev.yaml` — bind mount, `artisan serve`, Vite service, loopback-only ports, `COMPOSER_HOME`
  outside the repo.
- `.docker/dev/php.ini` — OPcache rechecks files.
- `vite.config.js` — dev-server block, active only when `VITE_DEV_SERVER` is set; ignores
  `storage/`, `vendor/` for file watching.
- `phpunit.xml` — every value forced as both `<server>` and `<env>`.
- `docs/robit/DEVELOPMENT.md`.
- Local `.env` (not committed): `VITE_PORT=5174`.

**Migrations and data.** None. The dev database was wiped once and re-seeded — `DEVELOPMENT.md`, "Daily commands".

**Tests and results.**
- HMR: Blade and CSS edits seen within ~6 s, no rebuild; files reverted.
- Persistence: `down` + `up` kept 1 user / 58 products; Redis `PONG`; all services healthy.
- Test isolation: canary host — new config 2 passed, old config 2 failed (`getaddrinfo`); real
  container — 11 passed, DB counts unchanged.
- Full suite inside the dev container: first run 179 failed / 1412 passed — `CACHE_DRIVER=file` from the
  container won over `phpunit.xml` the same way `DB_*` had. After forcing every value: **1591 passed
  (5446 assertions)**, 225.80 s, dev DB unchanged.

**Blockers.** RH-M2-01 (own `origin`) needs the owner's private repository. RH-M2-05 (CI) not started.

## 2026-11-11 — M1 audit

**Objective.** Pin the upstream, measure its state, record what Robit reuses.

**Outputs.** `BASELINE_AUDIT.md`, `UPSTREAM_MAPPING.md`,
`ARCHITECTURE_DECISIONS.md`, `RISKS.md`, and `private/SECURITY_GATE.md` (kept out of git, the repository is public); backlog statuses updated.

**Tests.** Full upstream suite at `befbc58`: 1591 passed (5446 assertions), 0 failed, 1083.94 s, local.
CI not run.

**Key findings.** Upstream's tenancy and Filament authorisation findings are fixed at `befbc58` as far
as its tests reach. Open for Robit: no payment-attempt ledger or webhook inbox (S-04, S-08, S-09),
float money (S-05), PayPal amount unverified (S-07), stock held by pending orders with no expiry
(S-03), queue `after_commit` off (U-H6), API serialises models (U-H3).

**Next.** Finish M2 (CI skeleton, own remote), then M3 single-store security tests.
