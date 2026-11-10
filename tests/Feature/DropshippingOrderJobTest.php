<?php

namespace Tests\Feature;

use App\Jobs\DispatchDropshippingOrder;
use App\Models\Order;
use App\Models\Product;
use App\Services\Fulfilment\OrderFulfilments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Liberu\Ecommerce\Dropxl\DropxlDefinition;
use Tests\Feature\Concerns\ActivatesModules;
use Tests\Feature\Modules\Concerns\InstallsFixtureModules;
use Tests\TestCase;

/** The DropXL module, installed with Composer and enabled, through the fulfilment boundary. */
class DropshippingOrderJobTest extends TestCase
{
    use ActivatesModules;
    use InstallsFixtureModules;
    use RefreshDatabase;

    /** @return list<string> */
    protected function fixtureModulesEnabled(): array
    {
        return ['ecommerce-dropxl'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->activateModule(DropxlDefinition::KEY, ['api_key' => 'dx-key', 'api_url' => 'https://api.dropxl.example']);
    }

    private function queuedDropshipOrder(array $product = []): Order
    {
        $order = Order::create([
            'customer_email' => 'buyer@example.com',
            'total_amount' => 100,
            'status' => Order::STATUS_SUPPLIER_QUEUED,
            'is_dropshipped' => true,
            'shipping_cost' => 0,
            'shipping_address' => '1 High Street',
        ]);

        $order->items()->create([
            'product_id' => Product::factory()->create(['supplier_id' => 'dropxl'] + $product)->id,
            'quantity' => 2,
            'price' => 25.00,
        ]);

        return $order;
    }

    private function runJob(Order $order): void
    {
        (new DispatchDropshippingOrder($order->id, 'dropxl'))->handle(app(OrderFulfilments::class));
    }

    public function test_successful_placement_moves_order_to_a_known_processing_status(): void
    {
        Http::fake([
            'https://api.dropxl.example/v1/orders' => Http::response(['reference' => 'DROPXL-1', 'status' => 'received'], 200),
        ]);

        $order = $this->queuedDropshipOrder();

        $this->runJob($order);
        $order->refresh();

        // The success status must be a status the rest of the system understands.
        $this->assertArrayHasKey(
            $order->status,
            Order::TRANSITIONS,
            "Job set an unknown order status [{$order->status}] that no transition map / consumer recognises"
        );
        $this->assertSame(Order::STATUS_PROCESSING, $order->status);
        $this->assertSame('dropxl', $order->supplier_id);
        $this->assertSame('DROPXL-1', $order->supplier_order_reference);
        $this->assertSame(['reference' => 'DROPXL-1', 'status' => 'received'], $order->supplier_response);
    }

    public function test_it_sends_dropxls_payload_shape_on_the_stores_own_key(): void
    {
        Http::fake(['*' => Http::response(['reference' => 'R'])]);

        $this->runJob($this->queuedDropshipOrder(['sku' => 'SKU-1']));

        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer dx-key')
            && $request['customer']['email'] === 'buyer@example.com'
            && $request['customer']['address'] === '1 High Street'
            && $request['items'][0]['sku'] === 'SKU-1'
            && $request['items'][0]['quantity'] === 2
            && (float) $request['items'][0]['price'] === 25.0
            && array_key_exists('shipping', $request->data())
            && (float) $request['shipping']['cost'] === 0.0
            && str_starts_with($request['reference'], 'order-'));
    }

    public function test_a_line_without_a_sku_falls_back_to_its_product_id(): void
    {
        Http::fake(['*' => Http::response(['reference' => 'R'])]);
        $order = $this->queuedDropshipOrder(['sku' => null]);

        $this->runJob($order);

        Http::assertSent(fn (Request $request) => $request['items'][0]['sku'] === $order->items()->sole()->product_id);
    }

    public function test_supplier_rejection_moves_order_to_supplier_failed(): void
    {
        Notification::fake();
        Http::fake(['https://api.dropxl.example/v1/orders' => Http::response(['message' => 'No such SKU'], 422)]);

        $order = $this->queuedDropshipOrder();

        $this->runJob($order);
        $order->refresh();

        $this->assertSame(Order::STATUS_SUPPLIER_FAILED, $order->status);
        $this->assertSame('No such SKU', $order->fulfilments()->sole()->failure_reason);
    }
}
