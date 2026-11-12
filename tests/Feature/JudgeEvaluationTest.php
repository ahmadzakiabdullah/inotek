<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryRubricMapping;
use App\Models\CompetitionSession;
use App\Models\JudgeAssignment;
use App\Models\Project;
use App\Models\Role;
use App\Models\Rubric;
use App\Models\RubricItem;
use App\Models\Score;
use App\Models\User;
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
            'session_id' => $this->session->id,
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
            ->get(route('judge.evaluations.show', $this->project).'?round=1');

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

    public function test_judge_cannot_submit_scores_above_rubric_item_maximum(): void
    {
        JudgeAssignment::create([
            'project_id' => $this->project->id,
            'judge_id' => $this->judge->id,
            'session_id' => $this->session->id,
            'round_no' => 1,
        ]);

        $response = $this->actingAs($this->judge)->post(route('judge.evaluations.store', $this->project), [
            'scores' => [$this->item1->id => 6, $this->item2->id => 3],
            'round_no' => 1,
        ]);

        $response->assertSessionHasErrors('scores');
        $this->assertDatabaseMissing('scores', ['project_id' => $this->project->id, 'judge_id' => $this->judge->id]);
    }

    public function test_judge_cannot_submit_scores_for_criteria_outside_the_category_rubric(): void
    {
        $otherRubric = Rubric::create(['name' => 'Other Rubric']);
        $foreignItem = RubricItem::create([
            'rubric_id' => $otherRubric->id,
            'criteria_name' => 'Foreign criterion',
            'weight' => 1.00,
            'max_points' => 5,
        ]);
        JudgeAssignment::create([
            'project_id' => $this->project->id,
            'judge_id' => $this->judge->id,
            'session_id' => $this->session->id,
            'round_no' => 1,
        ]);

        $response = $this->actingAs($this->judge)->post(route('judge.evaluations.store', $this->project), [
            'scores' => [$this->item1->id => 4, $this->item2->id => 3, $foreignItem->id => 5],
            'round_no' => 1,
        ]);

        $response->assertSessionHasErrors('scores');
        $this->assertDatabaseMissing('scores', ['project_id' => $this->project->id, 'judge_id' => $this->judge->id]);
    }

    public function test_judge_must_submit_exactly_round_one_or_two(): void
    {
        JudgeAssignment::create([
            'project_id' => $this->project->id,
            'judge_id' => $this->judge->id,
            'session_id' => $this->session->id,
            'round_no' => 1,
        ]);

        $response = $this->actingAs($this->judge)->post(route('judge.evaluations.store', $this->project), [
            'scores' => [$this->item1->id => 4, $this->item2->id => 3],
            'round_no' => 3,
        ]);

        $response->assertSessionHasErrors('round_no');
        $this->assertDatabaseMissing('scores', ['project_id' => $this->project->id, 'judge_id' => $this->judge->id]);
    }

    public function test_judge_must_submit_a_value_for_every_rubric_item(): void
    {
        JudgeAssignment::create([
            'project_id' => $this->project->id,
            'judge_id' => $this->judge->id,
            'session_id' => $this->session->id,
            'round_no' => 1,
        ]);

        $response = $this->actingAs($this->judge)->post(route('judge.evaluations.store', $this->project), [
            'scores' => [$this->item1->id => 4],
            'round_no' => 1,
        ]);

        $response->assertSessionHasErrors('scores');
        $this->assertDatabaseMissing('scores', ['project_id' => $this->project->id, 'judge_id' => $this->judge->id]);
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

    public function test_admin_can_assign_judge_when_judge_role_id_is_not_the_seeded_id(): void
    {
        Role::where('name', 'judge')->delete();
        $customJudgeRole = Role::create([
            'name' => 'judge',
            'label' => 'Judge',
            'description' => 'Judge with a nonstandard role ID.',
        ]);
        $judge = User::factory()->create(['role_id' => $customJudgeRole->id]);

        $response = $this->actingAs($this->admin)->post(route('admin.assignments.store'), [
            'project_id' => $this->project->id,
            'judge_id' => $judge->id,
            'round_no' => 1,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('judge_assignments', [
            'project_id' => $this->project->id,
            'judge_id' => $judge->id,
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

    public function test_admin_cannot_assign_round_2_to_project_outside_round_1_shortlist(): void
    {
        $outsideProject = Project::create([
            'session_id' => $this->session->id,
            'category_id' => $this->category->id,
            'user_id' => User::factory()->create(['role_id' => 4])->id,
            'title' => 'Outside Shortlist',
            'abstract' => 'Abstract',
            'institution_type' => 'utem',
            'status' => Project::STATUS_APPROVED,
            'supervisor_name' => 'Supervisor',
            'supervisor_email' => 'supervisor@example.com',
        ]);
        Score::create([
            'project_id' => $outsideProject->id,
            'judge_id' => $this->judge->id,
            'session_id' => $this->session->id,
            'round_no' => 1,
            'total' => 50,
            'score_details' => [],
        ]);
        for ($index = 1; $index <= 3; $index++) {
            $project = Project::create([
                'session_id' => $this->session->id,
                'category_id' => $this->category->id,
                'user_id' => User::factory()->create(['role_id' => 4])->id,
                'title' => "Shortlisted {$index}",
                'abstract' => 'Abstract',
                'institution_type' => 'utem',
                'status' => Project::STATUS_APPROVED,
                'supervisor_name' => 'Supervisor',
                'supervisor_email' => 'supervisor@example.com',
            ]);
            Score::create([
                'project_id' => $project->id,
                'judge_id' => $this->judge->id,
                'session_id' => $this->session->id,
                'round_no' => 1,
                'total' => 90 - $index,
                'score_details' => [],
            ]);
        }

        $response = $this->actingAs($this->admin)->post(route('admin.assignments.store'), [
            'project_id' => $outsideProject->id,
            'judge_id' => $this->judge2->id,
            'round_no' => 2,
        ]);

        $response->assertSessionHas('error', 'Round 2 assignments are limited to projects shortlisted from Round 1.');
        $this->assertDatabaseMissing('judge_assignments', [
            'project_id' => $outsideProject->id,
            'judge_id' => $this->judge2->id,
            'round_no' => 2,
        ]);
    }

    public function test_admin_can_change_round_2_shortlist_limit_for_active_session(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.round2.shortlistLimit'), ['limit' => 5]);

        $response->assertRedirect();
        $this->assertDatabaseHas('competition_sessions', [
            'id' => $this->session->id,
            'round2_shortlist_limit' => 5,
        ]);
    }

    public function test_admin_cannot_set_round_2_shortlist_limit_to_unsupported_value(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.round2.shortlistLimit'), ['limit' => 7]);

        $response->assertSessionHasErrors('limit');
        $this->assertSame(3, $this->session->fresh()->round2_shortlist_limit);
    }

    public function test_admin_cannot_reduce_shortlist_below_projects_with_existing_round_2_assignments(): void
    {
        $this->session->round2_shortlist_limit = 5;
        $this->session->save();

        $roundOneScores = [];
        for ($index = 0; $index < 4; $index++) {
            $project = Project::create([
                'session_id' => $this->session->id,
                'category_id' => $this->category->id,
                'user_id' => User::factory()->create(['role_id' => 4])->id,
                'title' => "Higher score {$index}",
                'abstract' => 'Abstract',
                'institution_type' => 'utem',
                'status' => Project::STATUS_APPROVED,
                'supervisor_name' => 'Supervisor',
                'supervisor_email' => 'supervisor@example.com',
            ]);
            $roundOneScores[] = $project;
            Score::create([
                'project_id' => $project->id,
                'judge_id' => $this->judge->id,
                'session_id' => $this->session->id,
                'round_no' => 1,
                'total' => 100 - $index,
                'score_details' => [],
            ]);
        }
        Score::create([
            'project_id' => $this->project->id,
            'judge_id' => $this->judge->id,
            'session_id' => $this->session->id,
            'round_no' => 1,
            'total' => 50,
            'score_details' => [],
        ]);
        JudgeAssignment::create([
            'project_id' => $this->project->id,
            'judge_id' => $this->judge2->id,
            'session_id' => $this->session->id,
            'round_no' => 2,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.round2.shortlistLimit'), ['limit' => 3]);

        $response->assertSessionHas('error', 'Remove Round 2 assignments outside the new shortlist before changing its size.');
        $this->assertSame(5, $this->session->fresh()->round2_shortlist_limit);
    }

    public function test_admin_cannot_assign_award_to_project_outside_shortlist(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.round2.assignAward', $this->project), [
            'award_level' => 'Gold',
        ]);

        $response->assertSessionHas('error', 'Awards can only be assigned to projects shortlisted for Round 2.');
        $this->assertDatabaseMissing('projects', ['id' => $this->project->id, 'award_level' => 'Gold']);
    }

    public function test_admin_cannot_assign_award_until_shortlisted_project_has_round_2_score(): void
    {
        Score::create([
            'project_id' => $this->project->id,
            'judge_id' => $this->judge->id,
            'session_id' => $this->session->id,
            'round_no' => 1,
            'total' => 90,
            'score_details' => [$this->item1->id => 5, $this->item2->id => 4],
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.round2.assignAward', $this->project), [
            'award_level' => 'Gold',
        ]);

        $response->assertSessionHas('error', 'An award can only be assigned after the project has a Round 2 evaluation.');
        $this->assertDatabaseMissing('projects', ['id' => $this->project->id, 'award_level' => 'Gold']);
    }

    public function test_admin_can_assign_award_to_shortlisted_project_with_round_2_score(): void
    {
        Score::create([
            'project_id' => $this->project->id,
            'judge_id' => $this->judge->id,
            'session_id' => $this->session->id,
            'round_no' => 1,
            'total' => 90,
            'score_details' => [$this->item1->id => 5, $this->item2->id => 4],
        ]);
        Score::create([
            'project_id' => $this->project->id,
            'judge_id' => $this->judge2->id,
            'session_id' => $this->session->id,
            'round_no' => 2,
            'total' => 95,
            'score_details' => [$this->item1->id => 5, $this->item2->id => 5],
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.round2.assignAward', $this->project), [
            'award_level' => 'Gold',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('projects', ['id' => $this->project->id, 'award_level' => 'Gold']);
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

    public function test_round_1_scores_are_frozen_once_round_2_judging_has_started(): void
    {
        JudgeAssignment::create([
            'project_id' => $this->project->id,
            'judge_id' => $this->judge->id,
            'session_id' => $this->session->id,
            'round_no' => 1,
        ]);
        JudgeAssignment::create([
            'project_id' => $this->project->id,
            'judge_id' => $this->judge2->id,
            'session_id' => $this->session->id,
            'round_no' => 2,
        ]);

        $response = $this->actingAs($this->judge)->post(route('judge.evaluations.store', $this->project), [
            'scores' => [$this->item1->id => 5, $this->item2->id => 5],
            'round_no' => 1,
        ]);

        $response->assertSessionHas('error', 'Round 1 evaluation is closed because Round 2 judging has started.');
        $this->assertDatabaseMissing('scores', ['project_id' => $this->project->id, 'round_no' => 1]);
    }

    public function test_judge_with_conflict_of_interest_cannot_be_assigned(): void
    {
        $supervisorJudge = User::factory()->create(['role_id' => 3, 'email' => 'A@UTEM.com']);

        $response = $this->actingAs($this->admin)->post(route('admin.assignments.store'), [
            'project_id' => $this->project->id,
            'judge_id' => $supervisorJudge->id,
            'round_no' => 1,
        ]);

        $response->assertSessionHas('error', 'This judge has a conflict of interest with the project (owner or supervisor).');
        $this->assertDatabaseMissing('judge_assignments', ['judge_id' => $supervisorJudge->id]);
    }

    public function test_judge_comment_length_is_limited(): void
    {
        JudgeAssignment::create([
            'project_id' => $this->project->id,
            'judge_id' => $this->judge->id,
            'session_id' => $this->session->id,
            'round_no' => 1,
        ]);

        $this->actingAs($this->judge)->post(route('judge.evaluations.store', $this->project), [
            'scores' => [$this->item1->id => 5, $this->item2->id => 5],
            'round_no' => 1,
            'comments' => str_repeat('a', 5001),
        ])->assertSessionHasErrors('comments');

        $this->actingAs($this->judge)->post(route('judge.evaluations.store', $this->project), [
            'scores' => 'not-an-array',
            'round_no' => 1,
        ])->assertSessionHasErrors('scores');
    }
}
