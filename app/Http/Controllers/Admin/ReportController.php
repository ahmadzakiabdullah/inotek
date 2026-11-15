<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompetitionSession;
use App\Services\AuditLogger;
use App\Services\Reports\Report;
use App\Services\Reports\ReportBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private readonly ReportBuilder $reports) {}

    /**
     * List the available reports for a session.
     */
    public function index(Request $request): Response
    {
        $sessions = CompetitionSession::orderByDesc('id')->get(['id', 'name', 'is_active', 'r2_locked']);
        $selected = $this->selectedSession($request, $sessions->first()?->id);

        return Inertia::render('admin/reports/Index', [
            'sessions' => $sessions,
            'selectedSessionId' => $selected?->id,
            'resultsFinalized' => (bool) $selected?->r2_locked,
            'reports' => collect(ReportBuilder::TYPES)
                ->map(fn (array $meta, string $key) => ['key' => $key, ...$meta])
                ->values(),
        ]);
    }

    /**
     * Download a report as CSV or PDF.
     */
    public function download(Request $request, string $type, string $format): HttpResponse
    {
        abort_unless(array_key_exists($type, ReportBuilder::TYPES), 404);
        abort_unless(in_array($format, ['csv', 'pdf'], true), 404);

        $request->validate(['session_id' => ['required', 'integer', 'exists:competition_sessions,id']]);
        $session = CompetitionSession::findOrFail($request->integer('session_id'));

        $report = $this->reports->build($type, $session);
        $filename = Str::slug("inotek {$session->name} {$type}").'-'.now()->format('Ymd-His').'.'.$format;

        AuditLogger::log(
            'EXPORT_REPORT',
            "Exported report '{$report->title}' ({$format}) for session '{$session->name}'."
        );

        return $format === 'csv'
            ? $this->csv($report, $filename)
            : $this->pdf($report, $filename);
    }

    private function csv(Report $report, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                throw new \RuntimeException('Unable to open the output stream for the CSV export.');
            }

            // UTF-8 byte order mark, so Excel reads names with non-ASCII characters correctly.
            fwrite($out, "\xEF\xBB\xBF");

            if ($report->note) {
                fputcsv($out, [$report->note], ',', '"', '');
            }

            fputcsv($out, $report->columns, ',', '"', '');
            foreach ($report->rows as $row) {
                fputcsv($out, array_map(fn ($value) => $this->csvSafe($value), $row), ',', '"', '');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function pdf(Report $report, string $filename): HttpResponse
    {
        return Pdf::loadView('pdf.report', [
            'report' => $report,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ])
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }

    /**
     * Participants type project titles and names, so a value such as
     * "=HYPERLINK(...)" must not run as a formula when the CSV is opened in Excel.
     */
    private function csvSafe(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }

    private function selectedSession(Request $request, ?int $fallbackId): ?CompetitionSession
    {
        $id = $request->integer('session_id') ?: (CompetitionSession::active()->id ?? $fallbackId);

        return $id ? CompetitionSession::find($id) : null;
    }
}
