<?php

namespace Tests\Feature\Catalog;

use App\Models\CatalogItem;
use App\Models\InventoryItem;
use App\Models\RecipeComponent;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogLifecycleTest extends TestCase
{
    public function test_competing_activation_and_component_deactivation_preserve_dependencies(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'stockrage-catalog-');
        $gate = $file.'.gate';
        $original = config('database.connections.sqlite.database');
        try {
            config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $file, 'database.connections.sqlite.busy_timeout' => 2000]);
            DB::purge('sqlite');
            Artisan::call('migrate', ['--force' => true]);
            $user = User::factory()->create();
            $stock = InventoryItem::create(['team_id' => $user->current_team_id, 'name' => 'Flour', 'kind' => 'ingredient', 'unit' => 'g']);
            $product = CatalogItem::create(['team_id' => $user->current_team_id, 'name' => 'Bread', 'sku' => 'B', 'kind' => 'food', 'active' => false, 'price_minor' => 100]);
            RecipeComponent::create(['catalog_item_id' => $product->id, 'inventory_item_id' => $stock->id, 'quantity_thousandths' => 1000, 'unit' => 'g']);
            $workers = [];
            foreach (['activate', 'deactivate'] as $mode) {
                $pipes = [];
                $process = proc_open([PHP_BINARY, base_path('tests/Support/catalog-worker.php'), $file, (string) $user->id, (string) $stock->id, $mode, $gate], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), null, ['bypass_shell' => true]);
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
            }sort($results);
            $this->assertSame(['applied', 'rejected'], $results);
            $this->assertFalse($product->fresh()->active && ! $stock->fresh()->active);
            $this->assertDatabaseCount('stock_movements', 0);
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
