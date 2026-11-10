<?php

namespace Liberu\Ecommerce\Contracts\Fulfilment;

final readonly class FulfilmentLine
{
    public function __construct(
        public int $productId,
        public int $quantity,
        public float $unitPrice,
        public ?string $sku = null,
        public ?string $supplierVariantId = null,
    ) {}
}
