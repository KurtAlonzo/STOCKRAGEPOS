<?php

namespace Tests\Feature;

use App\Actions\Teams\CreateTeam;
use App\Enums\TeamRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShellAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_is_closed(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertNotFound();
    }

    public function test_guest_entry_is_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_owner_entry_resolves_dashboard(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->get('/dashboard')->assertRedirect('/'.$u->currentTeam->slug.'/dashboard');
    }

    public function test_member_cannot_access_or_switch_store(): void
    {
        $u = User::factory()->create();
        $v = User::factory()->create();
        $t = $v->currentTeam;
        $t->members()->attach($u, ['role' => TeamRole::Member->value]);
        $old = $u->current_team_id;
        $this->actingAs($u)->get('/'.$t->slug.'/dashboard')->assertForbidden();
        $this->post(route('teams.switch', $t))->assertForbidden();
        $this->assertEquals($old, $u->fresh()->current_team_id);
    }

    public function test_switch_discards_old_path(): void
    {
        $u = User::factory()->create();
        $t = app(CreateTeam::class)->handle($u, 'Second');
        $this->actingAs($u)->from('/old/products/42?search=x')->post(route('teams.switch', $t))->assertRedirect('/'.$t->slug.'/dashboard');
    }

    public function test_unverified_owner_is_gated(): void
    {
        $u = User::factory()->unverified()->create();
        $this->actingAs($u)->get('/'.$u->currentTeam->slug.'/dashboard')->assertRedirect(route('verification.notice'));
    }

    public function test_no_store_does_not_grant_ownership(): void
    {
        $u = User::factory()->create();
        $u->teamMemberships()->delete();
        $this->actingAs($u)->get('/dashboard')->assertRedirect('/no-store-access');
        $this->post(route('teams.store'), ['name' => 'Bypass'])->assertForbidden();
    }

    public function test_production_denies_preview(): void
    {
        $this->get('/_dev/dashboard-reference')->assertNotFound();
        $this->post('/theme/toggle')->assertNotFound();
    }
}
