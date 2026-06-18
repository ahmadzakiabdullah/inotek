<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'id' => 1,
                'name' => 'admin',
                'label' => 'Admin',
                'description' => 'System administrator with full access.',
            ],
            [
                'id' => 2,
                'name' => 'lecturer',
                'label' => 'Lecturer',
                'description' => 'Lecturer supervising projects.',
            ],
            [
                'id' => 3,
                'name' => 'judge',
                'label' => 'Judge',
                'description' => 'Judge evaluating projects.',
            ],
            [
                'id' => 4,
                'name' => 'user',
                'label' => 'User',
                'description' => 'Standard user / Student.',
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['id' => $role['id']], $role);
        }
    }
}
