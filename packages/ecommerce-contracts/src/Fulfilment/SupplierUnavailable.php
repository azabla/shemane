<?php

namespace Liberu\Ecommerce\Contracts\Fulfilment;

use RuntimeException;

/** The outcome is unknown or the supplier could not answer: a timeout, a 5xx, a 429. The core retries. */
final class SupplierUnavailable extends RuntimeException {}
