# ROBIT HANDLOOM — Docker, Git, CI/CD and Operations Runbook

**Version 1.0 • 11 November 2026**  
**Scope:** Developer setup in Ubuntu (including Ubuntu running in UTM on an older Mac), own GitHub repository, Docker Compose, HMR, DB/Redis/Mailpit, tests, staging/production release and recovery.

**Important:** This document gives safe patterns. It is **not a verified installer for your clone**; adapt service names, entrypoint and `vite.config.js` after inspecting the pinned Liberu checkout. The repository's base Compose is not a generic Laravel Sail installation. Do not run destructive commands on existing data.

## 1. Source repository and ownership

Upstream: `https://github.com/liberusoftware/ecommerce-laravel`. It is MIT-licensed; retain original `LICENSE` and required copyright notice, even when you rebrand the whole application. Its README, `SECURITY.md`, `CONFORMANCE.md` and `MIGRATION_PLAN.md` must be reviewed at the **same pinned commit** you actually clone.

Recommended Git remote layout:

```bash
mkdir -p ~/Projects && cd ~/Projects
# If not yet cloned:
git clone https://github.com/liberusoftware/ecommerce-laravel.git robit-handloom
cd robit-handloom
git rev-parse HEAD    # record the exact baseline SHA
git remote rename origin upstream
git remote add origin git@github.com:YOUR_USERNAME/robit-handloom.git
git remote -v
# Only after creating an empty private GitHub repo and verifying permissions:
git branch -M main
git push -u origin main
git switch -c develop
git push -u origin develop
```

Use `upstream` only intentionally for future diffs/selected patches, not blind automatic merges during a migration. Preserve Git history to simplify audits and code attribution. Confirm `.env`, tokens, private maker documents and `.sql` backups are ignored.

## 2. Preflight commands (Ubuntu VM)

```bash
docker --version
docker compose version
docker info
git --version
df -h
free -h
docker ps --format 'table {{.Names}}\t{{.Ports}}'
```

If Docker daemon is unavailable, fix Docker Engine/Compose plugin installation before attempting to build. Docker group membership has near-root privileges: use a trusted local user and follow Docker's official post-install guidance rather than chmoding `/var/run/docker.sock` to world-writable.

The Mac's host PHP/Composer versions do not need to match the application image. Docker runs in the Ubuntu VM. Prefer editing files directly in the VM filesystem, not through a slow shared-folder mount, for large Laravel repos.

## 3. Inspect Liberu Compose before setting variables

```bash
cd ~/Projects/robit-handloom
ls -la Dockerfile docker-compose.yml .env.example composer.json package.json
sed -n '1,200p' docker-compose.yml
sed -n '1,160p' Dockerfile
cat package.json
docker compose config --services
```

Published Compose currently advertises `app`, `mysql`, `redis`, `mailpit`, plus optional profiles for `worker`, `horizon`, `reverb`. It uses `APP_PORT`, `FORWARD_DB_PORT`, `FORWARD_REDIS_PORT`, `FORWARD_MAIL_PORT`, `FORWARD_MAILPIT_DASHBOARD_PORT` and Docker internal service names. **Do not assume these values on a changed commit.** MySQL 8.0 is the documented default container; change database engines only after testing repository compatibility.

## 4. Docker local environment

```bash
cp .env.example .env
```

Edit `.env` with a **private local** value for `APP_KEY` and generated database password. For key creation, prefer the app runtime after build: `docker compose exec app php artisan key:generate`; do not overwrite a key after storing encrypted data or live sessions. The first build may require an `.env` file with empty key; follow repository entrypoint requirements.

Example `.env` concept (not a full file):

```dotenv
APP_NAME="Robit Handloom"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8001
APP_PORT=8001
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=robit_handloom
DB_USERNAME=robit_user
DB_PASSWORD=REPLACE_WITH_RANDOM_LOCAL_SECRET
REDIS_HOST=redis
REDIS_PORT=6379
FORWARD_DB_PORT=3307
FORWARD_REDIS_PORT=6380
FORWARD_MAIL_PORT=1026
FORWARD_MAILPIT_DASHBOARD_PORT=8026
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
```

Why ports differ: another Dockerized marketplace may already publish 3306/6379/8000/1025/8025. **Host ports** can be changed without changing **container-to-container** ports. If host ports should be entirely private, modify Compose with a reviewed override instead of exposing database/Redis on every interface. Compose `${APP_PORT:-8000}:8000` mapping means the app remains at 8000 inside the container but is on 8001 on the host.

Validate:

```bash
docker compose config --quiet
docker compose config --services
docker compose up -d --build
docker compose ps
docker compose logs --tail=100 app
```

After containers are running:

```bash
docker compose exec app php artisan key:generate
# Review seeder contents and current database; fresh database only:
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan migrate:status
docker compose exec app php artisan test
```

