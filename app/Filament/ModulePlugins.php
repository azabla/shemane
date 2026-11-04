<?php

namespace App\Filament;

use App\Support\Modules\ModuleInventory;
use App\Support\Modules\StoreModules;
use Filament\Contracts\Plugin;
use Liberu\Ecommerce\Contracts\Modules\ProvidesPanelPlugins;
use RuntimeException;

/** The plugins booted modules add to a panel: from their manifest, or from their definition. */
final readonly class ModulePlugins
{
    public function __construct(private ModuleInventory $inventory, private StoreModules $modules) {}

    /** @return list<Plugin> */
    public function forPanel(string $panel): array
    {
        $plugins = [];

        foreach ($this->inventory->booted as $manifest) {
            foreach ($manifest->filamentPlugins($panel) as $class) {
                $plugins[] = method_exists($class, 'make') ? $class::make() : app($class);
            }
        }

        foreach ($this->modules->definitions() as $definition) {
            if ($definition instanceof ProvidesPanelPlugins) {
                array_push($plugins, ...$definition->panelPlugins($panel));
            }
        }

        foreach ($plugins as $plugin) {
            if (! $plugin instanceof Plugin) {
                throw new RuntimeException('A module returned something that is not a Filament plugin: '.get_debug_type($plugin));
            }
        }

        return $plugins;
    }
}
