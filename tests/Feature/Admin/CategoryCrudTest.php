<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\CompetitionSession;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_guest_cannot_access_categories_management(): void
    {
        $response = $this->get(route('admin.categories.index'));
        $response->assertRedirect('/login');
    }

    public function test_non_admin_cannot_access_categories_management(): void
    {
        $user = User::factory()->create(['role_id' => 4]); // user role
        $response = $this->actingAs($user)->get(route('admin.categories.index'));
        $response->assertStatus(403);
    }

    public function test_admin_can_access_categories_management(): void
    {
        $admin = User::factory()->create(['role_id' => 1]); // admin
        $response = $this->actingAs($admin)->get(route('admin.categories.index'));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('admin/categories/index'));
    }

    public function test_admin_can_create_category(): void
    {
        $admin = User::factory()->create(['role_id' => 1]);
        $session = CompetitionSession::create([
            'name' => 'Session 1',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.categories.store'), [
            'session_id' => $session->id,
            'code' => 'C1',
            'name' => 'Green Energy',
        ]);

        $response->assertRedirect(route('admin.categories.index'));
        $this->assertDatabaseHas('categories', [
            'session_id' => $session->id,
            'code' => 'C1',
            'name' => 'Green Energy',
        ]);
    }

    public function test_admin_can_update_category(): void
    {
        $admin = User::factory()->create(['role_id' => 1]);
        $session = CompetitionSession::create([
            'name' => 'Session 1',
            'is_active' => true,
        ]);

        $category = Category::create([
            'session_id' => $session->id,
            'code' => 'C1',
            'name' => 'Green Energy',
        ]);

        $response = $this->actingAs($admin)->put(route('admin.categories.update', $category), [
            'session_id' => $session->id,
            'code' => 'C1-Updated',
            'name' => 'Eco Green Tech',
        ]);

        $response->assertRedirect(route('admin.categories.index'));
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'code' => 'C1-Updated',
            'name' => 'Eco Green Tech',
        ]);
    }

    public function test_admin_can_delete_category(): void
    {
        $admin = User::factory()->create(['role_id' => 1]);
        $session = CompetitionSession::create([
            'name' => 'Session 1',
            'is_active' => true,
        ]);

        $category = Category::create([
            'session_id' => $session->id,
            'code' => 'C1',
            'name' => 'Green Energy',
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.categories.destroy', $category));

        $response->assertRedirect(route('admin.categories.index'));
        $this->assertDatabaseMissing('categories', [
            'id' => $category->id,
        ]);
    }
}
