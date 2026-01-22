<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@vendora.com'],
            [
                'name' => 'Admin',
                'business_name' => 'Vendora Admin',
                'email' => 'admin@vendora.com',
                'password' => Hash::make('password'),
                'subscription_plan' => 'enterprise',
                'user_type' => 'admin',
                'email_verified_at' => now(),
            ]
        );
    }
}
