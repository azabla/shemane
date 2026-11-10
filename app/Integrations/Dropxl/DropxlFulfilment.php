<?php

namespace App\Integrations\Dropxl;

use App\Integrations\SupplierHttp;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Liberu\Ecommerce\Contracts\Fulfilment\ChecksAvailability;
use Liberu\Ecommerce\Contracts\Fulfilment\FulfilmentLine;
use Liberu\Ecommerce\Contracts\Fulfilment\FulfilmentProvider;
use Liberu\Ecommerce\Contracts\Fulfilment\FulfilmentRequest;
use Liberu\Ecommerce\Contracts\Fulfilment\FulfilmentResult;
use Liberu\Ecommerce\Contracts\Fulfilment\SupplierUnavailable;
use Liberu\Ecommerce\Contracts\Fulfilment\TrackingUpdate;

class DropxlFulfilment implements ChecksAvailability, FulfilmentProvider
{
    public function supplierKey(): string
    {
        return 'dropxl';
    }

    public function moduleKey(): string
    {
        return DropxlDefinition::KEY;
    }

    public function submit(FulfilmentRequest $request, array $settings): FulfilmentResult
    {
        $response = SupplierHttp::call($this->client($settings), fn (PendingRequest $r) => $r->post($this->url($settings, '/v1/orders'), [
            'customer' => [
                'name' => $request->recipient->name,
                'email' => $request->recipient->email,
                'address' => $request->recipient->address,
            ],
            'shipping' => ['method' => $request->shippingMethod, 'cost' => $request->shippingCost],
            'items' => array_map(fn (FulfilmentLine $line) => [
                'sku' => $line->sku ?? $line->productId,
                'quantity' => $line->quantity,
                'price' => $line->unitPrice,
            ], $request->lines),
            'reference' => $request->reference,
        ]));

        return $response->successful()
            ? FulfilmentResult::accepted((string) ($response->json('reference') ?? $response->json('id')), (array) $response->json())
            : FulfilmentResult::rejected((string) ($response->json('message') ?? "DropXL refused the order ({$response->status()})."), (array) $response->json());
    }

    public function track(string $supplierReference, array $settings): ?TrackingUpdate
    {
        $response = SupplierHttp::call($this->client($settings), fn (PendingRequest $r) => $r->get($this->url($settings, '/v1/orders/track'), ['reference' => $supplierReference]));

        if (! $response->successful()) {
            throw new SupplierUnavailable("DropXL could not report tracking ({$response->status()}).");
        }

        return filled($response->json('tracking_number'))
            ? new TrackingUpdate((string) $response->json('tracking_number'), $response->json('carrier'), $response->json('tracking_url'))
            : null;
    }

    public function availability(string $sku, int $quantity, array $settings): array
    {
        $response = SupplierHttp::call($this->client($settings), fn (PendingRequest $r) => $r->get($this->url($settings, '/v1/products/availability'), ['sku' => $sku, 'quantity' => $quantity]));

        if (! $response->successful()) {
            throw new SupplierUnavailable("DropXL could not check availability ({$response->status()}).");
        }

        return (array) $response->json();
    }

    /** @param  array<string, mixed>  $settings */
    private function client(array $settings): PendingRequest
    {
        return Http::withToken((string) $settings['api_key'])->acceptJson();
    }

    /** @param  array<string, mixed>  $settings */
    private function url(array $settings, string $path): string
    {
        return rtrim((string) (($settings['api_url'] ?? null) ?: config('services.dropxl.url')), '/').$path;
    }
}
