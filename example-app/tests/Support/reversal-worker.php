<?php

use App\Actions\Inventory\RecordStockOperation;
use App\Actions\Sales\ReverseSale;
use App\Models\InventoryItem;
use App\Models\Sale;
use App\Models\User;
use App\Services\Sales\ReversalAttempts;
use App\Services\Sales\ReversalRequest;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'testing') {
    throw new RuntimeException('Testing CLI only');
}
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $argv[1], 'database.connections.sqlite.busy_timeout' => 5000, 'cache.default' => 'file', 'cache.stores.file.path' => $argv[2]]);
DB::purge('sqlite');
$user = User::findOrFail($argv[3]);
$sale = Sale::where('team_id', $user->current_team_id)->findOrFail($argv[4]);
$input = json_decode(file_get_contents($argv[5]), true, 512, JSON_THROW_ON_ERROR);
$mode = $argv[8] ?? 'refund';
if ($mode === 'refund') {
    (new ReversalAttempts)->register($user->currentTeam, $user, $sale, ReversalRequest::canonical($sale->id, $input));
}
touch($argv[6].'.registered');
$deadline = microtime(true) + 25;
while (! is_file($argv[7])) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Barrier timed out');
    } usleep(10000);
}
if ($mode === 'stock') {
    $stock = InventoryItem::findOrFail($input['stockId']);
    try {
        (new RecordStockOperation)->handle($user->currentTeam, $user, $stock, 'receipt', '1.000', 'Concurrent delivery', (string) $stock->revision);
        $result = ['status' => 200];
    } catch (ValidationException) {
        $result = ['status' => 409];
    }
} else {
    $result = (new ReverseSale)->handle($user->currentTeam, $user, $sale, $input);
}
echo json_encode($result, JSON_THROW_ON_ERROR);