**Seeding warning:** Liberu documentation says many writing seeders are non-idempotent; run `--seed` only once on a new disposable local database, not routinely on existing data. Note that some upstream revisions configure automatic migration/seeding through entrypoint variables; inspect `RUNNING_MIGRATIONS_AND_SEEDERS` and logs first to avoid a duplicate run.

Open from Ubuntu VM: `http://localhost:8001`. From macOS host: use the VM's reachable network address or configured port-forwarding. Mailpit UI from the VM: `http://localhost:8026`. Do not expose it publicly.

## 5. Hot-reload development overlay

Base image may be built for production-like execution with **no host source bind mount**. On every PHP/Blade change, `docker compose up -d --build` would be inefficient. Add an *explicit development-only* overlay after validating paths. Keep production deployment on original pinned image/Compose design.

### 5.1 Example `compose.dev.yaml`

The following is a **starting template that must be adapted** to the exact Dockerfile and user's filesystem. It assumes `app` uses `/var/www/html` as the project root and the image contains `vendor/`.

```yaml
services:
  app:
    volumes:
      - ./:/var/www/html
      - robit_vendor:/var/www/html/vendor
      - robit_node_modules:/var/www/html/node_modules
    environment:
      APP_ENV: local
      APP_DEBUG: 'true'

  vite:
    image: node:22-alpine
    working_dir: /var/www/html
    command: sh -lc 'npm ci && npm run dev -- --host 0.0.0.0'
    volumes:
      - ./:/var/www/html
      - robit_node_modules:/var/www/html/node_modules
    ports:
      - '${VITE_FORWARD_PORT:-5174}:5173'
    environment:
      CHOKIDAR_USEPOLLING: 'true'
    networks:
      - appnet

volumes:
  robit_vendor:
  robit_node_modules:
```

**Review required before use:**

- Named vendor volume may copy image contents on its first initialization; verify actual vendor install path. If empty, run appropriate Composer install inside app/Composer container without `--ignore-platform-reqs`.
- Confirm node version requested by `package.json` `engines`, if declared, before choosing `node:22-alpine`.
- Vite must actually bind `0.0.0.0:5173`; `vite.config.js` may already define port/host/HMR configuration. Adjust overlay accordingly.
- From Mac browsing VM, HMR WebSocket needs the VM host/IP or forwarded hostname (`server.hmr.host` and client port), not automatically `localhost`. Keep the HMR server limited to trusted networks.
- Bind mount overrides baked application code; maintain `vendor` and `node_modules` and writable storage correctly. Check permissions/UID mapping in the image.
- If initial entrypoint is production-only or runs compiled Octane/RoadRunner with caching, add a safe development command/service rather than blindly replacing it.
- For security, prefer host-local database/Redis port bindings and avoid cloud/public exposure.

Validate and start overlay:

```bash
docker compose -f docker-compose.yml -f compose.dev.yaml config --quiet
docker compose -f docker-compose.yml -f compose.dev.yaml up -d --build
# Confirm app and Vite containers and browser HMR:
docker compose -f docker-compose.yml -f compose.dev.yaml ps
```

**HMR acceptance:** edit a harmless Blade heading and a CSS token. The browser updates without full PHP image rebuild; `storage/logs` remains persistent; queue jobs continue; Docker service restarts preserve DB data; `npm ci` is compatible with lock file. Do not enable HMR in production.

## 6. Useful day-to-day commands

```bash
# See compose state
docker compose ps
docker compose logs -f app

# Artisan and test suite
docker compose exec app php artisan about
docker compose exec app php artisan route:list
docker compose exec app php artisan migrate:status
docker compose exec app php artisan test

# Database / Redis sanity (adapt credentials)
docker compose exec mysql mysql -u robit_user -p robit_handloom
docker compose exec redis redis-cli ping

# Composer/module scan inside pinned runtime
docker compose exec app composer validate
docker compose exec app php artisan module:list

# Stop but preserve volumes
docker compose down
```

Do not run `docker compose down -v` unless intending to permanently remove local DB, Redis and app storage volumes. Use backups for necessary migrations and schema experiments. Do not run `php artisan optimize` in hot-reload dev unless you understand its cache effects.

## 7. A safe Git and branch workflow

```bash
git status
git switch develop
git pull --ff-only origin develop
git switch -c feature/robit-artisan-profiles
# after tests:
git add <specific files>
git commit -m 'feat(artisans): add consented profile foundation'
git push -u origin feature/robit-artisan-profiles
```

Pull request checklist: tests; code review; no secrets; preserved MIT notice; migration path/backfill; policy/security tests; docs and operational change; screenshots where visual. Do not force-push shared branches without coordination.

## 8. Environment separation and configuration

