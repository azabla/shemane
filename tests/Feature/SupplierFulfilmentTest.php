<?php

namespace Tests\Feature;

use App\Filament\App\Resources\Orders\Pages\EditOrder;
use App\Integrations\Dropxl\DropxlDefinition;
use App\Interfaces\PaymentGatewayInterface;
use App\Jobs\DispatchDropshippingOrder;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderFulfilment;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\Store;
use App\Models\User;
use App\Services\Fulfilment\OrderFulfilments;
use App\Services\PaymentGateways\StripeGateway;
use App\Support\Modules\StoreModules;
use App\View\Storefront\ProductPresenter;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Liberu\Ecommerce\FixtureSupplier\FixtureFulfilment;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Feature\Concerns\ActivatesStoreModules;
use Tests\Feature\Concerns\BuildsVariants;
use Tests\Feature\Modules\Concerns\InstallsFixtureModules;
use Tests\TestCase;

/** A product names its supplier, and the order goes there; never where the request says. */
class SupplierFulfilmentTest extends TestCase
{
    use ActivatesStoreModules;
    use BuildsVariants;
    use InstallsFixtureModules;
    use RefreshDatabase;

    private Product $tee;

    private ShippingMethod $courier;

    private object $gateway;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->store = $this->activateModule('fixture-supplier', ['api_token' => 'fx-token', 'region' => 'eu']);
        $this->gateway = new class implements PaymentGatewayInterface
        {
            public int $charges = 0;

            public function processPayment(float $amount, array $paymentDetails): array
            {
                $this->charges++;

                return ['success' => true, 'transaction_id' => 'ch_ok'];
            }

            public function processSubscription(string $planId, array $subscriptionDetails): array
            {
                return ['success' => true];
            }

            public function refundPayment(string $transactionId, float $amount): array
            {
                return ['success' => true];
            }
        };
        $this->app->instance(StripeGateway::class, $this->gateway);

