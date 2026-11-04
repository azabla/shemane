# Module architecture

How modules work in this platform, how to build one, and what is still open. Paths are relative to the repository root.

**Installation is not activation.** Composer and a deploy put code in a deployment. Each store then decides whether to use it. Stores never install PHP code, and nothing in the admin panel runs Composer.

| Phase | State |
| --- | --- |
| 1. Make the module manager real | Done ([ADR 0014](./adr/0014-host-module-registration.md)) |
| 2. Per-store lifecycle | Done ([ADR 0015](./adr/0015-per-store-module-activation.md)) |
| 3. Stable contracts | Done ([ADR 0016](./adr/0016-ecommerce-contracts-package.md)) |
| 4. Printful and DropXL | Boundary done, Printful extracted to a package. DropXL stays built in (§10) |
| 5. Distribution and CI | CI done. Publishing is **not** changed, and needs the maintainer's agreement (§11) |
| 6. Themes | Done. See [`THEMES.md`](./THEMES.md) |

Nothing here has been published or pushed. §12 lists what is open.

## 1. Responsibilities

| Who | Does | Where it is recorded |
| --- | --- | --- |
| Maintainers | Catalogue what is supported | `config/ecommerce-modules.php` |
| Deployment operator | Install with Composer, and enable | `composer.lock`, `MODULES_ENABLED` |
| The core | Discover, validate, register, gate | `ModuleServiceProvider`, `ModuleLoader` |
| Platform admin | Make a module available to stores | `store_modules.available`, on **Settings → Modules** |
| Merchant | Enable and configure it for their store | `store_modules.enabled` and `settings`, on **Integrations** |
| Existing authorization | Decide who may do any of this | `StoreAccess`, Gate abilities |

The lifecycle, in order:

1. **Catalogued:** listed as supported, with its install command.
2. **Installed:** `composer require`, committed lock file, deploy.
3. **Discovered:** the core finds its `module.json`.
4. **Validated:** manifest, Composer metadata, dependencies and versions all check out.
5. **Available to the deployment:** its provider boots.
6. **Available to a store:** a platform admin allows it.
7. **Enabled by that store.**
8. **Configured by that store:** every required setting is present.
9. **Authorized:** the person acting holds the permission.

A store's module is **active** only when steps 5 to 8 all hold. Steps 2 and 5 need a deploy or a restart. Steps 6 to 8 take effect immediately and register nothing.

## 2. Required and optional modules

`config('modules.required')` lists what the core's own classes need, and nothing else:

| Module | Why it is required |
| --- | --- |
| `module-manager` | Its classes do the discovery and validation |
| `tenancy` | `App\Traits\IsTenantModel` uses its `BelongsToTeam` |
| `users-roles` | `App\Models\User` extends its model |

`MODULES_DISABLED` cannot turn these off. They stay on, and `module:validate` fails on the misconfiguration. A required module that cannot load stops the boot with its name and the reason, because the alternative is a class-not-found error later.

Everything else is optional. An optional module that fails is left out, logged and reported, and every other module and store keeps working.

## 3. Registration

`App\Providers\ModuleServiceProvider` is the only path by which a module's provider is registered. It uses the manager's `Manifest`, `ModuleRegistry`, `ModuleValidator` and `RegistryCache` per module, rather than the manager's own provider, which stops at the first error. ADR 0014 has the reasoning.

A module boots when it is required, or its manifest sets `default_enabled`, or `MODULES_ENABLED` names it. `MODULES_DISABLED` overrides the last two.

```bash
php artisan module:list       # what is installed and booted, and why not. --json for scripts
php artisan module:validate   # every installed module, booted or not. Fails the build on any fault
php artisan module:status X   # one module in detail
php artisan module:cache      # validate, then cache; a cached boot skips validation
php artisan module:clear
php artisan module:features
```

Root `composer.json` lists `tenancy` and `users-roles` under `extra.laravel.dont-discover`, because both packages declare `extra.laravel.providers`. `module:validate` fails on any module that Laravel's package discovery would still register.

A provider that throws in `register()` is isolated. One that throws in `boot()` is not, because Laravel offers no hook for it. The composition test in §9 catches that before a release.

## 4. Building a module

```text
module-ecommerce-acme/                 liberusoftware/ecommerce-acme
├── composer.json                      type: liberu-module
├── module.json
├── src/
│   ├── AcmeServiceProvider.php
│   ├── AcmeDefinition.php             implements ModuleDefinition
│   └── AcmeFulfilment.php             implements FulfilmentProvider
├── config/ routes/ resources/ database/migrations/
└── tests/
```

