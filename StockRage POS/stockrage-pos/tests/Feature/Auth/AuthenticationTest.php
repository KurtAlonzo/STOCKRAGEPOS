<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_offers_one_click_demo_without_credentials(): void
    {
        config(['pos.admin_password' => 'test-admin-secret']);

        $this->get('/login')->assertOk()
            ->assertSee('Fill demo credentials')
            ->assertSee(route('login.demo'), false)
            ->assertDontSee('test-admin-secret')
            ->assertDontSee('name="password"', false)
            ->assertDontSee('name="email"', false);
        $this->assertGuest();
    }

    public function test_demo_button_signs_in_as_the_configured_admin(): void
    {
        $admin = User::factory()->create(['email' => config('pos.admin_email')]);
        $otherUser = User::factory()->create();

        $this->post(route('login.demo'), ['email' => $otherUser->email])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_demo_login_requires_the_configured_admin_to_exist(): void
    {
        User::factory()->create();

        $this->post(route('login.demo'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('demo');
        $this->assertGuest();
    }

    public function test_old_login_and_reviewer_link_cannot_sign_users_in(): void
    {
        $this->post('/login')->assertMethodNotAllowed();
        $this->post('/review-sign-in')->assertNotFound();
        $this->assertGuest();
    }

    public function test_logout_returns_to_the_demo_button(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->get('/login')->assertOk()->assertSee('Fill demo credentials');
    }
}