        $made = ['track_inventory' => false];
        $this->tee = $this->variableProduct(['Size' => ['M', 'L']], [
            $this->row(['Size' => 'M'], 25, 0, $made + ['sku' => 'TEE-M']),
            $this->row(['Size' => 'L'], 25, 0, $made + ['sku' => 'TEE-L']),
        ], ['name' => 'Tee', 'track_inventory' => false, 'supplier_id' => 'fixture']);
        $this->variantOf($this->tee, 'M')->update(['supplier_variant_id' => '4001']);
        $this->courier = ShippingMethod::create(['name' => 'Standard', 'base_rate' => 4, 'weight_rate' => 0, 'max_weight' => 100]);
    }

    private function addToCart(string $size, int $quantity = 1): void
    {
        $this->post(route('cart.add', $this->tee), ['variant_id' => $this->variantOf($this->tee, $size)->id, 'quantity' => $quantity])->assertSessionHasNoErrors();
    }

    private function checkout(array $overrides = [])
    {
        return $this->post(route('checkout.process'), $overrides + [
            'email' => 'buyer@example.com',
            'has_physical_products' => 1,
            'shipping_name' => 'Ada Lovelace',
            'shipping_address' => '1 High Street',
            'city' => 'Leeds',
            'state' => 'West Yorkshire',
            'postal_code' => 'LS1 1AA',
            'country' => 'GB',
            'shipping_method_id' => $this->courier->id,
            'payment_method' => 'stripe',
            'stripeToken' => 'tok_test',
        ]);
    }

    public function test_the_products_supplier_gets_the_order_whatever_the_request_names(): void
    {
        Queue::fake();
        $this->addToCart('M');

        $this->checkout(['supplier_id' => 'dropxl']);

        $order = Order::sole();
        $this->assertSame(['fixture', Order::STATUS_SUPPLIER_QUEUED, true], [$order->supplier_id, $order->status, (bool) $order->is_dropshipped]);
        $this->assertSame(['Leeds', 'West Yorkshire', 'LS1 1AA'], [$order->shipping_city, $order->shipping_state, $order->shipping_postal_code]);
        Queue::assertPushed(DispatchDropshippingOrder::class, fn ($job) => $job->supplierId === 'fixture');
    }

    public function test_a_supplier_needs_the_city_and_postcode(): void
    {
        $this->addToCart('M');

        $this->checkout(['shipping_name' => '', 'city' => '', 'postal_code' => ''])->assertSessionHasErrors(['shipping_name', 'city', 'postal_code']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_a_shopper_cannot_route_their_own_stock_order_to_any_supplier(): void
    {
        Queue::fake();
        $plain = Product::factory()->create(['price' => 10, 'inventory_count' => 5]);
        $this->post(route('cart.add', $plain), ['quantity' => 1]);

        $this->checkout(['dropship' => 'on', 'recipient_name' => 'Gran', 'recipient_email' => 'gran@example.com', 'supplier_id' => 'fixture']);

        $order = Order::sole();
        $this->assertNull($order->supplier_id);
        $this->assertFalse((bool) $order->is_dropshipped);
        $this->assertSame('Gran', $order->recipient_name);
        Queue::assertNothingPushed();
    }

    public function test_on_the_default_sync_queue_the_order_reaches_the_supplier_with_this_stores_credentials(): void
    {
        Http::fake([FixtureFulfilment::URL.'/*' => Http::response(['id' => 777])]);
        $this->addToCart('M', 2);
        $this->addToCart('L');

        $this->checkout();

        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer fx-token')
            && $request['recipient'] === 'Ada Lovelace'
            && array_column($request['lines'], 'quantity', 'sku') === ['TEE-M' => 2, 'TEE-L' => 1]);

        $order = Order::sole();
        $this->assertSame([Order::STATUS_PROCESSING, '777'], [$order->status, (string) $order->supplier_order_reference]);
    }

    public function test_a_supplier_is_sent_only_its_own_lines(): void
    {
        Http::fake([FixtureFulfilment::URL.'/*' => Http::response(['id' => 1])]);
        $mug = Product::factory()->create(['name' => 'Own-stock mug', 'price' => 8, 'inventory_count' => 5, 'sku' => 'MUG']);
        $this->addToCart('M');
        $this->post(route('cart.add', $mug), ['quantity' => 1]);

        $this->checkout();

        Http::assertSent(fn (Request $request) => array_column($request['lines'], 'sku') === ['TEE-M']);
        $this->assertSame('fixture', Order::sole()->supplier_id);
    }

    public function test_the_headless_checkout_follows_the_same_rules(): void
    {
        Queue::fake();
        Sanctum::actingAs($user = User::factory()->create());
        $variant = $this->variantOf($this->tee, 'M');
        CartItem::create(['user_id' => $user->id, 'product_id' => $this->tee->id, 'product_variant_id' => $variant->id, 'quantity' => 1, 'price' => 25, 'session_id' => 'api']);
        $mutation = 'mutation($input: CheckoutInput!) { checkout(input: $input) { id status } }';
        $input = ['country' => 'GB', 'paymentMethod' => 'stripe', 'stripeToken' => 'tok', 'supplierId' => 'dropxl'];

        $refused = $this->postJson('/api/graphql', ['query' => $mutation, 'variables' => ['input' => $input]])->json();
        $this->assertStringContainsString('street address, city and postal code', $refused['errors'][0]['message']);

        $this->postJson('/api/graphql', ['query' => $mutation, 'variables' => ['input' => $input + ['shippingAddress' => '1 High Street', 'city' => 'Leeds', 'postalCode' => 'LS1 1AA']]]);

        $order = Order::sole();
        $this->assertSame(['fixture', '1 High Street', 'LS1 1AA'], [$order->supplier_id, $order->shipping_address, $order->shipping_postal_code]);
        Queue::assertPushed(DispatchDropshippingOrder::class, fn ($job) => $job->supplierId === 'fixture');
    }

    public function test_the_customer_is_not_charged_when_the_store_cannot_send_the_order(): void
    {
        Queue::fake();
        $this->addToCart('M');
        app(StoreModules::class)->disable('fixture-supplier', $this->store->id);

        $this->checkout()->assertSessionHas('error');

        $this->assertSame(0, $this->gateway->charges);
        $this->assertDatabaseCount('orders', 0);
        Queue::assertNothingPushed();
    }

    public function test_the_headless_customer_is_not_charged_either(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        CartItem::create(['user_id' => $user->id, 'product_id' => $this->tee->id, 'product_variant_id' => $this->variantOf($this->tee, 'M')->id, 'quantity' => 1, 'price' => 25, 'session_id' => 'api']);
        app(StoreModules::class)->disable('fixture-supplier', $this->store->id);

        $response = $this->postJson('/api/graphql', ['query' => 'mutation($input: CheckoutInput!) { checkout(input: $input) { id } }', 'variables' => ['input' => [
            'country' => 'GB', 'paymentMethod' => 'stripe', 'stripeToken' => 'tok', 'shippingAddress' => '1 High Street', 'city' => 'Leeds', 'postalCode' => 'LS1 1AA',
        ]]])->json();

        $this->assertStringContainsString('cannot be ordered', $response['errors'][0]['message']);
        $this->assertSame(0, $this->gateway->charges);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_the_storefront_shows_it_as_sold_out_when_the_store_cannot_send_it(): void
    {
        $this->assertNotSame('out', app(ProductPresenter::class)->card($this->tee)['stock_state']);

        app(StoreModules::class)->disable('fixture-supplier', $this->store->id);
        app()->forgetScopedInstances();

        $this->assertSame('out', app(ProductPresenter::class)->card($this->tee)['stock_state']);
    }

    private function mixedCart(): void
    {
        $this->activateModule(DropxlDefinition::KEY, ['api_key' => 'dx', 'api_url' => 'https://api.dropxl.example'], $this->store);
        $chair = Product::factory()->create(['name' => 'Chair', 'price' => 50, 'inventory_count' => 5, 'sku' => 'CHAIR', 'supplier_id' => 'dropxl']);
        $this->addToCart('M');
        $this->post(route('cart.add', $chair), ['quantity' => 1]);
    }

    public function test_a_mixed_cart_sends_each_supplier_only_its_lines_and_records_both(): void
    {
        Http::fake([
            FixtureFulfilment::URL.'/*' => Http::response(['id' => 'FX-1']),
            'https://api.dropxl.example/*' => Http::response(['reference' => 'DX-1']),
        ]);
        $this->mixedCart();

        $this->checkout();

        Http::assertSent(fn (Request $r) => str_starts_with($r->url(), FixtureFulfilment::URL) && array_column($r['lines'], 'sku') === ['TEE-M']);
        Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://api.dropxl.example') && array_column($r['items'], 'sku') === ['CHAIR']);

        $order = Order::sole();
        $this->assertSame(Order::STATUS_PROCESSING, $order->status);
        $this->assertTrue((bool) $order->is_dropshipped);
        $this->assertNull($order->supplier_id, 'no single supplier owns this order');
        $this->assertEqualsCanonicalizing(['fixture' => 'FX-1', 'dropxl' => 'DX-1'], $order->fulfilments()->pluck('supplier_reference', 'supplier')->all());
    }

    public function test_one_supplier_failing_leaves_the_other_placed_and_retry_resends_only_the_failure(): void
    {
        Http::fake([
            FixtureFulfilment::URL.'/*' => Http::response(['id' => 'FX-1']),
            'https://api.dropxl.example/*' => Http::sequence()->push(['message' => 'No such SKU'], 422)->push(['reference' => 'DX-2']),
        ]);
        $this->mixedCart();

        $this->checkout();

        $order = Order::sole();
        $this->assertSame(Order::STATUS_SUPPLIER_FAILED, $order->status);
        $this->assertSame(OrderFulfilment::SUBMITTED, $order->fulfilments()->where('supplier', 'fixture')->sole()->status);
        $this->assertSame(OrderFulfilment::FAILED, $order->fulfilments()->where('supplier', 'dropxl')->sole()->status);

        app(OrderFulfilments::class)->retry($order);

        $this->assertSame(Order::STATUS_PROCESSING, $order->fresh()->status);
        Http::assertSentCount(3);
        $this->assertSame(1, collect(Http::recorded())->filter(fn ($pair) => str_starts_with($pair[0]->url(), FixtureFulfilment::URL))->count(), 'the placed supplier is not sent it again');
    }

    public function test_two_stores_use_their_own_credentials_for_the_same_supplier(): void
    {
        Http::fake([FixtureFulfilment::URL.'/*' => Http::response(['id' => 1])]);
        $other = Store::factory()->create();
        $this->activateModule('fixture-supplier', ['api_token' => 'other-token', 'region' => 'us'], $other);
        $product = Product::factory()->create(['supplier_id' => 'fixture', 'store_id' => $other->id]);
        $order = Order::withoutGlobalScopes()->forceCreate(['customer_email' => 'b@c.com', 'total_amount' => 10, 'status' => Order::STATUS_SUPPLIER_QUEUED, 'store_id' => $other->id]);
        $order->items()->create(['product_id' => $product->id, 'quantity' => 1, 'price' => 10]);

        app(OrderFulfilments::class)->submit($order->id, 'fixture');

        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer other-token'));
    }

    public function test_tracking_is_persisted_on_the_fulfilment_and_the_order(): void
    {
        Http::fake([
            FixtureFulfilment::URL.'/orders' => Http::response(['id' => 'FX-7']),
            FixtureFulfilment::URL.'/orders/FX-7' => Http::sequence()
                ->push(['tracking' => null])
                ->push(['tracking' => 'TRK-123', 'carrier' => 'DHL', 'url' => 'https://track.test/TRK-123']),
        ]);
        $this->addToCart('M');
        $this->checkout();

        $this->artisan('fulfilment:sync-tracking')->assertSuccessful();
        $order = Order::sole();
        $this->assertSame(OrderFulfilment::SUBMITTED, $order->fulfilments()->sole()->status, 'nothing shipped yet');

        $this->artisan('fulfilment:sync-tracking')->assertSuccessful();

        $fulfilment = $order->fulfilments()->sole();
        $this->assertSame([OrderFulfilment::SHIPPED, 'TRK-123', 'DHL', 'https://track.test/TRK-123'], [$fulfilment->status, $fulfilment->tracking_number, $fulfilment->carrier, $fulfilment->tracking_url]);
        $this->assertNotNull($fulfilment->shipped_at);
        $this->assertSame('TRK-123', $order->fresh()->supplier_tracking_number);

        $this->artisan('fulfilment:sync-tracking')->assertSuccessful();
        Http::assertSentCount(3);
    }

    public function test_staff_retry_a_failed_order_from_its_page(): void
    {
        Http::fake([FixtureFulfilment::URL.'/*' => Http::sequence()->push(['error' => 'Nope'], 422)->push(['id' => 'FX-2'])]);
        $this->addToCart('M');
        $this->checkout();
        $order = Order::sole();
        $this->assertSame(Order::STATUS_SUPPLIER_FAILED, $order->status);

        Role::findOrCreate('super_admin', 'web');
        $this->actingAs(User::factory()->create()->assignRole('super_admin'));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(EditOrder::class, ['record' => $order->getKey()])->callAction('retrySuppliers');

        $this->assertSame(Order::STATUS_PROCESSING, $order->fresh()->status);
        $this->assertSame('FX-2', $order->fulfilments()->sole()->supplier_reference);
    }
}
