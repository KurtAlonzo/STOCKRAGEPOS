<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $guarded = ['id'];

    /** @return BelongsTo<StockOperation, $this> */
    public function operation(): BelongsTo
    {
        return $this->belongsTo(StockOperation::class, 'stock_operation_id');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Stock movements are immutable.'));
        static::deleting(fn () => throw new \LogicException('Stock movements are immutable.'));
    }
}
