<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create General Manager
        User::create([
            'first_name' => 'General',
            'last_name' => 'Manager',
            'email' => 'gm@fms.com',
            'password' => Hash::make('password'),
            'role' => 'general_manager',
            'contact_number' => '+1234567890',
        ]);

        // Create Admin
        User::create([
            'first_name' => 'System',
            'last_name' => 'Admin',
            'email' => 'admin@fms.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'contact_number' => '+1234567891',
        ]);

        // Create sample customer
        User::create([
            'first_name' => 'John',
            'last_name' => 'Customer',
            'email' => 'customer@fms.com',
            'password' => Hash::make('password'),
            'role' => 'customer',
            'contact_number' => '+1234567892',
        ]);

        // You can add more sample data here if needed
    }
}