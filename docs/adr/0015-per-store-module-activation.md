# Modules are installed per deployment and activated per store

**Status**: accepted, 2026-11-04.

ADR 0011 made enablement deployment configuration, because providers, routes and config caches must be fixed at boot. That is right and stays, but it cannot express "this store uses Printful and that one does not", and every store in a deployment shares one codebase.

## Decision

Two levels, which never overlap:

- **Deployment:** Composer installs, `MODULES_ENABLED` boots. Both need a deploy or a restart.
- **Store:** one `store_modules` row per store and module holds `available` (the platform's choice), `enabled` (the merchant's) and encrypted `settings`. It changes immediately and registers nothing.

No row means off. A store's module is active only when the module is booted, available, enabled and has every required setting. `App\Support\Modules\StoreModules` is the only reader and writer, and it is scoped, so its memory cannot cross requests or jobs under Octane or Horizon. Every read names a store.

A module's routes, pages and services stay registered for the whole deployment and are gated per store: the `store.module:<name>` middleware returns 404, pages check in `canAccess()`, and `ModuleState::settings()` throws `ModuleNotActive`.

## Why not `liberusoftware/settings`

Its scoped settings support secrets, but they would add a module and a table, and activation state belongs on the same row as its configuration. Revisit if a third kind of per-store setting appears.

## Consequences

- Credentials are per store, with no environment fallback, because falling back is how one store ends up using another's account. Rotating `APP_KEY` needs `APP_PREVIOUS_KEYS`, or stored settings cannot be read.
- `settings` is one encrypted column, so the database cannot query inside it. Nothing needs to.
