<?php

namespace App\Providers;

use App\Console\Commands\Modules\ModuleCacheCommand;
use App\Console\Commands\Modules\ModuleListCommand;
use App\Console\Commands\Modules\ModuleStatusCommand;
use App\Console\Commands\Modules\ModuleValidateCommand;
use App\Support\Modules\ModuleInventory;
use App\Support\Modules\ModuleLoader;
use App\Support\Modules\RequiredModuleFailed;
use App\Support\Modules\StoreModules;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Liberu\Ecommerce\Contracts\Modules\ModuleState;
use Liberu\Foundation\ModuleManager\Cache\RegistryCache;
use Liberu\Foundation\ModuleManager\Console\ClearModulesCommand;
use Liberu\Foundation\ModuleManager\Console\ListFeaturesCommand;
use Liberu\Foundation\ModuleManager\ModuleRegistry;
use Liberu\Foundation\ModuleManager\ModuleValidator;
use Throwable;

/** The one path by which a module's provider is registered. See docs/MODULE_ARCHITECTURE.md. */
class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleLoader::class, fn ($app) => new ModuleLoader(
            $app->make(ModuleValidator::class),
            $app->make(RegistryCache::class),
            (array) config('modules.paths', []),
            (array) config('modules.enabled', []),
            (array) config('modules.disabled', []),
            (array) config('modules.required', []),
            (bool) config('modules.cache', false),
            (string) config('modules.cache_path'),
        ));
        $this->app->singleton(ModuleInventory::class, fn ($app) => $app->make(ModuleLoader::class)->load());
        $this->app->singleton(ModuleRegistry::class, fn ($app) => new ModuleRegistry($app->make(ModuleInventory::class)->manifests));
        $this->app->scoped(StoreModules::class);
        $this->app->alias(StoreModules::class, ModuleState::class);

        $inventory = $this->app->make(ModuleInventory::class);

        foreach ($inventory->booted as $name => $manifest) {
            if ($name === 'module-manager') {
                continue;
            }

            try {
                $this->app->register($manifest->provider());
            } catch (Throwable $e) {
                if ($inventory->isRequired($name)) {
                    throw RequiredModuleFailed::because($name, $e->getMessage());
                }

                $inventory->markFailed($name, "Its provider failed to register: {$e->getMessage()}");
            }
        }
    }

    public function boot(ModuleInventory $inventory): void
    {
        foreach ($inventory->problems as $name => $problems) {
            Log::warning("Module [{$name}] was not booted.", ['problems' => $problems]);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                ModuleListCommand::class,
                ModuleValidateCommand::class,
                ModuleStatusCommand::class,
                ModuleCacheCommand::class,
                ClearModulesCommand::class,
                ListFeaturesCommand::class,
            ]);
        }
    }
}
