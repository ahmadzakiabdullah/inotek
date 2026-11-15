<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CompetitionSession;
use App\Models\JudgeAssignment;
use App\Models\Project;
use App\Models\Rubric;
use App\Models\RubricItem;
use App\Models\Score;
use App\Models\Setting;
use App\Models\User;
use App\Services\JudgeAutoAssigner;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoAssignAndFeedbackTest extends TestCase
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

    private function enableAutoAssign(int $perProject = 2): void
    {
        Setting::set(JudgeAutoAssigner::SETTING_ENABLED, '1');
        Setting::set(JudgeAutoAssigner::SETTING_JUDGES_PER_PROJECT, (string) $perProject);
    }

    public function test_auto_assign_is_refused_and_hidden_while_turned_off(): void
    {
        $this->project('ICT-01');
        User::factory()->count(2)->create(['role_id' => 3]);

        $this->actingAs($this->admin)->get(route('admin.assignments.index'))
            ->assertInertia(fn ($page) => $page->where('autoAssign.enabled', false));

        $this->actingAs($this->admin)
            ->post(route('admin.assignments.auto'), ['round_no' => 1, 'judges_per_project' => 2])
            ->assertSessionHasErrors('error');

        $this->assertDatabaseCount('judge_assignments', 0);
    }

    public function test_admin_can_turn_auto_assign_on_in_system_settings(): void
    {
        $this->actingAs($this->admin)->post(route('admin.settings.update'), [
            'system_name' => 'INOTEK',
            'global_font' => 'Inter',
            'primary_color' => '#4f46e5',
            'date_format' => 'd/m/Y',
            'timezone' => 'Asia/Kuala_Lumpur',
            'auto_assign_enabled' => true,
            'auto_assign_judges_per_project' => 4,
        ])->assertSessionHasNoErrors();

        $this->assertTrue(JudgeAutoAssigner::isEnabled());
        $this->assertSame(4, JudgeAutoAssigner::judgesPerProject());
    }

    public function test_auto_assign_spreads_work_evenly_and_skips_conflicts(): void
    {
        $this->enableAutoAssign();
        $judges = User::factory()->count(4)->create(['role_id' => 3]);
        $projects = collect(['ICT-01', 'ICT-02', 'ICT-03', 'ICT-04'])->map(fn ($code) => $this->project($code));

        // Judge 0 supervises ICT-01, and judge 1 already judges ICT-02.
        $projects[0]->update(['supervisor_email' => $judges[0]->email]);
        JudgeAssignment::create(['project_id' => $projects[1]->id, 'judge_id' => $judges[1]->id, 'session_id' => $this->session->id, 'round_no' => 1]);

        $this->actingAs($this->admin)
            ->post(route('admin.assignments.auto'), ['round_no' => 1, 'judges_per_project' => 2])
            ->assertSessionHasNoErrors();

        foreach ($projects as $project) {
            $this->assertSame(2, JudgeAssignment::where('project_id', $project->id)->where('round_no', 1)->count());
        }

        $this->assertDatabaseMissing('judge_assignments', ['project_id' => $projects[0]->id, 'judge_id' => $judges[0]->id]);

        // 8 assignments over 4 judges: everyone gets exactly 2.
        $loads = JudgeAssignment::where('round_no', 1)->get()->countBy('judge_id')->values()->all();
        $this->assertSame([2, 2, 2, 2], $loads);

        // Running it again adds nothing.
        $this->actingAs($this->admin)->post(route('admin.assignments.auto'), ['round_no' => 1, 'judges_per_project' => 2]);
        $this->assertDatabaseCount('judge_assignments', 8);
    }

    public function test_auto_assign_reports_projects_it_cannot_fill(): void
    {
        $this->enableAutoAssign();
        User::factory()->count(2)->create(['role_id' => 3]);
        $this->project('ICT-01');

        $this->actingAs($this->admin)
            ->post(route('admin.assignments.auto'), ['round_no' => 1, 'judges_per_project' => 3])
            ->assertSessionHasErrors('error');

        // It still assigns the judges that are available.
        $this->assertDatabaseCount('judge_assignments', 2);
    }

    public function test_round_two_auto_assign_uses_the_shortlist_and_new_judges(): void
    {
        $this->enableAutoAssign();
        $this->session->update(['round2_shortlist_limit' => 3]);
        $judges = User::factory()->count(4)->create(['role_id' => 3]);
        $projects = collect(['ICT-01', 'ICT-02', 'ICT-03', 'ICT-04'])->map(fn ($code) => $this->project($code));

        // Judge 0 scores every project in Round 1; ICT-04 has the lowest score.
        foreach ($projects as $index => $project) {
            JudgeAssignment::create(['project_id' => $project->id, 'judge_id' => $judges[0]->id, 'session_id' => $this->session->id, 'round_no' => 1]);
            Score::create([
                'project_id' => $project->id,
                'judge_id' => $judges[0]->id,
                'session_id' => $this->session->id,
                'round_no' => 1,
                'total' => 90 - $index * 10,
                'score_details' => [],
            ]);
        }

        $this->actingAs($this->admin)
            ->post(route('admin.assignments.auto'), ['round_no' => 2, 'judges_per_project' => 2])
            ->assertSessionHasNoErrors();

        $roundTwo = JudgeAssignment::where('round_no', 2)->get();
        $this->assertCount(6, $roundTwo);
        $this->assertFalse($roundTwo->contains('project_id', $projects[3]->id));
        $this->assertFalse($roundTwo->contains('judge_id', $judges[0]->id));
    }

    public function test_feedback_is_released_anonymously_after_results_are_finalized(): void
    {
        $participant = User::factory()->create(['role_id' => 4]);
        $project = $this->project('ICT-01', $participant);
        $rubric = Rubric::create(['name' => 'Rubric']);
        $novelty = RubricItem::create(['rubric_id' => $rubric->id, 'criteria_name' => 'Novelty', 'weight' => 0.5, 'max_points' => 5]);
        $impact = RubricItem::create(['rubric_id' => $rubric->id, 'criteria_name' => 'Impact', 'weight' => 0.5, 'max_points' => 5]);

        foreach ([['Dr. Secret', 'Great prototype', 4, 3], ['Prof. Hidden', null, 5, 5]] as [$name, $comment, $a, $b]) {
            $judge = User::factory()->create(['role_id' => 3, 'name' => $name]);
            Score::create([
                'project_id' => $project->id,
                'judge_id' => $judge->id,
                'session_id' => $this->session->id,
                'round_no' => 1,
                'total' => ($a + $b) * 10,
                'score_details' => [$impact->id => $b, $novelty->id => $a],
                'comments' => $comment,
                'best_presenter' => 'Someone',
            ]);
        }

        $this->actingAs($participant)->get(route('projects.index'))
            ->assertInertia(fn ($page) => $page->where('feedback', null));

        $this->session->update(['r2_locked' => true]);

        $response = $this->actingAs($participant)->get(route('projects.index'))
            ->assertInertia(fn ($page) => $page
                ->has('feedback', 1)
                ->where('feedback.0.round', 1)
                ->where('feedback.0.average', 85)
                ->where('feedback.0.evaluations.0.label', 'Judge 1')
                ->where('feedback.0.evaluations.0.comments', 'Great prototype')
                // Criteria follow the rubric order, not the order the scores were saved in.
                ->where('feedback.0.evaluations.0.criteria.0.name', 'Novelty')
                ->where('feedback.0.evaluations.0.criteria.0.score', 4)
                ->where('feedback.0.evaluations.0.criteria.0.max', 5)
                ->where('feedback.0.evaluations.1.label', 'Judge 2')
                ->missing('feedback.0.evaluations.0.best_presenter'));

        $this->assertStringNotContainsString('Dr. Secret', $response->getContent());
        $this->assertStringNotContainsString('Prof. Hidden', $response->getContent());
    }
}
