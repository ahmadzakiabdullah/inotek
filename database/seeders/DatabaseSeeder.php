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
        // Seed default roles first
        $this->call(RoleSeeder::class);

        // Seed custom Admin user
        User::factory()->create([
            'name' => 'Ahmad Zaki',
            'username' => 'ahmadzaki',
            'email' => 'ahmadzaki@utem.edu.my',
            'password' => Hash::make('M@k108011!tmm'),
            'role_id' => 1, // admin
        ]);

        // Seed default Admin user
        User::factory()->create([
            'name' => 'Administrator',
            'username' => 'admin',
            'email' => 'admin@inotek.test',
            'role_id' => 1, // admin
        ]);

        // Seed default Regular user
        User::factory()->create([
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'role_id' => 4, // user
        ]);

        // Seed competition sessions, categories, and rubrics
        $this->call(CompetitionSeeder::class);
    }
}
