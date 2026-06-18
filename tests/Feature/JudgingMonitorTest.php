<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CompetitionSession;
use App\Models\Project;
use App\Models\User;
use App\Models\JudgeAssignment;
use App\Models\Score;
use App\Models\AuditLog;
use App\Mail\JudgeNudgeMail;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Inertia\Testing\AssertableInertia as Assert;

class JudgingMonitorTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $judge;
    protected User $judge2;
    protected User $student;
    protected CompetitionSession $session;
    protected Category $category;
    protected Project $project1;
    protected Project $project2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        // Create Users
        $this->admin = User::factory()->create(['role_id' => 1]); // admin
        $this->judge = User::factory()->create(['role_id' => 3, 'name' => 'Judge John', 'email' => 'john@judge.com']); // judge
        $this->judge2 = User::factory()->create(['role_id' => 3, 'name' => 'Judge Jane', 'email' => 'jane@judge.com']); // judge
        $this->student = User::factory()->create(['role_id' => 4]); // student

        // Create Active Session
        $this->session = CompetitionSession::create([
            'name' => 'Session 2026',
            'is_active' => true,
            'r2_locked' => false,
        ]);

        // Create Category
        $this->category = Category::create([
            'session_id' => $this->session->id,
            'code' => 'C1',
            'name' => 'Tech Category',
        ]);

        // Create Projects
        $this->project1 = Project::create([
            'session_id' => $this->session->id,
            'category_id' => $this->category->id,
            'user_id' => $this->student->id,
            'title' => 'Project Alpha',
            'abstract' => 'An innovation test.',
            'institution_type' => 'utem',
            'status' => Project::STATUS_APPROVED,
            'pcode' => 'C1-01',
            'supervisor_name' => 'Prof. Dr. Ali',
            'supervisor_email' => 'ali@utem.edu.my',
        ]);

        $this->project2 = Project::create([
            'session_id' => $this->session->id,
            'category_id' => $this->category->id,
            'user_id' => $this->student->id,
            'title' => 'Project Beta',
            'abstract' => 'Another innovation test.',
            'institution_type' => 'utem',
            'status' => Project::STATUS_APPROVED,
            'pcode' => 'C1-02',
            'supervisor_name' => 'Prof. Dr. Ali',
            'supervisor_email' => 'ali@utem.edu.my',
        ]);
    }

    /**
     * Test that admin dashboard has progress tracking details.
     */
    public function test_admin_dashboard_shows_category_progress_and_pending_judges()
    {
        // Setup assignments
        // Judge 1 assigned to project 1
        JudgeAssignment::create([
            'project_id' => $this->project1->id,
            'judge_id' => $this->judge->id,
            'session_id' => $this->session->id,
            'round_no' => 1,
        ]);

        // Judge 2 assigned to project 1
        JudgeAssignment::create([
            'project_id' => $this->project1->id,
            'judge_id' => $this->judge2->id,
            'session_id' => $this->session->id,
            'round_no' => 1,
        ]);

        // Create 1 score submitted (Judge 1 evaluated project 1)
        Score::create([
            'project_id' => $this->project1->id,
            'judge_id' => $this->judge->id,
            'session_id' => $this->session->id,
            'round_no' => 1,
            'total' => 85.5,
            'score_details' => ['Novelty' => 5, 'Execution' => 4],
            'comments' => 'Good work',
        ]);

        // Judge 2 has NOT evaluated project 1. Thus, Judge 2 is a pending judge.
        $response = $this->actingAs($this->admin)
            ->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('categoryProgress')
            ->has('pendingJudges')
            ->where('categoryProgress.0.completed_scores', 1)
            ->where('categoryProgress.0.total_assignments', 2)
            ->where('categoryProgress.0.progress_percentage', 50)
            ->where('pendingJudges.0.id', $this->judge2->id)
            ->where('pendingJudges.0.name', 'Judge Jane')
            ->has('pendingJudges.0.pending_projects', 1)
        );
    }

    /**
     * Test that admin can trigger judging reminder (nudge) mails and audit log.
     */
    public function test_nudge_endpoint_sends_emails_and_logs_audit()
    {
        Mail::fake();

        // Setup pending assignment
        JudgeAssignment::create([
            'project_id' => $this->project1->id,
            'judge_id' => $this->judge->id,
            'session_id' => $this->session->id,
            'round_no' => 1,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.judges.nudge'));

        $response->assertRedirect();
        
        // Assert email sent to judge 1
        Mail::assertSent(JudgeNudgeMail::class, function ($mail) {
            return $mail->hasTo($this->judge->email) &&
                   $mail->judge->id === $this->judge->id &&
                   $mail->pendingProjects->count() === 1 &&
                   $mail->pendingProjects->first()['pcode'] === 'C1-01';
        });

        // Assert audit log exists
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'NUDGE_JUDGES',
            'user_id' => $this->admin->id,
        ]);
    }

    /**
     * Test non-admin role is blocked from using the nudge endpoint.
     */
    public function test_non_admins_cannot_call_nudge_endpoint()
    {
        Mail::fake();

        // Setup pending assignment
        JudgeAssignment::create([
            'project_id' => $this->project1->id,
            'judge_id' => $this->judge->id,
            'session_id' => $this->session->id,
            'round_no' => 1,
        ]);

        // Student cannot access
        $response = $this->actingAs($this->student)
            ->post(route('admin.judges.nudge'));

        $response->assertStatus(403);
        Mail::assertNothingSent();

        // Judge cannot access
        $response = $this->actingAs($this->judge)
            ->post(route('admin.judges.nudge'));

        $response->assertStatus(403);
        Mail::assertNothingSent();
    }
}
