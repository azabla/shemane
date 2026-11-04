<?php

namespace Liberu\Ecommerce\Contracts\Modules;

/** Optional, on a ModuleDefinition: configure on the module's own page instead of the core's generated form. */
interface ProvidesConfigurationPage
{
    public function configurationUrl(int $storeId): string;
}
