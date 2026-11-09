<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property array<int, array<string, mixed>> $lines_json
 * @property CarbonImmutable $occurred_at
 */
class Sale extends Model
{
    protected $guarded = ['id'];

    /** @return HasOne<Payment, $this> */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    /** @return HasOne<SaleReversal, $this> */
    public function reversal(): HasOne
    {
        return $this->hasOne(SaleReversal::class);
    }

    protected function casts(): array
    {
        return ['lines_json' => 'array', 'occurred_at' => 'immutable_datetime', 'subtotal_minor' => 'integer', 'total_minor' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $sale) {
            if (array_diff(array_keys($sale->getDirty()), ['status', 'updated_at'])) {
                throw new \LogicException('Sale snapshots are immutable.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Sales are immutable.'));
    }
}
