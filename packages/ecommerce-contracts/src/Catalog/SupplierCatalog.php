<?php

namespace Liberu\Ecommerce\Contracts\Catalog;

/** Reads what a supplier offers. It describes products and never writes them: see ProductImporter. */
interface SupplierCatalog
{
    public function supplierKey(): string;

    /**
     * @param  array<string, mixed>  $settings  this store's settings for the module
     * @return list<int|string> the supplier's product ids
     */
    public function productIds(array $settings): array;

    /** @param  array<string, mixed>  $settings */
    public function product(int|string $supplierProductId, array $settings): ImportedProduct;
}
