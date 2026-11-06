# Modules are offered by the platform and activated per team

**Status**: accepted, 2026-11-06. Supersedes [ADR 0015](./0015-per-store-module-activation.md).

ADR 0015 activated and configured modules per store. A team is the tenant and the unit a merchant manages, and a team with several stores wants one set of credentials and one choice per integration, not one per shopfront.

## Decision

- **Application:** Composer and `config/modules.php` decide what is installed and booted.
- **Platform:** a `module_availability` row per module says whether teams may use it, set on **Settings → Module availability**. Withdrawing a module turns it off for every team and keeps their settings.
- **Team:** a `team_modules` row per team and module holds `enabled` and encrypted `settings`, managed on **Integrations** by members with `store:manage`.
- **Store:** inherits its team's modules. A store with no team has none.

`App\Support\Modules\TeamModules` is the only reader and writer, and it is scoped. Modules ask by store through `ModuleState`, which resolves the store to its team, or by team through its `currentTeamId()`, `isActiveForTeam()` and `teamSettings()`. `ReactsToActivation` is called for each of the team's stores, and for a store added to a team that already uses the module.

There is no per-store override and no subscription entitlement.

## Consequences

- `ProvidesConfigurationPage::configurationUrl()` takes the team id, and a module's own pages gate on the team and `Abilities::MANAGE_MODULES`. The core never picks a store for a team with several: `currentStoreId()` is null there.
- `liberusoftware/ecommerce-contracts` changed in place at 1.0.0, which has not been released.
- Credentials are per team, with no environment fallback.
