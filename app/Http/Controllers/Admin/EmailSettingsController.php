<?php

namespace App\Http\Controllers\Admin;

use App\Console\Commands\PruneOldRecords;
use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use App\Notifications\SystemNotification;
use App\Services\AuditLogger;
use App\Services\EmailNotifications;
use App\Services\EmailTemplates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class EmailSettingsController extends Controller
{
    /**
     * A queued job older than this suggests the queue worker is not running.
     */
    private const STALE_JOB_MINUTES = 5;

    public function index(): Response
    {
        return Inertia::render('admin/email/Index', [
            'status' => $this->deliveryStatus(),
            'maintenance' => $this->maintenanceStatus(),
            'settings' => [
                'enabled' => EmailNotifications::enabled(),
                'events' => EmailNotifications::events(),
                'footer' => EmailTemplates::footer(),
            ],
            'branding' => [
                'logo_url' => asset('images/email-logo.png'),
                'default_footer' => EmailTemplates::DEFAULT_FOOTER,
                'max_footer' => EmailTemplates::MAX_FOOTER,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $keys = array_keys(EmailNotifications::EVENTS);

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'events' => ['sometimes', 'array:'.implode(',', $keys)],
            'events.*' => ['boolean'],
            'footer' => ['nullable', 'string', 'max:'.EmailTemplates::MAX_FOOTER],
        ]);

        $footer = (string) ($validated['footer'] ?? '');
        preg_match_all('/\{([a-z_]+)\}/', $footer, $matches);
        $unknown = array_diff($matches[1], ['year', 'system_name']);

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'footer' => 'Unknown placeholder: {'.implode('}, {', $unknown).'}. The footer can use {year} and {system_name}.',
            ]);
        }

        if ($request->has('footer')) {
            EmailTemplates::saveFooter($footer);
        }

        /** @var array<string, bool> $events */
        $events = array_map(fn ($value) => (bool) $value, $validated['events'] ?? []);
        $enabled = (bool) $validated['enabled'];

        EmailNotifications::update($enabled, $events);

        $eventSummary = collect($events)
            ->map(fn (bool $on, string $key) => $key.'='.($on ? 'on' : 'off'))
            ->implode(', ');

        AuditLogger::log(
            'UPDATE_EMAIL_SETTINGS',
            'Email delivery '.($enabled ? 'enabled' : 'disabled').($eventSummary !== '' ? "; events: {$eventSummary}" : '')
        );

        return $this->backWithSuccess('Email settings saved.');
    }

    /**
     * Send a test email to the signed-in admin straight away (not queued), so
     * delivery problems are reported on the page.
     */
    public function test(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user || trim((string) $user->email) === '') {
            return $this->backWithError('Your account has no email address.');
        }

        try {
            $user->notifyNow(new SystemNotification(
                'Test Email',
                'This is a test email from INOTEK. If you can read it, email delivery is working.',
                '/dashboard/email',
                'info',
                mailEvent: EmailNotifications::TEST,
            ), ['mail']);
        } catch (Throwable $e) {
            report($e);
            EmailLog::record(EmailNotifications::TEST, (string) $user->email, 'Test Email', EmailLog::STATUS_FAILED, $e->getMessage());

            return $this->backWithError('The test email could not be sent: '.$e->getMessage());
        }

        AuditLogger::log('SEND_TEST_EMAIL', "Sent a test email to {$user->email}");

        return $this->backWithSuccess("Test email sent to {$user->email}. Check the inbox and the spam folder.");
    }

    /**
     * Put every failed job back on the queue; the worker picks them up
     * within a minute.
     */
    public function retryFailed(): RedirectResponse
    {
        $count = $this->failedJobCount();

        if ($count === 0) {
            return $this->backWithError('There are no failed jobs to retry.');
        }

        Artisan::call('queue:retry', ['id' => ['all']]);
        AuditLogger::log('RETRY_FAILED_JOBS', "Retried {$count} failed queue job(s)");

        return $this->backWithSuccess("{$count} failed job(s) put back on the queue. They are retried within a minute; check the Logs tab.");
    }

    /**
     * Delete every failed job without retrying it.
     */
    public function discardFailed(): RedirectResponse
    {
        $count = $this->failedJobCount();

        if ($count === 0) {
            return $this->backWithError('There are no failed jobs to discard.');
        }

        Artisan::call('queue:flush');
        AuditLogger::log('DISCARD_FAILED_JOBS', "Discarded {$count} failed queue job(s)");

        return $this->backWithSuccess("{$count} failed job(s) discarded.");
    }

    private function failedJobCount(): int
    {
        return Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function maintenanceStatus(): array
    {
        $lastRun = PruneOldRecords::lastRun();
        $at = $lastRun ? Carbon::parse($lastRun['at']) : null;

        return [
            'last_run_at' => $at?->toIso8601String(),
            'last_run_human' => $at?->diffForHumans(),
            'deleted' => $lastRun['deleted'] ?? null,
            // It runs daily at 03:00, so more than two days means the scheduler is not running.
            'overdue' => $at !== null && $at->lt(now()->subDays(2)),
            'retention' => [
                'email_logs' => PruneOldRecords::EMAIL_LOG_DAYS,
                'notifications' => PruneOldRecords::READ_NOTIFICATION_DAYS,
                'failed_jobs' => PruneOldRecords::FAILED_JOB_DAYS,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function deliveryStatus(): array
    {
        $mailer = (string) config('mail.default');
        $queue = (string) config('queue.default');
        $pending = null;
        $failed = null;
        $oldestMinutes = null;

        if ($queue === 'database' && Schema::hasTable('jobs')) {
            $pending = DB::table('jobs')->count();
            $oldest = DB::table('jobs')->min('created_at');
            $oldestMinutes = $oldest ? (int) floor((time() - (int) $oldest) / 60) : null;
        }

        if (Schema::hasTable('failed_jobs')) {
            $failed = DB::table('failed_jobs')->count();
        }

        return [
            'mailer' => $mailer,
            'host' => $mailer === 'smtp' ? config('mail.mailers.smtp.host') : null,
            'port' => $mailer === 'smtp' ? config('mail.mailers.smtp.port') : null,
            'from_address' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
            'delivers' => ! in_array($mailer, ['log', 'array'], true),
            'queue' => $queue,
            'pending_jobs' => $pending,
            'failed_jobs' => $failed,
            'oldest_job_minutes' => $oldestMinutes,
            'worker_stalled' => $oldestMinutes !== null && $oldestMinutes >= self::STALE_JOB_MINUTES,
            'stale_minutes' => self::STALE_JOB_MINUTES,
        ];
    }
}
