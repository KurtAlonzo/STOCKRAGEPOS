<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasFactory;

    public const COMPLETED = 'completed';
    public const VOIDED = 'voided';

    protected $fillable = [
        'user_id', 'total', 'amount_paid', 'change_amount', 'payment_method', 'status', 'voided_at', 'voided_by', 'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'voided_at' => 'datetime',
        ];
    }

    /** The cashier who made the sale (null for old sales). */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /** Reports and the dashboard only count real (not voided) sales. */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::COMPLETED);
    }

    public function isVoided(): bool
    {
        return $this->status === self::VOIDED;
    }

    /** Sales minus what the items cost you (cost is copied into each item at sale time). */
    public function getProfitAttribute(): float
    {
        return (float) $this->items->sum(fn ($i) => ((float) $i->price - (float) $i->cost) * $i->qty);
    }

    public function getTotalQtyAttribute(): int
    {
        return (int) $this->items->sum('qty');
    }
}
