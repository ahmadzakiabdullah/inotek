<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\CompetitionSession;
use App\Models\Rubric;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RubricCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_guest_cannot_access_rubrics_management(): void
    {
        $response = $this->get(route('admin.rubrics.index'));
        $response->assertRedirect('/login');
    }

    public function test_non_admin_cannot_access_rubrics_management(): void
    {
        $user = User::factory()->create(['role_id' => 4]); // user role
        $response = $this->actingAs($user)->get(route('admin.rubrics.index'));
        $response->assertStatus(403);
    }

    public function test_admin_can_access_rubrics_management(): void
    {
        $admin = User::factory()->create(['role_id' => 1]); // admin
        $response = $this->actingAs($admin)->get(route('admin.rubrics.index'));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('admin/rubrics/index'));
    }

    public function test_admin_can_create_rubric_with_valid_weights(): void
    {
        $admin = User::factory()->create(['role_id' => 1]);
        $session = CompetitionSession::create(['name' => 'Session 1', 'is_active' => true]);
        $category = Category::create(['session_id' => $session->id, 'code' => 'C1', 'name' => 'Green Energy']);

        $response = $this->actingAs($admin)->post(route('admin.rubrics.store'), [
            'name' => 'Standard Rubric',
            'description' => 'Test rubric description.',
            'items' => [
                ['criteria_name' => 'Criteria 1', 'weight' => 0.60, 'max_points' => 5],
                ['criteria_name' => 'Criteria 2', 'weight' => 0.40, 'max_points' => 5],
            ],
            'category_ids' => [$category->id],
        ]);

        $response->assertRedirect(route('admin.rubrics.index'));

        $this->assertDatabaseHas('rubrics', [
            'name' => 'Standard Rubric',
        ]);

        $this->assertDatabaseHas('rubric_items', [
            'criteria_name' => 'Criteria 1',
            'weight' => 0.60,
        ]);

        $this->assertDatabaseHas('category_rubric_mapping', [
            'category_id' => $category->id,
        ]);
    }

    public function test_admin_can_create_rubric_if_weights_do_not_sum_to_1(): void
    {
        $admin = User::factory()->create(['role_id' => 1]);

        $response = $this->actingAs($admin)->post(route('admin.rubrics.store'), [
            'name' => 'Legacy Rubric',
            'description' => 'Test description.',
            'items' => [
                [
                    'section' => 'A',
                    'code' => 'A1',
                    'criteria_name' => 'Criteria 1',
                    'description' => 'LO2, PO3',
                    'weight' => 0.50,
                    'max_points' => 5,
                    'scale_descriptions' => ['0' => 'Zero', '1' => 'One', '2' => 'Two', '3' => 'Three', '4' => 'Four', '5' => 'Five'],
                ],
                [
                    'section' => 'A',
                    'code' => 'A2',
                    'criteria_name' => 'Criteria 2',
                    'description' => 'LO8, PO10',
                    'weight' => 0.55,
                    'max_points' => 5,
                    'scale_descriptions' => null,
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.rubrics.index'));

        $this->assertDatabaseHas('rubrics', [
            'name' => 'Legacy Rubric',
        ]);

        $this->assertDatabaseHas('rubric_items', [
            'section' => 'A',
            'code' => 'A1',
            'criteria_name' => 'Criteria 1',
            'description' => 'LO2, PO3',
            'weight' => 0.50,
        ]);
        
        $this->assertDatabaseHas('rubric_items', [
            'section' => 'A',
            'code' => 'A2',
            'criteria_name' => 'Criteria 2',
            'weight' => 0.55,
        ]);
    }

    public function test_admin_can_update_rubric(): void
    {
        $admin = User::factory()->create(['role_id' => 1]);
        $session = CompetitionSession::create(['name' => 'Session 1', 'is_active' => true]);
        $category = Category::create(['session_id' => $session->id, 'code' => 'C1', 'name' => 'Green Energy']);

        $rubric = Rubric::create(['name' => 'Original Rubric']);

        $response = $this->actingAs($admin)->put(route('admin.rubrics.update', $rubric), [
            'name' => 'Updated Rubric',
            'description' => 'Updated desc.',
            'items' => [
                ['criteria_name' => 'New Criteria', 'weight' => 1.00, 'max_points' => 5],
            ],
            'category_ids' => [$category->id],
        ]);

        $response->assertRedirect(route('admin.rubrics.index'));

        $this->assertDatabaseHas('rubrics', [
            'id' => $rubric->id,
            'name' => 'Updated Rubric',
        ]);

        $this->assertDatabaseHas('rubric_items', [
            'rubric_id' => $rubric->id,
            'criteria_name' => 'New Criteria',
            'weight' => 1.00,
        ]);
    }

    public function test_admin_can_delete_rubric(): void
    {
        $admin = User::factory()->create(['role_id' => 1]);
        $rubric = Rubric::create(['name' => 'ToDelete']);

        $response = $this->actingAs($admin)->delete(route('admin.rubrics.destroy', $rubric));

        $response->assertRedirect(route('admin.rubrics.index'));
        $this->assertDatabaseMissing('rubrics', [
            'id' => $rubric->id,
        ]);
    }
}
