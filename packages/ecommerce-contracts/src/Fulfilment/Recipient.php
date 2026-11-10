<?php

namespace Liberu\Ecommerce\Contracts\Fulfilment;

/** Handed over at submission. A module should not store it unless it joins GDPR export and erasure. */
final readonly class Recipient
{
    public function __construct(
        public ?string $name,
        public ?string $email,
        public ?string $address,
        public ?string $city = null,
        public ?string $state = null,
        public ?string $postalCode = null,
        public ?string $countryCode = null,
    ) {}
}
