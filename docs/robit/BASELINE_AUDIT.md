# Robit Handloom — baseline audit (M1)

Audited 2026-11-11 in the Ubuntu VM (UTM), from the checkout at `/home/andu/projects/shemane`.
Commands and outputs are recorded as run; nothing here is taken from the README or the Robit pack.

## 1. Repository

| Item | Value |
| --- | --- |
| Pinned baseline | `befbc58e15cdc09674cfaf5d61c08a4d62ae545f` — `main`, "Merge pull request #1105 from liberusoftware/feat/feature-modules", 2026-11-10 23:40 +0300 |
| Remote | `origin https://github.com/liberusoftware/ecommerce-laravel.git` — the upstream itself, not a Robit repository (ADR-012) |
| Local branches | `main`, `fix/app-healthcheck` (one unpushed commit `aa493ac`, Docker), `robit/m1-baseline-audit` (this work) |
| Uncommitted at audit start | `composer.json`, `composer.lock` — the RoadRunner packages added for the local image (§5) |
| Licence | `LICENSE`: MIT, "Copyright (c) 2024-2026 Liberu Software". Must be kept |
| Upstream self-description | `SECURITY.md`: "pre-production and mid-migration"; known findings in `docs/CONFORMANCE.md` §6 (snapshot at `2d1024c`) |
| Size | 56 models, 29 policies, 83 migrations, 274 test files |

## 2. Platform and dependencies

Measured in the running container (`php artisan about`) and from `composer.json` at `befbc58`.

| Component | Constraint | Installed |
| --- | --- | --- |
| PHP | `^8.5` | 8.5.12 |
| Laravel | `^13.38.0` | 13.38.0 |
| Filament | `^5.11.0` | 5.11.0 |
| Livewire | `^4.4.8` | 4.4.8 |
| Octane | `^2.22.0` | 2.22.0, RoadRunner server |
| Jetstream / Fortify | `^5.5.3` | teams + 2FA available |
| Spatie permission | `^8.3.2` | with `bezhansalleh/filament-shield ^4.3.1` |
| GraphQL | `webonyx/graphql-php ^15.39.0` | code-first schema `app/GraphQL/StorefrontSchema.php` |
| Payments | `laravel/cashier ^16.9.0`, `srmklive/paypal ^3.2.0` | Stripe and PayPal adapters |
| Liberu packages | `module-manager ^1.9`, `module-manager-filament ^1.9`, `tenancy ^1.0.1`, `users-roles ^1.0`, `ecommerce-contracts ^1.1`, `ecommerce-dropxl ^0.1`, `ecommerce-printful ^0.1` | — |
| Frontend | Vite `^8.3.5`, Tailwind `^4.3.3`, `laravel-vite-plugin ^3.2.0`, Preline, Font Awesome, Inter | No GSAP, no Alpine package (Alpine comes with Livewire) |
| Node | Not declared (`engines` absent) | Dockerfile assets stage `node:20-alpine`; CI `install.yml` uses Node 26 |
| Database | MySQL 8.0 (Compose); sqlite `:memory:` for tests | — |
| Installation mode | `ECOMMERCE_MODE` default `single` (`config/ecommerce.php:20`) | `single` (checked in the running app) |

## 3. Architecture

From `CLAUDE.md`, `CONTEXT.md` and confirmed in code:

- **Host → merchant.** `ResolveChannel` middleware resolves the request host via `ChannelResolver` to a
  `Channel` → `Store` → `Team`. Unknown hosts are a 404. The initial channel migration binds the
  `APP_URL` host plus `localhost` and `127.0.0.1`.
- **Tenancy at the model.** `IsStoreScoped` (18 models) and `IsTenantModel` (12 models) add global
  scopes. Deliberate exits: `withoutGlobalScope('store')`, `StoreContext::acrossAllStores()`.
- **Panels.** `/admin` (platform, no tenancy) and `/app` (merchant, Team tenancy), both with
  `strictAuthorization()`. Authorisation through `App\Support\StoreAccess`.
- **Checkout.** `CheckoutController` (session cart) and `HeadlessCheckoutService` (GraphQL) both call
  `CheckoutService` for coupons, stock reserve/release, downloads, dropship, capture.
- **Orders.** `Order::transitionTo()` with `Order::TRANSITIONS`; history in `OrderStatusHistory`.
- **Themes.** `channels.theme` selects `themes/<name>/` view overrides (`docs/THEMES.md`).
- **Modules.** Installed by Composer, offered by the platform, activated per team.

