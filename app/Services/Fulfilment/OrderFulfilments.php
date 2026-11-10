<?php

namespace App\Services\Fulfilment;

use App\Jobs\DispatchDropshippingOrder;
use App\Models\Order;
use App\Models\OrderFulfilment;
use App\Models\OrderItem;
use App\Notifications\SupplierFailureNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Liberu\Ecommerce\Contracts\Fulfilment\FulfilmentLine;
use Liberu\Ecommerce\Contracts\Fulfilment\FulfilmentRequest;
use Liberu\Ecommerce\Contracts\Fulfilment\Recipient;
use Liberu\Ecommerce\Contracts\Fulfilment\SupplierUnavailable;
use Liberu\Ecommerce\Contracts\Modules\ModuleNotActive;

/** Sends a paid order's lines to the suppliers its products name, and records what happens. */
final class OrderFulfilments
{
    public function __construct(private readonly FulfilmentProviders $providers) {}

    /** @param  list<string>  $suppliers */
    public function queue(Order $order, array $suppliers): void
    {
        $order->update([
            'is_dropshipped' => true,
            'supplier_id' => count($suppliers) === 1 ? $suppliers[0] : null,
        ]);

        // Every row exists before any job runs, because a sync queue runs the first job right here
        // and the order must not count as fully placed while a second supplier is still to come.
        foreach ($suppliers as $supplier) {
            $this->row($order, $supplier);
        }

        $order->transitionTo(Order::STATUS_SUPPLIER_QUEUED, notes: 'Supplier order queued ('.implode(', ', $suppliers).')');

        foreach ($suppliers as $supplier) {
            DispatchDropshippingOrder::dispatch($order->id, $supplier);
        }
    }

    /**
     * One attempt. A supplier that could not answer throws, so the queue retries with the same
     * reference; a supplier that refused, or a store without the module, is recorded as a failure.
     *
     * @throws SupplierUnavailable
     */
    public function submit(int $orderId, string $supplier): void
    {
        $order = Order::with(['items.product', 'items.variant', 'user'])->find($orderId);

        if ($order === null || ! in_array($order->status, [Order::STATUS_SUPPLIER_QUEUED, Order::STATUS_SUPPLIER_FAILED], true)) {
            return;
        }

        $fulfilment = $this->row($order, $supplier);

        if ($fulfilment->isPlaced() || filled($order->supplier_order_reference)) {
            return;
        }

        try {
            $provider = $this->providers->provider($supplier, (int) $order->store_id);
            $settings = $this->providers->settings($provider, (int) $order->store_id);
        } catch (ModuleNotActive) {
            $this->fail($order, $fulfilment, "This store does not have the {$supplier} integration active.");

            return;
        }

        $lines = $this->linesFor($order, $supplier);

        if ($lines === []) {
            $this->fail($order, $fulfilment, "No line on this order belongs to {$supplier}.");

            return;
        }

        $result = $provider->submit($this->request($order, $fulfilment, $lines), $settings);

        if (! $result->accepted) {
            $fulfilment->update(['response' => $result->response]);
            $this->fail($order, $fulfilment, (string) $result->rejectionReason);

            return;
        }

        $fulfilment->update([
            'status' => OrderFulfilment::SUBMITTED,
            'supplier_reference' => $result->supplierReference,
            'response' => $result->response,
            'failure_reason' => null,
            'submitted_at' => now(),
        ]);

        $this->mirrorOntoOrder($order, $fulfilment);
        $this->settle($order);
    }

    /** The queue gave up on an attempt that never got an answer. */
    public function exhausted(int $orderId, string $supplier, string $reason): void
    {
        $order = Order::find($orderId);

        if ($order === null || ! in_array($order->status, [Order::STATUS_SUPPLIER_QUEUED, Order::STATUS_SUPPLIER_FAILED], true)) {
            return;
        }

        $fulfilment = $this->row($order, $supplier);

        if (! $fulfilment->isPlaced()) {
            $this->fail($order, $fulfilment, $reason);
        }
    }

    /** Sends every failed fulfilment again, with its original reference. */
    public function retry(Order $order): void
    {
        $failed = $order->fulfilments()->where('status', OrderFulfilment::FAILED)->get();

        $order->transitionTo(Order::STATUS_SUPPLIER_QUEUED, notes: 'Supplier order retried');

        foreach ($failed as $fulfilment) {
            $fulfilment->update(['status' => OrderFulfilment::QUEUED, 'failure_reason' => null]);
        }

        foreach ($failed as $fulfilment) {
            DispatchDropshippingOrder::dispatch($order->id, $fulfilment->supplier);
        }
    }