`composer.json`. Do **not** add `extra.laravel.providers`: installing must boot nothing.

```json
{
    "name": "liberusoftware/ecommerce-acme",
    "type": "liberu-module",
    "version": "1.0.0",
    "require": { "php": "^8.5", "liberusoftware/ecommerce-contracts": "^1.0" },
    "autoload": { "psr-4": { "Liberu\\Ecommerce\\Acme\\": "src/" } },
    "extra": { "liberu": { "name": "ecommerce-acme" } }
}
```

`module.json`. Every key shown is required except `$schema` and `requires.capabilities`:

```json
{
    "name": "ecommerce-acme",
    "display_name": "Acme",
    "description": "Acme fulfilment.",
    "version": "1.0.0",
    "category": "adapter",
    "provider": "Liberu\\Ecommerce\\Acme\\AcmeServiceProvider",
    "requires": {
        "php": "^8.5",
        "laravel": "^13.0",
        "packages": { "liberusoftware/ecommerce-contracts": "^1.0" }
    },
    "capabilities": ["ecommerce.fulfilment.acme"],
    "default_enabled": false,
    "features": ["Sends a store's Acme lines to its Acme account"]
}
```

The rules `module:validate` enforces:

- `name` matches `extra.liberu.name`, and `type` is `liberu-module`.
- `version` matches the version Composer installed.
- `requires.packages` lists exactly the `liberusoftware/*` packages in Composer's `require`.
- `provider` is autoloadable and extends `ServiceProvider`.
- Module names, Composer packages and capabilities are unique across installed modules.
- PHP, Laravel, package and capability constraints are all satisfied.

The provider uses ordinary Laravel, and registers with the core by container tag:

```php
class AcmeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/acme.php', 'acme');
        $this->app->tag(AcmeDefinition::class, ModuleDefinition::class);
        $this->app->tag(AcmeFulfilment::class, FulfilmentProvider::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'acme');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'acme');
    }
}
```

Never import `App\…`. Everything a module needs from the core is in `liberusoftware/ecommerce-contracts`. Its README states the versioning rules.

**Migrations.** Prefix tables with the module's name. **Date each migration after the newest core table it references.** Laravel sorts migrations by filename across every path, so an earlier date fails its foreign key on a fresh MySQL database. SQLite does not notice, which is why CI runs both. Keep migrations backward compatible: rolling back an image does not roll back a schema.

**Modules holding personal data** must join GDPR export and erasure. `FulfilmentRequest` hands over the recipient at submission so that a module need not store it. No contract for joining GDPR exists yet, because no module stores any; add one before the first that does.

## 5. Declaring a module to stores

```php
class AcmeDefinition implements ModuleDefinition
{
    public function key(): string { return 'ecommerce-acme'; }
    public function label(): string { return 'Acme'; }
    public function description(): string { return 'Acme fulfilment.'; }

    public function settings(): array
    {
        return [
            new SettingField('api_token', 'API token', SettingField::SECRET, required: true),
            new SettingField('region', 'Region', SettingField::SELECT, required: true, options: ['eu' => 'Europe']),
        ];
    }

    public function validateSettings(array $settings): array
    {
        return []; // field => message, for what the rules cannot express
    }
}
```

The core stores settings encrypted per store, keeps only declared fields, never redisplays a secret, and treats a blank secret as "keep the current one". Rotating `APP_KEY` needs `APP_PREVIOUS_KEYS`, or stored settings cannot be read.

Reading state, by injecting `ModuleState`:

```php
$state->isActive('ecommerce-acme', $storeId);   // null means the current request's store
$state->settings('ecommerce-acme', $storeId);   // throws ModuleNotActive for a store without it
```

Under the hood this is `App\Support\Modules\StoreModules`. It is scoped, and every read names a store, so nothing crosses requests or jobs under Octane or Horizon.

Optional interfaces on the definition:

| Interface | For |
| --- | --- |
| `ProvidesPanelPlugins` | Adding Filament plugins to `admin` or `app` |
| `ProvidesConfigurationPage` | Configuring on the module's own page instead of the generated form |
| `ReactsToActivation` | Running something when a store turns it on or off |

### Filament

