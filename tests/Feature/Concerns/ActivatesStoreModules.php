<?php

namespace Tests\Feature\Concerns;

use App\Models\Store;
use App\Support\Modules\StoreModules;

trait ActivatesStoreModules
{
    /** @param  array<string, mixed>  $settings */
    protected function activateModule(string $module, array $settings, ?Store $store = null): Store
    {
        $store ??= Store::query()->orderBy('id')->firstOrFail();
        $modules = app(StoreModules::class);
        $modules->setAvailable($module, $store->id, true);
        $modules->configure($module, $store->id, $settings);
        $modules->enable($module, $store->id);

        return $store;
    }
}
