<?php

namespace Liberu\Ecommerce\Contracts\Fulfilment;

final readonly class TrackingUpdate
{
    public function __construct(
        public string $trackingNumber,
        public ?string $carrier = null,
        public ?string $trackingUrl = null,
    ) {}
}
