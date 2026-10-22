<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;

class ChangelogController extends Controller
{
    /**
     * Display the system updates timeline.
     */
    public function index(): Response
    {
        // Remember cached version for 1 hour to maximize read performance
        $changelog = Cache::remember('system.changelog', 3600, function () {
            $path = base_path('docs/changelog.md');
            if (! File::exists($path)) {
                return [];
            }

            $content = File::get($path);

            return $this->parseChangelog($content);
        });

        return Inertia::render('changelog/Index', [
            'timeline' => $changelog,
        ]);
    }

    /**
     * Clear the cached changelog to force reload.
     */
    public function clearCache(): RedirectResponse
    {
        Cache::forget('system.changelog');

        return back()->with('success', 'Changelog cache cleared successfully!');
    }

    /**
     * Parser logic converting SemVer markdown to timeline structure.
     */
    /**
     * @return list<array{version: string, date: string, badge: string|null, changes: array<string, list<string>>}>
     */
    private function parseChangelog(string $content): array
    {
        $lines = explode("\n", $content);
        $timeline = [];
        /** @var array{version: string, date: string, badge: string|null, changes: array<string, list<string>>}|null $currentVersion */
        $currentVersion = null;
        /** @var list<array{version: string, date: string, badge: string|null, changes: array<string, list<string>>}> $timeline */
        $currentGroup = null;

        foreach ($lines as $line) {
            $line = trim($line);

            if (empty($line)) {
                continue;
            }

            // Match "### [v1.5.0] - 2026-06-18 (Current Release)"
            if (preg_match('/^###\s+\[(v\d+\.\d+\.\d+)\]\s+-\s+([\d-]+)(?:\s+\((.+)\))?/', $line, $matches)) {
                if ($currentVersion) {
                    $timeline[] = $currentVersion;
                }
                $currentVersion = [
                    'version' => $matches[1],
                    'date' => $matches[2],
                    'badge' => $matches[3] ?? null,
                    'changes' => [],
                ];
                $currentGroup = null;

                continue;
            }

            // Match "#### Added", "#### Changed", "#### Fixed", etc.
            if (preg_match('/^####\s+(Added|Changed|Fixed|Deprecated|Removed)/i', $line, $matches) && $currentVersion) {
                $currentGroup = strtolower($matches[1]);
                $currentVersion['changes'][$currentGroup] = [];

                continue;
            }

            // Match list items starting with "*" or "-"
            if (preg_match('/^[*-]\s+(.+)/', $line, $matches) && $currentVersion && $currentGroup !== null) {
                $currentVersion['changes'][$currentGroup][] = $matches[1];
            }
        }

        if ($currentVersion) {
            $timeline[] = $currentVersion;
        }

        return $timeline;
    }
}
