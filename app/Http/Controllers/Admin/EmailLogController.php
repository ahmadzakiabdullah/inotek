<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use App\Services\EmailLogRecorder;
use App\Services\EmailNotifications;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailLogController extends Controller
{
    private const PER_PAGE = 25;

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:'.EmailLog::STATUS_SENT.','.EmailLog::STATUS_FAILED],
            'event' => ['nullable', 'string', 'max:50'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));

        $logs = EmailLog::query()
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['event'] ?? null, fn ($query, $event) => $query->where('event', $event))
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn ($q) => $q->where('recipient', 'like', $like)->orWhere('subject', 'like', $like));
            })
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (EmailLog $log) => [
                'id' => $log->id,
                'event' => $log->event,
                'recipient' => $log->recipient,
                'subject' => $log->subject,
                'status' => $log->status,
                'error' => $log->error,
                'created_at' => $log->created_at?->toIso8601String(),
            ]);

        $since = now()->subDays(30);

        return Inertia::render('admin/email/Logs', [
            'logs' => $logs,
            'filters' => [
                'status' => $validated['status'] ?? '',
                'event' => $validated['event'] ?? '',
                'search' => $search,
            ],
            'events' => self::eventLabels(),
            'summary' => [
                'sent' => EmailLog::where('status', EmailLog::STATUS_SENT)->where('created_at', '>=', $since)->count(),
                'failed' => EmailLog::where('status', EmailLog::STATUS_FAILED)->where('created_at', '>=', $since)->count(),
            ],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public static function eventLabels(): array
    {
        $labels = array_map(fn (array $event) => $event['label'], EmailNotifications::EVENTS);

        return $labels + [
            EmailNotifications::ANNOUNCEMENT => 'Announcement',
            'judge_reminder' => 'Judge reminder',
            EmailNotifications::TEST => 'Test email',
            EmailLogRecorder::EVENT_OTHER => 'Other (e.g. password reset)',
        ];
    }
}
