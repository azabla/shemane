<?php

namespace Liberu\Ecommerce\Contracts\Fulfilment;

final readonly class FulfilmentRequest
{
    /** @param  list<FulfilmentLine>  $lines  only this supplier's lines */
    public function __construct(
        public string $reference,
        public int $storeId,
        public Recipient $recipient,
        public array $lines,
        public ?string $giftMessage = null,
        public ?string $shippingMethod = null,
        public float $shippingCost = 0.0,
    ) {}
}
