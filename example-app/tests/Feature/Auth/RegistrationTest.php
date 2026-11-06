<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_is_unavailable(): void
    {
        $this->get('/register?invitation=old')->assertNotFound();
        $this->post('/register', ['name' => 'Owner', 'email' => 'owner@example.com', 'password' => 'Password123!', 'password_confirmation' => 'Password123!'])->assertNotFound();
        $this->assertDatabaseCount('users', 0);
    }
}
