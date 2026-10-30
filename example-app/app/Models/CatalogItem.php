<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property int $id
 * @property int $team_id
 * @property string $name
 * @property string $sku
 * @property string $kind
 * @property string|null $stock_behavior
 * @property int|null $category_id
 * @property int|null $stock_item_id
 * @property bool $active
 * @property int $price_minor
 * @property int $revision
 * @property int $recipe_revision
 * @property-read InventoryItem|null $stockItem
 * @property-read Collection<int, RecipeComponent> $components
 * @property-read Collection<int, CatalogItem> $addons
 */
class CatalogItem extends Model
{
    protected $guarded = ['id'];

    /** @return BelongsTo<InventoryItem, $this> */
    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'stock_item_id');
    }

    /** @return HasMany<RecipeComponent, $this> */
    public function components(): HasMany
    {
        return $this->hasMany(RecipeComponent::class)->orderBy('inventory_item_id');
    }

    /** @return BelongsToMany<CatalogItem, $this> */
    public function addons(): BelongsToMany
    {
        return $this->belongsToMany(CatalogItem::class, 'catalog_allowed_addons', 'catalog_item_id', 'addon_id')->orderBy('catalog_items.id');
    }

    protected function casts(): array
    {
        return ['active' => 'boolean', 'price_minor' => 'integer', 'revision' => 'integer', 'recipe_revision' => 'integer'];
    }
}
