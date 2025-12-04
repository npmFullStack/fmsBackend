<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
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
        
       // Create Customer
        User::create([
            'first_name' => 'Norway',
            'last_name' => 'Mangorangca',
            'email' => 'norway@gmail.com',
            'password' => Hash::make('password'),
            'role' => 'customer',
            'contact_number' => '+1234567891',
        ]);
    }
}