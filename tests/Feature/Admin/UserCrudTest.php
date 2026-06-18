<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles for testing
        $this->seed(RoleSeeder::class);
    }

    /**
     * Guest users cannot access user management.
     */
    public function test_guest_cannot_access_users_management(): void
    {
        $response = $this->get(route('admin.users.index'));

        $response->assertRedirect('/login');
    }

    /**
     * Non-admin users cannot access user management.
     */
    public function test_non_admin_cannot_access_users_management(): void
    {
        $user = User::factory()->create([
            'role_id' => 4, // user
        ]);

        $response = $this->actingAs($user)->get(route('admin.users.index'));

        $response->assertStatus(403);
    }

    /**
     * Admins can view the users management page.
     */
    public function test_admin_can_access_users_management(): void
    {
        $admin = User::factory()->create([
            'role_id' => 1, // admin
        ]);

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('admin/users/index'));
    }

    /**
     * Admins can create new user accounts.
     */
    public function test_admin_can_create_user(): void
    {
        $admin = User::factory()->create([
            'role_id' => 1, // admin
        ]);

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'New Staff Member',
            'username' => 'new_staff',
            'email' => 'staff@inotek.test',
            'password' => 'password123',
            'role_id' => 2, // lecturer
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', [
            'username' => 'new_staff',
            'email' => 'staff@inotek.test',
            'role_id' => 2,
        ]);

        $user = User::where('username', 'new_staff')->first();
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    /**
     * Admins can update user accounts.
     */
    public function test_admin_can_update_user(): void
    {
        $admin = User::factory()->create([
            'role_id' => 1, // admin
        ]);

        $user = User::factory()->create([
            'name' => 'Old Name',
            'username' => 'old_user',
            'email' => 'old@inotek.test',
            'role_id' => 4, // user
        ]);

        $response = $this->actingAs($admin)->put(route('admin.users.update', $user), [
            'name' => 'Updated Name',
            'username' => 'updated_user',
            'email' => 'updated@inotek.test',
            'password' => 'newpassword123', // changing password
            'role_id' => 3, // judge
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'username' => 'updated_user',
            'email' => 'updated@inotek.test',
            'role_id' => 3,
        ]);

        $user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $user->password));
    }

    /**
     * Admins cannot change their own admin role.
     */
    public function test_admin_cannot_demote_themselves(): void
    {
        $admin = User::factory()->create([
            'role_id' => 1, // admin
        ]);

        $response = $this->actingAs($admin)->put(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'username' => $admin->username,
            'email' => $admin->email,
            'role_id' => 4, // trying to change role to normal user
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $admin->refresh();
        $this->assertEquals(1, $admin->role_id); // remains admin
    }

    /**
     * Admins can delete other users.
     */
    public function test_admin_can_delete_user(): void
    {
        $admin = User::factory()->create([
            'role_id' => 1, // admin
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($admin)->delete(route('admin.users.destroy', $user));

        $response->assertRedirect(route('admin.users.index'));
        $this->assertSoftDeleted('users', [
            'id' => $user->id,
        ]);
    }

    /**
     * Admins cannot delete their own logged-in account.
     */
    public function test_admin_cannot_delete_themselves(): void
    {
        $admin = User::factory()->create([
            'role_id' => 1, // admin
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.users.destroy', $admin));

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
        ]);
    }
}
