<?php

namespace App\Console\Commands;

use App\Services\Fulfilment\OrderFulfilments;
use Illuminate\Console\Command;

class SyncFulfilmentTracking extends Command
{
    protected $signature = 'fulfilment:sync-tracking';

    protected $description = 'Ask suppliers for tracking on submitted orders and record what they answer';

    public function handle(OrderFulfilments $fulfilments): int
    {
        $this->info($fulfilments->syncTracking().' fulfilments gained tracking.');

        return self::SUCCESS;
    }
}
