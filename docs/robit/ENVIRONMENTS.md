# Robit Handloom — environments

Local, staging and production run the same image. Only configuration differs, and it lives in the
environment, never in git. Local setup is in [`DEVELOPMENT.md`](./DEVELOPMENT.md). Staging and
production do not exist yet. This is the minimum that has to be true before either is created.

## Settings that differ

| Setting | Local | Staging | Production |
| --- | --- | --- | --- |
| `APP_ENV` | `local` | `staging` | `production` |
| `APP_DEBUG` | `true` | `false` | `false` |
| `APP_KEY` | generated per checkout | own key | own key, held in the secret store |
| `APP_URL` | `http://localhost:8000` | staging HTTPS host | live HTTPS host |
| `ECOMMERCE_MODE` | `single` | `single` | `single` (ADR-002) |
| `DB_*` | compose `mysql`, named volume | its own database | managed database with backups |
| `REDIS_*` | compose `redis` | its own instance | private instance with a password |
| `CACHE_DRIVER` / `SESSION_DRIVER` | `file` | `redis` | `redis` |
| `QUEUE_CONNECTION` | `sync` | `redis` plus a worker | `redis` plus a monitored worker |
| `MAIL_*` | Mailpit | sandbox sender | authenticated transactional provider |
| `STRIPE_*` | test keys or empty | test keys | live keys, only after M6 |
| `PAYPAL_*` | empty | empty | empty (see below) |
| `SEED_DEMO_CATALOGUE` | `true` | `false` | `false` |
| `SEED_ADMIN_PASSWORD` | set | set once, then removed | set once, then removed |
| `RUNNING_MIGRATIONS_AND_SEEDERS` | — | `false`, migrate as a release step | `false`, migrate as a release step |
| `LOG_LEVEL` | `debug` | `info` | `warning` |

`PaymentGatewayFactory::isConfigured()` offers a gateway at checkout as soon as its keys are filled.
An empty `PAYPAL_*` is therefore what keeps PayPal off, and it stays empty until its amount
verification is rebuilt (M6). Stripe live keys wait for the payment-attempt ledger and webhook
inbox, also in M6.

## Rules for staging and production

- **Separate everything.** Staging never shares a database, Redis, bucket, mail sender or webhook
  endpoint with production. A restored backup in staging must not be able to email customers or
  call a payment provider.
- **Secrets stay out of git.** Use the host's secret store or GitHub Actions secrets. `.env` is
  gitignored; keep it that way. Never paste keys into issues, PRs or logs.
- **The image is pinned.** Deploy the commit-SHA image built by CI (`ghcr.io/azabla/shemane`, once
  `PUBLISH_IMAGE=true`), never a floating `latest`.
- **Migrations are a release step.** Run `php artisan migrate --force` once per release, never from
  every container start. Seeders are not all idempotent; run them only on an empty database.
- **Only the proxy is public.** MySQL, Redis, Mailpit and Vite are never exposed.
  `TrustProxies` trusts every proxy (`$proxies = '*'`), so the app container must be reachable only
  through the reverse proxy. Otherwise a client can forge `X-Forwarded-For` and get around per-IP
  rate limits.
- **TLS ends at the proxy.** Session cookies are `secure` by default outside local and testing
  (`config/session.php`).
- **Hostnames are explicit.** `TrustHosts` accepts `APP_URL` and its subdomains plus every channel
  domain, and answers 400 to anything else. Add each storefront domain as a channel domain before
  pointing DNS at it.
- **Health checks.** The image's `HEALTHCHECK` only proves that Octane answers. Orchestrators should
  probe `GET /health`, which answers on any host and returns `503` when the database is unreachable.
- **Queues before real jobs.** `config/queue.php` has `after_commit => false`, so set it, or use
  `afterCommit()`, before moving off `sync` (R-08).
- **Staff accounts.** MFA is not enforced yet (S-14); it must be before production. Change the
  seeded admin password by hand on first login. Nothing forces it.

## Before production

Open blockers are tracked in [`RISKS.md`](./RISKS.md) and the backlog. Production stays closed to
customers until the M6 payment work and the M3 security tests are done.
