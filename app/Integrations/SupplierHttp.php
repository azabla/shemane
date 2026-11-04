<?php

namespace App\Integrations;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Liberu\Ecommerce\Contracts\Fulfilment\SupplierUnavailable;

/** What counts as "no answer" from a supplier's API: no connection, a timeout, a 5xx or a 429. */
final class SupplierHttp
{
    /** @param  callable(PendingRequest): Response  $call */
    public static function call(PendingRequest $request, callable $call): Response
    {
        try {
            $response = $call($request->connectTimeout(5)->timeout(20));
        } catch (ConnectionException $e) {
            throw new SupplierUnavailable('Could not reach the supplier: '.$e->getMessage());
        }

        if ($response->serverError() || in_array($response->status(), [408, 429], true)) {
            throw new SupplierUnavailable("The supplier answered {$response->status()}.");
        }

        return $response;
    }
}
