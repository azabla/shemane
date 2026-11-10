<?php

namespace Liberu\Ecommerce\Contracts\Catalog;

final readonly class ImportedProduct
{
    /**
     * @param  list<ImportedVariant>  $variants
     * @param  list<string>  $gallery  image URLs
     */
    public function __construct(
        public string $supplier,
        public string $supplierProductId,
        public string $name,
        public float $price,
        public array $variants = [],
        public array $gallery = [],
        public ?string $image = null,
        public ?string $categoryName = null,
        public ?string $categoryDescription = null,
        public ?string $description = null,
    ) {}
}
