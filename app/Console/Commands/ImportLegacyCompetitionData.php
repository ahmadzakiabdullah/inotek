<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ImportLegacyCompetitionData extends Command
{
    protected $signature = 'inotek:import-legacy-competition {--commit : Write the import into the selected database}';

    protected $description = 'Preview or import legacy INOTEK registrations and submitted scores into the normalized competition tables.';

    /** @var array<string, array{code:string,name:string,prefix:string,rubric_id:int}> */
    private const CATEGORIES = [
        'C1' => ['code' => 'C1', 'name' => 'Teknologi Hijau (Green Technology)', 'prefix' => 'GT', 'rubric_id' => 1],
        'C2' => ['code' => 'C2', 'name' => 'Kejuruteraan Sistem (System Engineering)', 'prefix' => 'SE', 'rubric_id' => 1],
        'C3' => ['code' => 'C3', 'name' => 'Teknologi Baru Muncul (Emerging Technology)', 'prefix' => 'ET', 'rubric_id' => 1],
        'C4' => ['code' => 'C4', 'name' => 'Teknologi Kesihatan (Health Innovation Technology)', 'prefix' => 'HIT', 'rubric_id' => 1],
        'C5' => ['code' => 'C5', 'name' => 'Reka Bentuk Perindustrian (Industrial Design Product)', 'prefix' => 'IDP', 'rubric_id' => 2],
        'C6' => ['code' => 'C6', 'name' => 'Produk Digital (Digital Product)', 'prefix' => 'DP', 'rubric_id' => 3],
    ];

    /** @var array<int, int> */
    private const PROJECT_STATUSES = [
        1 => 1, // Legacy draft/unverified.
        3 => 3, // Verified, awaiting approval.
        4 => 4, // Approved.
        5 => 4, // Approved (legacy state also has approval date).
        6 => 6, // Cancelled.
    ];

    public function handle(): int
    {
        $database = DB::connection()->getDatabaseName();

        if (! in_array($database, ['db4inotekdev', 'db4inotek'], true)) {
            $this->error("Refusing to import: selected database is '{$database}', expected 'db4inotekdev' or 'db4inotek'.");

            return self::FAILURE;
        }

        foreach (['sessions', 'pendaftaran', 'score', 'users', 'competition_sessions', 'categories', 'projects', 'team_members', 'scores', 'rubrics', 'rubric_items', 'category_rubric_mapping'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->error("Required table '{$table}' is missing.");

                return self::FAILURE;
            }
        }

        if (! $this->supervisorContactsNullable()) {
            $this->error('Run the pending migrations on db4inotekdev first so historical projects can omit unavailable supervisor contact details.');

            return self::FAILURE;
        }

        $registrations = DB::table('pendaftaran')->orderBy('id')->get();
        $users = DB::table('users')->get(['id', 'username', 'nokp']);
        $usersByUsername = $this->indexUsers($users, 'username');
        $usersByNokp = $this->indexUsers($users, 'nokp');
        $sourceSessionIds = $registrations->pluck('session_id')->unique()->filter()->values()->all();
        $legacySessions = DB::table('sessions')->whereIn('id', $sourceSessionIds)->orderBy('id')->get()->keyBy('id');

        $eligibleProjects = [];
        $unmatchedOwners = 0;
        $unsupportedProjects = 0;

        foreach ($registrations as $registration) {
            $ownerId = $this->resolveOwner($registration->user, $usersByUsername, $usersByNokp);
            $categoryCode = strtoupper(trim((string) $registration->kategoriBidang));

            if ($ownerId === null) {
                $unmatchedOwners++;
                continue;
            }

            if (! $legacySessions->has($registration->session_id)
                || ! isset(self::CATEGORIES[$categoryCode])
                || ! isset(self::PROJECT_STATUSES[(int) $registration->status])
                || ! in_array(strtolower(trim((string) $registration->katIns)), ['utem', 'ipt'], true)
                || trim((string) $registration->pcode) === '') {
                $unsupportedProjects++;
                continue;
            }

            $eligibleProjects[$this->legacyKey($registration->session_id, $registration->pcode)] = [
                'registration' => $registration,
                'owner_id' => $ownerId,
                'category_code' => $categoryCode,
            ];
        }

        foreach (self::CATEGORIES as $category) {
            if (! DB::table('rubrics')->where('id', $category['rubric_id'])->exists()) {
                $this->error("Expected legacy rubric ID {$category['rubric_id']} for {$category['code']} is missing.");

                return self::FAILURE;
            }
        }

        $submittedScores = DB::table('score')->where('status', 1)->orderBy('id')->get();
        $judgeUsernames = array_fill_keys(array_keys($usersByUsername), true);
        $importableScores = 0;
        $scoresWithoutProject = 0;
        $scoresWithoutJudge = 0;
        $scoresWithoutDetails = 0;

        foreach ($submittedScores as $score) {
            $key = $this->legacyKey($score->session_id, $score->pcode);
            $details = json_decode((string) $score->score_details, true);

            if (! isset($eligibleProjects[$key])) {
                $scoresWithoutProject++;
                continue;
            }

            if (! isset($judgeUsernames[(string) $score->juri])) {
                $scoresWithoutJudge++;
                continue;
            }

            if (! is_array($details)) {
                $scoresWithoutDetails++;
                continue;
            }

            $importableScores++;
        }

        $this->table(['Item', 'Kiraan'], [
            ['Sesi legacy', $legacySessions->count()],
            ['Pendaftaran lama', $registrations->count()],
            ['Projek boleh dipaut ke user', count($eligibleProjects)],
            ['Pemilik tiada padanan', $unmatchedOwners],
            ['Projek dengan data/status tidak disokong', $unsupportedProjects],
            ['Markah dihantar (status 1)', $submittedScores->count()],
            ['Markah boleh dipaut', $importableScores],
            ['Markah tanpa projek yang boleh diimport', $scoresWithoutProject],
            ['Markah tanpa akaun juri', $scoresWithoutJudge],
            ['Markah tanpa JSON sah', $scoresWithoutDetails],
            ['Markah draf (status 0), dikekalkan dalam jadual lama', DB::table('score')->where('status', 0)->count()],
        ]);

        if (! $this->option('commit')) {
            $this->info("Mod pratonton pada {$database}: tiada data ditulis. Selepas menyemak kiraan ini, tambah --commit untuk import ke {$database}.");

            return self::SUCCESS;
        }

        try {
            $result = DB::transaction(function () use ($legacySessions, $registrations, $usersByUsername, $usersByNokp): array {
                $sessionMap = $this->syncSessions($legacySessions);
                $categoryMap = $this->syncCategories($sessionMap);
                $projectStats = $this->syncProjects($registrations, $sessionMap, $categoryMap, $usersByUsername, $usersByNokp);
                $scoreStats = $this->syncScores($sessionMap, $usersByUsername);

                return $projectStats + $scoreStats;
            });
        } catch (Throwable $exception) {
            $this->error('Import dibatalkan dan transaksi di-rollback: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("Import selesai pada {$database}:");
        foreach ($result as $name => $count) {
            $this->line("- {$name}: {$count}");
        }

        $this->warn('Jadual legacy pendaftaran, score, sessions dan penyelidik tidak dipadam atau diubah.');

        return self::SUCCESS;
    }

    private function supervisorContactsNullable(): bool
    {
        $columns = DB::select(
            'SELECT COLUMN_NAME, IS_NULLABLE FROM information_schema.columns '
            .'WHERE table_schema = DATABASE() AND table_name = ? AND column_name IN (?, ?)',
            ['projects', 'supervisor_name', 'supervisor_email'],
        );

        return count($columns) === 2 && collect($columns)->every(fn ($column) => $column->IS_NULLABLE === 'YES');
    }

    /** @param \Illuminate\Support\Collection<int, object> $users
     *  @return array<string, int>
     */
    private function indexUsers($users, string $field): array
    {
        $indexed = [];

        foreach ($users as $user) {
            $value = trim((string) ($user->{$field} ?? ''));
            if ($value !== '' && ! isset($indexed[$value])) {
                $indexed[$value] = (int) $user->id;
            }
        }

        return $indexed;
    }

    /** @param array<string, int> $usersByUsername
     *  @param array<string, int> $usersByNokp
     */
    private function resolveOwner(?string $legacyUser, array $usersByUsername, array $usersByNokp): ?int
    {
        $legacyUser = trim((string) $legacyUser);

        return $usersByUsername[$legacyUser] ?? $usersByNokp[$legacyUser] ?? null;
    }

    /** @param \Illuminate\Support\Collection<int, object> $legacySessions
     *  @return array<int, int>
     */
    private function syncSessions($legacySessions): array
    {
        $sessionMap = [];

        foreach ($legacySessions as $legacy) {
            $now = now();
            $targetId = DB::table('competition_sessions')->where('name', $legacy->name)->value('id');
            $values = [
                'is_active' => (bool) $legacy->is_active,
                'r2_locked' => (bool) ($legacy->r2_locked ?? false),
                'updated_at' => $now,
            ];

            if ($targetId === null) {
                $targetId = DB::table('competition_sessions')->insertGetId($values + [
                    'name' => $legacy->name,
                    'created_at' => $now,
                ]);
            } else {
                DB::table('competition_sessions')->where('id', $targetId)->update($values);
            }

            $sessionMap[(int) $legacy->id] = (int) $targetId;
        }

        return $sessionMap;
    }

    /** @param array<int, int> $sessionMap
     *  @return array<string, array{category_id:int,rubric_id:int}>
     */
    private function syncCategories(array $sessionMap): array
    {
        $categoryMap = [];
        $hasPrefix = Schema::hasColumn('categories', 'prefix');
        $hasSortOrder = Schema::hasColumn('categories', 'sort_order');
        $hasActive = Schema::hasColumn('categories', 'is_active');
        $hasAllowTeam = Schema::hasColumn('categories', 'allow_team');
        $hasCreatedAt = Schema::hasColumn('categories', 'created_at');
        $hasUpdatedAt = Schema::hasColumn('categories', 'updated_at');

        foreach ($sessionMap as $targetSessionId) {
            foreach (array_values(self::CATEGORIES) as $index => $category) {
                $now = now();
                $categoryId = DB::table('categories')
                    ->where('code', $category['code'])
                    ->value('id');
                $values = [
                    'session_id' => null,
                    'code' => $category['code'],
                    'name' => $category['name'],
                ];

                if ($hasPrefix) {
                    $values['prefix'] = $category['prefix'];
                }
                if ($hasSortOrder) {
                    $values['sort_order'] = $index + 1;
                }
                if ($hasActive) {
                    $values['is_active'] = true;
                }
                if ($hasAllowTeam) {
                    $values['allow_team'] = false;
                }
                if ($hasUpdatedAt) {
                    $values['updated_at'] = $now;
                }

                if ($categoryId === null) {
                    if ($hasCreatedAt) {
                        $values['created_at'] = $now;
                    }
                    $categoryId = DB::table('categories')->insertGetId($values);
                } else {
                    DB::table('categories')->where('id', $categoryId)->update($values);
                }

                $mappingExists = DB::table('category_rubric_mapping')
                    ->where('session_id', $targetSessionId)
                    ->where('category_id', $categoryId)
                    ->exists();

                if (! $mappingExists) {
                    $mapping = [
                        'session_id' => $targetSessionId,
                        'category_id' => $categoryId,
                        'rubric_id' => $category['rubric_id'],
                    ];

                    if (Schema::hasColumn('category_rubric_mapping', 'category_code')) {
                        $mapping['category_code'] = null;
                    }
                    if (Schema::hasColumn('category_rubric_mapping', 'created_at')) {
                        $mapping['created_at'] = $now;
                    }
                    if (Schema::hasColumn('category_rubric_mapping', 'updated_at')) {
                        $mapping['updated_at'] = $now;
                    }

                    DB::table('category_rubric_mapping')->insert($mapping);
                }

                $categoryMap[$targetSessionId.'|'.$category['code']] = [
                    'category_id' => (int) $categoryId,
                    'rubric_id' => $category['rubric_id'],
                ];
            }
        }

        return $categoryMap;
    }

    /** @param \Illuminate\Support\Collection<int, object> $registrations
     *  @param array<int, int> $sessionMap
     *  @param array<string, array{category_id:int,rubric_id:int}> $categoryMap
     *  @param array<string, int> $usersByUsername
     *  @param array<string, int> $usersByNokp
     *  @return array<string, int>
     */
    private function syncProjects($registrations, array $sessionMap, array $categoryMap, array $usersByUsername, array $usersByNokp): array
    {
        $created = 0;
        $existing = 0;
        $membersCreated = 0;
        $projectIds = [];

        foreach ($registrations as $registration) {
            $ownerId = $this->resolveOwner($registration->user, $usersByUsername, $usersByNokp);
            $categoryCode = strtoupper(trim((string) $registration->kategoriBidang));
            $targetSessionId = $sessionMap[(int) $registration->session_id] ?? null;
            $category = $targetSessionId === null ? null : ($categoryMap[$targetSessionId.'|'.$categoryCode] ?? null);

            if ($ownerId === null || $targetSessionId === null || $category === null) {
                continue;
            }

            $projectId = DB::table('projects')
                ->where('session_id', $targetSessionId)
                ->where('pcode', $registration->pcode)
                ->value('id');

            if ($projectId === null) {
                $createdAt = $this->legacyTimestamp($registration->tkhMohon ?? null);
                $updatedAt = $this->legacyTimestamp($registration->tkhKemaskini ?? null, $createdAt);
                $projectId = DB::table('projects')->insertGetId([
                    'session_id' => $targetSessionId,
                    'category_id' => $category['category_id'],
                    'user_id' => $ownerId,
                    'pcode' => $registration->pcode,
                    'title' => (string) $registration->namaProduk,
                    'abstract' => (string) $registration->abstrak,
                    'poster_url' => $this->nullableString($registration->poster ?? null),
                    'video_url' => $this->nullableString($registration->youtube ?? null),
                    'institution_type' => strtolower(trim((string) $registration->katIns)),
                    'status' => self::PROJECT_STATUSES[(int) $registration->status],
                    'supervisor_name' => $this->nullableString($registration->namaPenasihat ?? null),
                    'supervisor_email' => null,
                    'supervisor_phone' => null,
                    'admin_comments' => null,
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                    'deleted_at' => null,
                ]);
                $created++;

                foreach ([2, 3, 4] as $memberNumber) {
                    $name = $this->nullableString($registration->{'namaPemohon'.$memberNumber} ?? null);
                    if ($name === null) {
                        continue;
                    }

                    $phone = $memberNumber === 2
                        ? $this->nullableString($registration->nohpPemohon2 ?? null)
                        : null;

                    DB::table('team_members')->insert([
                        'project_id' => $projectId,
                        'name' => $name,
                        'email' => null,
                        'phone' => $phone,
                        'created_at' => $createdAt,
                        'updated_at' => $updatedAt,
                        'deleted_at' => null,
                    ]);
                    $membersCreated++;
                }
            } else {
                $existing++;
            }

            $projectIds[$this->legacyKey($registration->session_id, $registration->pcode)] = (int) $projectId;
        }

        return [
            'Projek baharu' => $created,
            'Projek sedia ada (tidak ditulis semula)' => $existing,
            'Ahli kumpulan diimport' => $membersCreated,
        ];
    }

    /** @param array<int, int> $sessionMap
     *  @param array<string, int> $usersByUsername
     *  @return array<string, int>
     */
    private function syncScores(array $sessionMap, array $usersByUsername): array
    {
        $projects = DB::table('projects')->get(['id', 'session_id', 'pcode', 'category_id']);
        $projectsByKey = [];
        $rubricByCategory = [];

        foreach ($projects as $project) {
            $projectsByKey[$project->session_id.'|'.base64_encode((string) $project->pcode)] = $project;
            $rubricByCategory[$project->session_id.'|'.$project->category_id] = DB::table('category_rubric_mapping')
                ->where('session_id', $project->session_id)
                ->where('category_id', $project->category_id)
                ->value('rubric_id');
        }

        $itemsByRubric = [];
        $imported = 0;
        $skipped = 0;
        $scores = DB::table('score')->where('status', 1)->orderBy('id')->get();

        foreach ($scores as $legacyScore) {
            $targetSessionId = $sessionMap[(int) $legacyScore->session_id] ?? null;
            $projectKey = $targetSessionId === null ? null : $targetSessionId.'|'.base64_encode((string) $legacyScore->pcode);
            $project = $projectKey === null ? null : ($projectsByKey[$projectKey] ?? null);
            $judgeId = $usersByUsername[(string) $legacyScore->juri] ?? null;
            $legacyDetails = json_decode((string) $legacyScore->score_details, true);

            if ($project === null || $judgeId === null || ! is_array($legacyDetails)) {
                $skipped++;
                continue;
            }

            $rubricId = $rubricByCategory[$project->session_id.'|'.$project->category_id] ?? null;
            if ($rubricId === null) {
                $skipped++;
                continue;
            }

            if (! isset($itemsByRubric[$rubricId])) {
                $itemsByRubric[$rubricId] = DB::table('rubric_items')
                    ->where('rubric_id', $rubricId)
                    ->get(['id', 'code'])
                    ->mapWithKeys(fn ($item) => [strtoupper((string) $item->code) => (int) $item->id])
                    ->all();
            }

            $normalizedDetails = [];
            foreach ($legacyDetails as $key => $value) {
                $itemId = $itemsByRubric[$rubricId][strtoupper((string) $key)] ?? null;
                if ($itemId !== null && is_numeric($value)) {
                    $normalizedDetails[(string) $itemId] = (float) $value;
                }
            }

            $alreadyImported = DB::table('scores')
                ->where('project_id', $project->id)
                ->where('judge_id', $judgeId)
                ->where('session_id', $targetSessionId)
                ->where('round_no', $legacyScore->round_no)
                ->exists();

            if ($alreadyImported) {
                $skipped++;
                continue;
            }

            $submittedAt = $this->legacyTimestamp($legacyScore->submitted_at ?? null, $this->legacyTimestamp($legacyScore->scoreDate ?? null));
            DB::table('scores')->insert([
                'project_id' => $project->id,
                'judge_id' => $judgeId,
                'session_id' => $targetSessionId,
                'round_no' => max(1, (int) $legacyScore->round_no),
                'total' => (float) $legacyScore->total,
                'score_details' => json_encode($normalizedDetails, JSON_THROW_ON_ERROR),
                'comments' => $this->nullableString($legacyScore->comment ?? null),
                'best_presenter' => null,
                'created_at' => $submittedAt,
                'updated_at' => $submittedAt,
            ]);
            $imported++;
        }

        return [
            'Markah dihantar diimport' => $imported,
            'Markah dihantar dilangkau (projek/juri sudah ada atau tak sepadan)' => $skipped,
        ];
    }

    private function legacyKey(int|string|null $sessionId, string|null $pcode): string
    {
        return (string) $sessionId.'|'.base64_encode((string) $pcode);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function legacyTimestamp(mixed $value, ?string $fallback = null): string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '' || str_starts_with($value, '0000-00-00')) {
            return $fallback ?? now()->toDateTimeString();
        }

        try {
            return Carbon::parse($value)->toDateTimeString();
        } catch (Throwable) {
            return $fallback ?? now()->toDateTimeString();
        }
    }
}
