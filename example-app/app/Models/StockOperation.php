<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int|null $sale_id
 * @property int|null $sale_reversal_id
 */
class StockOperation extends Model
{
    protected $guarded = ['id'];

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /** @return BelongsTo<SaleReversal, $this> */
    public function reversal(): BelongsTo
    {
        return $this->belongsTo(SaleReversal::class, 'sale_reversal_id');
    }
}
