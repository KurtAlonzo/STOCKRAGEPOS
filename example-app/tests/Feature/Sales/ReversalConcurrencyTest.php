<?php

namespace Tests\Feature\Sales;

use App\Actions\Inventory\RecordStockOperation;
use App\Actions\Sales\ReverseSale;
use App\Models\InventoryItem;
use App\Models\Sale;
use App\Services\Sales\ReversalAttempts;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\SalesBasket;
use Tests\TestCase;

class ReversalConcurrencyTest extends TestCase
{
    use SalesBasket;

    public function test_process_races_interruption_and_durable_rejection(): void
    {
        foreach (['same', 'different', 'stock', 'abandoned', 'rejected'] as $mode) {
            $this->race($mode);
        }
    }

    private function race(string $mode): void
    {
        $dir = sys_get_temp_dir().'/stockrage-reversal-'.bin2hex(random_bytes(8));
        mkdir($dir);
        touch($dir.'/db.sqlite');
        $old = config('database.connections.sqlite.database');
        $oldCache = config('cache.default');
        $oldCachePath = config('cache.stores.file.path');
        $workers = [];
        try {
            config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $dir.'/db.sqlite', 'database.connections.sqlite.busy_timeout' => 5000, 'cache.default' => 'file', 'cache.stores.file.path' => $dir.'/cache']);
            DB::purge('sqlite');
            Artisan::call('migrate', ['--force' => true]);
            [$u,$store,$url,$checkout,,, $cheese] = $this->basket();
            $original = $this->postJson($url.'/checkouts', $checkout)->assertOk()->json('sale');
            $sale = Sale::find($original['id']);
            $input = ['idempotencyKey' => 'aaaaaaaa-1111-4111-8111-111111111111', 'reason' => 'Returned', 'refundConfirmed' => true, 'refundMethod' => 'cash', 'refundReference' => null, 'restock' => [['inventoryItemId' => $cheese->stock_item_id, 'quantity' => '1.000']]];
            if ($mode === 'rejected') {
                InventoryItem::whereKey($cheese->stock_item_id)->update(['balance_thousandths' => 9007199254740000]);
            }
            for ($i = 0; $i < (in_array($mode, ['abandoned', 'rejected']) ? 1 : 2); $i++) {
                $request = $input;
                $workerMode = 'refund';
                if ($i && $mode === 'different') {
                    $request['idempotencyKey'] = 'bbbbbbbb-1111-4111-8111-111111111111';
                }
                if ($i && $mode === 'stock') {
                    $request = ['stockId' => $cheese->stock_item_id];
                    $workerMode = 'stock';
                }
                file_put_contents($dir.'/request'.$i, json_encode($request));
                $pipes = [];
                $process = proc_open([PHP_BINARY, base_path('tests/Support/reversal-worker.php'), $dir.'/db.sqlite', $dir.'/cache', (string) $u->id, (string) $sale->id, $dir.'/request'.$i, $dir.'/'.$i, $dir.'/gate', $workerMode], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), null, ['bypass_shell' => true]);
                $this->assertIsResource($process);
                fclose($pipes[0]);
                $workers[] = [$process, $pipes];
            }
            $deadline = microtime(true) + 20;
            foreach (array_keys($workers) as $i) {
                while (! is_file($dir.'/'.$i.'.registered')) {
                    if (microtime(true) > $deadline) {
                        $this->fail('Registration barrier timed out');
                    } usleep(10000);
                }
            }
            if ($mode === 'abandoned') {
                proc_terminate($workers[0][0]);
                foreach ([1, 2] as $pipe) {
                    fclose($workers[0][1][$pipe]);
                } proc_close($workers[0][0]);
                $workers = [];
                $this->assertSame('pending', (new ReversalAttempts)->lookup($store, $u, $sale, $input['idempotencyKey'])['status']);
                $this->assertDatabaseCount('sale_reversals', 0);
                $this->assertSame(200, (new ReverseSale)->handle($store, $u, $sale, $input)['status']);
            } else {
                if ($mode === 'rejected') {
                    $rejected = (new ReverseSale)->handle($store, $u, $sale, $input);
                    $this->assertSame(422, $rejected['status']);
                    $stock = InventoryItem::find($cheese->stock_item_id);
                    (new RecordStockOperation)->handle($store, $u, $stock, 'adjustment', '-9007199254739.000', 'Correct balance', (string) $stock->revision);
                }
                touch($dir.'/gate');
                $statuses = [];
                foreach ($workers as $i => [$process,$pipes]) {
                    $out = stream_get_contents($pipes[1]);
                    $err = stream_get_contents($pipes[2]);
                    fclose($pipes[1]);
                    fclose($pipes[2]);
                    $this->assertSame(0, proc_close($process), $err.$out);
                    $workers[$i] = null;
                    $result = json_decode($out, true, 512, JSON_THROW_ON_ERROR);
                    if ($result['status'] === 202) {
                        $result = (new ReverseSale)->handle($store, $u, $sale, json_decode(file_get_contents($dir.'/request'.$i), true));
                    }
                    $statuses[] = $result['status'];
                    if ($mode === 'rejected') {
                        $this->assertSame($rejected, $result);
                    }
                }
                $workers = [];
                sort($statuses);
                $this->assertSame(match ($mode) {
                    'same' => [200, 200], 'different' => [200, 409], 'rejected' => [422], default => [200, 200]
                }, $statuses);
            }
            $this->assertDatabaseCount('sale_reversals', $mode === 'rejected' ? 0 : 1);
            $this->assertDatabaseCount('payments', 1);
            $this->assertSame($mode === 'rejected' ? 1000 : ($mode === 'stock' ? 3000 : 2000), InventoryItem::find($cheese->stock_item_id)->balance_thousandths);
            $this->assertDatabaseCount('stock_movements', in_array($mode, ['stock', 'rejected']) ? ($mode === 'stock' ? 8 : 7) : 7);
        } finally {
            foreach ($workers as $worker) {
                if ($worker) {
                    proc_terminate($worker[0]);
                    foreach ([1, 2] as $pipe) {
                        if (is_resource($worker[1][$pipe])) {
                            fclose($worker[1][$pipe]);
                        }
                    } proc_close($worker[0]);
                }
            }
            DB::disconnect('sqlite');
            config(['database.connections.sqlite.database' => $old, 'cache.default' => $oldCache, 'cache.stores.file.path' => $oldCachePath]);
            DB::purge('sqlite');
            app('cache')->forgetDriver('file');
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $path) {
                $path->isDir() ? rmdir($path->getPathname()) : unlink($path->getPathname());
            } rmdir($dir);
        }
    }
}
