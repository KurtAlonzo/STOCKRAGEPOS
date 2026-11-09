<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property array<string, mixed> $request_json
 * @property array{status: int, body: array<string, mixed>}|null $result_json
 */
class ReversalAttempt extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['request_json' => 'array', 'result_json' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $attempt) {
            if (array_diff(array_keys($attempt->getDirty()), ['status', 'result_json', 'updated_at']) || ($attempt->getOriginal('status') !== 'pending' && $attempt->isDirty(['status', 'result_json']))) {
                throw new \LogicException('Reversal operation identity and terminal outcomes are immutable.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Reversal attempts are retained.'));
    }
}
