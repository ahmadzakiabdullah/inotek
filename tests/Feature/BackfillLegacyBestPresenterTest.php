<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CompetitionSession;
use App\Models\Project;
use App\Models\Score;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BackfillLegacyBestPresenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    /**
     * MySQL commits implicitly on DDL, so the legacy tables are created before the
     * test transaction starts and removed after it is rolled back.
     */
    protected function refreshTestDatabase(): void
    {
        if (! RefreshDatabaseState::$migrated) {
            $this->artisan('migrate:fresh', $this->migrateFreshUsing());
            $this->app[Kernel::class]->setArtisan(null);
            RefreshDatabaseState::$migrated = true;
        }

        $this->dropLegacyTables();
        // Production's legacy `sessions` table has a name; in the test database
        // that table is Laravel's session store, so give it a name column.
        Schema::table('sessions', fn (Blueprint $table) => $table->string('name')->nullable());
        Schema::create('score', function (Blueprint $table) {
            $table->id();
            $table->string('session_id');
            $table->string('pcode');
            $table->string('juri');
            $table->integer('round_no');
            $table->integer('status');
            $table->string('best_presenter')->nullable();
        });

        $this->beginDatabaseTransaction();
        // Registered after the transaction, so it runs after the rollback.
        $this->beforeApplicationDestroyed(fn () => $this->dropLegacyTables());
    }

    private function dropLegacyTables(): void
    {
        Schema::dropIfExists('score');

        if (Schema::hasColumn('sessions', 'name')) {
            Schema::table('sessions', fn (Blueprint $table) => $table->dropColumn('name'));
        }
    }

    public function test_preview_writes_nothing_and_commit_fills_only_empty_nominations(): void
    {
        $session = CompetitionSession::create(['name' => 'Sesi 1/2026', 'is_active' => true, 'r2_locked' => false]);
        DB::table('sessions')->insert(['id' => 'legacy-7', 'name' => 'Sesi 1/2026', 'payload' => '', 'last_activity' => 0]);
        $category = Category::create(['session_id' => $session->id, 'code' => 'C1', 'name' => 'Technology']);
        $participant = User::factory()->create(['role_id' => 4]);
        $judge = User::factory()->create(['role_id' => 3, 'username' => 'juri1']);
        $project = Project::create([
            'session_id' => $session->id,
            'category_id' => $category->id,
            'user_id' => $participant->id,
            'pcode' => 'GT-01',
            'title' => 'Solar',
            'abstract' => 'Abstract',
            'institution_type' => 'utem',
            'status' => Project::STATUS_APPROVED,
            'supervisor_name' => 'Supervisor',
            'supervisor_email' => 'supervisor@example.com',
        ]);
        $empty = Score::create(['project_id' => $project->id, 'judge_id' => $judge->id, 'session_id' => $session->id, 'round_no' => 1, 'total' => 70, 'score_details' => []]);
        $kept = Score::create(['project_id' => $project->id, 'judge_id' => $judge->id, 'session_id' => $session->id, 'round_no' => 2, 'total' => 80, 'score_details' => [], 'best_presenter' => 'Already chosen']);

        DB::table('score')->insert([
            ['session_id' => 'legacy-7', 'pcode' => 'GT-01', 'juri' => 'juri1', 'round_no' => 1, 'status' => 1, 'best_presenter' => 'Ali'],
            ['session_id' => 'legacy-7', 'pcode' => 'GT-01', 'juri' => 'juri1', 'round_no' => 2, 'status' => 1, 'best_presenter' => 'Abu'],
            ['session_id' => 'legacy-7', 'pcode' => 'GT-99', 'juri' => 'juri1', 'round_no' => 1, 'status' => 1, 'best_presenter' => 'Nobody'],
        ]);

        $this->artisan('inotek:backfill-legacy-presenters')->assertSuccessful();
        $this->assertNull($empty->fresh()->best_presenter);

        $this->artisan('inotek:backfill-legacy-presenters', ['--commit' => true])->assertSuccessful();
        $this->assertSame('Ali', $empty->fresh()->best_presenter);
        $this->assertSame('Already chosen', $kept->fresh()->best_presenter);
    }
}
