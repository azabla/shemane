<?php

namespace Liberu\Ecommerce\Contracts\Catalog;

final readonly class ImportedVariant
{
    /** @param  array<string, string>  $options  axis => value, such as ['Size' => 'M'] */
    public function __construct(
        public string $supplierVariantId,
        public array $options,
        public float $price,
        public ?string $sku = null,
    ) {}
}
