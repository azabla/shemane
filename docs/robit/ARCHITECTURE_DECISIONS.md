# Robit Handloom — architecture decisions

Numbering follows blueprint §24. Status is **accepted** (decided and in effect), **proposed**
(recommended, waiting on the owner) or **open**.

## ADR-001 Adopt the Liberu baseline at a pinned SHA — accepted

Build on `liberusoftware/ecommerce-laravel` at `befbc58` rather than a new commerce engine. Its
checkout money core (`CheckoutService`), store scoping (`IsStoreScoped`), host resolution
(`ResolveChannel`) and order state machine (`Order::transitionTo()`) already meet several Robit
invariants and carry tests. Robit code is additive: extension tables prefixed `rh_`, a storefront
theme, and new services. Keep the MIT `LICENSE` and its notice.

## ADR-002 Single merchant — accepted

Run with `ECOMMERCE_MODE=single` (the default in `config/ecommerce.php:20`): one Team, one Store, one
Channel. Single mode already blocks self-service team creation (`TeamPolicy::create`). Store scoping
stays switched on; one store does not make missing authorisation safe (blueprint §16.4).

## ADR-003 Database engine — accepted

MySQL 8.0, as shipped in `docker-compose.yml`. Tests run on sqlite `:memory:`, as upstream CI does.
No Robit migration may use engine-specific SQL without a sqlite path or a MySQL-only test group.

## ADR-004 Payments and settlement currency — open

Blocked on M0. Stripe and PayPal stay disabled for the Robit channel. The PayPal path also fails
SECURITY_GATE S-07. Robit adds a provider-neutral payment-attempt ledger and webhook inbox (M6)
before any adapter is enabled.

## ADR-010 Deployment and local environment — accepted for development

Production image unchanged in shape (Octane/RoadRunner, cached config). Development uses an additive
`compose.dev.yaml`: bind-mounted source, `artisan serve`, Vite HMR, loopback-only service ports,
vendor and node_modules on the VM disk. See `DEVELOPMENT.md`.

## ADR-011 Where Robit code lives — proposed

Upstream keeps modules in their own repositories. For Robit, start inside this application under
`app/Robit/` (or the agreed namespace) with its own migrations prefixed `rh_`. Extract to modules only
once a boundary is stable. Owner to confirm before M5.

## ADR-012 Git remotes — accepted (2026-11-11)

`origin` is `azabla/shemane`; `upstream` is `liberusoftware/ecommerce-laravel`, fetch only (its push URL is
set to `DISABLED`). Upstream changes are taken by `git fetch upstream` and a deliberate merge (R-09).
`azabla/shemane` is public by the owner's choice. The security findings stay in the gitignored
`docs/robit/private/SECURITY_GATE.md` until each is fixed (R-06).
