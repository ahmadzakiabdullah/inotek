<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CompetitionSession;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportAwardsTest extends TestCase
{
    use RefreshDatabase;

    private CompetitionSession $session;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->session = CompetitionSession::create(['name' => 'Sesi 1/2026', 'is_active' => false, 'r2_locked' => false]);
        $this->category = Category::create(['session_id' => $this->session->id, 'code' => 'C1', 'name' => 'Technology']);
    }

    public function test_preview_writes_nothing_and_commit_sets_awards_from_an_excel_csv(): void
    {
        $gold = $this->project('GT-01');
        $silver = $this->project('se-02');
        $untouched = $this->project('GT-03', 'Bronze');
        // Excel "CSV UTF-8" with a byte order mark, a header row, Malay award names and mixed case codes.
        $file = $this->csv("\xEF\xBB\xBFKod Projek,Anugerah\r\nGT-01,Emas\r\nSE-02,silver\r\n\r\n");

        $this->artisan('inotek:import-awards', ['file' => $file, '--session' => $this->session->id])
            ->expectsOutputToContain('Preview only')
            ->assertSuccessful();
        $this->assertNull($gold->fresh()->award_level);

        $this->artisan('inotek:import-awards', ['file' => $file, '--session' => $this->session->id, '--commit' => true])
            ->assertSuccessful();

        $this->assertSame('Gold', $gold->fresh()->award_level);
        $this->assertSame('Silver', $silver->fresh()->award_level);
        // Projects missing from the file keep their award.
        $this->assertSame('Bronze', $untouched->fresh()->award_level);
        $this->assertDatabaseHas('audit_logs', ['action' => 'IMPORT_AWARDS']);
    }

    public function test_nothing_is_written_while_any_row_has_a_problem(): void
    {
        $gold = $this->project('GT-01');
        $this->project('GT-02', null, Project::STATUS_CANCELLED);
        $file = $this->csv("GT-01;Gold\nGT-02;Silver\nXX-99;Bronze\nGT-01;Platinum\n");

        $this->artisan('inotek:import-awards', ['file' => $file, '--session' => $this->session->id, '--commit' => true])
            ->expectsOutputToContain('No project with this code')
            ->expectsOutputToContain('Project is not approved')
            ->expectsOutputToContain("Unknown award 'Platinum'")
            ->assertFailed();

        $this->assertNull($gold->fresh()->award_level);
    }

    public function test_none_clears_an_award_and_an_unknown_session_fails(): void
    {
        $project = $this->project('GT-01', 'Gold');

        $this->artisan('inotek:import-awards', ['file' => $this->csv("GT-01,None\n"), '--session' => $this->session->id, '--commit' => true])
            ->assertSuccessful();
        $this->assertNull($project->fresh()->award_level);

        $this->artisan('inotek:import-awards', ['file' => $this->csv("GT-01,Gold\n"), '--session' => 999])
            ->assertFailed();
    }

    private function project(string $code, ?string $award = null, int $status = Project::STATUS_APPROVED): Project
    {
        return Project::create([
            'session_id' => $this->session->id,
            'category_id' => $this->category->id,
            'user_id' => User::factory()->create(['role_id' => 4])->id,
            'pcode' => $code,
            'title' => "Project {$code}",
            'abstract' => 'Abstract',
            'institution_type' => 'utem',
            'status' => $status,
            'award_level' => $award,
            'supervisor_name' => 'Supervisor',
            'supervisor_email' => 'supervisor@example.com',
        ]);
    }

    private function csv(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'awards');
        file_put_contents($path, $contents);

        return $path;
    }
}