`App\Filament\ModulePlugins` adds plugins to both panels, from `panelPlugins()` or from the manifest's `presentation.filament` for the fleet's presentation packages. The manager's validator allows manifest plugins only on `presentation` modules, so an `adapter` uses `panelPlugins()`.

A module's pages are registered for the whole deployment, so each one must gate itself:

```php
public static function canAccess(): bool
{
    $state = app(ModuleState::class);
    $storeId = $state->currentStoreId();

    return $storeId !== null
        && $state->isActive('ecommerce-acme', $storeId)
        && Gate::allows(Abilities::MANAGE_STORE, $storeId);
}
```

Modules ask `Abilities::MANAGE_STORE`, `MANAGE_CATALOGUE` or `MANAGE_ORDERS`, each taking a store id, and never name a role. `strictAuthorization()` makes a module model with no policy fail loudly.

### Routes

```php
Route::middleware(['web', 'store.module:ecommerce-acme'])->get('/acme/status', ...);
```

`store.module` returns 404 for a store without the module active. Leave supplier webhooks out of it, so that in-flight orders still get their updates after a store switches the module off.

### When a module is off

| Situation | Behaviour |
| --- | --- |
| Storefront product whose supplier is inactive | Page renders, and `ProductPresenter` reports it sold out, so themes need no change |
| Checkout contains such a line | Refused before payment, on both checkout paths |
| Queued order when the module has gone inactive | Moves to `supplier_failed`, and staff are notified. Retrying later is safe |
| Module's pages and routes | Hidden and denied; 404 |
| Disabled in one store | Other stores are unaffected, and its settings are kept |
| Removed from `MODULES_ENABLED`, or the package removed | The same as inactive for every store. Data stays |

## 6. Fulfilment

A product's `supplier_id` decides where its lines go, and the request never does.

```php
class AcmeFulfilment implements FulfilmentProvider
{
    public function supplierKey(): string { return 'acme'; }             // the value in products.supplier_id
    public function moduleKey(): string { return 'ecommerce-acme'; }

    public function submit(FulfilmentRequest $request, array $settings): FulfilmentResult { /* … */ }
    public function track(string $supplierReference, array $settings): ?TrackingUpdate { /* … */ }
}
```

The contract a provider must keep:

- **Send `$request->reference` to the supplier and rely on it to dedupe.** A timeout leaves the outcome unknown, and the core sends the same reference again.
- **Set a timeout** on every HTTP call.
- **Return a result only for a real answer.** `FulfilmentResult::rejected()` means the supplier refused, and a person must act.
- **Throw `SupplierUnavailable`** for no connection, a timeout, a 5xx or a 429. The core retries.

The flow:

1. Before charging, `CheckoutService::assertFulfillable()` refuses a line whose supplier is not active for its store.
2. After payment, `Fulfilment\OrderFulfilments` writes one `order_fulfilments` row per supplier, then queues one `DispatchDropshippingOrder` per row.
3. Each job sends only that supplier's lines, with that store's credentials. It retries an outage and cannot overlap itself. A placed row is never sent again.
4. The order moves to `processing` when every supplier has it, and to `supplier_failed` if any refuses. Staff retry from the order page, which resends only what failed.
5. `fulfilment:sync-tracking` runs hourly and records tracking on each row.

**Why `order_fulfilments` exists.** One `supplier_order_reference` per order could not represent two suppliers, and `supplierFor()` responded to a mixed cart by sending it to nobody after taking payment. For a single-supplier order, the existing `orders.supplier_*` columns are still written, so older views keep working.

**Catalogues.** A module implements `SupplierCatalog` to describe products as `ImportedProduct`, and calls the core's `ProductImporter` to write them. Modules never touch `App\Models\Product`, and the importer writes into one named store.

## 7. Installing, upgrading and removing

**Install**

1. `composer require liberusoftware/ecommerce-acme`, and commit the lock file.
2. Add it to `MODULES_ENABLED`.
3. Merge, let CI pass, build and deploy. The deploy runs its migrations.
4. On **Settings → Modules**, choose the stores. Each merchant then configures and enables it on **Integrations**.

To develop locally against an unpublished module, add a temporary path repository with `composer config repositories.acme path ../module-ecommerce-acme`, and do not commit it.

**Upgrade**

1. A Dependabot or manual PR updates the lock file, and CI runs the matrix.
2. Build runs `module:validate`. Deploy runs `migrate --isolated`, then restarts Octane and Horizon.

**Remove**

These are separate steps on purpose, and the data stays unless you choose step 3.

