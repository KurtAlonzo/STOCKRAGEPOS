<?php

use App\Actions\Catalog\SaveCatalogItem;
use App\Actions\Inventory\SaveInventoryItem;
use App\Models\CatalogItem;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $argv[1], 'database.connections.sqlite.busy_timeout' => 2000]);
DB::purge('sqlite');
$deadline = microtime(true) + 10;
while (! file_exists($argv[5]) && microtime(true) < $deadline) {
    usleep(10000);
}
try {
    $user = User::findOrFail($argv[2]);
    $stock = InventoryItem::findOrFail($argv[3]);
    if ($argv[4] === 'deactivate') {
        (new SaveInventoryItem)->handle($user->currentTeam, $user, ['name' => $stock->name, 'active' => false, 'lowStockThreshold' => null, 'expectedRevision' => '1'], $stock);
    } else {
        $product = CatalogItem::firstOrFail();
        (new SaveCatalogItem)->handle($user->currentTeam, $user, ['name' => $product->name, 'sku' => $product->sku, 'kind' => 'food', 'active' => true, 'priceMinor' => 100, 'categoryId' => null, 'allowedAddonIds' => [], 'components' => [['inventoryItemId' => $stock->id, 'quantity' => '1', 'unit' => 'g']], 'expectedRevision' => '1'], $product);
    }
    echo 'applied';
} catch (ValidationException $error) {
    echo 'rejected';
}
