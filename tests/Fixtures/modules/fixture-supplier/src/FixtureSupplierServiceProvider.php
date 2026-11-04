<?php

namespace Liberu\Ecommerce\FixtureSupplier;

use Illuminate\Support\ServiceProvider;

class FixtureSupplierServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->instance('fixture-supplier.booted', true);
    }
}
