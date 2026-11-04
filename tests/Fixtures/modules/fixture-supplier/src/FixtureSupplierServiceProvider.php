<?php

namespace Liberu\Ecommerce\FixtureSupplier;

use Illuminate\Support\ServiceProvider;
use Liberu\Ecommerce\Contracts\Modules\ModuleDefinition;

class FixtureSupplierServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->instance('fixture-supplier.booted', true);
        $this->app->tag(FixtureDefinition::class, ModuleDefinition::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
