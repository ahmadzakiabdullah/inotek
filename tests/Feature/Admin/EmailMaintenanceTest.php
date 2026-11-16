<?php

namespace Tests\Feature\Admin;

use App\Console\Commands\PruneOldRecords;
use App\Models\EmailLog;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmailMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->admin = User::factory()->create(['role_id' => 1]);
    }

    private function failedJob(): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['uuid' => (string) Str::uuid(), 'displayName' => 'Test', 'job' => 'x', 'data' => [], 'attempts' => 3]),
            'exception' => 'RuntimeException: 550 Relay denied',
            'failed_at' => now(),
        ]);
    }

    private function notification(User $user, ?string $readAt, string $createdAt): void
    {
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\SystemNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => '{}',
            'read_at' => $readAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    public function test_admin_can_retry_failed_jobs(): void
    {
        $this->failedJob();
        $this->failedJob();

        $this->actingAs($this->admin)->post(route('admin.email.failed.retry'))->assertSessionHasNoErrors();

        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertSame(2, DB::table('jobs')->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'RETRY_FAILED_JOBS']);
    }

    public function test_admin_can_discard_failed_jobs(): void
    {
        $this->failedJob();

        $this->actingAs($this->admin)->post(route('admin.email.failed.discard'))->assertSessionHasNoErrors();

        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'DISCARD_FAILED_JOBS']);
    }

    public function test_retry_without_failed_jobs_reports_an_error(): void
    {
        $this->actingAs($this->admin)->post(route('admin.email.failed.retry'))->assertSessionHasErrors('error');
    }

    public function test_only_admins_can_handle_failed_jobs(): void
    {
        $this->failedJob();
        $judge = User::factory()->create(['role_id' => 3]);

        $this->actingAs($judge)->post(route('admin.email.failed.retry'))->assertForbidden();
        $this->actingAs($judge)->post(route('admin.email.failed.discard'))->assertForbidden();
        $this->assertSame(1, DB::table('failed_jobs')->count());
    }

    public function test_prune_deletes_only_old_housekeeping_rows(): void
    {
        $user = User::factory()->create();

        EmailLog::record('test', 'old@example.com', 'Old', EmailLog::STATUS_SENT);
        EmailLog::query()->update(['created_at' => now()->subDays(PruneOldRecords::EMAIL_LOG_DAYS + 1)]);
        EmailLog::record('test', 'new@example.com', 'New', EmailLog::STATUS_SENT);

        $old = now()->subDays(PruneOldRecords::READ_NOTIFICATION_DAYS + 1)->toDateTimeString();
        $this->notification($user, $old, $old);            // read and old: deleted
        $this->notification($user, null, $old);            // unread: kept
        $this->notification($user, now()->toDateTimeString(), now()->toDateTimeString()); // recent: kept

        $this->failedJob();
        DB::table('failed_jobs')->update(['failed_at' => now()->subDays(PruneOldRecords::FAILED_JOB_DAYS + 1)]);
        $this->failedJob();

        $this->artisan('inotek:prune', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(2, EmailLog::count());
        $this->assertNull(PruneOldRecords::lastRun());

        $this->artisan('inotek:prune')->assertSuccessful();

        $this->assertSame(['new@example.com'], EmailLog::pluck('recipient')->all());
        $this->assertSame(2, DB::table('notifications')->count());
        $this->assertSame(1, DB::table('failed_jobs')->count());
        $this->assertSame(['email_logs' => 1, 'notifications' => 1, 'failed_jobs' => 1], PruneOldRecords::lastRun()['deleted'] ?? null);
    }

    public function test_settings_page_shows_cleanup_status_and_the_schedule_exists(): void
    {
        $this->actingAs($this->admin)->get(route('admin.email.index'))
            ->assertInertia(fn ($page) => $page
                ->where('maintenance.last_run_at', null)
                ->where('maintenance.retention.email_logs', PruneOldRecords::EMAIL_LOG_DAYS));

        $this->artisan('inotek:prune');

        $this->actingAs($this->admin)->get(route('admin.email.index'))
            ->assertInertia(fn ($page) => $page->where('maintenance.overdue', false)->whereNot('maintenance.last_run_at', null));

        $this->artisan('schedule:list')->expectsOutputToContain('inotek:prune')->assertSuccessful();
    }
}
