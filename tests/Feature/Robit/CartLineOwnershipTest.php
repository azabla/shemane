<?php

namespace Tests\Feature\Robit;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// RH-M3-02: a cart line id names one owner's row; nobody else can change it by guessing the id.
class CartLineOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function ownersLine(User $owner): CartItem
    {
        $category = ProductCategory::create(['name' => 'Scarves', 'slug' => 'scarves-'.uniqid()]);
        $product = Product::create([
            'name' => 'Fixture scarf', 'slug' => 'fixture-scarf-'.uniqid(), 'price' => 40.00,
            'category_id' => $category->id, 'inventory_count' => 10, 'is_downloadable' => false,
        ]);

        $this->actingAs($owner)->post(route('cart.add', $product), ['quantity' => 2])->assertRedirect();
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        return CartItem::where('user_id', $owner->id)->sole();
    }

    public function test_another_customer_cannot_change_or_remove_the_line(): void
    {
        $line = $this->ownersLine(User::factory()->create());
        $other = User::factory()->create();

        $this->actingAs($other)->put(route('cart.update', $line->id), ['quantity' => 9])->assertSessionHas('error');
        $this->actingAs($other)->delete(route('cart.remove', $line->id))->assertSessionHas('error');

        $this->assertSame(2, (int) $line->fresh()?->quantity);
    }

    public function test_a_guest_cannot_change_or_remove_a_customers_line(): void
    {
        $line = $this->ownersLine(User::factory()->create());

        $this->put(route('cart.update', $line->id), ['quantity' => 9])->assertSessionHas('error');
        $this->delete(route('cart.remove', $line->id))->assertSessionHas('error');

        $this->assertSame(2, (int) $line->fresh()?->quantity);
    }
}
