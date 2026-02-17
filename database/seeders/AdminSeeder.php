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
        $users = [
            [
                'email' => 'admin@vendora.com',
                'name' => 'Admin',
                'user_type' => 'admin',
            ],
            [
                'email' => 'augustinmaputol@gmail.com',
                'name' => 'Augustin Maputol',
                'user_type' => 'vendor',
            ],
            [
                'email' => 'jayarvacs@gmail.com',
                'name' => 'Jayar Vacs',
                'user_type' => 'vendor',
            ],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'password' => Hash::make('password'),
                    'user_type' => $user['user_type'],
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}
