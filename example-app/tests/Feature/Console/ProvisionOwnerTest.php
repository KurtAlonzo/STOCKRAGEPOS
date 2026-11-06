<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProvisionOwnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_provisions_unverified_owner(): void
    {
        Notification::fake();
        $this->artisan('stockrage:provision-owner')->expectsQuestion('Owner name', 'Owner')->expectsQuestion('Email address', 'OWNER@example.com')->expectsQuestion('Store name', 'Main store')->expectsQuestion('Password', 'StrongPassword123!')->expectsQuestion('Confirm password', 'StrongPassword123!')->assertSuccessful();
        $u = User::where('email', 'owner@example.com')->firstOrFail();
        $this->assertNull($u->email_verified_at);
        $this->assertTrue(Hash::check('StrongPassword123!', $u->password));
        $this->assertTrue($u->ownsTeam($u->currentTeam));
        $this->assertTrue($u->currentTeam->is_personal);
        Notification::assertSentTo($u, VerifyEmail::class);
    }

    public function test_duplicate_email_does_not_promote_or_create(): void
    {
        $u = User::factory()->create(['email' => 'OWNER@example.com']);
        $this->artisan('stockrage:provision-owner')->expectsQuestion('Owner name', 'Other')->expectsQuestion('Email address', 'OWNER@example.com')->expectsQuestion('Store name', 'Other')->expectsQuestion('Password', 'StrongPassword123!')->expectsQuestion('Confirm password', 'StrongPassword123!')->assertFailed();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('teams', 1);
    }
}
