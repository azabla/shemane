<?php

namespace App\Support\Modules;

use RuntimeException;

/** The application cannot run without this module, so stopping with its name beats a later class-not-found. */
final class RequiredModuleFailed extends RuntimeException
{
    public static function because(string $name, string $problem): self
    {
        return new self("Required module [{$name}] cannot load: {$problem} Run composer install, then php artisan module:validate.");
    }
}
