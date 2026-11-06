<?php

use App\Actions\Pos\CompleteCheckout;
use App\Models\User;
use App\Services\Pos\CheckoutAttempts;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $argv[1], 'database.connections.sqlite.busy_timeout' => 5000, 'cache.default' => 'file', 'cache.stores.file.path' => $argv[2]]);
DB::purge('sqlite');
$user = User::findOrFail($argv[3]);
$input = json_decode(file_get_contents($argv[4]), true, 512, JSON_THROW_ON_ERROR);
if (($argv[7] ?? '') === 'register-only') {
    (new CheckoutAttempts)->register($user->currentTeam, $user, $input);
    touch($argv[5].'.registered');
    while (true) {
        usleep(10000);
    }
}
touch($argv[5].'.ready');
$deadline = microtime(true) + 20;
while (! is_file($argv[6])) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Barrier timed out');
    } usleep(10000);
}
$result = (new CompleteCheckout)->handle($user->currentTeam, $user, $input);
echo json_encode($result, JSON_THROW_ON_ERROR);
