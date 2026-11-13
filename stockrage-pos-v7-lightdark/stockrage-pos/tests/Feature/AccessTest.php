<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_page_requires_login(): void
    {
        foreach (['/dashboard', '/pos', '/products', '/products/create', '/sales', '/stock-history', '/reports', '/reports/export', '/settings', '/profile'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }

        $this->postJson('/pos/checkout', [])->assertUnauthorized();
    }

    public function test_public_registration_is_disabled(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['name' => 'x', 'email' => 'x@x.com', 'password' => 'password', 'password_confirmation' => 'password'])->assertStatus(404);
    }

    public function test_admin_can_log_in_and_every_page_renders(): void
    {
        $this->seed();

        $this->post('/login', ['email' => 'admin@stockrage.test', 'password' => 'password'])->assertRedirect('/dashboard');

        foreach (['/dashboard', '/pos', '/products', '/products/create', '/sales', '/stock-history', '/reports', '/reports?period=monthly', '/reports?period=yearly', '/settings', '/profile'] as $url) {
            $this->get($url)->assertOk()->assertSee('Stockrage', false);
        }

        $this->get('/')->assertRedirect('/dashboard');
    }

    public function test_live_site_refuses_to_seed_a_weak_admin_password(): void
    {
        $this->app['env'] = 'production';

        config(['admin.password' => 'password']);
        try {
            $this->expectException(\RuntimeException::class);
            app(\Database\Seeders\AdminUserSeeder::class)->run();
        } finally {
            $this->assertDatabaseCount('users', 0);
        }
    }

    public function test_live_site_accepts_a_strong_admin_password(): void
    {
        $this->app['env'] = 'production';

        config(['admin.password' => 'Takoyaki-2026!']);
        app(\Database\Seeders\AdminUserSeeder::class)->run();

        $this->assertDatabaseCount('users', 1);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('Takoyaki-2026!', User::firstOrFail()->password));
    }

    public function test_login_page_shows_the_logo(): void
    {
        $this->get('/login')->assertOk()->assertSee('images/stockrage-logo-light.png', false)->assertDontSee('bg-white px-8 py-7', false);
    }
}
