<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CompetitionSession;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_admin_dashboard_shows_correct_statistics_and_recent_projects()
    {
        $admin = User::factory()->create(['role_id' => 1]);
        $session = CompetitionSession::create(['name' => 'Session 2026', 'is_active' => true]);
        $category = Category::create(['session_id' => $session->id, 'code' => 'C1', 'name' => 'Tech']);

        // Create projects with different status
        // Submitted
        Project::create([
            'session_id' => $session->id,
            'category_id' => $category->id,
            'user_id' => User::factory()->create(['role_id' => 4])->id,
            'title' => 'Project Submitted',
            'abstract' => 'Abstract text',
            'institution_type' => 'utem',
            'status' => Project::STATUS_SUBMITTED,
            'supervisor_name' => 'Supervisor A',
            'supervisor_email' => 'a@utem.com',
        ]);

        // Approved
        Project::create([
            'session_id' => $session->id,
            'category_id' => $category->id,
            'user_id' => User::factory()->create(['role_id' => 4])->id,
            'title' => 'Project Approved',
            'abstract' => 'Abstract text 2',
            'institution_type' => 'utem',
            'status' => Project::STATUS_APPROVED,
            'supervisor_name' => 'Supervisor B',
            'supervisor_email' => 'b@utem.com',
        ]);

        // Rejected/Edit
        Project::create([
            'session_id' => $session->id,
            'category_id' => $category->id,
            'user_id' => User::factory()->create(['role_id' => 4])->id,
            'title' => 'Project Rejected',
            'abstract' => 'Abstract text 3',
            'institution_type' => 'utem',
            'status' => Project::STATUS_EDIT,
            'supervisor_name' => 'Supervisor C',
            'supervisor_email' => 'c@utem.com',
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->has('stats')
            ->where('stats.total_projects', 3)
            ->where('stats.pending_reviews', 1)
            ->where('stats.approved_projects', 1)
            ->where('stats.rejected_projects', 1)
            ->has('recent_projects', 3)
            ->where('role', 'admin')
        );
    }

    public function test_lecturer_dashboard_shows_only_supervised_projects_and_statistics()
    {
        $lecturer = User::factory()->create(['role_id' => 2, 'email' => 'lecturer@utem.edu.my']);
        $session = CompetitionSession::create(['name' => 'Session 2026', 'is_active' => true]);
        $category = Category::create(['session_id' => $session->id, 'code' => 'C1', 'name' => 'Tech']);

        // Supervised project by lecturer
        Project::create([
            'session_id' => $session->id,
            'category_id' => $category->id,
            'user_id' => User::factory()->create(['role_id' => 4])->id,
            'title' => 'Lecturer Supervised Project',
            'abstract' => 'Abstract text',
            'institution_type' => 'utem',
            'status' => Project::STATUS_SUBMITTED,
            'supervisor_name' => 'Lecturer A',
            'supervisor_email' => 'lecturer@utem.edu.my',
        ]);

        // Unsupervised project
        Project::create([
            'session_id' => $session->id,
            'category_id' => $category->id,
            'user_id' => User::factory()->create(['role_id' => 4])->id,
            'title' => 'Other Supervised Project',
            'abstract' => 'Abstract text 2',
            'institution_type' => 'utem',
            'status' => Project::STATUS_SUBMITTED,
            'supervisor_name' => 'Lecturer B',
            'supervisor_email' => 'other_lecturer@utem.edu.my',
        ]);

        $response = $this->actingAs($lecturer)->get(route('dashboard'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->has('stats')
            ->where('stats.total_projects', 1)
            ->where('stats.pending_reviews', 1)
            ->where('stats.approved_projects', 0)
            ->where('stats.rejected_projects', 0)
            ->has('my_projects', 1)
            ->where('my_projects.0.title', 'Lecturer Supervised Project')
            ->where('role', 'lecturer')
        );
    }

    public function test_student_dashboard_shows_active_project_if_exists()
    {
        $student = User::factory()->create(['role_id' => 4]);
        $session = CompetitionSession::create(['name' => 'Session 2026', 'is_active' => true]);
        $category = Category::create(['session_id' => $session->id, 'code' => 'C1', 'name' => 'Tech']);

        Project::create([
            'session_id' => $session->id,
            'category_id' => $category->id,
            'user_id' => $student->id,
            'title' => 'Student Project',
            'abstract' => 'Abstract text',
            'institution_type' => 'utem',
            'status' => Project::STATUS_SUBMITTED,
            'supervisor_name' => 'Supervisor A',
            'supervisor_email' => 'a@utem.com',
        ]);

        $response = $this->actingAs($student)->get(route('dashboard'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('project.title', 'Student Project')
            ->where('role', 'user')
        );
    }

    public function test_student_dashboard_shows_null_project_if_no_project_or_no_active_session()
    {
        $student = User::factory()->create(['role_id' => 4]);

        // Case A: No active session
        $response = $this->actingAs($student)->get(route('dashboard'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('project', null)
            ->where('role', 'user')
        );

        // Case B: Active session exists but student has no project
        CompetitionSession::create(['name' => 'Session 2026', 'is_active' => true]);
        $response = $this->actingAs($student)->get(route('dashboard'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('project', null)
        );
    }
}
