<?php

namespace App\Support\Modules;

use App\Models\Store;
use Liberu\Ecommerce\Contracts\Stores\StoreDirectory;

final class Stores implements StoreDirectory
{
    public function names(): array
    {
        return Store::query()->orderBy('name')->pluck('name', 'id')->all();
    }
}
