<?php

namespace App\Models;

use App\Traits\IsStoreScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One supplier's part of an order. An order spanning two suppliers has two of these. */
class OrderFulfilment extends Model
{
    use IsStoreScoped;

    public const QUEUED = 'queued';

    public const SUBMITTED = 'submitted';

    public const SHIPPED = 'shipped';

    public const FAILED = 'failed';

    protected $fillable = [
        'order_id', 'store_id', 'supplier', 'reference', 'status', 'supplier_reference',
        'tracking_number', 'carrier', 'tracking_url', 'failure_reason', 'response', 'submitted_at', 'shipped_at',
    ];

    protected $casts = [
        'response' => 'array',
        'submitted_at' => 'datetime',
        'shipped_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isPlaced(): bool
    {
        return in_array($this->status, [self::SUBMITTED, self::SHIPPED], true);
    }
}
