<?php

namespace Liberu\Ecommerce\Contracts\Modules;

/** Optional, on a ModuleDefinition: adds Filament plugins to a panel. Nothing here requires Filament. */
interface ProvidesPanelPlugins
{
    /**
     * @param  string  $panel  `admin` or `app`
     * @return list<object> Filament plugin instances
     */
    public function panelPlugins(string $panel): array;
}
