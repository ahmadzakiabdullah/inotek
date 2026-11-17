<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\CompetitionSession;
use App\Models\JudgeAssignment;
use App\Models\Project;
use App\Models\Round2Wildcard;
use App\Models\Score;
use App\Models\Setting;
use App\Models\User;
use App\Services\Round2Wildcards;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Round2WildcardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $judge;

    private CompetitionSession $session;

    private Category $category;

    /** @var array<string, Project> */
    private array $projects = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create(['role_id' => 1]);
        $this->judge = User::factory()->create(['role_id' => 3]);
        $this->session = CompetitionSession::create(['name' => 'Sesi 2/2026', 'is_active' => true, 'round2_shortlist_limit' => 3]);
        $this->category = Category::create(['code' => 'GT', 'name' => 'Green Technology']);

        // GT-01..03 make the Top 3; GT-04 (nominated) and GT-05 (not nominated) miss out.
        foreach (['GT-01' => 90, 'GT-02' => 85, 'GT-03' => 80, 'GT-04' => 60, 'GT-05' => 55] as $code => $total) {
            $project = $this->project($code, $this->category);
            Score::create([
                'project_id' => $project->id,
                'judge_id' => $this->judge->id,
                'session_id' => $this->session->id,
                'round_no' => 1,
                'total' => $total,
                'score_details' => [],
                'best_presenter' => $code === 'GT-04' ? 'Ali' : null,
            ]);
            $this->projects[$code] = $project;
        }
    }

    public function test_admin_adds_a_best_presenter_nominee_who_then_competes_like_a_finalist(): void
    {
        $nominee = $this->projects['GT-04'];

        $this->actingAs($this->admin)->get(route('admin.round2.index'))
            ->assertInertia(fn ($page) => $page
                ->where('wildcardLimit', 1)
                ->has('wildcardCandidates', 1)
                ->where('wildcardCandidates.0.pcode', 'GT-04')
                ->where('wildcardCandidates.0.nominations', 1));

        $this->actingAs($this->admin)
            ->post(route('admin.round2.wildcards.add', $nominee), ['reason' => 'Requested by the organizer'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('round2_wildcards', ['project_id' => $nominee->id, 'added_by' => $this->admin->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ADD_ROUND2_WILDCARD']);

        $this->actingAs($this->admin)->get(route('admin.round2.index'))
            ->assertInertia(fn ($page) => $page
                ->has('wildcards', 1)
                ->where('wildcards.0.pcode', 'GT-04')
                ->where('wildcards.0.reason', 'Requested by the organizer')
                ->has('wildcardCandidates', 0));

        // A Round 2 judge can be assigned to it.
        $roundTwoJudge = User::factory()->create(['role_id' => 3]);
        $this->actingAs($this->admin)
            ->post(route('admin.assignments.store'), ['project_id' => $nominee->id, 'judge_id' => $roundTwoJudge->id, 'round_no' => 2])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('judge_assignments', ['project_id' => $nominee->id, 'round_no' => 2]);

        // And it can receive an award once it has a Round 2 score.
        Score::create([
            'project_id' => $nominee->id,
            'judge_id' => $roundTwoJudge->id,
            'session_id' => $this->session->id,
            'round_no' => 2,
            'total' => 88,
            'score_details' => [],
        ]);
        $this->actingAs($this->admin)
            ->post(route('admin.round2.assignAward', $nominee), ['award_level' => 'Gold'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Gold', $nominee->fresh()->award_level);
    }

    public function test_only_nominees_outside_round_two_within_the_limit_can_be_added(): void
    {
        $add = fn (Project $project, string $reason = 'Organizer request') => $this->actingAs($this->admin)
            ->post(route('admin.round2.wildcards.add', $project), ['reason' => $reason]);

        // Not nominated, already shortlisted, reason missing.
        $add($this->projects['GT-05'])->assertSessionHasErrors('error');
        $add($this->projects['GT-01'])->assertSessionHasErrors('error');
        $add($this->projects['GT-04'], '')->assertSessionHasErrors('reason');

        // The limit is per category and comes from System Settings.
        $second = $this->project('GT-06', $this->category);
        Score::create(['project_id' => $second->id, 'judge_id' => $this->judge->id, 'session_id' => $this->session->id, 'round_no' => 1, 'total' => 50, 'score_details' => [], 'best_presenter' => 'Abu']);

        $add($this->projects['GT-04'])->assertSessionHasNoErrors();
        $add($second)->assertSessionHasErrors('error');

        Setting::set(Round2Wildcards::SETTING_LIMIT, '2', 'integer');
        $add($second)->assertSessionHasNoErrors();
        $this->assertSame(2, Round2Wildcard::count());

        // Lecturers cannot add wildcards.
        $lecturer = User::factory()->create(['role_id' => 2]);
        $this->actingAs($lecturer)->post(route('admin.round2.wildcards.add', $this->projects['GT-05']), ['reason' => 'Organizer request'])
            ->assertForbidden();
    }

    public function test_no_wildcard_once_round_two_scoring_has_started_in_the_category(): void
    {
        Score::create([
            'project_id' => $this->projects['GT-01']->id,
            'judge_id' => User::factory()->create(['role_id' => 3])->id,
            'session_id' => $this->session->id,
            'round_no' => 2,
            'total' => 70,
            'score_details' => [],
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.round2.wildcards.add', $this->projects['GT-04']), ['reason' => 'Organizer request'])
            ->assertSessionHasErrors('error');

        $this->assertSame(0, Round2Wildcard::count());

        Setting::set(Round2Wildcards::SETTING_LIMIT, '0', 'integer');
        $this->actingAs($this->admin)->get(route('admin.round2.index'))
            ->assertInertia(fn ($page) => $page->where('wildcardLimit', 0));
    }

    public function test_a_wildcard_with_round_two_judges_cannot_be_removed(): void
    {
        $nominee = $this->projects['GT-04'];
        $this->actingAs($this->admin)->post(route('admin.round2.wildcards.add', $nominee), ['reason' => 'Organizer request']);
        $assignment = JudgeAssignment::create(['project_id' => $nominee->id, 'judge_id' => User::factory()->create(['role_id' => 3])->id, 'session_id' => $this->session->id, 'round_no' => 2]);

        $this->actingAs($this->admin)->post(route('admin.round2.wildcards.remove', $nominee))->assertSessionHasErrors('error');
        $this->assertSame(1, Round2Wildcard::count());

        $assignment->delete();
        $this->actingAs($this->admin)->post(route('admin.round2.wildcards.remove', $nominee))->assertSessionHasNoErrors();
        $this->assertSame(0, Round2Wildcard::count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'REMOVE_ROUND2_WILDCARD']);
    }

    public function test_only_admins_see_the_wildcard_label_on_the_leaderboard(): void
    {
        $nominee = $this->projects['GT-04'];
        $this->actingAs($this->admin)->post(route('admin.round2.wildcards.add', $nominee), ['reason' => 'Organizer request']);
        $this->session->update(['r2_locked' => true]);

        $find = fn ($response) => collect($response->json("leaderboardData.{$this->category->id}"))->firstWhere('pcode', 'GT-04');

        $this->assertTrue($find($this->actingAs($this->admin)->getJson(route('dashboard.leaderboard')))['round2_wildcard']);

        $lecturer = User::factory()->create(['role_id' => 2]);
        $this->assertArrayNotHasKey('round2_wildcard', $find($this->actingAs($lecturer)->getJson(route('dashboard.leaderboard'))));
        $this->assertArrayNotHasKey('round2_wildcard', $find($this->actingAs($this->judge)->getJson(route('dashboard.leaderboard'))));

        auth()->logout();
        $this->assertArrayNotHasKey('round2_wildcard', $find($this->getJson(route('leaderboard.index'))));
    }

    public function test_round_two_page_warns_about_a_tie_at_the_shortlist_cut_off(): void
    {
        $this->actingAs($this->admin)->get(route('admin.round2.index'))
            ->assertInertia(fn ($page) => $page->where('shortlistTies', []));

        // GT-07 ties GT-03 (80) for third place; the code order leaves it out.
        $tied = $this->project('GT-07', $this->category);
        Score::create(['project_id' => $tied->id, 'judge_id' => $this->judge->id, 'session_id' => $this->session->id, 'round_no' => 1, 'total' => 80, 'score_details' => []]);

        $this->actingAs($this->admin)->get(route('admin.round2.index'))
            ->assertInertia(fn ($page) => $page
                ->where("shortlistTies.{$this->category->id}.in.0.pcode", 'GT-03')
                ->where("shortlistTies.{$this->category->id}.out.0.pcode", 'GT-07')
                ->where("shortlistTies.{$this->category->id}.out.0.avg_score", 80));
    }

    public function test_deleting_a_project_removes_its_judge_assignments_but_keeps_scores(): void
    {
        $project = $this->projects['GT-01'];
        JudgeAssignment::create(['project_id' => $project->id, 'judge_id' => $this->judge->id, 'session_id' => $this->session->id, 'round_no' => 1]);

        $this->actingAs($this->admin)->post(route('admin.approvals.delete', $project))->assertRedirect();

        $this->assertSoftDeleted('projects', ['id' => $project->id]);
        $this->assertDatabaseMissing('judge_assignments', ['project_id' => $project->id]);
        $this->assertDatabaseHas('scores', ['project_id' => $project->id]);
    }

    public function test_the_limit_is_set_in_system_settings(): void
    {
        $this->actingAs($this->admin)->post(route('admin.settings.update'), [
            'system_name' => 'INOTEK',
            'global_font' => 'Inter',
            'primary_color' => '#4f46e5',
            'date_format' => 'd/m/Y',
            'timezone' => 'Asia/Kuala_Lumpur',
            'round2_wildcard_limit' => 3,
        ])->assertSessionHasNoErrors();

        $this->assertSame(3, Round2Wildcards::limit());

        $this->actingAs($this->admin)->post(route('admin.settings.update'), [
            'system_name' => 'INOTEK',
            'global_font' => 'Inter',
            'primary_color' => '#4f46e5',
            'date_format' => 'd/m/Y',
            'timezone' => 'Asia/Kuala_Lumpur',
            'round2_wildcard_limit' => 11,
        ])->assertSessionHasErrors('round2_wildcard_limit');
    }

    private function project(string $code, Category $category): Project
    {
        return Project::create([
            'session_id' => $this->session->id,
            'category_id' => $category->id,
            'user_id' => User::factory()->create(['role_id' => 4])->id,
            'pcode' => $code,
            'title' => "Project {$code}",
            'abstract' => 'Abstract',
            'institution_type' => 'utem',
            'status' => Project::STATUS_APPROVED,
            'supervisor_name' => 'Supervisor',
            'supervisor_email' => 'supervisor@example.com',
        ]);
    }
}
