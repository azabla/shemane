<?php

namespace Tests\Feature\Modules;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Liberu\Ecommerce\Contracts\Catalog\ImportedProduct;
use Liberu\Ecommerce\Contracts\Catalog\ImportedVariant;
use Liberu\Ecommerce\Contracts\Catalog\ProductImporter;
use Tests\TestCase;

/** What the core promises any module that imports products. */
class SupplierProductImporterTest extends TestCase
{
    use RefreshDatabase;

    private function hoodie(array $sizes = ['M' => '501', 'L' => '502'], array $gallery = ['https://cdn.test/1.png']): ImportedProduct
    {
        $variants = [];
        foreach ($sizes as $size => $id) {
            $variants[] = new ImportedVariant($id, ['Colour' => 'Black', 'Size' => $size], 40.0, "HD-{$size}");
        }

        return new ImportedProduct('acme', '90', 'Hoodie', 40.0, $variants, $gallery, 'https://cdn.test/thumb.png', 'Knitwear', 'Warm things');
    }

    public function test_it_creates_a_made_to_order_product_with_the_suppliers_ids_on_each_variant(): void
    {
        $store = Store::factory()->create();

        $id = app(ProductImporter::class)->import($store->id, $this->hoodie());

        $product = Product::withoutGlobalScopes()->findOrFail($id);
        $this->assertSame([$store->id, 'acme', '90', false], [$product->store_id, $product->supplier_id, $product->supplier_product_id, (bool) $product->track_inventory]);
        $this->assertSame(['501', '502'], $product->variants()->orderBy('supplier_variant_id')->pluck('supplier_variant_id')->all());
        $this->assertSame('Knitwear', ProductCategory::withoutGlobalScopes()->findOrFail($product->category_id)->name);
        $this->assertSame($store->id, ProductCategory::withoutGlobalScopes()->findOrFail($product->category_id)->store_id);
        $this->assertSame('https://cdn.test/thumb.png', $product->featured_image);
    }

    public function test_a_reimport_keeps_the_product_and_retires_a_dropped_variant_without_duplicating_the_gallery(): void
    {
        $store = Store::factory()->create();
        $importer = app(ProductImporter::class);

        $first = $importer->import($store->id, $this->hoodie());
        $second = $importer->import($store->id, $this->hoodie(['M' => '501']));

        $this->assertSame($first, $second);
        $product = Product::withoutGlobalScopes()->findOrFail($first);
        $this->assertSame(1, $product->images()->count());
        $this->assertSame(['501'], $product->variants()->where('is_active', true)->pluck('supplier_variant_id')->all());
    }

    public function test_the_same_supplier_product_in_two_stores_stays_two_products(): void
    {
        [$a, $b] = Store::factory()->count(2)->create();
        $importer = app(ProductImporter::class);

        $this->assertNotSame($importer->import($a->id, $this->hoodie()), $importer->import($b->id, $this->hoodie()));
    }

    public function test_it_can_map_onto_an_existing_product_of_that_store_only(): void
    {
        [$a, $b] = Store::factory()->count(2)->create();
        $own = Product::factory()->create(['store_id' => $a->id, 'name' => 'Kept name']);
        $other = Product::factory()->create(['store_id' => $b->id]);
        $importer = app(ProductImporter::class);

        $this->assertSame($own->id, $importer->import($a->id, $this->hoodie(), $own->id));
        $this->assertSame('Kept name', $own->fresh()->name);

        $this->expectException(ModelNotFoundException::class);
        $importer->import($a->id, $this->hoodie(), $other->id);
    }
}
