<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    public const TYPES = ['in', 'sale', 'adjustment', 'void'];

    protected $fillable = [
        'product_id', 'type', 'qty_change', 'previous_qty', 'new_qty', 'reason',
    ];

    protected function casts(): array
    {
        return [
            'qty_change' => 'integer',
            'previous_qty' => 'integer',
            'new_qty' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