1. Merchants disable it. The Modules page shows which stores are allowed.
2. Remove it from `MODULES_ENABLED` and deploy. The code is present and does not boot.
3. Optional and destructive, while the files still exist: `php artisan migrate:reset --path=modules/<name>/database/migrations`, and delete its `store_modules` rows.
4. `composer remove`, and deploy.

## 8. Why the admin panel does not run Composer

A web process able to write code turns any admin compromise into code execution. The Kubernetes setup runs several replicas from one image, so a change in one pod is not in the others and disappears on restart. And it would put production out of step with the lock file in Git. The Modules page shows the commands instead.

## 9. Testing and CI

| Level | What it proves | Where |
| --- | --- | --- |
| Boot | Real application boots with broken, missing and disabled modules | `tests/Feature/Modules/ModuleBootTest.php`, `OptionalModuleBootTest.php` |
| Acceptance | One module on, in one store and not another, with isolated settings, routes and services, and authorization | `tests/Feature/Modules/StoreModuleLifecycleTest.php` |
| Commerce | Mixed carts, no charge when unfulfillable, retries, tracking, per-store credentials | `tests/Feature/SupplierFulfilmentTest.php`, `DispatchDropshippingOrderTest.php` |
| Composition | Any module, after a real `composer require` | `tests/Composition/InstalledModuleTest.php`, via `.github/scripts/verify-module.sh` |
| Module's own | Its behaviour | The module's `tests/`, run from a host |

`tests/Fixtures/modules/fixture-supplier` is the acceptance fixture: a complete module with settings, a route, a migration, a Filament page and a fulfilment provider. It is never published.

`.github/workflows/modules.yml`:

| Job | Fails when |
| --- | --- |
| `core` | The publisher's repository guard is removed; an installed module or a `composer.json` theme is committed; a manifest or theme is invalid; anything optional boots by default |
| `installed-module` | The fixture or Printful fails a real install, `module:validate`, the composition test or its own tests |
| `installed-theme` | The fixture theme fails a real install or Vite build, or loses its own classes |
| `mysql` | Module and commerce tests fail on MySQL 8.4 |
| `image` | The built image lacks a required module, or `module:validate` fails in it |

`tests.yml` remains the core-without-optional-modules suite, since it installs none. A new module joins `installed-module` by adding a matrix row.

A new module's own repository keeps the three thin workflows in [`MODULE_DEVELOPMENT.md`](./MODULE_DEVELOPMENT.md) §4, on bare tags.

## 10. Printful and DropXL

**Printful** is `packages/ecommerce-printful`, a complete `liberu-module` package requiring only the contracts. `jmhclothing` and `coldphysique` use it. It is a path repository because publishing needs approval; to split it, move the directory to its own repository unchanged and replace the path repository with a version.

**DropXL stays built in**, at `app/Integrations/Dropxl`, activated per store through the same contracts. In the site repositories, only tests inherited from the core mention it, and no site's themes or seeders do, and nothing confirms its API URL is real. Extracting it would prove nothing. Decide whether to keep it once someone needs it.

**What changes when a site merges this.** The sites are not modified by this work. On their next merge from the core:

| Change | What the site must do |
| --- | --- |
| New `tenancy`, contracts and `dont-discover` entries | Resolve `composer.lock` by re-running Composer |
| Supplier credentials leave `.env` | Enter them per store on **Integrations**. `PRINTFUL_API_TOKEN` and `DROPXL_API_KEY` are no longer used |
| A site using Printful | `composer require liberusoftware/ecommerce-printful`, add `ecommerce-printful` to `MODULES_ENABLED`, then allow and enable it. **Until then its products show as sold out** |
| `printful:import-products` | Now needs `--store=<id>` on a multi-store deployment |
| `dropshipping.suppliers.printful.categories` | Moves to `printful.categories` |
| Site tests with `supplier_id => 'printful'` | Must activate the module in `setUp`, or checkout rightly refuses them |
| Theme checkouts with a supplier `<select>` | Harmless, as the list is empty and the value is ignored, but worth removing |

## 11. The package publisher

`.github/workflows/sync-packages.yml` copies `modules/*` and `themes/*` from a host into shared repositories, tags them and updates Packagist. It is unchanged, apart from the guard limiting it to `liberusoftware/ecommerce-laravel`, which `modules.yml` now protects.

