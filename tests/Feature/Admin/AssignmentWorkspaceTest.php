<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\CompetitionSession;
use App\Models\JudgeAssignment;
use App\Models\Project;
use App\Models\Score;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected CompetitionSession $session;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create(['role_id' => 1]);
        $this->session = CompetitionSession::create(['name' => 'Session 2026', 'is_active' => true, 'r2_locked' => false]);
        $this->category = Category::create(['session_id' => $this->session->id, 'code' => 'ICT', 'name' => 'ICT']);
    }

    private function project(string $code, ?User $owner = null, string $supervisorEmail = 'supervisor@example.com'): Project
    {
        return Project::create([
            'session_id' => $this->session->id,
            'category_id' => $this->category->id,
            'user_id' => ($owner ?? User::factory()->create(['role_id' => 4]))->id,
            'pcode' => $code,
            'title' => "Project {$code}",
            'abstract' => 'Abstract',
            'institution_type' => 'utem',
            'status' => Project::STATUS_APPROVED,
            'supervisor_name' => 'Supervisor',
            'supervisor_email' => $supervisorEmail,
        ]);
    }

    public function test_workspace_sends_coverage_and_eligibility_data(): void
    {
        $judge = User::factory()->create(['role_id' => 3, 'name' => 'Judge A']);
        $supervisorJudge = User::factory()->create(['role_id' => 3, 'email' => 'Supervisor@Example.com']);
        $project = $this->project('ICT-01');
        $assignment = JudgeAssignment::create(['project_id' => $project->id, 'judge_id' => $judge->id, 'session_id' => $this->session->id, 'round_no' => 1]);
        Score::create(['project_id' => $project->id, 'judge_id' => $judge->id, 'session_id' => $this->session->id, 'round_no' => 1, 'total' => 80, 'score_details' => []]);

        $this->actingAs($this->admin)->get(route('admin.assignments.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/assignments/Index')
                ->has('projects', 1)
                ->where('projects.0.pcode', 'ICT-01')
                // The supervisor's email matches the judge case-insensitively.
                ->where("projects.0.conflicts.{$supervisorJudge->id}", 'supervisor')
                ->where('projects.0.round_one_judge_ids', [$judge->id])
                ->where('assignments.0.id', $assignment->id)
                ->where('assignments.0.scored', true)
                ->where('roundOneLocked', false)
                ->where('targetJudges', 3)
                ->missing('projects.0.supervisor_email'));
    }

    public function test_bulk_assigns_eligible_pairs_and_reports_the_skipped_ones(): void
    {
        $judge = User::factory()->create(['role_id' => 3, 'name' => 'Judge A']);
        $owner = User::factory()->create(['role_id' => 3]);
        $p1 = $this->project('ICT-01');
        $p2 = $this->project('ICT-02', $owner);
        $p3 = $this->project('ICT-03');

        $this->actingAs($this->admin)->post(route('admin.assignments.bulk'), [
            'round_no' => 1,
            'pairs' => [
                ['project_id' => $p1->id, 'judge_id' => $judge->id],
                ['project_id' => $p3->id, 'judge_id' => $judge->id],
                ['project_id' => $p2->id, 'judge_id' => $owner->id],   // owner: conflict
                ['project_id' => $p1->id, 'judge_id' => $judge->id],   // duplicate within the batch
                ['project_id' => $p1->id, 'judge_id' => $this->admin->id], // not a judge
            ],
        ])->assertSessionHasErrors('error');

        $this->assertSame(2, JudgeAssignment::count());
        $this->assertDatabaseHas('judge_assignments', ['project_id' => $p1->id, 'judge_id' => $judge->id, 'round_no' => 1]);
        $this->assertDatabaseHas('judge_assignments', ['project_id' => $p3->id, 'judge_id' => $judge->id, 'round_no' => 1]);

        $message = session('errors')->first('error');
        $this->assertStringContainsString('Assigned 2, skipped 3', $message);
        $this->assertStringContainsString('conflict of interest', $message);

        // One summary notification for the judge, not one per project.
        $this->assertSame(1, $judge->notifications()->count());
    }

    public function test_bulk_assignment_succeeds_without_errors_when_every_pair_is_eligible(): void
    {
        $judges = User::factory()->count(2)->create(['role_id' => 3]);
        $project = $this->project('ICT-01');

        $this->actingAs($this->admin)->post(route('admin.assignments.bulk'), [
            'round_no' => 1,
            'pairs' => $judges->map(fn (User $judge) => ['project_id' => $project->id, 'judge_id' => $judge->id])->all(),
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, JudgeAssignment::where('project_id', $project->id)->count());
    }

    public function test_bulk_respects_round_locks(): void
    {
        $judge = User::factory()->create(['role_id' => 3]);
        $project = $this->project('ICT-01');
        // Any Round 2 assignment closes Round 1.
        JudgeAssignment::create(['project_id' => $project->id, 'judge_id' => User::factory()->create(['role_id' => 3])->id, 'session_id' => $this->session->id, 'round_no' => 2]);

        $this->actingAs($this->admin)->post(route('admin.assignments.bulk'), [
            'round_no' => 1,
            'pairs' => [['project_id' => $project->id, 'judge_id' => $judge->id]],
        ])->assertSessionHasErrors(['error' => 'Round 1 is closed because Round 2 judging has started.']);

        $this->assertDatabaseMissing('judge_assignments', ['judge_id' => $judge->id]);
    }

    public function test_assignment_can_be_removed_with_post_for_iis(): void
    {
        $judge = User::factory()->create(['role_id' => 3]);
        $project = $this->project('ICT-01');
        $assignment = JudgeAssignment::create(['project_id' => $project->id, 'judge_id' => $judge->id, 'session_id' => $this->session->id, 'round_no' => 1]);

        $this->actingAs($this->admin)->post(route('admin.assignments.delete', $assignment))->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('judge_assignments', ['id' => $assignment->id]);
    }
}
