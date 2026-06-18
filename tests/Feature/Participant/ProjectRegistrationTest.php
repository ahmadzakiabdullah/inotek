<?php

namespace Tests\Feature\Participant;

use App\Models\Category;
use App\Models\CompetitionSession;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_guest_cannot_access_projects_dashboard(): void
    {
        $response = $this->get(route('projects.index'));
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_access_projects_dashboard(): void
    {
        $user = User::factory()->create(['role_id' => 4]); // student role
        $response = $this->actingAs($user)->get(route('projects.index'));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('participant/project/index'));
    }

    public function test_user_can_save_project_draft(): void
    {
        $user = User::factory()->create(['role_id' => 4]);
        $session = CompetitionSession::create(['name' => 'Session 1', 'is_active' => true]);
        $category = Category::create(['session_id' => $session->id, 'code' => 'C1', 'name' => 'Tech', 'allow_team' => true]);

        $response = $this->actingAs($user)->post(route('projects.store'), [
            'category_id' => $category->id,
            'title' => 'Sample Inventions',
            'abstract' => 'This is a sample abstract detailing innovation and design.',
            'institution_type' => 'utem',
            'supervisor_name' => 'Dr. Supervisor',
            'supervisor_email' => 'supervisor@utem.edu.my',
            'supervisor_phone' => '+60123456789',
            'team_members' => [
                ['name' => 'Member A', 'email' => 'a@test.com', 'phone' => '123'],
                ['name' => 'Member B', 'email' => 'b@test.com', 'phone' => '456'],
            ],
        ]);

        $response->assertRedirect(route('projects.index'));

        $this->assertDatabaseHas('projects', [
            'user_id' => $user->id,
            'title' => 'Sample Inventions',
            'status' => Project::STATUS_NEW,
        ]);

        $this->assertDatabaseHas('team_members', [
            'name' => 'Member A',
            'email' => 'a@test.com',
        ]);
    }

    public function test_user_cannot_save_team_members_if_category_disallows_team(): void
    {
        $user = User::factory()->create(['role_id' => 4]);
        $session = CompetitionSession::create(['name' => 'Session 1', 'is_active' => true]);
        $category = Category::create(['session_id' => $session->id, 'code' => 'C1', 'name' => 'Tech', 'allow_team' => false]);

        $response = $this->actingAs($user)->post(route('projects.store'), [
            'category_id' => $category->id,
            'title' => 'Sample Inventions',
            'abstract' => 'This is a sample abstract detailing innovation and design.',
            'institution_type' => 'utem',
            'supervisor_name' => 'Dr. Supervisor',
            'supervisor_email' => 'supervisor@utem.edu.my',
            'supervisor_phone' => '+60123456789',
            'team_members' => [
                ['name' => 'Member A', 'email' => 'a@test.com', 'phone' => '123'],
            ],
        ]);

        $response->assertSessionHasErrors('team_members');
        $this->assertDatabaseMissing('projects', [
            'user_id' => $user->id,
            'title' => 'Sample Inventions',
        ]);
    }

    public function test_student_limited_to_one_project_submission(): void
    {
        $user = User::factory()->create(['role_id' => 4]); // user role (student)
        $session = CompetitionSession::create(['name' => 'Session 1', 'is_active' => true]);
        $category = Category::create(['session_id' => $session->id, 'code' => 'C1', 'name' => 'Tech']);

        // Register first project
        Project::create([
            'session_id' => $session->id,
            'category_id' => $category->id,
            'user_id' => $user->id,
            'title' => 'Project 1',
            'abstract' => 'Sample',
            'institution_type' => 'utem',
            'status' => Project::STATUS_NEW,
            'supervisor_name' => 'Super',
            'supervisor_email' => 's@test.com',
        ]);

        // Attempt second project registration
        $response = $this->actingAs($user)->post(route('projects.store'), [
            'category_id' => $category->id,
            'title' => 'Project 2',
            'abstract' => 'Sample 2',
            'institution_type' => 'utem',
            'supervisor_name' => 'Super',
            'supervisor_email' => 's@test.com',
        ]);

        $response->assertSessionHasErrors('project');
        $this->assertDatabaseMissing('projects', [
            'title' => 'Project 2',
        ]);
    }

    public function test_user_can_submit_project_generating_pcode(): void
    {
        $user = User::factory()->create(['role_id' => 4]);
        $session = CompetitionSession::create(['name' => 'Session 1', 'is_active' => true]);
        $category = Category::create(['session_id' => $session->id, 'code' => 'C1', 'name' => 'Tech']);

        $project = Project::create([
            'session_id' => $session->id,
            'category_id' => $category->id,
            'user_id' => $user->id,
            'title' => 'Project 1',
            'abstract' => 'Sample',
            'institution_type' => 'utem',
            'status' => Project::STATUS_NEW,
            'supervisor_name' => 'Super',
            'supervisor_email' => 's@test.com',
        ]);

        $response = $this->actingAs($user)->post(route('projects.submit', $project));

        $response->assertRedirect(route('projects.index'));

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'status' => Project::STATUS_SUBMITTED,
            'pcode' => 'C1-01',
        ]);
    }

    public function test_user_cannot_edit_project_after_final_submission(): void
    {
        $user = User::factory()->create(['role_id' => 4]);
        $session = CompetitionSession::create(['name' => 'Session 1', 'is_active' => true]);
        $category = Category::create(['session_id' => $session->id, 'code' => 'C1', 'name' => 'Tech']);

        $project = Project::create([
            'session_id' => $session->id,
            'category_id' => $category->id,
            'user_id' => $user->id,
            'title' => 'Project 1',
            'abstract' => 'Sample',
            'institution_type' => 'utem',
            'status' => Project::STATUS_SUBMITTED,
            'supervisor_name' => 'Super',
            'supervisor_email' => 's@test.com',
        ]);

        $response = $this->actingAs($user)->put(route('projects.update', $project), [
            'category_id' => $category->id,
            'title' => 'Updated Title',
            'abstract' => 'New Sample Abstract',
            'institution_type' => 'utem',
            'supervisor_name' => 'Super',
            'supervisor_email' => 's@test.com',
        ]);

        $response->assertSessionHasErrors('status');
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'title' => 'Project 1', // Title remains unchanged
        ]);
    }
}
