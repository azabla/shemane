# The host registers modules with the manager's classes, not its provider

**Status**: superseded by [ADR 0018](./0018-the-managers-provider-registers-modules.md), 2026-11-06. Accepted 2026-11-04.

`liberusoftware/module-manager` has been installed since ADR 0011, and its provider was never registered. So `MODULES_ENABLED` did nothing, no `module:*` command existed, and `users-roles` and `tenancy` booted through Laravel package discovery regardless of configuration.

Registering `ModuleManagerServiceProvider` would fix the wiring and add a new failure. Its `register()` stops at the first problem: `ModuleDiscovery::discover()` throws on any installed manifest that fails to parse, enabled or not, and `ModuleValidationGuard` throws on any selected module that fails validation. One broken optional integration would take down every store in the deployment on every request. The class is `final`.

## Decision

`App\Providers\ModuleServiceProvider` is the only registrar. `App\Support\Modules\ModuleLoader` uses the manager's `Manifest`, `ModuleRegistry`, `ModuleValidator` and `RegistryCache`, and applies them per module:

- An optional module that fails is left out, logged and reported by `module:list`. `module:validate` fails on it, so a build stops before a deployment does.
- A module in `config('modules.required')` that fails stops the boot with its name and the reason.
- `MODULES_DISABLED` cannot turn off a required module. It stays on and `module:validate` reports the misconfiguration.
- `extra.laravel.dont-discover` in the root `composer.json` stops discovery registering modules, and `module:validate` fails on any module that discovery would still register.

`module-manager`, `tenancy` and `users-roles` are required: `App\Traits\IsTenantModel` uses the tenancy package, and `App\Models\User` extends the users-roles model.

## Consequences

- The manager's selection and validation rules still apply, so this stays compatible with its manifests. The loader reads module names out of the manager's messages, so a change to their format surfaces as a failed `ModuleBootTest`.
- A provider that throws in `register()` is isolated. One that throws in `boot()` is not, because Laravel offers no hook for it; the composition test in `.github/workflows/modules.yml` is what catches it.
- Fewer than the manager's commands are reused: `module:list`, `module:validate`, `module:status` and `module:cache` are the host's, because the manager's versions throw or cannot see modules excluded at load.

Upstream could make this ADR unnecessary by giving the manager's provider this per-module behaviour. Until then, the host carries it. The manager is not being replaced.
