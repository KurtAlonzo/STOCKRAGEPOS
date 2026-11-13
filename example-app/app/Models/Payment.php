<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string, mixed> $input_json
 * @property CarbonImmutable $recorded_at
 */
class Payment extends Model
{
    protected $guarded = ['id'];

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    protected function casts(): array
    {
        return ['input_json' => 'array', 'recorded_at' => 'immutable_datetime', 'received_minor' => 'integer', 'change_minor' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Payments are immutable.'));
        static::deleting(fn () => throw new \LogicException('Payments are immutable.'));
    }
}
