<?php

namespace App\Console\Commands;

use App\Models\CompetitionSession;
use App\Models\Project;
use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Sets project award levels from a CSV file of project code and award, for
 * sessions whose awards were decided outside this system (such as Sesi 1/2026,
 * judged in the legacy system). The Round 2 screen only lets admins award
 * shortlisted projects one at a time; this applies an official list in one go.
 */
class ImportAwards extends Command
{
    protected $signature = 'inotek:import-awards
        {file : CSV file with the project code in the first column and the award in the second}
        {--session= : Competition session ID (default: the active session)}
        {--commit : Write the awards (default is a preview)}';

    protected $description = 'Preview or set project award levels (Gold, Silver, Bronze) from a CSV of project code and award.';

    /** Accepted spellings, in English and Malay. "None" clears an award. */
    private const AWARDS = [
        'gold' => 'Gold', 'emas' => 'Gold',
        'silver' => 'Silver', 'perak' => 'Silver',
        'bronze' => 'Bronze', 'gangsa' => 'Bronze',
        'none' => null, 'tiada' => null, '-' => null,
    ];

    public function handle(): int
    {
        $session = $this->resolveSession();

        if ($session === null) {
            return self::FAILURE;
        }

        $file = $this->argument('file');
        $rows = is_string($file) ? $this->readRows($file) : null;

        if ($rows === null) {
            return self::FAILURE;
        }

        $projects = Project::where('session_id', $session->id)
            ->get(['id', 'pcode', 'title', 'status', 'award_level'])
            ->keyBy(fn (Project $project) => $this->normalizeCode($project->pcode));

        $changes = [];
        $unchanged = 0;
        $problems = [];
        $seen = [];

        foreach ($rows as $line => [$code, $awardText]) {
            $key = $this->normalizeCode($code);
            $awardKey = strtolower(trim($awardText));

            if (! array_key_exists($awardKey, self::AWARDS)) {
                $problems[] = [$line, $code, "Unknown award '{$awardText}' (use Gold, Silver, Bronze or None)"];

                continue;
            }

            $award = self::AWARDS[$awardKey];
            $project = $projects->get($key);

            if ($project === null) {
                $problems[] = [$line, $code, "No project with this code in {$session->name}"];

                continue;
            }

            if ($project->status !== Project::STATUS_APPROVED) {
                $problems[] = [$line, $code, 'Project is not approved'];

                continue;
            }

            if (isset($seen[$key]) && $seen[$key] !== $award) {
                $problems[] = [$line, $code, 'Listed again with a different award'];

                continue;
            }

            $seen[$key] = $award;

            if ($project->award_level === $award) {
                $unchanged++;

                continue;
            }

            $changes[$project->id] = [$project, $award];
        }

        $replacing = collect($changes)->filter(fn ($change) => $change[0]->award_level !== null)->count();
        $byAward = collect($changes)->countBy(fn ($change) => $change[1] ?? 'None (cleared)');

        $this->info("Session: {$session->name} (ID {$session->id})");
        $this->table(['Item', 'Count'], [
            ['Rows read', count($rows)],
            ['Will be set', count($changes)],
            ['...of which replace an existing award', $replacing],
            ['Already set to this award', $unchanged],
            ['Problems', count($problems)],
        ]);

        if ($byAward->isNotEmpty()) {
            $this->table(['Award', 'Projects'], $byAward->sortKeys()->map(fn ($count, $award) => [$award, $count])->values()->all());
        }

        if ($problems !== []) {
            $this->warn('Fix these rows and run the preview again. Nothing is written while any row has a problem:');
            $this->table(['Line', 'Code', 'Problem'], $problems);

            return self::FAILURE;
        }

        if (! $this->option('commit')) {
            $this->info('Preview only: nothing was written. Run again with --commit to set the awards.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($changes, $session): void {
            foreach ($changes as [$project, $award]) {
                $project->update(['award_level' => $award]);
            }

            AuditLogger::log('IMPORT_AWARDS', 'Imported '.count($changes)." award levels from a CSV file for session '{$session->name}'");
        });

        $this->info('Set '.count($changes).' award levels.');

        return self::SUCCESS;
    }

    private function resolveSession(): ?CompetitionSession
    {
        $requested = $this->option('session');

        if ($requested !== null && ! is_numeric($requested)) {
            $this->error('--session must be a competition session ID (a number).');

            return null;
        }

        $session = is_numeric($requested)
            ? CompetitionSession::find((int) $requested)
            : CompetitionSession::active();

        if ($session === null) {
            $this->error(is_numeric($requested)
                ? "Competition session ID {$requested} does not exist."
                : 'There is no active session. Pass --session=<id>.');
        }

        return $session;
    }

    /**
     * Rows keyed by their line number in the file, as [code, award]. A first
     * row whose second column is not an award is treated as a header.
     *
     * @return array<int, array{0:string, 1:string}>|null
     */
    private function readRows(string $path): ?array
    {
        if (! is_file($path) || ! is_readable($path)) {
            $this->error("Cannot read the file '{$path}'.");

            return null;
        }

        $lines = preg_split('/\r\n|\r|\n/', (string) file_get_contents($path)) ?: [];
        // Excel's "CSV UTF-8" starts with a byte order mark.
        $lines[0] = preg_replace('/^\xEF\xBB\xBF/', '', $lines[0] ?? '') ?? '';
        $delimiter = $this->detectDelimiter($lines[0]);
        $rows = [];

        foreach ($lines as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            $cells = array_map(fn ($cell) => trim((string) $cell), str_getcsv($line, $delimiter, '"', ''));
            [$code, $award] = [$cells[0], $cells[1] ?? ''];

            if ($index === 0 && ! array_key_exists(strtolower($award), self::AWARDS)) {
                continue;
            }

            if ($code === '' && $award === '') {
                continue;
            }

            $rows[$index + 1] = [$code, $award];
        }

        if ($rows === []) {
            $this->error('The file has no rows to import.');

            return null;
        }

        return $rows;
    }

    private function detectDelimiter(string $firstLine): string
    {
        $counts = [',' => substr_count($firstLine, ','), ';' => substr_count($firstLine, ';'), "\t" => substr_count($firstLine, "\t")];
        arsort($counts);

        return (string) array_key_first($counts);
    }

    private function normalizeCode(?string $code): string
    {
        return strtoupper(trim((string) $code));
    }
}
