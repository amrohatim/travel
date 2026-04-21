<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Seed the application's users.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'phone' => '0900000001',
                'role' => 'admin',
            ],
            [
                'name' => 'Office User',
                'email' => 'office@example.com',
                'phone' => '0900000002',
                'role' => 'office',
            ],
            [
                'name' => 'Traveler User',
                'email' => 'traveler@example.com',
                'phone' => '0900000003',
                'role' => 'traveler',
            ],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'role' => $user['role'],
                    'phone' => $user['phone'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}
