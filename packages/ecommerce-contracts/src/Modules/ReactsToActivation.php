<?php

namespace Liberu\Ecommerce\Contracts\Modules;

/** Optional, on a ModuleDefinition: runs when a store turns the module on or off. */
interface ReactsToActivation
{
    public function activated(int $storeId): void;

    public function deactivated(int $storeId): void;
}
