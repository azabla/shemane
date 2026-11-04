<?php

namespace Liberu\Ecommerce\FixtureThrows;

use Illuminate\Support\ServiceProvider;
use RuntimeException;

class FixtureThrowsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        throw new RuntimeException('fixture provider failed');
    }
}
