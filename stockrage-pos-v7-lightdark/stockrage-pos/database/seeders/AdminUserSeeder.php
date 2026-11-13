<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /** The one admin account. Registration is disabled, so this is the only way in. */
    public function run(): void
    {
        $email = config('admin.email');
        $password = (string) config('admin.password');

        // Never create a live admin login with a guessable password.
        if (app()->isProduction() && ($password === 'password' || strlen($password) < 8)) {
            throw new \RuntimeException(
                'Set ADMIN_PASSWORD in .env to a strong password (8+ characters, not "password") before seeding a live site.'
            );
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Admin',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ],
        );
    }
}
