<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('pos.demo_email');
        $password = config('pos.demo_password');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen((string) $password) < 16
            || strcasecmp($email, config('pos.admin_email')) === 0) {
            throw new RuntimeException('Configure separate demo credentials first.');
        }

        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => 'Demo Admin', 'password' => $password],
        );

        if (!$user->wasRecentlyCreated && !Hash::check($password, $user->password)) {
            throw new RuntimeException('Demo email is already in use. Choose another address.');
        }
    }
}
