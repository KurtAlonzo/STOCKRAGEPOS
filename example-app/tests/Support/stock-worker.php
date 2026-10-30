<?php

use App\Actions\Inventory\RecordStockOperation;
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
while (! file_exists($argv[4]) && microtime(true) < $deadline) {
    usleep(10000);
}
try {
    $user = User::findOrFail($argv[2]);
    $item = InventoryItem::findOrFail($argv[3]);
    (new RecordStockOperation)->handle($user->currentTeam, $user, $item, 'receipt', '1', 'Concurrent receipt', '1');
    echo 'applied';
} catch (ValidationException $e) {
    echo 'stale';
}
