<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryRubricMapping;
use App\Models\CompetitionSession;
use App\Models\Project;
use App\Models\Rubric;
use App\Models\RubricItem;
use App\Models\User;
use App\Models\JudgeAssignment;
use App\Models\Score;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JudgeEvaluationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $judge;
    protected User $judge2;
    protected User $student;
    protected CompetitionSession $session;
    protected Category $category;
    protected Project $project;
    protected Rubric $rubric;
    protected RubricItem $item1;
    protected RubricItem $item2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        // Create Users
        $this->admin = User::factory()->create(['role_id' => 1]);
        $this->judge = User::factory()->create(['role_id' => 3]);
        $this->judge2 = User::factory()->create(['role_id' => 3]);
        $this->student = User::factory()->create(['role_id' => 4]);

        // Create Active Session, Category, Rubric
        $this->session = CompetitionSession::create([
            'name' => 'Session 2026',
            'is_active' => true,
            'r2_locked' => false,
        ]);

        $this->category = Category::create([
            'session_id' => $this->session->id,
            'code' => 'C1',
            'name' => 'Tech Category',
        ]);

        $this->rubric = Rubric::create([
            'name' => 'Standard Rubric',
            'description' => 'Test Rubric',
        ]);

        $this->item1 = RubricItem::create([
            'rubric_id' => $this->rubric->id,
            'criteria_name' => 'Novelty',
            'weight' => 0.60,
            'max_points' => 5,
        ]);

        $this->item2 = RubricItem::create([
            'rubric_id' => $this->rubric->id,
            'criteria_name' => 'Execution',
            'weight' => 0.40,
            'max_points' => 5,
        ]);

        CategoryRubricMapping::create([
            'category_id' => $this->category->id,
            'rubric_id' => $this->rubric->id,
        ]);

        // Create Approved Project
        $this->project = Project::create([
            'session_id' => $this->session->id,
            'category_id' => $this->category->id,
            'user_id' => $this->student->id,
            'title' => 'Innovative Project',
            'abstract' => 'Abstract text',
            'institution_type' => 'utem',
            'status' => Project::STATUS_APPROVED,
            'supervisor_name' => 'Supervisor A',
            'supervisor_email' => 'a@utem.com',
        ]);
    }

    public function test_guests_cannot_access_judge_or_admin_assignment_routes()
    {
        $this->get(route('judge.evaluations.index'))->assertRedirect(route('login'));
        $this->get(route('admin.assignments.index'))->assertRedirect(route('login'));
    }

    public function test_non_judges_cannot_access_judge_evaluations()
    {
        $this->actingAs($this->student)->get(route('judge.evaluations.index'))->assertStatus(403);
    }

    public function test_judge_dashboard_only_shows_assigned_projects()
    {
        // Unassigned project response check
        $response = $this->actingAs($this->judge)->get(route('judge.evaluations.index'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('judge/Dashboard')
            ->has('assignments', 0)
        );

        // Assign judge to project
        JudgeAssignment::create([
            'project_id' => $this->project->id,
            'judge_id' => $this->judge->id,
            'session_id' => $this->session->id,
            'round_no' => 1,
        ]);

        $response = $this->actingAs($this->judge)->get(route('judge.evaluations.index'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('judge/Dashboard')
            ->has('assignments', 1)
            ->where('assignments.0.title', 'Innovative Project')
        );
    }

    public function test_judge_cannot_view_evaluation_form_for_unassigned_project()
    {
        $this->actingAs($this->judge)
            ->get(route('judge.evaluations.show', $this->project))
            ->assertStatus(403);
    }

    public function test_judge_can_view_evaluation_form_and_submit_scores()
    {
        // Assign first
        JudgeAssignment::create([
            'project_id' => $this->project->id,
            'judge_id' => $this->judge->id,
            'session_id' => $this->session->id,
            'round_no' => 1,
        ]);

        $response = $this->actingAs($this->judge)
            ->get(route('judge.evaluations.show', $this->project) . '?round=1');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('judge/EvaluationForm')
            ->where('project.title', 'Innovative Project')
            ->has('rubric.items', 2)
        );

        // Submit scores: novelty = 4 (out of 5), execution = 3 (out of 5)
        // Calculations:
        // Novelty: (4 / 5) * 0.60 * 100 = 48.0
        // Execution: (3 / 5) * 0.40 * 100 = 24.0
        // Total expected: 48.0 + 24.0 = 72.0%
        $postData = [
            'scores' => [
                $this->item1->id => 4,
                $this->item2->id => 3,
            ],
            'comments' => 'Good effort',
            'best_presenter' => 'Ali',
            'round_no' => 1,
        ];

        $submitResponse = $this->actingAs($this->judge)
            ->post(route('judge.evaluations.store', $this->project), $postData);

        $submitResponse->assertRedirect(route('judge.evaluations.index'));

        // Assert score stored in DB
        $this->assertDatabaseHas('scores', [
            'project_id' => $this->project->id,
            'judge_id' => $this->judge->id,
            'session_id' => $this->session->id,
            'round_no' => 1,
            'total' => 72.00,
            'comments' => 'Good effort',
            'best_presenter' => 'Ali',
        ]);
    }

    public function test_admin_can_assign_judge_in_round_1()
    {
        $response = $this->actingAs($this->admin)->post(route('admin.assignments.store'), [
            'project_id' => $this->project->id,
            'judge_id' => $this->judge->id,
            'round_no' => 1,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('judge_assignments', [
            'project_id' => $this->project->id,
            'judge_id' => $this->judge->id,
            'round_no' => 1,
        ]);
    }

    public function test_admin_cannot_assign_judge_in_round_2_if_they_scored_the_project_in_round_1()
    {
        // Add score for Round 1
        Score::create([
            'project_id' => $this->project->id,
            'judge_id' => $this->judge->id,
            'session_id' => $this->session->id,
            'round_no' => 1,
            'total' => 80.00,
            'score_details' => [$this->item1->id => 4, $this->item2->id => 4],
        ]);

        // Attempt Round 2 assignment
        $response = $this->actingAs($this->admin)->post(route('admin.assignments.store'), [
            'project_id' => $this->project->id,
            'judge_id' => $this->judge->id,
            'round_no' => 2,
        ]);

        $response->assertSessionHas('error', 'This judge is not eligible for Round 2 because they evaluated this project in Round 1.');
        $this->assertDatabaseMissing('judge_assignments', [
            'project_id' => $this->project->id,
            'judge_id' => $this->judge->id,
            'round_no' => 2,
        ]);

        // Try with judge2 who did NOT score the project in Round 1
        $response2 = $this->actingAs($this->admin)->post(route('admin.assignments.store'), [
            'project_id' => $this->project->id,
            'judge_id' => $this->judge2->id,
            'round_no' => 2,
        ]);

        $response2->assertRedirect();
        $this->assertDatabaseHas('judge_assignments', [
            'project_id' => $this->project->id,
            'judge_id' => $this->judge2->id,
            'round_no' => 2,
        ]);
    }

    public function test_judge_cannot_evaluate_if_round_2_is_locked()
    {
        // Lock Round 2
        $this->session->r2_locked = true;
        $this->session->save();

        // Assign judge to Round 2
        JudgeAssignment::create([
            'project_id' => $this->project->id,
            'judge_id' => $this->judge->id,
            'session_id' => $this->session->id,
            'round_no' => 2,
        ]);

        // Try submitting Round 2 score
        $postData = [
            'scores' => [
                $this->item1->id => 5,
                $this->item2->id => 5,
            ],
            'round_no' => 2,
        ];

        $response = $this->actingAs($this->judge)
            ->post(route('judge.evaluations.store', $this->project), $postData);

        $response->assertSessionHas('error', 'Round 2 evaluation has been locked and cannot be modified.');
        $this->assertDatabaseMissing('scores', [
            'project_id' => $this->project->id,
            'judge_id' => $this->judge->id,
            'round_no' => 2,
        ]);
    }

    public function test_admin_can_toggle_round_2_lock()
    {
        $this->assertFalse($this->session->r2_locked);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.round2.lockToggle'));

        $response->assertRedirect();
        $this->session->refresh();
        $this->assertTrue($this->session->r2_locked);
    }
}
