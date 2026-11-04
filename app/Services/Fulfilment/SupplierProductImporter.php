<?php

namespace App\Services\Fulfilment;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Services\ProductVariantService;
use App\Services\StoreContext;
use Illuminate\Support\Str;
use Liberu\Ecommerce\Contracts\Catalog\ImportedProduct;
use Liberu\Ecommerce\Contracts\Catalog\ImportedVariant;
use Liberu\Ecommerce\Contracts\Catalog\ProductImporter;
use RuntimeException;

/**
 * Writes a supplier's product into one store, named explicitly: a console run has no store scope,
 * and a match on the supplier's ids alone could be another merchant's product.
 */
final class SupplierProductImporter implements ProductImporter
{
    public function __construct(private readonly ProductVariantService $variants) {}

    public function import(int $storeId, ImportedProduct $imported, ?int $ontoProductId = null): int
    {
        return StoreContext::writingTo($storeId, function () use ($storeId, $imported, $ontoProductId) {
            $query = Product::withoutGlobalScope('store')->where('store_id', $storeId);

            $product = $ontoProductId !== null
                ? (clone $query)->findOrFail($ontoProductId)
                : (clone $query)->where(['supplier_id' => $imported->supplier, 'supplier_product_id' => $imported->supplierProductId])->first() ?? new Product(['store_id' => $storeId]);

            $product->store_id = $storeId;
            $product->fill([
                'supplier_id' => $imported->supplier,
                'supplier_product_id' => $imported->supplierProductId,
                'track_inventory' => false,
            ]);

            if (! $product->exists) {
                $product->fill([
                    'name' => $imported->name,
                    'slug' => $this->freeSlug($imported->name),
                    'price' => $imported->price,
                    'description' => $imported->description,
                    'category_id' => $this->category($storeId, $imported)?->id,
                    'inventory_count' => 0,
                ]);
            }

            $product->featured_image = $product->featured_image ?: $imported->image;
            $product->save();

            $this->syncVariants($product, $imported->variants);
            $this->syncGallery($product, $imported->gallery);

            return $product->id;
        });
    }

    private function category(int $storeId, ImportedProduct $imported): ?ProductCategory
    {
        if ($imported->categoryName === null) {
            return null;
        }

        return ProductCategory::withoutGlobalScope('store')->firstOrCreate(
            ['store_id' => $storeId, 'slug' => Str::slug($imported->categoryName)],
            ['name' => $imported->categoryName, 'description' => $imported->categoryDescription],
        );
    }

    /** @param  list<ImportedVariant>  $imported */
    private function syncVariants(Product $product, array $imported): void
    {
        if ($imported === []) {
            return;
        }

        $current = $product->variants()->get();
        $rows = [];
        $attributes = [];

        foreach ($imported as $variant) {
            $values = [];

            foreach ($variant->options as $axis => $value) {
                $values[] = ['name' => $axis, 'value' => $value];
                $attributes[$axis][$value] = $value;
            }

            $rows[] = [
                // A re-import keeps each variant by the supplier's id; otherwise the service matches by combination.
                'id' => $current->firstWhere('supplier_variant_id', $variant->supplierVariantId)?->id,
                'attribute_values' => $values,
                'sku' => $variant->sku,
                'price' => $variant->price,
                'track_inventory' => false,
                'inventory_count' => 0,
                'is_active' => true,
            ];
        }

        $axes = array_map(fn (string $axis, array $values) => ['name' => $axis, 'values' => array_values($values)], array_keys($attributes), $attributes);

        $this->variants->sync($product, $axes, $rows);

        $byLabel = $product->variants()->get()->keyBy(fn (ProductVariant $v) => $v->label());

        foreach ($rows as $i => $row) {
            $variant = $byLabel->get(ProductVariant::labelFor($row['attribute_values']))
                ?? throw new RuntimeException("Supplier variant {$imported[$i]->supplierVariantId} was not kept.");
            $variant->update(['supplier_variant_id' => $imported[$i]->supplierVariantId]);
        }
    }

    /** @param  list<string>  $urls  added to the gallery once each */
    private function syncGallery(Product $product, array $urls): void
    {
        $have = $product->images()->pluck('image');
        $order = (int) $product->images()->max('order');

        foreach (collect($urls)->unique()->diff($have) as $url) {
            $product->images()->create(['image' => $url, 'order' => ++$order]);
        }
    }

    private function freeSlug(string $name): string
    {
        $slug = $base = Str::slug($name) ?: 'product';

        for ($i = 2; Product::withoutGlobalScopes()->where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