**The problem.** It points the opposite way from independently maintained modules, and products overwrite each other. `module-users-roles` has sync commits from three products. `module-module-manager` took commits from five products on 2026-11-02, moving from `1.4.3` to `v1.6.2`, with `v` tags that skip the release gates that trigger only on bare tags ([ADR 0005](./adr/0005-bare-version-tags.md)). The GitHub API reports `main` unprotected on `module-users-roles`, `module-tenancy` and `module-module-manager`, so its review-PR path does not apply to them.

**What this work did not change.** The publisher still exists, `modules/tenancy` and `modules/users-roles` are still tracked, and the merged publisher has still never run from this repository. The `packages/` directory is outside its paths, so it cannot publish the contracts or Printful. Nothing on this branch touches `modules/**`, so merging it will not trigger a run.

**The recommended transition**, which needs the maintainer's agreement because other products share these repositories:

1. Publish `ecommerce-contracts` and `ecommerce-printful` from their own repositories, on bare tags, with Packagist's GitHub hook.
2. Pin `tenancy`, `users-roles` and `module-manager` to exact versions, and review each bump.
3. Remove the tracked `modules/tenancy` and `modules/users-roles`, ignore `modules/*`, and drop their path repository. The image already takes installed modules from Composer, so the Dockerfile only loses its `COPY modules/users-roles` line.
4. Remove `sync-packages.yml` from this repository only, and its script and README section. Other products keep theirs for as long as they choose.

Consequences of doing so: edits to those two modules move to their own repositories, a clone no longer contains their source until `composer install`, and the fleet's `THEMES.md` §3.2 needs an ADR or an upstream change.

## 12. Open decisions and limits

**Need a decision**

| Decision | Recommendation | Who |
| --- | --- | --- |
| Does ecommerce leave host-to-package publishing? (§11) | Yes | Upstream maintainer |
| Publish `ecommerce-contracts` and `ecommerce-printful`, and public or private? | Public on Packagist | You, with the maintainer |
| One package per integration, against the fleet's four ([ADR 0016](./adr/0016-ecommerce-contracts-package.md)) | One. Needs a catalogue entry in `ECOMMERCE.md` for both packages | Upstream maintainer |
| Do the wave `module-ecommerce-*` packages stay the plan? | Pause them. They have their own data model, and `module-ecommerce-dropshipping`'s manifest is invalid | Upstream maintainer |
| Move to manager `1.4.4`+ | Hold until reviewed, as §11 explains. An upstream fix giving its provider per-module isolation would make ADR 0014 unnecessary | Upstream maintainer |
| The checkout's "ship to recipient" option still adds `shipping.drop_shipping_premium` | Kept, so that no price changed in this work. It no longer sends anything to a supplier, so decide whether it should still cost more | You |

**Known limits**

- Supplier APIs have not been called. The tests use response shapes from documentation and memory, and Printful's shipment fields in particular need checking against a real account before going live.
- Tracking is polled hourly. There are no supplier webhooks, tracking is not emailed to customers, and it does not move the order's status.
- A shopper can still add an unavailable supplier product through a direct POST to the cart. Checkout refuses it before payment.
- `printful.categories` is per deployment rather than per store.
- A module's own tests extend the host's test case, so they run from a host. Moving Printful to its own repository needs `liberusoftware/package-testbench` for standalone runs.
- `modules.yml` has not run on GitHub. Each job's commands were run locally, in Docker, against MySQL 8.4 and in a real image build. Site CI status could not be read, because those repositories are private.

## 13. What was found before this work

Kept briefly, because it explains the design. The first assessment is in this file's Git history.

| Finding | Now |
| --- | --- |
| The manager was installed and never registered, so no `module:*` command existed and `MODULES_*` did nothing | Fixed |
| `users-roles` and `tenancy` booted through package discovery, bypassing it | Fixed |
| The image lacked `modules/module-manager` | Fixed, and proved in a real build |
| One invalid installed manifest would stop every boot | Fixed for optional modules |
| Supplier credentials were global to a deployment | Per store |
| A shopper's request could choose the supplier | Removed |
| A cart with two suppliers reached neither, after payment | Fixed |
| An outage was recorded as a supplier's rejection, so the job's retry could not happen | Fixed |
| Nothing wrote tracking | `fulfilment:sync-tracking` |
| `dropship_*` wave tables and `App\Modules` were attempts that never connected to a running host | Neither is in use. See ADR 0011 and §12 |
| The publisher ran in private site repositories, and could have published from them | Guarded; sites get this on their next merge |
