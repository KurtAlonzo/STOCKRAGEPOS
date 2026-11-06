<?php

namespace App\Actions\Catalog;

use App\Models\CatalogItem;
use App\Models\Category;
use App\Models\Team;
use App\Support\StoreCatalogTransaction;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class SaveCategory
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Team $store, array $input, ?Category $category = null): Category
    {
        return StoreCatalogTransaction::run($store, function () use ($store, $input, $category) {
            $current = $category ? Category::where('team_id', $store->id)->whereKey($category->id)->firstOrFail() : null;
            $data = Validator::make($input, ['name' => 'required|string|max:100', 'active' => 'required|boolean', 'expectedRevision' => $current ? 'required|string' : 'prohibited'])->validate();
            $name = trim($data['name']);
            $key = mb_strtolower($name);
            if ($name === '') {
                throw ValidationException::withMessages(['name' => 'Enter a category name.']);
            }
            if ($current && (string) $current->revision !== $data['expectedRevision']) {
                throw ValidationException::withMessages(['expectedRevision' => 'This category changed. Reload before saving.']);
            }
            if (Category::where('team_id', $store->id)->where('name_key', $key)->when($current, fn ($q) => $q->where('id', '!=', $current->id))->exists()) {
                throw ValidationException::withMessages(['name' => 'This category already exists.']);
            }
            if ($current && ! $data['active']) {
                $names = CatalogItem::where('team_id', $store->id)->where('category_id', $current->id)->where('active', true)->pluck('name');
                if ($names->isNotEmpty()) {
                    throw ValidationException::withMessages(['active' => 'Used by active products: '.$names->join(', ')]);
                }
            }
            $values = ['name' => $name, 'name_key' => $key, 'active' => $data['active']];
            if ($current) {
                $current->update([...$values, 'revision' => $current->revision + 1]);

                return $current;
            }

            return Category::create([...$values, 'team_id' => $store->id]);
        });
    }
}
