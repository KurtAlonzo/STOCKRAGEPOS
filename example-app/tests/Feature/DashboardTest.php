<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_redirect_to_login(): void
    {
        $u = User::factory()->create();
        $this->get(route('dashboard', ['current_team' => $u->currentTeam->slug]))->assertRedirect(route('login'));
    }

    public function test_owner_dashboard_has_no_invitation_data(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->get(route('dashboard', ['current_team' => $u->currentTeam->slug]))->assertOk()->assertInertia(fn (Assert $page) => $page->component('dashboard')->missing('pendingInvitations'));
    }
}
