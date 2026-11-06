<?php

namespace Tests\Feature\Concerns;

use App\Models\Store;
use App\Models\Team;
use App\Support\Modules\TeamModules;

trait ActivatesModules
{
    /**
     * Offers the module, then configures and enables it for the store's team. A store without a team gets one.
     *
     * @param  array<string, mixed>  $settings
     */
    protected function activateModule(string $module, array $settings, ?Store $store = null): Store
    {
        $store ??= Store::query()->orderBy('id')->firstOrFail();

        if ($store->team_id === null) {
            $store->forceFill(['team_id' => Team::factory()->create()->id])->save();
        }

        app()->forgetScopedInstances();
        $modules = app(TeamModules::class);
        $modules->setAvailable($module, true);
        $modules->configure($module, $store->team_id, $settings);
        $modules->enable($module, $store->team_id);

        return $store;
    }

    protected function deactivateModule(string $module, int $storeId): void
    {
        $modules = app(TeamModules::class);
        $modules->disable($module, $modules->teamOfStore($storeId));
    }
}
