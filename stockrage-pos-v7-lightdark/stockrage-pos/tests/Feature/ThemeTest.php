<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\Theme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flushCache();
    }

    private function base(array $extra = []): array
    {
        return array_merge(['business_name' => 'Shop', 'default_low_stock_threshold' => 5], $extra);
    }

    public function test_default_theme_is_the_logo_red(): void
    {
        $this->actingAs(User::factory()->create());
        $this->assertSame('red', Theme::current());
        $this->get('/dashboard')->assertOk()->assertSee('data-theme="red"', false);
    }

    public function test_theme_can_be_saved_and_is_applied_everywhere(): void
    {
        $this->actingAs(User::factory()->create());
        $this->put('/settings', $this->base(['theme_color' => 'blue']))->assertSessionHas('success');

        $this->assertSame('blue', Setting::get('theme_color'));
        $this->get('/dashboard')->assertOk()->assertSee('data-theme="blue"', false)->assertSee('name="theme-color" content="#3b82f6"', false);
        $this->get('/settings')->assertOk()->assertSee('Theme color')->assertSee('Matcha Green');

        auth()->logout();
        $this->get('/login')->assertOk()->assertSee('data-theme="blue"', false);
    }

    public function test_unknown_theme_is_rejected_and_saving_without_one_keeps_the_current(): void
    {
        $this->actingAs(User::factory()->create());
        $this->put('/settings', $this->base(['theme_color' => 'hotdog']))->assertSessionHasErrors('theme_color');
        $this->assertNull(Setting::get('theme_color'));

        Setting::set('theme_color', 'green');
        $this->put('/settings', $this->base())->assertSessionHasNoErrors();
        $this->assertSame('green', Setting::get('theme_color'));
    }

    public function test_a_bad_stored_value_falls_back_to_red(): void
    {
        Setting::set('theme_color', 'nonsense');
        $this->assertSame('red', Theme::current());
    }

    public function test_light_dark_switch_is_on_the_app_and_the_login_page(): void
    {
        $this->get('/login')->assertOk()->assertSee('window.toggleMode()', false)->assertSee('stockrage.mode', false);

        $this->actingAs(User::factory()->create());
        $this->get('/dashboard')->assertOk()
            ->assertSee('Switch between light and dark mode')
            ->assertSee('Light mode')
            ->assertSee('Dark mode')
            ->assertSee('stockrage.mode', false);
    }
}
