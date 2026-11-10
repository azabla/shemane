<?php

namespace Liberu\Ecommerce\Contracts\Fulfilment;

/** Optional, on a FulfilmentProvider: staff can ask whether the supplier has a SKU. */
interface ChecksAvailability
{
    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed> the supplier's answer
     *
     * @throws SupplierUnavailable
     */
    public function availability(string $sku, int $quantity, array $settings): array;
}
