<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property array<string, mixed> $request_json
 * @property array{status: int, body: array<string, mixed>} $result_json
 */
class CheckoutAttempt extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['request_json' => 'array', 'result_json' => 'array'];
    }
}
