<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use App\Notifications\SystemNotification;
use App\Services\AuditLogger;
use App\Services\EmailNotifications;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
            'settings' => [
                'enabled' => EmailNotifications::enabled(),
                'events' => EmailNotifications::events(),
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
        ]);

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
