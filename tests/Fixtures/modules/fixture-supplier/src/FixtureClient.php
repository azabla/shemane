<?php

namespace Liberu\Ecommerce\FixtureSupplier;

use Liberu\Ecommerce\Contracts\Modules\ModuleState;

/** A service that reads its store's settings, so it cannot run for a store without the module. */
class FixtureClient
{
    public function __construct(private readonly ModuleState $state) {}

    public function regionFor(?int $storeId = null): string
    {
        return $this->state->settings('fixture-supplier', $storeId)['region'];
    }
}
