<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Core role records are needed in every environment.
        $this->call(RoleSeeder::class);

        // Demo accounts use predictable credentials and must never be created in production.
        if (! app()->environment(['local', 'testing'])) {
            if (! User::withRole('admin')->exists()) {
                $email = env('INOTEK_BOOTSTRAP_ADMIN_EMAIL');
                $password = env('INOTEK_BOOTSTRAP_ADMIN_PASSWORD');

                if (! filter_var($email, FILTER_VALIDATE_EMAIL) || ! is_string($password) || strlen($password) < 16) {
                    throw new RuntimeException('Set a valid INOTEK_BOOTSTRAP_ADMIN_EMAIL and an INOTEK_BOOTSTRAP_ADMIN_PASSWORD of at least 16 characters to create the first production admin.');
                }

                if (User::withTrashed()->where('email', $email)->exists()) {
                    throw new RuntimeException('INOTEK_BOOTSTRAP_ADMIN_EMAIL is already associated with an account. Use an unused email for the first production admin.');
                }

                $username = Str::slug(Str::before($email, '@')) ?: 'admin';
                if (User::withTrashed()->where('username', $username)->exists()) {
                    $username .= '-'.Str::lower(Str::random(8));
                }

                $admin = User::create([
                    'name' => 'System Administrator',
                    'username' => $username,
                    'email' => $email,
                    'password' => $password,
                    'role_id' => \App\Models\Role::where('name', 'admin')->value('id'),
                ]);
                $admin->forceFill(['email_verified_at' => now()])->save();
            }

            return;
        }

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
