<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryRubricMapping;
use App\Models\CompetitionSession;
use App\Models\Project;
use App\Models\Rubric;
use App\Models\RubricItem;
use App\Models\Score;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicLeaderboardPrivacyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_public_leaderboard_hides_results_and_personal_data_until_round_two_is_finalized(): void
    {
        [$session, $category, $project, $judge] = $this->makeScoredProject(false);

        $response = $this->getJson(route('leaderboard.index'));

        $response->assertOk()
            ->assertJsonMissingPath("leaderboardData.{$category->id}.0.avg_r1")
            ->assertJsonMissingPath("leaderboardData.{$category->id}.0.final_score")
            ->assertJsonMissingPath("leaderboardData.{$category->id}.0.username")
            ->assertJsonMissingPath("leaderboardData.{$category->id}.0.judges")
            ->assertJsonMissingPath("leaderboardData.{$category->id}.0.best_presenter")
            ->assertJsonMissingPath("leaderboardData.{$category->id}.0.abstract");

        $this->assertSame($session->id, $response->json('activeSession.id'));
        $this->assertSame($project->title, $response->json("leaderboardData.{$category->id}.0.title"));
        $this->assertNotSame($judge->name, json_encode($response->json()));
    }

    public function test_public_leaderboard_publishes_final_score_without_judge_details_after_finalization(): void
    {
        [, $category, $project, $judge] = $this->makeScoredProject(true);

        $response = $this->getJson(route('leaderboard.index'));

        $response->assertOk()
            ->assertJsonPath("leaderboardData.{$category->id}.0.final_score", 75)
            ->assertJsonPath("leaderboardData.{$category->id}.0.title", $project->title)
            ->assertJsonMissingPath("leaderboardData.{$category->id}.0.username")
            ->assertJsonMissingPath("leaderboardData.{$category->id}.0.judges")
            ->assertJsonMissingPath("leaderboardData.{$category->id}.0.best_presenter");

        $this->assertNotSame($judge->name, json_encode($response->json()));
        $this->assertStringNotContainsString('Private judging comment', json_encode($response->json()));
    }

    public function test_internal_leaderboard_keeps_judging_details(): void
    {
        [, $category, $project, $judge] = $this->makeScoredProject(false);
        foreach ([1, 3] as $roleId) { // admin, judge
            $staff = User::factory()->create(['role_id' => $roleId]);

            $this->actingAs($staff)->getJson(route('dashboard.leaderboard'))
                ->assertOk()
                ->assertJsonPath("leaderboardData.{$category->id}.0.username", $project->user->name)
                ->assertJsonPath("leaderboardData.{$category->id}.0.judges.0.name", $judge->name)
                ->assertJsonPath("leaderboardData.{$category->id}.0.judges.0.comments", 'Private judging comment');
        }
    }

    public function test_lecturer_leaderboard_hides_results_until_round_two_is_finalized(): void
    {
        [$session, $category, $project] = $this->makeScoredProject(false);
        $project->update(['award_level' => 'Gold']);
        $lecturer = User::factory()->create(['role_id' => 2]);

        $this->actingAs($lecturer)->getJson(route('dashboard.leaderboard'))
            ->assertOk()
            ->assertJsonPath("leaderboardData.{$category->id}.0.award_level", null)
            ->assertJsonMissingPath("leaderboardData.{$category->id}.0.final_score")
            ->assertJsonMissingPath("leaderboardData.{$category->id}.0.judges");

        $session->update(['r2_locked' => true]);

        $this->actingAs($lecturer)->getJson(route('dashboard.leaderboard'))
            ->assertJsonPath("leaderboardData.{$category->id}.0.award_level", 'Gold')
            ->assertJsonPath("leaderboardData.{$category->id}.0.final_score", 75);
    }

    public function test_participant_and_supervisor_pages_hide_award_until_round_two_is_finalized(): void
    {
        [$session, , $project] = $this->makeScoredProject(false);
        $project->update(['award_level' => 'Gold']);
        $participant = $project->user;
        $supervisor = User::factory()->create(['role_id' => 2, 'email' => 'supervisor@example.com']);

        $this->actingAs($participant)->get(route('projects.index'))
            ->assertInertia(fn ($page) => $page->missing('project.award_level'));
        $this->actingAs($participant)->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->missing('project.award_level'));
        $this->actingAs($supervisor)->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->missing('my_projects.0.award_level'));

        $session->update(['r2_locked' => true]);

        $this->actingAs($participant)->get(route('projects.index'))
            ->assertInertia(fn ($page) => $page->where('project.award_level', 'Gold'));
    }

    public function test_award_and_rankings_stay_hidden_until_results_are_finalized(): void
    {
        [$session, $category, $project] = $this->makeScoredProject(false);
        $project->update(['award_level' => 'Gold']);

        $this->getJson(route('leaderboard.index'))
            ->assertOk()
            ->assertJsonPath("leaderboardData.{$category->id}.0.award_level", null);

        $this->get(route('home'))
            ->assertInertia(fn ($page) => $page->has('showcase.rankings', 0));

        $session->update(['r2_locked' => true]);

        $this->getJson(route('leaderboard.index'))
            ->assertJsonPath("leaderboardData.{$category->id}.0.award_level", 'Gold');

        $this->get(route('home'))
            ->assertInertia(fn ($page) => $page->where('showcase.rankings.0.name', $project->title));
    }

    public function test_round_two_finalists_rank_above_round_one_only_projects(): void
    {
        [$session, $category, , $judge] = $this->makeScoredProject(true);
        $finalist = Project::create([
            'session_id' => $session->id,
            'category_id' => $category->id,
            'user_id' => User::factory()->create(['role_id' => 4])->id,
            'pcode' => 'C1-02',
            'title' => 'Finalist',
            'abstract' => 'Abstract',
            'institution_type' => 'utem',
            'status' => Project::STATUS_APPROVED,
            'supervisor_name' => 'Supervisor',
            'supervisor_email' => 'supervisor@example.com',
        ]);
        foreach ([1 => 60, 2 => 70] as $round => $total) {
            Score::create([
                'project_id' => $finalist->id,
                'judge_id' => $judge->id,
                'session_id' => $session->id,
                'round_no' => $round,
                'total' => $total,
                'score_details' => [],
            ]);
        }

        // Solar Innovation has 75 in Round 1 only; the finalist (70 in Round 2) ranks first.
        $this->getJson(route('leaderboard.index'))
            ->assertJsonPath("leaderboardData.{$category->id}.0.title", 'Finalist')
            ->assertJsonPath("leaderboardData.{$category->id}.1.title", 'Solar Innovation');
    }

    /** @return array{CompetitionSession, Category, Project, User} */
    private function makeScoredProject(bool $finalized): array
    {
        $session = CompetitionSession::create([
            'name' => 'Session 2026',
            'is_active' => true,
            'r2_locked' => $finalized,
        ]);
        $category = Category::create(['session_id' => $session->id, 'code' => 'C1', 'name' => 'Technology']);
        $participant = User::factory()->create(['role_id' => 4, 'name' => 'Private participant']);
        $judge = User::factory()->create(['role_id' => 3, 'name' => 'Private judge']);
        $project = Project::create([
            'session_id' => $session->id,
            'category_id' => $category->id,
            'user_id' => $participant->id,
            'pcode' => 'C1-01',
            'title' => 'Solar Innovation',
            'abstract' => 'Private abstract',
            'institution_type' => 'utem',
            'status' => Project::STATUS_APPROVED,
            'supervisor_name' => 'Supervisor',
            'supervisor_email' => 'supervisor@example.com',
        ]);
        $rubric = Rubric::create(['name' => 'Technology rubric']);
        RubricItem::create([
            'rubric_id' => $rubric->id,
            'criteria_name' => 'Quality',
            'weight' => 1,
            'max_points' => 5,
        ]);
        CategoryRubricMapping::create(['session_id' => $session->id, 'category_id' => $category->id, 'rubric_id' => $rubric->id]);
        Score::create([
            'project_id' => $project->id,
            'judge_id' => $judge->id,
            'session_id' => $session->id,
            'round_no' => 1,
            'total' => 75,
            'score_details' => [],
            'comments' => 'Private judging comment',
            'best_presenter' => 'Private nominee',
        ]);

        return [$session, $category, $project, $judge];
    }
}
