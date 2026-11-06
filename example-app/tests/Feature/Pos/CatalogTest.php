<?php

namespace Tests\Feature\Pos;

use App\Actions\Catalog\SaveCatalogItem;
use App\Actions\Teams\CreateTeam;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_paginated_picker_filters_dependencies_and_route_store_selection(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $store = $user->currentTeam;
        $save = new SaveCatalogItem;
        $base = ['categoryId' => null, 'active' => true, 'allowedAddonIds' => [], 'kind' => 'retail', 'priceMinor' => 5000, 'lowStockThreshold' => null];
        for ($i = 0; $i < 26; $i++) {
            $save->handle($store, $user, [...$base, 'name' => sprintf('Water %02d', $i), 'sku' => 'W'.$i]);
        }
        $save->handle($store, $user, [...$base, 'name' => 'Inactive', 'sku' => 'INACTIVE', 'active' => false]);
        $selected = app(CreateTeam::class)->handle($user, 'Second');
        $user->switchTeam($selected);
        $url = '/'.$store->slug.'/pos';
        DB::enableQueryLog();
        $this->withHeader('X-POS-Actor-Id', (string) $user->id)->actingAs($user)->getJson($url.'/catalog')->assertOk()->assertJsonCount(25, 'catalog.data')->assertJsonCount(25, 'inventory')->assertJsonPath('catalog.total', 26)->assertJsonPath('hasProducts', true);
        $queries = collect(DB::getQueryLog())->filter(fn ($q) => preg_match('/inventory_items|recipe_components|catalog_allowed_addons|catalog_items/', $q['query']));
        DB::disableQueryLog();
        $this->assertLessThanOrEqual(15, $queries->count());
        $this->assertSame($selected->id, $user->fresh()->current_team_id);
        $this->getJson($url.'/catalog?page=2')->assertJsonCount(1, 'catalog.data');
        $this->getJson($url.'/catalog?query=W25')->assertJsonCount(1, 'catalog.data');
        $this->getJson($url.'/catalog?query=missing')->assertJsonCount(0, 'catalog.data')->assertJsonPath('hasProducts', true);
        $this->getJson($url.'/catalog?categoryId=999')->assertNotFound();
        foreach (['page=0', 'categoryId=bad', 'query='.str_repeat('x', 256)] as $bad) {
            $this->getJson($url.'/catalog?'.$bad)->assertUnprocessable();
        }
        $this->get($url)->assertOk();
        $this->assertSame($store->id, $user->fresh()->current_team_id);
    }
}
