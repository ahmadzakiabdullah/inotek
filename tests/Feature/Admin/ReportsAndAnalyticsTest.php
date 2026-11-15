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

class ReportsAndAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected CompetitionSession $session;

    protected Category $category;

    /** @var list<User> */
    protected array $judges = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create(['role_id' => 1]);
        $this->session = CompetitionSession::create(['name' => 'Session 2026', 'is_active' => true, 'r2_locked' => false]);
        $this->category = Category::create(['session_id' => $this->session->id, 'code' => 'ICT', 'name' => 'ICT']);
        foreach (['Lenient Judge', 'Strict Judge', 'Middle Judge'] as $name) {
            $this->judges[] = User::factory()->create(['role_id' => 3, 'name' => $name]);
        }
    }

    private function project(string $code, string $title, int $status = Project::STATUS_APPROVED, string $institution = 'utem'): Project
    {
        return Project::create([
            'session_id' => $this->session->id,
            'category_id' => $this->category->id,
            'user_id' => User::factory()->create(['role_id' => 4])->id,
            'pcode' => $code,
            'title' => $title,
            'abstract' => 'Abstract',
            'institution_type' => $institution,
            'status' => $status,
            'supervisor_name' => 'Supervisor',
            'supervisor_email' => 'supervisor@example.com',
        ]);
    }

    private function score(Project $project, User $judge, float $total, int $round = 1): void
    {
        JudgeAssignment::create(['project_id' => $project->id, 'judge_id' => $judge->id, 'session_id' => $this->session->id, 'round_no' => $round]);
        Score::create([
            'project_id' => $project->id,
            'judge_id' => $judge->id,
            'session_id' => $this->session->id,
            'round_no' => $round,
            'total' => $total,
            'score_details' => [],
            'comments' => 'Comment',
        ]);
    }

    public function test_only_admins_can_open_reports_and_analytics(): void
    {
        $lecturer = User::factory()->create(['role_id' => 2]);

        foreach (['admin.reports.index', 'admin.analytics.index'] as $route) {
            $this->actingAs($lecturer)->get(route($route))->assertForbidden();
            $this->actingAs($this->admin)->get(route($route))->assertOk();
        }

        $this->actingAs($lecturer)
            ->get(route('admin.reports.download', ['type' => 'participants', 'format' => 'csv', 'session_id' => $this->session->id]))
            ->assertForbidden();
    }

    public function test_every_report_downloads_as_csv_and_pdf(): void
    {
        $project = $this->project('ICT-01', 'Solar Robot');
        $this->score($project, $this->judges[0], 80);

        foreach (['results', 'participants', 'judge-scores', 'judging-status'] as $type) {
            $csv = $this->actingAs($this->admin)
                ->get(route('admin.reports.download', ['type' => $type, 'format' => 'csv', 'session_id' => $this->session->id]));
            $csv->assertOk();
            $this->assertStringContainsString('text/csv', $csv->headers->get('Content-Type'));
            $this->assertStringContainsString('Solar Robot', $csv->streamedContent());

            $pdf = $this->actingAs($this->admin)
                ->get(route('admin.reports.download', ['type' => $type, 'format' => 'pdf', 'session_id' => $this->session->id]));
            $pdf->assertOk();
            $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        }
    }

    public function test_csv_starts_with_a_bom_marks_drafts_and_neutralizes_formulas(): void
    {
        $this->project('ICT-01', '=HYPERLINK("http://evil.example","click")');

        $content = $this->actingAs($this->admin)
            ->get(route('admin.reports.download', ['type' => 'participants', 'format' => 'csv', 'session_id' => $this->session->id]))
            ->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString("'=HYPERLINK", $content);
        $this->assertStringNotContainsString(',"=HYPERLINK', $content);

        $results = $this->actingAs($this->admin)
            ->get(route('admin.reports.download', ['type' => 'results', 'format' => 'csv', 'session_id' => $this->session->id]))
            ->streamedContent();

        $this->assertStringContainsString('DRAFT', $results);
    }

    public function test_results_report_ranks_round_two_finalists_first(): void
    {
        $roundOneOnly = $this->project('ICT-01', 'High Round One');
        $finalist = $this->project('ICT-02', 'Finalist');
        $this->score($roundOneOnly, $this->judges[0], 95);
        $this->score($finalist, $this->judges[0], 70);
        $this->score($finalist, $this->judges[1], 60, 2);
        $this->session->update(['r2_locked' => true]);

        $lines = array_values(array_filter(explode("\n", $this->actingAs($this->admin)
            ->get(route('admin.reports.download', ['type' => 'results', 'format' => 'csv', 'session_id' => $this->session->id]))
            ->streamedContent())));

        $this->assertStringNotContainsString('DRAFT', implode("\n", $lines));
        $this->assertStringContainsString('Finalist', $lines[1]);
        $this->assertStringContainsString('High Round One', $lines[2]);
    }

    public function test_unknown_report_type_is_not_found(): void
    {
        $this->actingAs($this->admin)
            ->get('/dashboard/reports/everything.csv?session_id='.$this->session->id)
            ->assertNotFound();
    }

    public function test_analytics_summarises_the_session_and_flags_lenient_and_strict_judges(): void
    {
        $this->project('ICT-09', 'Pending One', Project::STATUS_SUBMITTED, 'ipt');
        [$lenient, $strict, $middle] = $this->judges;

        // Lenient scores 10 points above the others, strict 10 below, on 3 shared projects.
        foreach (['ICT-01', 'ICT-02', 'ICT-03'] as $code) {
            $project = $this->project($code, "Project {$code}");
            $this->score($project, $lenient, 80);
            $this->score($project, $strict, 60);
            $this->score($project, $middle, 70);
        }

        $this->actingAs($this->admin)->get(route('admin.analytics.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/analytics/Index')
                ->where('session.summary.registered', 4)
                ->where('session.summary.approved', 3)
                ->where('session.summary.pending', 1)
                ->where('session.summary.evaluations', 9)
                ->where('session.byInstitution.1.registered', 1)
                ->where('session.scoreDistribution.6.round1', 3) // 60–69
                ->where('session.judgeConsistency', function ($rows) {
                    $byJudge = collect($rows)->keyBy('judge');

                    return $byJudge['Lenient Judge']['average_deviation'] == 15.0
                        && $byJudge['Strict Judge']['average_deviation'] == -15.0
                        && $byJudge['Middle Judge']['average_deviation'] == 0.0;
                })
                ->has('trend', 1));
    }
}
