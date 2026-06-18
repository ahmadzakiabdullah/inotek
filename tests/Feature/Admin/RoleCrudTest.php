<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles for testing
        $this->seed(RoleSeeder::class);
    }

    /**
     * Non-logged in users are redirected to login.
     */
    public function test_guest_cannot_access_roles_management(): void
    {
        $response = $this->get(route('admin.roles.index'));

        $response->assertRedirect('/login');
    }

    /**
     * Non-admin users receive a 403 Forbidden.
     */
    public function test_non_admin_cannot_access_roles_management(): void
    {
        $user = User::factory()->create([
            'role_id' => 4, // user
        ]);

        $response = $this->actingAs($user)->get(route('admin.roles.index'));

        $response->assertStatus(403);
    }

    /**
     * Admin users can view the roles list.
     */
    public function test_admin_can_access_roles_management(): void
    {
        $admin = User::factory()->create([
            'role_id' => 1, // admin
        ]);

        $response = $this->actingAs($admin)->get(route('admin.roles.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('admin/roles/index'));
    }

    /**
     * Admin can create a custom role.
     */
    public function test_admin_can_create_custom_role(): void
    {
        $admin = User::factory()->create([
            'role_id' => 1, // admin
        ]);

        $response = $this->actingAs($admin)->post(route('admin.roles.store'), [
            'name' => 'manager',
            'label' => 'Manager',
            'description' => 'A custom role for managers.',
        ]);

        $response->assertRedirect(route('admin.roles.index'));
        $this->assertDatabaseHas('roles', [
            'name' => 'manager',
            'label' => 'Manager',
        ]);
    }

    /**
     * Admin can update a custom role.
     */
    public function test_admin_can_update_custom_role(): void
    {
        $admin = User::factory()->create([
            'role_id' => 1, // admin
        ]);

        $role = Role::create([
            'name' => 'editor',
            'label' => 'Editor',
            'description' => 'A custom editor role.',
        ]);

        $response = $this->actingAs($admin)->put(route('admin.roles.update', $role), [
            'name' => 'senior-editor',
            'label' => 'Senior Editor',
            'description' => 'An updated description.',
        ]);

        $response->assertRedirect(route('admin.roles.index'));
        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'senior-editor',
            'label' => 'Senior Editor',
        ]);
    }

    /**
     * Admin cannot change system name for protected roles.
     */
    public function test_admin_cannot_rename_protected_roles(): void
    {
        $admin = User::factory()->create([
            'role_id' => 1, // admin
        ]);

        $adminRole = Role::find(1); // admin role is ID 1

        $response = $this->actingAs($admin)->put(route('admin.roles.update', $adminRole), [
            'name' => 'super-admin', // renaming protected name
            'label' => 'Super Admin',
            'description' => 'Updated desc.',
        ]);

        // Should redirect back and not update the name
        $response->assertRedirect(route('admin.roles.index'));
        $this->assertDatabaseHas('roles', [
            'id' => 1,
            'name' => 'admin', // remains 'admin'
        ]);
    }

    /**
     * Admin can delete custom roles.
     */
    public function test_admin_can_delete_custom_role(): void
    {
        $admin = User::factory()->create([
            'role_id' => 1, // admin
        ]);

        $role = Role::create([
            'name' => 'custom-role',
            'label' => 'Custom Role',
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.roles.destroy', $role));

        $response->assertRedirect(route('admin.roles.index'));
        $this->assertDatabaseMissing('roles', [
            'id' => $role->id,
        ]);
    }

    /**
     * Admin cannot delete protected roles.
     */
    public function test_admin_cannot_delete_protected_roles(): void
    {
        $admin = User::factory()->create([
            'role_id' => 1, // admin
        ]);

        $lecturerRole = Role::find(2); // lecturer role is ID 2

        $response = $this->actingAs($admin)->delete(route('admin.roles.destroy', $lecturerRole));

        $response->assertRedirect(route('admin.roles.index'));
        $this->assertDatabaseHas('roles', [
            'id' => 2,
        ]);
    }
}
