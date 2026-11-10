<?php

namespace Liberu\Ecommerce\Contracts\Stores;

/** The core implements this. Lets a platform-wide module page offer a choice of store without the core's models. */
interface StoreDirectory
{
    /** @return array<int, string> every store's name by id, ordered by name */
    public function names(): array;
}