    /** @return int how many fulfilments gained tracking */
    public function syncTracking(): int
    {
        $updated = 0;

        OrderFulfilment::query()
            ->where('status', OrderFulfilment::SUBMITTED)
            ->whereNotNull('supplier_reference')
            ->with('order')
            ->chunkById(100, function (Collection $fulfilments) use (&$updated) {
                foreach ($fulfilments as $fulfilment) {
                    $updated += $this->trackOne($fulfilment) ? 1 : 0;
                }
            });

        return $updated;
    }

    private function trackOne(OrderFulfilment $fulfilment): bool
    {
        try {
            $provider = $this->providers->provider($fulfilment->supplier, (int) $fulfilment->store_id);
            $update = $provider->track($fulfilment->supplier_reference, $this->providers->settings($provider, (int) $fulfilment->store_id));
        } catch (ModuleNotActive|SupplierUnavailable $e) {
            Log::info('Fulfilment tracking skipped', ['fulfilment' => $fulfilment->id, 'reason' => $e->getMessage()]);

            return false;
        }

        if ($update === null) {
            return false;
        }

        $fulfilment->update([
            'status' => OrderFulfilment::SHIPPED,
            'tracking_number' => $update->trackingNumber,
            'carrier' => $update->carrier,
            'tracking_url' => $update->trackingUrl,
            'shipped_at' => now(),
        ]);

        if ($fulfilment->order?->fulfilments()->count() === 1) {
            $fulfilment->order->update(['supplier_tracking_number' => $update->trackingNumber]);
        }

        return true;
    }

    private function row(Order $order, string $supplier): OrderFulfilment
    {
        return $order->fulfilments()->firstOrCreate(['supplier' => $supplier], [
            'store_id' => $order->store_id,
            'reference' => "order-{$order->id}-{$supplier}",
            'status' => OrderFulfilment::QUEUED,
        ]);
    }

    /** @return list<FulfilmentLine> */
    private function linesFor(Order $order, string $supplier): array
    {
        return $order->items
            ->filter(fn (OrderItem $item) => $item->product?->supplier_id === $supplier && ! $item->product->is_downloadable)
            ->map(fn (OrderItem $item) => new FulfilmentLine(
                (int) $item->product_id,
                (int) $item->quantity,
                (float) $item->price,
                $item->sku ?? $item->product?->sku,
                $item->variant?->supplier_variant_id,
            ))
            ->values()
            ->all();
    }

    /** @param  list<FulfilmentLine>  $lines */
    private function request(Order $order, OrderFulfilment $fulfilment, array $lines): FulfilmentRequest
    {
        return new FulfilmentRequest(
            $fulfilment->reference,
            (int) $order->store_id,
            new Recipient(
                $order->recipient_name ?: $order->shipping_name ?: $order->user?->name ?: $order->customer_email,
                $order->recipient_email ?: $order->customer_email,
                $order->shipping_address,
                $order->shipping_city,
                $order->shipping_state,
                $order->shipping_postal_code,
                $order->billing_country,
            ),
            $lines,
            $order->gift_message,
            $order->shipping_method_id === null ? $order->shipping_service : (string) $order->shipping_method_id,
            (float) ($order->shipping_cost ?? 0),
        );
    }

    private function fail(Order $order, OrderFulfilment $fulfilment, string $reason): void
    {
        $fulfilment->update(['status' => OrderFulfilment::FAILED, 'failure_reason' => $reason]);
        $this->mirrorOntoOrder($order, $fulfilment);

        if ($order->status === Order::STATUS_SUPPLIER_QUEUED) {
            $order->transitionTo(Order::STATUS_SUPPLIER_FAILED, notes: "{$fulfilment->supplier}: {$reason}");
        }

        Notification::route('mail', config('mail.from.address'))
            ->notify(new SupplierFailureNotification("Supplier order failed for order {$order->id} ({$fulfilment->supplier}): {$reason}"));
    }

    /** The order moves on only when every supplier has it. */
    private function settle(Order $order): void
    {
        $placed = $order->fulfilments()->get()->every(fn (OrderFulfilment $f) => $f->isPlaced());

        if ($placed && $order->status === Order::STATUS_SUPPLIER_QUEUED) {
            $order->transitionTo(Order::STATUS_PROCESSING, notes: 'Supplier order placed');
        }
    }

    /** An order with one supplier keeps these columns, which admin views and older orders read. */
    private function mirrorOntoOrder(Order $order, OrderFulfilment $fulfilment): void
    {
        if ($order->fulfilments()->count() === 1) {
            $order->update([
                'supplier_id' => $fulfilment->supplier,
                'supplier_order_reference' => $fulfilment->supplier_reference,
                'supplier_response' => $fulfilment->response ?? ['error' => $fulfilment->failure_reason],
            ]);
        }
    }
}
