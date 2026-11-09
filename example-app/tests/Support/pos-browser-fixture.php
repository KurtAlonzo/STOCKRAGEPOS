<?php

use App\Actions\Catalog\SaveCatalogItem;
use App\Models\InventoryItem;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'testing' || ! getenv('TASK5_STORAGE')) {
    throw new RuntimeException('Testing CLI only');
}
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->useStoragePath(getenv('TASK5_STORAGE'));
$app->make(Kernel::class)->bootstrap();
$expected = realpath(__DIR__.'/../../storage/framework/testing').DIRECTORY_SEPARATOR.'task5-browser';
if (realpath(getenv('TASK5_STORAGE')) !== $expected || config('database.connections.sqlite.database') !== $expected.DIRECTORY_SEPARATOR.'test.sqlite') {
    throw new RuntimeException('Unsafe fixture path');
}
$command = $argv[1] ?? 'counts';
if ($command === 'reset') {
    if (! is_file(config('database.connections.sqlite.database'))) {
        touch(config('database.connections.sqlite.database'));
    }
    Artisan::call('migrate:fresh', ['--force' => true]);
    app('cache')->flush();
    $u = User::factory()->create(['name' => 'Browser Owner', 'email' => 'task5@example.test', 'password' => 'password']);
    $base = ['categoryId' => null, 'active' => true, 'allowedAddonIds' => [], 'lowStockThreshold' => null];
    $save = new SaveCatalogItem;
    $water = $save->handle($u->currentTeam, $u, [...$base, 'name' => 'Water', 'sku' => 'W', 'kind' => 'retail', 'priceMinor' => 5000]);
    $cheese = $save->handle($u->currentTeam, $u, [...$base, 'name' => 'Cheese', 'sku' => 'CH', 'kind' => 'addon', 'stockBehavior' => 'direct', 'priceMinor' => 1500]);
    InventoryItem::whereKey($water->stock_item_id)->update(['balance_thousandths' => 2000]);
    InventoryItem::whereKey($cheese->stock_item_id)->update(['balance_thousandths' => 3000]);
    $parts = [];
    $ids = [];
    foreach ([['Beef', 'g', 250000, '100'], ['Sauce', 'g', -5000, '10'], ['Seasoning', 'g', 1000, '0.125'], ['Packaging', 'each', 2000, '1']] as [$name,$unit,$balance,$qty]) {
        $stock = InventoryItem::create(['team_id' => $u->current_team_id, 'name' => $name, 'kind' => $unit === 'each' ? 'supply' : 'ingredient', 'unit' => $unit, 'balance_thousandths' => $balance]);
        $ids[$name] = $stock->id;
        $parts[] = ['inventoryItemId' => $stock->id, 'unit' => $unit, 'quantity' => $qty];
    }
    $extra = $save->handle($u->currentTeam, $u, [...$base, 'name' => 'Extra beef', 'sku' => 'EX', 'kind' => 'addon', 'stockBehavior' => 'recipe', 'priceMinor' => 3000, 'components' => [['inventoryItemId' => $ids['Beef'], 'unit' => 'g', 'quantity' => '50']]]);
    $save->handle($u->currentTeam, $u, [...$base, 'name' => 'Burger', 'sku' => 'B', 'kind' => 'food', 'priceMinor' => 10000, 'components' => $parts, 'allowedAddonIds' => [$cheese->id, $extra->id]]);
    User::factory()->create(['name' => 'Other Owner', 'email' => 'other@example.test', 'password' => 'password']);
    echo json_encode(['slug' => $u->currentTeam->slug, 'actorId' => $u->id, 'storeId' => $u->current_team_id]);
} elseif ($command === 'counts') {
    echo json_encode(['sales' => DB::table('sales')->count(), 'payments' => DB::table('payments')->count(), 'movements' => DB::table('stock_movements')->count(), 'attempts' => DB::table('checkout_attempts')->get()->toArray()]);
} elseif ($command === 'price') {
    DB::table('catalog_items')->where('sku', 'W')->update(['price_minor' => 5500, 'revision' => 2]);
} elseif ($command === 'disable') {
    DB::table('catalog_items')->where('sku', 'W')->update(['active' => false]);
} elseif ($command === 'recipe') {
    DB::table('recipe_components')->where('quantity_thousandths', 100000)->update(['quantity_thousandths' => 125000]);
    DB::table('catalog_items')->where('sku', 'B')->increment('revision');
    DB::table('inventory_items')->where('name', 'Beef')->update(['balance_thousandths' => 0]);
} elseif ($command === 'off-page') {
    $u = User::where('email', 'task5@example.test')->firstOrFail();
    for ($i = 0; $i < 26; $i++) {
        (new SaveCatalogItem)->handle($u->currentTeam, $u, ['name' => sprintf('A filler %02d', $i), 'sku' => 'F'.$i, 'kind' => 'retail', 'priceMinor' => 100, 'categoryId' => null, 'active' => true, 'allowedAddonIds' => [], 'lowStockThreshold' => null]);
    }
} elseif ($command === 'second-store') {
    $u = User::where('email', 'task5@example.test')->firstOrFail();
    $store = Team::factory()->create(['name' => 'Second Browser Store']);
    $store->members()->attach($u, ['role' => 'owner']);
    echo json_encode(['slug' => $store->slug]);
} elseif ($command === 'evict') {
    app('cache')->flush();
} else {
    throw new RuntimeException('Unknown fixture action');
}
