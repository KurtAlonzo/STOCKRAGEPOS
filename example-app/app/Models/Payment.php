<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property array<string, mixed> $input_json
 * @property CarbonImmutable $recorded_at
 */
class Payment extends Model
{
    protected $guarded = ['id'];

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
