<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

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

        $hasGuardName = Schema::hasColumn('roles', 'guard_name');

        foreach ($roles as $role) {
            if ($hasGuardName) {
                $role['guard_name'] = 'web';
            }

            Role::updateOrCreate(['id' => $role['id']], $role);
        }
    }
}
