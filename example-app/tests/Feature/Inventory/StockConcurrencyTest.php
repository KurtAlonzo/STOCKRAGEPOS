<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StockConcurrencyTest extends TestCase
{
    public function test_competing_connections_apply_one_revision_once(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'stockrage-stock-');
        $gate = $file.'.gate';
        $original = config('database.connections.sqlite.database');
        try {
            config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $file, 'database.connections.sqlite.busy_timeout' => 2000]);
            DB::purge('sqlite');
            Artisan::call('migrate', ['--force' => true]);
            $u = User::factory()->create();
            $item = InventoryItem::create(['team_id' => $u->current_team_id, 'name' => 'Rice', 'kind' => 'ingredient', 'unit' => 'g']);
            $workers = [];
            for ($i = 0; $i < 2; $i++) {
                $pipes = [];
                $process = proc_open([PHP_BINARY, base_path('tests/Support/stock-worker.php'), $file, (string) $u->id, (string) $item->id, $gate], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), null, ['bypass_shell' => true]);
                $this->assertIsResource($process);
                fclose($pipes[0]);
                $workers[] = [$process, $pipes];
            }
            touch($gate);
            $results = [];
            foreach ($workers as [$process,$pipes]) {
                $results[] = stream_get_contents($pipes[1]);
                $errors = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $this->assertSame(0, proc_close($process), $errors);
            }
            sort($results);
            $this->assertSame(['applied', 'stale'], $results);
            $this->assertSame(1000, $item->fresh()->balance_thousandths);
            $this->assertDatabaseCount('stock_operations', 1);
            $this->assertDatabaseCount('stock_movements', 1);
        } finally {
            DB::disconnect('sqlite');
            config(['database.connections.sqlite.database' => $original]);
            DB::purge('sqlite');
            if (is_file($file)) {
                unlink($file);
            }if (is_file($gate)) {
                unlink($gate);
            }
        }
    }
}
