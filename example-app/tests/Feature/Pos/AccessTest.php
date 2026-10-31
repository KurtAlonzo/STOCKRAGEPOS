<?php

namespace Tests\Feature\Pos;

use App\Enums\TeamRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_pos_requires_verified_store_owner_for_every_route(): void
    {
        $this->withoutVite();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $base = '/'.$owner->currentTeam->slug.'/pos';
        $this->get($base)->assertRedirect('/login');
        $this->getJson($base.'/catalog')->assertUnauthorized();
        $this->postJson($base.'/quotes', [])->assertUnauthorized();
        foreach ([null, TeamRole::Admin, TeamRole::Member] as $role) {
            if ($role) {
                $owner->currentTeam->members()->syncWithoutDetaching([$other->id => ['role' => $role->value]]);
            }
            $this->actingAs($other)->get($base)->assertForbidden();
            $this->getJson($base.'/catalog')->assertForbidden();
            $this->postJson($base.'/quotes', [])->assertForbidden();
        }
        $unverified = User::factory()->unverified()->create();
        $base = '/'.$unverified->currentTeam->slug.'/pos';
        $this->actingAs($unverified)->get($base)->assertRedirect(route('verification.notice'));
        $this->getJson($base.'/catalog')->assertForbidden();
        $this->postJson($base.'/quotes', [])->assertForbidden();
    }
}
