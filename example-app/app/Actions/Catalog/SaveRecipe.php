<?php

namespace App\Actions\Catalog;

use App\Models\CatalogItem;
use App\Models\InventoryItem;
use App\Models\RecipeComponent;
use App\Models\Team;
use App\Support\Quantity;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class SaveRecipe
{
    /**
     * @param  list<array<string, mixed>>  $components
     */
    public function handle(Team $store, CatalogItem $item, array $components): void
    {
        Validator::make(['components' => $components], ['components' => 'array', 'components.*.inventoryItemId' => 'required|integer', 'components.*.unit' => 'required|in:each,g,ml', 'components.*.quantity' => 'required|string'])->validate();
        if ($item->active && count($components) === 0) {
            throw ValidationException::withMessages(['components' => 'An active recipe needs at least one component.']);
        }
        $old = RecipeComponent::where('catalog_item_id', $item->id)->orderBy('inventory_item_id')->get();
        $seen = [];
        $rows = [];
        foreach ($components as $i => $part) {
            $stock = InventoryItem::where('team_id', $store->id)->whereKey($part['inventoryItemId'])->firstOrFail();
            $field = 'components.'.$i.'.inventoryItemId';
            if (isset($seen[$stock->id])) {
                throw ValidationException::withMessages([$field => 'This component is already in the recipe.']);
            }$seen[$stock->id] = true;
            if (! in_array($stock->kind, ['ingredient', 'supply'], true) || (! $stock->active && ($item->active || ! $old->contains('inventory_item_id', $stock->id)))) {
                throw ValidationException::withMessages([$field => 'Select an active ingredient or supply.']);
            }
            if ($part['unit'] !== $stock->unit) {
                throw ValidationException::withMessages(['components.'.$i.'.unit' => 'Use the configured base unit.']);
            }
            try {
                $amount = Quantity::parse($part['quantity'], $stock->unit);
            } catch (ValidationException $e) {
                throw ValidationException::withMessages(['components.'.$i.'.quantity' => array_values($e->errors())[0]]);
            }
            if ($amount <= 0) {
                throw ValidationException::withMessages(['components.'.$i.'.quantity' => 'Recipe quantity must be positive.']);
            }
            $rows[] = ['inventory_item_id' => $stock->id, 'quantity_thousandths' => $amount, 'unit' => $stock->unit];
        }
        usort($rows, fn ($a, $b) => $a['inventory_item_id'] <=> $b['inventory_item_id']);
        $previous = $old->map(fn ($r) => ['inventory_item_id' => (int) $r->inventory_item_id, 'quantity_thousandths' => (int) $r->quantity_thousandths, 'unit' => $r->unit])->all();
        if ($previous !== $rows) {
            RecipeComponent::where('catalog_item_id', $item->id)->delete();
            foreach ($rows as $row) {
                RecipeComponent::create([...$row, 'catalog_item_id' => $item->id]);
            }$item->update(['recipe_revision' => $item->recipe_revision + 1]);
        }
    }
}
