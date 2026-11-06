# The manager's provider registers modules

**Status**: accepted, 2026-11-06. Supersedes [ADR 0014](./0014-host-module-registration.md).

ADR 0014 kept a host copy of module loading because `ModuleManagerServiceProvider` stopped at the first broken module. `liberusoftware/module-manager` 1.7.0 adds per-module isolation to the provider itself, behind `modules.isolate_failures`, with the same rules the host carried: an optional module that fails is left out and reported, and a module in `modules.required` that fails stops the boot by name and cannot be disabled.

## Decision

- `config/app.php` registers `ModuleManagerServiceProvider` after `App\Providers\ModuleServiceProvider`, and `config/modules.php` sets `isolate_failures` to `true`.
- The host's `ModuleLoader`, `ModuleInventory`, `RequiredModuleFailed` and its `module:list`, `module:status`, `module:validate` and `module:cache` are removed in favour of the manager's.
- `App\Providers\ModuleServiceProvider` keeps what is the host's: team activation, the contracts modules plug into, the built-in integrations and the theme commands.
- The manager's `module:validate` only warns when Laravel package discovery would register a module unless given `--strict`. CI and the image build run it with `--strict`, so the build still fails on that.

- `liberusoftware/module-manager-filament` `^1.6` is the generic diagnostics page, **Operations → Foundation Operations** in the admin panel: every installed module, its status and its problems. The host's admin page now only offers modules to teams (ADR 0019).

## Consequences

- The host requires `liberusoftware/module-manager` `^1.7` and `liberusoftware/module-manager-filament` `^1.6`.
- `module:validate` and `module:list` word their output as the manager does.
- The page admits whom the host's `viewModuleDiagnostics` gate allows: `super_admin` or the `manage_modules` platform permission. Filament 1.6 ignores the gate and asks observability's `ObservabilityActor::isAdmin()` instead, which `App\Models\User` answers the same way.
- Filament 1.6 requires the `observability` module booted. Its next release drops that requirement (branch `feat/optional-observability`); upgrading to it removes observability, Pulse and Telescope from the lock, and the `dont-discover` entries and `ObservabilityActor` below go with them.
- Observability brings `laravel/pulse` and `laravel/telescope`. Both are in `extra.laravel.dont-discover`, so neither registers routes or records anything; their tables are created and stay empty. Observability also adds an `X-Correlation-ID` response header to `web` and `api`, and defines a `viewHorizon` gate that the host's `HorizonServiceProvider`, booting later, overrides.
