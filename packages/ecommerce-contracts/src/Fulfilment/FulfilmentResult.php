<?php

namespace Liberu\Ecommerce\Contracts\Fulfilment;

/** The supplier answered. A rejection is final until a person acts; an outage is SupplierUnavailable. */
final readonly class FulfilmentResult
{
    /** @param  array<string, mixed>  $response  the supplier's answer, kept with the fulfilment */
    private function __construct(
        public bool $accepted,
        public ?string $supplierReference,
        public ?string $rejectionReason,
        public array $response,
    ) {}

    /** @param  array<string, mixed>  $response */
    public static function accepted(?string $supplierReference, array $response = []): self
    {
        return new self(true, $supplierReference, null, $response);
    }

    /** @param  array<string, mixed>  $response */
    public static function rejected(string $reason, array $response = []): self
    {
        return new self(false, null, $reason, $response);
    }
}
