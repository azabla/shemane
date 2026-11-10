<?php

namespace Liberu\Ecommerce\FixtureSupplier\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;

class FixturePlugin implements Plugin
{
    public function getId(): string
    {
        return 'fixture-supplier';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([FixturePage::class]);
    }

    public function boot(Panel $panel): void {}
}
