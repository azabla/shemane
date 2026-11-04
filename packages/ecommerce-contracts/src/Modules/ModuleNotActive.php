<?php

namespace Liberu\Ecommerce\Contracts\Modules;

use RuntimeException;

final class ModuleNotActive extends RuntimeException
{
    public static function for(string $module, ?int $storeId): self
    {
        return new self("Module [{$module}] is not active for store [".($storeId ?? 'none').'].');
    }
}
