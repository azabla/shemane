<?php

namespace App\Jobs;

use App\Services\Fulfilment\OrderFulfilments;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

/** One supplier's part of one order. See OrderFulfilments. */
class DispatchDropshippingOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public int $orderId, public string $supplierId) {}

    /** @return list<object> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping("fulfilment:{$this->orderId}:{$this->supplierId}"))->releaseAfter(60)->expireAfter(300)];
    }

    public function handle(OrderFulfilments $fulfilments): void
    {
        $fulfilments->submit($this->orderId, $this->supplierId);
    }

    public function failed(?Throwable $e): void
    {
        app(OrderFulfilments::class)->exhausted($this->orderId, $this->supplierId, 'Supplier could not be reached after retries: '.($e?->getMessage() ?? 'unknown error'));
    }
}
