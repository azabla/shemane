<?php

namespace Liberu\Ecommerce\Contracts\Fulfilment;

/**
 * Sends orders to a supplier. Register with `$this->app->tag(MyProvider::class, FulfilmentProvider::class)`.
 *
 * submit() must send the request's reference to the supplier and rely on it to dedupe, because a
 * timeout leaves the outcome unknown and the core will send the same reference again. HTTP calls
 * need a timeout. Return a result only for a real answer; throw SupplierUnavailable otherwise.
 */
interface FulfilmentProvider
{
    /** The value a product holds in supplier_id, such as `printful`. */
    public function supplierKey(): string;

    /** The module a store must have active to use this provider: its ModuleDefinition::key(). */
    public function moduleKey(): string;

    /**
     * @param  array<string, mixed>  $settings  this store's settings for the module
     *
     * @throws SupplierUnavailable
     */
    public function submit(FulfilmentRequest $request, array $settings): FulfilmentResult;

    /**
     * @param  array<string, mixed>  $settings
     *
     * @throws SupplierUnavailable
     */
    public function track(string $supplierReference, array $settings): ?TrackingUpdate;
}
