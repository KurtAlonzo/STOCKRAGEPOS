<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string, mixed> $request_json
 * @property CarbonImmutable $occurred_at
 */
class SaleReversal extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['request_json' => 'array', 'occurred_at' => 'immutable_datetime', 'total_minor' => 'integer', 'refund_confirmed' => 'boolean'];
    }

    /** @return BelongsTo<ReversalAttempt, $this> */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ReversalAttempt::class, 'reversal_attempt_id');
    }

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Reversals are immutable.'));
        static::deleting(fn () => throw new \LogicException('Reversals are immutable.'));
    }
}
