<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CompetitionSession;
use App\Models\JudgeAssignment;
use App\Models\Project;
use App\Models\Score;
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

    public function test_student_dashboard_lists_past_projects_with_certificates_and_published_feedback()
    {
        $student = User::factory()->create(['role_id' => 4]);
        $judge = User::factory()->create(['role_id' => 3]);
        $finalized = CompetitionSession::create(['name' => 'Sesi 1/2026', 'is_active' => false, 'r2_locked' => true]);
        $unfinished = CompetitionSession::create(['name' => 'Sesi 2/2025', 'is_active' => false, 'r2_locked' => false]);
        $current = CompetitionSession::create(['name' => 'Sesi 2/2026', 'is_active' => true]);

        $make = function (CompetitionSession $session, string $title, int $status = Project::STATUS_APPROVED, ?User $owner = null) use ($student) {
            $category = Category::firstOrCreate(['code' => 'C1'], ['name' => 'Tech']);

            return Project::create([
                'session_id' => $session->id,
                'category_id' => $category->id,
                'user_id' => ($owner ?? $student)->id,
                'title' => $title,
                'abstract' => 'Abstract text',
                'institution_type' => 'utem',
                'status' => $status,
                'award_level' => 'Gold',
                'supervisor_name' => 'Supervisor A',
                'supervisor_email' => 'a@utem.com',
            ]);
        };

        $past = $make($finalized, 'Finalized Project');
        $make($unfinished, 'Unfinished Project');
        $make($current, 'Current Project', Project::STATUS_SUBMITTED);
        $make($finalized, 'Cancelled Project', Project::STATUS_CANCELLED);
        $make($finalized, 'Someone Else', Project::STATUS_APPROVED, User::factory()->create(['role_id' => 4]));
        Score::create([
            'project_id' => $past->id,
            'judge_id' => $judge->id,
            'session_id' => $finalized->id,
            'round_no' => 1,
            'total' => 80,
            'score_details' => [],
            'comments' => 'Well presented',
        ]);

        $this->actingAs($student)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('project.title', 'Current Project')
                ->has('past_projects', 2)
                // Newest session first.
                ->where('past_projects.0.title', 'Unfinished Project')
                ->where('past_projects.0.results_published', false)
                ->where('past_projects.0.award_level', null)
                ->where('past_projects.0.feedback', [])
                ->where('past_projects.1.title', 'Finalized Project')
                ->where('past_projects.1.session_name', 'Sesi 1/2026')
                ->where('past_projects.1.award_level', 'Gold')
                ->where('past_projects.1.feedback.0.evaluations.0.comments', 'Well presented'));

        // The listed certificate can be downloaded by its owner.
        $this->actingAs($student)->get(route('certificates.downloadParticipation', $past))->assertOk();
    }

    public function test_admin_dashboard_ignores_judge_assignments_of_deleted_projects()
    {
        $admin = User::factory()->create(['role_id' => 1]);
        $judge = User::factory()->create(['role_id' => 3]);
        $session = CompetitionSession::create(['name' => 'Sesi 2/2026', 'is_active' => true]);
        $category = Category::create(['session_id' => $session->id, 'code' => 'C1', 'name' => 'Tech']);
        $project = Project::create([
            'session_id' => $session->id,
            'category_id' => $category->id,
            'user_id' => User::factory()->create(['role_id' => 4])->id,
            'pcode' => 'SE-01',
            'title' => 'Test project',
            'abstract' => 'Abstract text',
            'institution_type' => 'utem',
            'status' => Project::STATUS_APPROVED,
            'supervisor_name' => 'Supervisor A',
            'supervisor_email' => 'a@utem.com',
        ]);
        JudgeAssignment::create(['project_id' => $project->id, 'judge_id' => $judge->id, 'session_id' => $session->id, 'round_no' => 1]);

        // Soft-deleting the project leaves its assignment in place.
        $project->delete();

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('pendingJudges', 0));
    }
}