| Setting | Local | Staging | Production |
|---|---|---|---|
| APP_DEBUG | true | false | false |
| Gateway | fake/sandbox | approved sandbox | approved live only |
| Mail | Mailpit | test-sender sandbox | authenticated transactional provider |
| DB | local named volume | isolated managed DB | managed DB with backup/replica as needed |
| Redis | dev container | dedicated | dedicated private service |
| Object storage | local/MinIO | staging bucket | secured production bucket/CDN |
| Media | dummy marked synthetic or approved | approved staging | consented originals/derivatives |
| Webhooks | sandbox | sandbox routed to staging | live verified endpoint |
| Data | disposable | anonymized/test | real with retention/privacy policies |

Never import customer/artist PII into Git, dev seeders or public screenshots. Use environment-specific secrets and endpoints. User-provided images need explicit usage consent, not automatic public upload.

## 9. CI pipeline stages

1. Composer validate and install from lock.
2. NPM clean install and Vite production build.
3. PHP formatter (`composer check` if present), static analysis and lint.
4. DB schema migration test on fresh DB and upgrade test on seeded prior version.
5. Unit, feature, security, authorization, payment webhooks and concurrency tests.
6. Dependency scans and Docker image scan.
7. Produce immutable image tagged by commit SHA; do not deploy floating `latest` without traceability.
8. Staging smoke test and approval; production rollout with backward-compatible migrations.
9. Health checks, queue connectivity, payment callback and low-stock monitoring.

Use secrets in CI-managed secret store. Default GitHub workflow access should have minimum scopes.

## 10. Production deployment minimum

- TLS and correct reverse proxy trusted headers.
- `APP_ENV=production`, `APP_DEBUG=false`, validated `APP_KEY`, DB credentials, Redis and mail sender.
- No publicly exposed MySQL/Redis/Mailpit/HMR services.
- Queue workers and scheduler monitored; Horizon only if Redis queue and correctly configured.
- Fixed image digest/SHA and safe migration rollout.
- Production error tracking and log redaction.
- Versioned object media and private bucket access.
- Payment webhooks signed; idempotent inbox and reconciliation.
- Backups encrypted, off-host and restore-drilled.
- Documented operator escalation path and rollback limitations.

## 11. Backup and restore verification

**Backup:** relational database dump or managed snapshot, app config/secrets separately, encrypted media/archive where needed. Keep off-host retention and monitor failures.  
**Restore drill:** create isolated test environment, restore snapshot, run consistency checks, verify sample product/order/maker data and ability to serve an authenticated account; ensure external payment/courier callbacks are disabled so restored staging never contacts customers.  
**Recovery target:** define RPO/RTO with business owner before launch; don't invent unrealistic guarantees.

## 12. Security hardening checklist

- Review upstream `SECURITY.md` and `CONFORMANCE.md` at pinned SHA.
- Prove record policies and cross-store scopes, including GraphQL/API/Filament/Blade/sitemap and background jobs.
- Confirm checkout uses exact server totals, reservation locks and verified payment callbacks.
- Deny direct payment amount/status editing in Filament.
- Require MFA for privileged staff, store private docs with authorization and audit financial actions.
- Harden guest order lookup, login and admin URLs with rate limits.
- Run package audit, tests and independent review before real payment activation.

## 13. Diagnosing typical failures

| Symptom | First checks | Avoid |
|---|---|---|
| App container exits | `docker compose ps`, `logs app`, env and entrypoint | Deleting volumes immediately |
| Cannot reach database | `DB_HOST=mysql`, DB credentials, mysql health, existing port | Setting DB_HOST=localhost inside app |
| Admin forbidden | real role/policy, panel authorization, store context | Granting global admin indiscriminately |
| No email | queue worker, Mailpit, mail log, failed jobs | Sending live production emails for tests |
| Blade changes don't show | source bind mount, view cache, correct container, Vite | Rebuilding image repeatedly without diagnosis |
| Browser HMR socket fails | VM IP, Vite port, `server.hmr.host`, firewall | Exposing dev server to internet |
| Payment pending | callback signature, provider verify, inbox, queue | Changing `payment_status` manually |
| Low stock despite units | reservation ledger, unique units, failed releases | Overwriting stock count arbitrarily |
| Slow pages | DB query count, media sizes, queues, render waterfall | Adding more animation |
| New migration breaks DB | table/constraint conflicts, upgrade test, backup | `migrate:fresh` on important DB |

## 14. Links and provenance

- Repo: https://github.com/liberusoftware/ecommerce-laravel
- Install: https://github.com/liberusoftware/ecommerce-laravel/blob/main/docs/INSTALLATION.md
- Compose: https://github.com/liberusoftware/ecommerce-laravel/blob/main/docker-compose.yml
- Operations: https://github.com/liberusoftware/ecommerce-laravel/blob/main/docs/OPERATIONS.md
- Security: https://github.com/liberusoftware/ecommerce-laravel/blob/main/SECURITY.md
- Laravel queues: https://laravel.com/framework/docs/13.x/queues
- Livewire navigation: https://livewire.laravel.com/docs/4.x/navigate

**End of Docker/DevOps runbook.**
