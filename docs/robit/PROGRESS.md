# Robit Handloom — progress log

Newest first. Each entry follows the execution guide's milestone format (§C).

## 2026-11-12 — M3 slice 1: single-merchant boundary and direct-ID access

**Objective.** Prove, by direct requests, that nobody opens a second merchant, store staff stay out of
the platform panel, and one customer cannot change another's records.

**Baseline verified.** Most protections already exist upstream with tests; the gaps were direct requests
against team creation, a sweep of `/admin` for store roles, and cart lines by id.

**Changes.** Tests only, no application code:
- `tests/Feature/Robit/SingleMerchantBoundaryTest.php` — 8 tests: every team/store creation path refused
  in single mode; every parameter-free `/admin` page refused for the four store roles.
- `tests/Feature/Robit/CartLineOwnershipTest.php` — another customer or a guest cannot change or remove a
  cart line by id.

**Tests and results.** 10 passed. Each was shown to fail when the protection is removed: all 7 creation
tests fail in `saas` mode; both cart tests fail with the owner filter removed from `CartService`.
Already covered upstream and recorded rather than duplicated: order-form money fields, API status and
refunds, order confirmation, chat, payment methods, downloads, returns.

**Open in M3.** Queue context (RH-M3-04); the real Robit store record and domain (owner's facts); API
serialisation (U-H3).

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
