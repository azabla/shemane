<?php

namespace Tests\Feature;

use App\Jobs\DispatchDropshippingOrder;
use App\Models\Order;
use App\Models\OrderFulfilment;
use App\Models\Product;
use App\Services\Fulfilment\OrderFulfilments;
use App\Support\Modules\StoreModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Liberu\Ecommerce\Contracts\Fulfilment\SupplierUnavailable;
use RuntimeException;
use Tests\Feature\Concerns\ActivatesStoreModules;
use Tests\Feature\Modules\Concerns\InstallsFixtureModules;
use Tests\TestCase;

class DispatchDropshippingOrderTest extends TestCase
{
    use ActivatesStoreModules;
    use InstallsFixtureModules;
    use RefreshDatabase;

    private const ORDERS = 'https://fixture-supplier.test/orders';

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->activateModule('fixture-supplier', ['api_token' => 'token', 'region' => 'eu']);
    }

    private function runJob(Order $order): void
    {
        (new DispatchDropshippingOrder($order->id, 'fixture'))->handle(app(OrderFulfilments::class));
    }

    private function queuedOrder(array $overrides = []): Order
    {
        $product = Product::factory()->create(['inventory_count' => 5, 'supplier_id' => 'fixture']);
        $order = Order::create(array_merge([
            'customer_email' => 'buyer@example.com',
            'total_amount' => 20,
            'status' => Order::STATUS_SUPPLIER_QUEUED,
            'is_dropshipped' => true,
        ], $overrides));
        $order->items()->create(['product_id' => $product->id, 'quantity' => 1, 'price' => 20]);

        return $order;
    }

    public function test_successful_placement_transitions_to_processing_with_history(): void
    {
        Http::fake([self::ORDERS => Http::response(['id' => 'SUP-123'])]);
        $order = $this->queuedOrder();

        $this->runJob($order);

        $order->refresh();
        $this->assertSame(Order::STATUS_PROCESSING, $order->status);
        $this->assertSame('SUP-123', $order->supplier_order_reference);
        $this->assertNotNull(
            $order->statusHistory()->where('to_status', Order::STATUS_PROCESSING)->first(),
            'status change must go through the state machine (audit row)'
        );
    }

    public function test_supplier_rejection_transitions_to_supplier_failed_and_records_why(): void
    {
        Http::fake([self::ORDERS => Http::response(['error' => 'Unknown SKU'], 422)]);
        $order = $this->queuedOrder();

        $this->runJob($order);

        $this->assertSame(Order::STATUS_SUPPLIER_FAILED, $order->refresh()->status);
        $this->assertSame('Unknown SKU', $order->fulfilments()->sole()->failure_reason);
    }

    public function test_already_placed_order_is_not_placed_again(): void
    {
        Http::fake([self::ORDERS => Http::response(['id' => 'SUP-1'])]);
        $order = $this->queuedOrder();
        $order->update(['supplier_order_reference' => 'ALREADY-PLACED']);

        $this->runJob($order);

        Http::assertNothingSent();
        $this->assertSame('ALREADY-PLACED', $order->refresh()->supplier_order_reference);
    }

    public function test_running_the_job_twice_places_one_order(): void
    {
        Http::fake([self::ORDERS => Http::response(['id' => 'SUP-1'])]);
        $order = $this->queuedOrder();

        $this->runJob($order);
        $order->update(['status' => Order::STATUS_SUPPLIER_QUEUED]);
        $this->runJob($order);

        Http::assertSentCount(1);
    }

    public function test_order_no_longer_queued_is_skipped(): void
    {
        Http::fake();
        $order = $this->queuedOrder();
        $order->update(['status' => Order::STATUS_REFUNDED]); // refunded while queued

        $this->runJob($order);

        Http::assertNothingSent();
        $this->assertSame(Order::STATUS_REFUNDED, $order->refresh()->status);
    }

    public function test_an_outage_rethrows_and_leaves_the_order_queued_for_retry_with_the_same_reference(): void
    {
        $order = $this->queuedOrder();
        Http::fakeSequence(self::ORDERS)->push('Bad gateway', 502)->push(['id' => 'SUP-9']);

        try {
            $this->runJob($order);
            $this->fail('expected the exception to propagate for a queue retry');
        } catch (SupplierUnavailable) {
            // expected
        }

        $this->assertSame(Order::STATUS_SUPPLIER_QUEUED, $order->refresh()->status, 'an outage is not a rejection');
        $this->assertSame(OrderFulfilment::QUEUED, $order->fulfilments()->sole()->status);

        $this->runJob($order);

        $this->assertSame(Order::STATUS_PROCESSING, $order->refresh()->status);
        $references = [];
        Http::assertSent(function (Request $request) use (&$references) {
            $references[] = $request['reference'];

            return true;
        });
        $this->assertSame(["order-{$order->id}-fixture", "order-{$order->id}-fixture"], $references);
    }

    public function test_a_timeout_is_an_outage_not_a_rejection(): void
    {
        Http::fake(fn () => throw new ConnectionException('timed out'));
        $order = $this->queuedOrder();

        $this->expectException(SupplierUnavailable::class);

        try {
            $this->runJob($order);
        } finally {
            $this->assertSame(Order::STATUS_SUPPLIER_QUEUED, $order->refresh()->status);
        }
    }

    public function test_failed_hook_marks_supplier_failed_after_retries_exhausted(): void
    {
        $order = $this->queuedOrder();

        (new DispatchDropshippingOrder($order->id, 'fixture'))->failed(new RuntimeException('gave up'));

        $this->assertSame(Order::STATUS_SUPPLIER_FAILED, $order->refresh()->status);
        $this->assertStringContainsString('gave up', $order->fulfilments()->sole()->failure_reason);
    }

    public function test_a_store_without_the_module_fails_the_order_without_calling_anyone(): void
    {
        Http::fake();
        $order = $this->queuedOrder();
        app(StoreModules::class)->disable('fixture-supplier', $order->store_id);

        $this->runJob($order);

        Http::assertNothingSent();
        $this->assertSame(Order::STATUS_SUPPLIER_FAILED, $order->refresh()->status);
        Notification::assertCount(1);
    }

    public function test_the_job_retries_and_never_overlaps_itself(): void
    {
        $job = new DispatchDropshippingOrder(1, 'fixture');

        $this->assertGreaterThan(1, $job->tries);
        $this->assertNotEmpty($job->middleware());
    }
}