Commerce map and gap matrix: [`UPSTREAM_MAPPING.md`](./UPSTREAM_MAPPING.md).

## 4. Docker audit

| Item | Finding |
| --- | --- |
| Services | `app`, `mysql`, `redis`, `mailpit`; profiles `worker`, `horizon`, `reverb` (`docker compose config --services`, `--profiles`) |
| App image | Multi-stage: `composer-deps` (`--no-dev`), `assets` (`node:20-alpine`, `npm run build`), runtime `php:8.5-cli-alpine` with supervisord |
| Entrypoint | `.docker/start-container`: `storage:link`, `optimize:clear`, `event:cache`, `config:cache`, `route:cache`, then supervisord. Passing a command skips all of it (`exec "$@"`) |
| Source mount | None. Code is baked in; every change needs a rebuild |
| PHP config | `.docker/php.ini` sets `opcache.validate_timestamps = 0` — PHP never notices edited files |
| Persistent volumes | `mysql`, `redis`, `storage` (`storage/app`), `logs` |
| Ports | App `8000`; MySQL `3306`, Redis, Mailpit `1025`/`8025` all on `0.0.0.0` |
| Vite / HMR | No dev service; assets only built inside the image |
| Unpinned inputs | `composer:latest`, `install-php-extensions` `latest`, `axllent/mailpit:latest` |
| Runs under UTM Ubuntu | Yes: 2 vCPU, 7.2 GiB RAM, Docker 29.8.1, Compose 5.5.1 |
| Port collisions | `marketplace_redis_cache` holds `127.0.0.1:6379` → this checkout uses `FORWARD_REDIS_PORT=6380` |

## 5. Defects found and fixed before the audit (branch `fix/app-healthcheck`)

Found while bringing the stack up; committed as `aa493ac`, not pushed.

1. **Health check starved the container.** `php artisan octane:status` every 2 s overran its 5 s
   timeout on 2 vCPUs and piled up (20 concurrent processes observed). Replaced with an HTTP probe.
2. **RoadRunner was never in the image.** No `rr` binary, no `.rr.yaml`, the shipped config was
   RoadRunner v2's, and Octane listened on `80` while Compose maps `8000`. Fixed in the Dockerfile and
   `.docker/octane/RoadRunner/`.
3. **Worker memory cap of 128 MB** killed every worker after one request. Raised to 512 MB.
4. **Still open:** `spiral/roadrunner-http` and `spiral/roadrunner-cli` must be added to
   `composer.json`/`composer.lock`. They are in the working tree only; upstream requires the lock to be
   written by its `composer-require` workflow.

## 6. Baseline test run

Run at `befbc58` in a clean worktree, mirroring `tests.yml`: PHP 8.5 CLI with `intl`, `bcmath`, `gd`,
`zip`, `sockets`, `pcntl`, `pdo_sqlite`; `composer install`; `cp .env.example .env`;
`php artisan key:generate`; `php artisan test`. `memory_limit=-1`, as `setup-php` does in CI.

```text
Tests:    1591 passed (5446 assertions)
Duration: 1083.94s
```

No failures, skips, risky or incomplete tests. The first attempt failed for a reason in the harness,
not the code: child PHP processes inherited the default 128 MB `memory_limit`, and the Composer cache
had been placed inside the checkout, where a tree-walking test crawled it. Both corrected before the
recorded run.

**What this proves:** upstream's own suite passes at `befbc58` on PHP 8.5.12 — including its store
scoping, GraphQL isolation, IDOR, sitemap, state-machine and checkout inventory tests that back the
"fixed" rows in `SECURITY_GATE.md`. **What it does not prove:** any Robit invariant that no upstream
test covers — those rows stay *confirmed* or *unverified* until M3/M6 add the tests.

GitHub Actions has not run this; per the repository's rule a local pass is not the authority.

## 7. Smallest end-to-end vertical slice

One synthetic, clearly labelled stocked product sold to a guest on the Robit channel, with a
**fake** payment adapter, through to a paid order and an admin view of it:

1. M3 — Robit store and channel bootstrap command; direct-request tests for S-01 and U-H4.
2. M5 (minimal) — `rh_product_specs` and `rh_variant_specs` for that one product, publish gate.
3. M6 (minimal) — `rh_payment_attempts` and `rh_payment_webhook_inbox` with a fake provider; the order
   becomes paid only from a verified fake webhook; duplicate webhook test; last-unit race test.
4. Admin: order view showing attempt and inbox rows read-only.

No real provider, product or artisan data is involved.
