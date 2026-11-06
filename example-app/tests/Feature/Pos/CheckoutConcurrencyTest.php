<?php

namespace Tests\Feature\Pos;

use App\Actions\Catalog\SaveCatalogItem;
use App\Actions\Pos\CompleteCheckout;
use App\Actions\Pos\CreateQuote;
use App\Models\InventoryItem;
use App\Models\User;
use App\Services\Pos\CheckoutAttempts;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CheckoutConcurrencyTest extends TestCase
{
    public function test_competing_keys_same_key_and_duplicate_reference_are_serialized(): void
    {
        foreach (['same', 'last', 'reference'] as $mode) {
            $this->race($mode);
        }
    }

    private function race(string $mode): void
    {
        $dir = sys_get_temp_dir().'/stockrage-checkout-'.bin2hex(random_bytes(8));
        mkdir($dir);
        $file = $dir.'/db.sqlite';
        touch($file);
        $old = config('database.connections.sqlite.database');
        $cache = config('cache.default');
        $cachePath = config('cache.stores.file.path');
        try {
            config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $file, 'database.connections.sqlite.busy_timeout' => 5000, 'cache.default' => 'file', 'cache.stores.file.path' => $dir.'/cache']);
            DB::purge('sqlite');
            Artisan::call('migrate', ['--force' => true]);
            $u = User::factory()->create();
            $item = (new SaveCatalogItem)->handle($u->currentTeam, $u, ['name' => 'Water', 'sku' => 'W', 'kind' => 'retail', 'priceMinor' => 5000, 'categoryId' => null, 'active' => true, 'allowedAddonIds' => [], 'lowStockThreshold' => null]);
            InventoryItem::whereKey($item->stock_item_id)->update(['balance_thousandths' => $mode === 'last' ? 1000 : 10000]);
            $lines = [['id' => '11111111-1111-4111-8111-111111111111', 'productId' => $item->id, 'quantity' => 1, 'addonIds' => []]];
            $payment = $mode === 'reference' ? ['method' => 'gcash', 'receivedConfirmed' => true, 'reference' => 'receipt-one'] : ['method' => 'cash', 'tenderedMinor' => 5000];
            $quote = (new CreateQuote)->handle($u->currentTeam, $u, ['lines' => $lines, 'payment' => $payment]);
            $request = ['idempotencyKey' => '11111111-aaaa-4111-8111-111111111111', 'quoteId' => $quote['id'], 'quoteRevision' => $quote['revision'], 'lines' => $lines, 'payment' => $payment, 'acknowledgedWarningIds' => []];
            $workers = [];
            for ($i = 0; $i < 2; $i++) {
                $input = $request;
                if ($i && $mode !== 'same') {
                    $input['idempotencyKey'] = '22222222-aaaa-4222-8222-222222222222';
                }
                file_put_contents($dir.'/request'.$i, json_encode($input));
                $pipes = [];
                $process = proc_open([PHP_BINARY, base_path('tests/Support/checkout-worker.php'), $file, $dir.'/cache', (string) $u->id, $dir.'/request'.$i, $dir.'/'.$i, $dir.'/gate'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), null, ['bypass_shell' => true]);
                $this->assertIsResource($process);
                fclose($pipes[0]);
                $workers[] = [$process, $pipes];
            }
            $deadline = microtime(true) + 20;
            while (! is_file($dir.'/0.ready') || ! is_file($dir.'/1.ready')) {
                if (microtime(true) > $deadline) {
                    $this->fail('Worker readiness timed out');
                } usleep(10000);
            }
            touch($dir.'/gate');
            $statuses = [];
            foreach ($workers as [$process,$pipes]) {
                $out = stream_get_contents($pipes[1]);
                $err = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $this->assertSame(0, proc_close($process), $err.$out);
                $result = json_decode($out, true, 512, JSON_THROW_ON_ERROR);
                if ($result['status'] === 202) {
                    $original = json_decode(file_get_contents($dir.'/request'.count($statuses)), true);
                    $result = (new CompleteCheckout)->handle($u->currentTeam, $u, $original);
                } $statuses[] = $result['status'];
            }
            sort($statuses);
            $this->assertSame($mode === 'same' ? [200, 200] : [200, 409], $statuses);
            $this->assertDatabaseCount('sales', 1);
            $this->assertDatabaseCount('payments', 1);
            $this->assertDatabaseCount('stock_movements', 1);
            $this->assertSame($mode === 'last' ? 0 : 9000, InventoryItem::find($item->stock_item_id)->balance_thousandths);
            // A committed registration survives a processor disappearing before posting.
            $input = [...$request, 'idempotencyKey' => '33333333-aaaa-4333-8333-333333333333'];
            file_put_contents($dir.'/abandoned-request', json_encode($input));
            $pipes = [];
            $abandoned = proc_open([PHP_BINARY, base_path('tests/Support/checkout-worker.php'), $file, $dir.'/cache', (string) $u->id, $dir.'/abandoned-request', $dir.'/abandoned', $dir.'/gate', 'register-only'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), null, ['bypass_shell' => true]);
            $this->assertIsResource($abandoned);
            fclose($pipes[0]);
            $deadline = microtime(true) + 20;
            while (! is_file($dir.'/abandoned.registered')) {
                if (microtime(true) > $deadline) {
                    proc_terminate($abandoned);
                    $this->fail('Durable registration barrier timed out');
                }usleep(10000);
            }
            proc_terminate($abandoned);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($abandoned);
            $this->assertSame('pending', (new CheckoutAttempts)->lookup($u->currentTeam, $u, $input['idempotencyKey'])['status']);
            $result = (new CompleteCheckout)->handle($u->currentTeam, $u, $input);
            $this->assertSame($mode === 'same' ? 200 : 409, $result['status']);
        } finally {
            DB::disconnect('sqlite');
            config(['database.connections.sqlite.database' => $old, 'cache.default' => $cache, 'cache.stores.file.path' => $cachePath]);
            DB::purge('sqlite');
            app('cache')->forgetDriver('file');
            // Only this randomly generated test-owned directory is removed.
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $path) {
                $path->isDir() ? rmdir($path->getPathname()) : unlink($path->getPathname());
            } rmdir($dir);
        }
    }
}
