<?php

namespace Liberu\Ecommerce\FixtureSupplier;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Liberu\Ecommerce\Contracts\Fulfilment\FulfilmentProvider;
use Liberu\Ecommerce\Contracts\Fulfilment\FulfilmentRequest;
use Liberu\Ecommerce\Contracts\Fulfilment\FulfilmentResult;
use Liberu\Ecommerce\Contracts\Fulfilment\SupplierUnavailable;
use Liberu\Ecommerce\Contracts\Fulfilment\TrackingUpdate;

class FixtureFulfilment implements FulfilmentProvider
{
    public const URL = 'https://fixture-supplier.test';

    public function supplierKey(): string
    {
        return 'fixture';
    }

    public function moduleKey(): string
    {
        return 'fixture-supplier';
    }

    public function submit(FulfilmentRequest $request, array $settings): FulfilmentResult
    {
        try {
            $response = Http::withToken($settings['api_token'])->connectTimeout(5)->timeout(20)->post(self::URL.'/orders', [
                'reference' => $request->reference,
                'recipient' => $request->recipient->name,
                'lines' => array_map(fn ($line) => ['sku' => $line->sku, 'quantity' => $line->quantity], $request->lines),
            ]);
        } catch (ConnectionException $e) {
            throw new SupplierUnavailable($e->getMessage());
        }

        if ($response->serverError() || $response->status() === 429) {
            throw new SupplierUnavailable("The supplier answered {$response->status()}.");
        }

        return $response->successful()
            ? FulfilmentResult::accepted((string) $response->json('id'), (array) $response->json())
            : FulfilmentResult::rejected((string) $response->json('error', 'Rejected'), (array) $response->json());
    }

    public function track(string $supplierReference, array $settings): ?TrackingUpdate
    {
        $response = Http::withToken($settings['api_token'])->timeout(20)->get(self::URL.'/orders/'.$supplierReference);

        return filled($response->json('tracking'))
            ? new TrackingUpdate($response->json('tracking'), $response->json('carrier'), $response->json('url'))
            : null;
    }
}
