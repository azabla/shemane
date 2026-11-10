<?php

namespace Liberu\Ecommerce\Contracts\Catalog;

/** The core implements this. Modules describe products; the core owns writing them into a store. */
interface ProductImporter
{
    /**
     * Creates or updates the store's product for this supplier product.
     *
     * @param  int|null  $ontoProductId  map onto this existing product of the store instead
     * @return int the local product id
     */
    public function import(int $storeId, ImportedProduct $product, ?int $ontoProductId = null): int;
}
