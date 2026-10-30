<?php

namespace Database\Seeders;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Create the admin account named by ADMIN_EMAIL and ADMIN_PASSWORD.
     *
     * Without both, no account is created, so the repository never ships
     * a login with a known password.
     */
    public function run(): void
    {
        $email = config('app.admin.email');
        $password = config('app.admin.password');

        if (blank($email) || blank($password)) {
            $this->command?->warn('AdminSeeder skipped: set ADMIN_EMAIL and ADMIN_PASSWORD in .env to create the admin account.');

            return;
        }

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Admin',
                'password' => Hash::make($password),
                'user_type' => UserType::Admin,
                'email_verified_at' => now(),
            ]
        );
    }
}
