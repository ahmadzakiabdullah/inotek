# Cadangan Penambahbaikan Sistem INOTEK

Disediakan berdasarkan audit kod pada 12 November 2026. Dokumen ini menyenaraikan isu yang ditemui, mengikut keutamaan, beserta cadangan pembetulan yang konkrit.

**Petunjuk keutamaan:**
- **P0**: betulkan segera (kebocoran data atau keputusan)
- **P1**: betulkan sebelum pusingan penjurian seterusnya
- **P2**: penambahbaikan kestabilan atau prestasi
- **P3**: kebersihan kod

---

## Status pelaksanaan (dikemas kini 12 November 2026)

Semua perubahan kod berikut sudah dibuat di server, tetapi **belum di-commit**. Backup kod sebelum perubahan disimpan di mesin pengaudit (`scratchpad/backup-pre-fix/source.tar` dan `tracked.diff`).

**Pengesahan yang dibuat:**
- Ujian penuh dijalankan pada salinan setempat dengan MySQL 8: 163 ujian, 147 lulus.
- 16 kegagalan yang tinggal **sama persis** pada kod sebelum perubahan, jadi ia isu sedia ada (lihat "Ujian lapuk" di bawah).
- Tiada regresi. 13 ujian baharu untuk item P0/P1 lulus semuanya.
- PHPStan lulus pada fail yang diubah.

| # | Item | Status | Nota |
|---|---|---|---|
| 0 | Commit snapshot / aliran deploy | ⏳ Manual | Perlu dilakukan oleh pemilik repo |
| 1 | Props minimum halaman verify sijil | ✅ Siap | `CertificateController::verify` |
| 2 | Sembunyi anugerah dan ranking sebelum dikunci | ✅ Siap | Leaderboard, laman utama, sijil pencapaian |
| 3 | Hadkan pendaftaran projek dan semakan konflik | ✅ Siap | Route `role:user`; tidak boleh semak projek sendiri; konflik juri disemak dalam `ScoreCalculator::hasConflictOfInterest` |
| 4 | Halang padam sesi/kategori yang ada projek | ✅ Siap | Ditambah juga untuk role yang masih ada pengguna. FK belum ditukar kepada `restrict` (perlu migration pada DB production) |
| 5 | SMTP dan queue worker | ⏳ Manual | Konfigurasi `.env` dan servis Windows |
| 6 | `MustVerifyEmail` | ⏸️ Ditangguh | Bergantung pada #5; jika diaktifkan sekarang, pengguna baharu akan terkunci |
| 7 | Kunci Round 1 | ✅ Siap (berbeza daripada cadangan) | R1 dikunci **secara automatik** sebaik ada penugasan R2. Tidak perlu kolum atau migration baharu. Untuk buka semula, padam penugasan R2 |
| 8 | Susunan leaderboard | ✅ Siap | Finalis R2 sentiasa di atas |
| 9 | `r2_locked` keluar daripada borang sesi | ✅ Siap | |
| 10 | Senarai pelajar hanya untuk admin | ✅ Siap | |
| 11 | Role dilindungi ikut nama | ✅ Siap | |
| 12 | QR sijil dijana setempat | ✅ Siap | `bacon/bacon-qr-code` (GD), sudah ada melalui Fortify |
| 13 | Mesej ralat dalaman | ✅ Siap | |
| 14 | Validasi markah juri | ✅ Siap | `bail`, `comments` max 5000, projek mesti diluluskan |
| 15 | Validasi lain | ✅ Siap | Had `abstract` 10,000 (bukan 5000, kerana panjang data sedia ada tidak dapat disemak); `category_id` mesti integer |
| 16 | `LOG_LEVEL` production | ⏳ Manual | Ubah dalam `.env` |
| — | Prestasi | ✅ Kebanyakan siap | `rubric_id` dalam `scores` belum dibuat (perlu migration) |
| — | Kebersihan kod | 🟡 Separuh | Siap: helper `toast()`, trait `ProjectValidationRules`, `CompetitionSession::active()`, escape changelog. Belum: `ProjectPolicy`, enum status, buang route alias lama |

**Tambahan yang ditemui semasa pelaksanaan:**
- Migration `2026_11_02_000001_scope_rubric_mappings_to_sessions` gagal pada pangkalan data baharu (MySQL error 1553). Ia sudah dibetulkan. Production tidak terjejas kerana migration ini sudah dijalankan di sana.
- 8 migration menggunakan `information_schema`, jadi ujian **mesti** dijalankan dengan MySQL. Tetapan `phpunit.xml` (SQLite) tidak akan berfungsi.

**Ujian lapuk (sedia ada, perlu dikemas kini atau dibuang):** 16 ujian dalam `CompetitionSessionTest`, `ProjectRegistrationTest`, `CategoryCrudTest`, `ProjectVerificationTest` dan `NotificationSystemTest` gagal kerana:
- tidak menghantar `registration_mode` atau tarikh sesi yang kini diwajibkan; atau
- masih menganggap kategori terikat pada sesi (kini kategori dikongsi antara sesi).

**Langkah manual yang masih perlu dibuat:**
1. `npm run build`, supaya perubahan frontend berkuat kuasa (pautan sidebar pensyarah, escape changelog).
2. Konfigurasi SMTP, `queue:work` dan `LOG_LEVEL` dalam `.env` (#5, #16).
3. Commit semua perubahan ke git (#0).

---

## Prasyarat: aliran kerja deploy

Kod sekarang diubah terus pada server production (`\\10.1.2.22\e\events\inotek`). Lebih 115 fail telah diubah tanpa commit, dan `phpunit` tidak dipasang di server, jadi ujian tidak dapat dijalankan di situ.

- [ ] Commit perubahan semasa ke branch baharu, contohnya `git checkout -b prod-snapshot-2026-11`, kemudian push.
- [ ] Buat semua pembetulan di mesin dev yang ada `composer install` lengkap (dengan pakej dev).
- [ ] Jalankan `php artisan test` dan `vendor/bin/phpstan` sebelum deploy.
- [ ] Deploy ke production melalui `git pull` diikuti `composer install --no-dev --optimize-autoloader`, `php artisan migrate --force`, `php artisan optimize` dan `npm run build`.

---

## P0: Kebocoran data dan keputusan

### 1. Halaman awam pengesahan sijil mendedahkan data peribadi

**Fail:** `app/Http/Controllers/CertificateController.php` (method `verify`)

**Masalah:** Seluruh model `Project` (bersama `user` dan `teamMembers`) dihantar sebagai props Inertia. Data ini termasuk e-mel dan username pelajar, e-mel dan telefon ahli pasukan, `supervisor_email`, `supervisor_phone` dan `admin_comments`. Semuanya boleh dibaca dalam atribut `data-page` pada HTML, walaupun skrin hanya memaparkan nama.

**Cadangan:** hantar hanya medan yang perlu dipaparkan.

```php
public function verify(string $hash): Response
{
    $project = Project::where('certificate_hash', $hash)
        ->where('status', Project::STATUS_APPROVED)
        ->with(['user:id,name', 'category:id,name,code', 'session:id,name', 'teamMembers:id,project_id,name'])
        ->first();

    return Inertia::render('certificate/Verify', [
        'verified' => (bool) $project,
        'hash' => $hash,
        'project' => $project ? [
            'title' => $project->title,
            'pcode' => $project->pcode,
            'award_level' => $project->session?->r2_locked ? $project->award_level : null,
            'user' => ['name' => $project->user?->name],
            'category' => ['name' => $project->category?->name, 'code' => $project->category?->code],
            'session' => ['name' => $project->session?->name],
            'team_members' => $project->teamMembers->map(fn ($m) => ['name' => $m->name])->values(),
        ] : null,
    ]);
}
```

Selepas itu, kemas kini jenis TypeScript dalam `resources/js/pages/certificate/Verify.tsx` supaya sepadan.

**Ujian:** tambah ujian yang memastikan respons halaman verify tidak mengandungi `@` daripada e-mel pelajar, ahli pasukan atau penyelia.

### 2. Keputusan bocor sebelum dimuktamadkan

Keputusan dianggap rasmi hanya selepas `r2_locked = true`. Sebelum itu, tiada apa-apa maklumat ranking atau anugerah patut didedahkan kepada umum.

| Lokasi | Masalah | Pembetulan |
|---|---|---|
| `LeaderboardController::index` (medan `award_level`) | `award_level` sentiasa dihantar ke leaderboard awam dan ke `?api=true` | Hantar `award_level` hanya jika `$isAuthenticatedLeaderboard \|\| $isPublicResultsPublished` |
| `WelcomeController::__invoke` (`rankings`) | Top 3 projek mengikut markah dipaparkan di laman utama sepanjang masa | Kembalikan `rankings => []` selagi `! $session->r2_locked` |
| `CertificateController::downloadAchievement` | Pelajar boleh memuat turun sijil pencapaian sebelum keputusan diumumkan | Tambah semakan `if (! $project->session?->r2_locked && ! $user->hasRole('admin')) abort(403);` |

**Ujian:** kembangkan `tests/Feature/PublicLeaderboardPrivacyTest.php` supaya menyemak `award_level` dan `rankings` sebelum dan selepas keputusan dikunci.

---

## P1: Integriti pertandingan

### 3. Semua peranan boleh mendaftar projek, menyebabkan konflik kepentingan

**Fail:** `routes/web.php`, `ProjectController`, `JudgeAssignmentController`

**Masalah:**
- Route `projects.*` hanya memerlukan `auth`. Pensyarah, juri dan admin boleh mendaftar projek tanpa had.
- Pensyarah boleh menetapkan `supervisor_email` kepada e-mel sendiri, kemudian meluluskan projek itu sendiri.
- Penugasan juri tidak menyemak sama ada juri itu pemilik projek atau penyelianya.

**Cadangan:**

1. Hadkan route projek peserta kepada peranan `user`:
   ```php
   Route::middleware(['auth', 'verified', 'role:user'])->prefix('dashboard')->group(function () {
       Route::resource('projects', ProjectController::class)->except(['create', 'show', 'edit']);
       Route::post('projects/{project}/submit', [ProjectController::class, 'submit'])->name('projects.submit');
   });
   ```
   Laluan sijil dan notifikasi kekal dalam kumpulan `auth` biasa.
2. Buang syarat `if ($user->role?->name === 'user')` dalam `ProjectController::store`, supaya had satu projek setiap sesi dikenakan tanpa pengecualian.
3. Dalam `ProjectApprovalController::approve`, `reject` dan `cancel`, tolak tindakan jika `$project->user_id === auth()->id()`.
4. Dalam `JudgeAssignmentController::store`, tambah semakan konflik:
   ```php
   if ($project->user_id === $judgeId
       || strcasecmp((string) $project->supervisor_email, (string) $user->email) === 0) {
       return back()->with('error', 'Juri ini mempunyai konflik kepentingan dengan projek tersebut.');
   }
   ```

### 4. Memadam sesi atau kategori memadam semua data berkaitan

**Masalah:** FK `projects`, `scores` dan `judge_assignments` menggunakan `cascadeOnDelete`. Memadam sesi lama atau kategori akan memadam kekal semua projek, markah dan penugasan di bawahnya.

**Cadangan:**
- `CompetitionSessionController::destroy`: tolak pemadaman jika sesi masih ada projek.
  ```php
  if (Project::withTrashed()->where('session_id', $session->id)->exists()) {
      // flash error: 'Sesi ini mempunyai projek. Arkibkan sahaja, jangan padam.'
      return redirect()->route('admin.sessions.index');
  }
  ```
- `CategoryController::destroy`: buat semakan yang sama untuk `category_id`.
- Dalam jangka panjang, tukar FK tersebut kepada `restrictOnDelete()` melalui migration baharu, dan tambah `SoftDeletes` pada `Category` dan `CompetitionSession`.
- Rekod setiap pemadaman sesi atau kategori dalam `AuditLogger`.

### 5. E-mel dan notifikasi tidak sampai di production

**Masalah:**
- `.env` production menetapkan `MAIL_MAILER=log`. Peringatan kepada juri, reset kata laluan dan pengesahan e-mel hanya ditulis ke log, tidak dihantar.
- `SystemNotification` menggunakan `ShouldQueue` dengan `QUEUE_CONNECTION=database`. Notifikasi hanya akan dihantar jika ada `queue:work` yang sedang berjalan.

**Cadangan:**
- [ ] Tetapkan konfigurasi SMTP UTeM dalam `.env` (`MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_FROM_ADDRESS` dan lain-lain).
- [ ] Jalankan `php artisan queue:work --tries=3` sebagai Windows Service (NSSM) atau Task Scheduler, dan pastikan ia dimulakan semula selepas reboot.
- [ ] Untuk `JudgeNudgeMail`, gunakan `Mail::to(...)->queue(...)` supaya permintaan HTTP tidak tergantung jika SMTP lambat.
- [ ] Semak jadual `failed_jobs` secara berkala.

### 6. Pengesahan e-mel tidak berkuat kuasa

**Masalah:** Ciri `Features::emailVerification()` diaktifkan dalam Fortify, tetapi model `User` tidak melaksanakan `MustVerifyEmail`. Akibatnya, middleware `verified` membenarkan semua pengguna.

**Cadangan:** selepas item #5 selesai (e-mel benar-benar dihantar):
```php
use Illuminate\Contracts\Auth\MustVerifyEmail;

class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
```
Kemudian tandakan semua pengguna sedia ada sebagai sudah disahkan dengan `UPDATE users SET email_verified_at = NOW() WHERE email_verified_at IS NULL`, supaya mereka tidak terkunci.

### 7. Markah Round 1 boleh diubah selepas senarai pendek dibentuk

**Masalah:** Tiada kunci untuk Round 1. Juri boleh mengubah markah R1 selepas senarai pendek R2 dibentuk, dan ini boleh mengubah senarai pendek serta membatalkan penugasan atau anugerah R2.

**Cadangan:**
- Tambah kolum `r1_locked` (boolean) pada `competition_sessions`.
- Dalam `JudgeController::store`, tolak jika `$roundNo === 1 && $activeSession->r1_locked`.
- Kunci R1 secara automatik apabila penugasan R2 pertama dibuat, atau sediakan butang "Muktamadkan Round 1" di halaman Round 2.
- Rekod tindakan kunci ini dalam audit log.

### 8. Kedudukan leaderboard mencampurkan markah R1 dan R2

**Masalah:** `final_score` mengambil markah R2 jika ada, dan R1 jika tiada. Projek yang tidak tersenarai pendek tetapi mendapat markah R1 90 akan berada di atas finalis yang mendapat markah R2 70.

**Cadangan:** susun mengikut dua kunci. Projek yang ada markah R2 (finalis) sentiasa di atas dan disusun mengikut `avg_r2`. Projek lain disusun selepasnya mengikut `avg_r1`.
```php
$catProjects = $catProjects->sortBy([
    fn ($a, $b) => ($b['judges_r2'] > 0) <=> ($a['judges_r2'] > 0),
    fn ($a, $b) => $b['final_score'] <=> $a['final_score'],
]);
```
Gunakan logik yang sama dalam `WelcomeController`.

### 9. `r2_locked` boleh ditukar tanpa audit log

**Masalah:** `r2_locked` boleh diubah melalui borang sesi dalam `CompetitionSessionController::store` dan `update`, dan perubahan ini tidak direkod dalam audit log.

**Cadangan:** buang `r2_locked` daripada validasi dan borang sesi. Satu-satunya cara untuk menukarnya ialah melalui `Round2Controller::lockToggle`, yang sudah merekodkan audit log.

---

## P2: Keselamatan tambahan dan kestabilan

### 10. Senarai pelajar didedahkan kepada pensyarah

**Masalah:** `ProjectApprovalController::index` menghantar `students` (nama dan e-mel semua pelajar) kepada pensyarah, walaupun hanya admin boleh menggunakan `storeProject`.

**Cadangan:**
```php
'students' => $user->hasRole('admin')
    ? User::withRole('user')->orderBy('name')->get(['id', 'name', 'email'])
    : [],
```

### 11. Role dilindungi berdasarkan ID, bukan nama

**Masalah:** `RoleController` melindungi role menggunakan ID `[1, 2, 3, 4]`. Jika ID berbeza di sesuatu persekitaran, role `admin` boleh dipadam atau dinamakan semula.

**Cadangan:** lindungi berdasarkan nama.
```php
private const PROTECTED = ['admin', 'lecturer', 'judge', 'user'];
$isProtected = in_array($role->name, self::PROTECTED, true);
```
Tolak juga pemadaman jika `$role->users()->exists()`.

### 12. Kod QR sijil bergantung pada servis luar

**Masalah:** QR dijana melalui `api.qrserver.com` menggunakan `file_get_contents` tanpa timeout. URL pengesahan dihantar kepada pihak ketiga, dan muat turun sijil boleh tergantung jika servis itu lambat.

**Cadangan:** gunakan `bacon/bacon-qr-code`, yang sudah ada dalam `vendor`. Jadikannya kebergantungan langsung dengan `composer require bacon/bacon-qr-code`.
```php
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

$svg = (new Writer(new ImageRenderer(new RendererStyle(120), new SvgImageBackEnd())))->writeString($verifyUrl);
$qrCodeData = base64_encode($svg); // dalam Blade: data:image/svg+xml;base64,...
```
Gabungkan juga kod `downloadParticipation` dan `downloadAchievement` yang hampir sama ke dalam satu method `renderCertificate()` private.

### 13. Mesej ralat dalaman dipaparkan kepada pengguna

**Masalah:** `JudgeAssignmentController::store` memaparkan `$e->getMessage()` kepada pengguna.

**Cadangan:** gantikan dengan mesej umum, dan rekod butiran ralat menggunakan `report($e)`.

### 14. Validasi markah juri tidak lengkap

**Masalah dan cadangan dalam `JudgeController::store`:**
- `comments` tiada had panjang: tukar kepada `'nullable|string|max:5000'`.
- Jika `scores` dihantar bukan sebagai array, fungsi validasi akan crash (500). Tambah `'bail'` sebagai peraturan pertama dalam `$scoreRules`.
- Juri boleh menilai projek yang sudah dibatalkan atau dipadam selepas ditugaskan. Tambah semakan `$project->status === Project::STATUS_APPROVED` dalam `show` dan `store`.

### 15. Validasi lain

- `ProjectApprovalController::storeProject`: pastikan `user_id` milik pengguna dengan role `user`:
  ```php
  'user_id' => ['required', Rule::exists('users', 'id')->where(fn ($q) => $q->whereIn('role_id', Role::where('name', 'user')->pluck('id')))],
  ```
- Perbandingan `supervisor_email` sensitif huruf besar/kecil. Simpan `supervisor_email` dalam huruf kecil (`Str::lower`) semasa store dan update, dan bandingkan menggunakan huruf kecil.
- `abstract`: tambah had, contohnya `max:5000`.

### 16. Log production terlalu terperinci

**Cadangan:** tetapkan `LOG_LEVEL=warning` dalam `.env` production, dan tukar `LOG_STACK=daily` dengan `LOG_DAILY_DAYS=30` supaya fail log tidak membesar tanpa had.

---

## P2: Prestasi

| Lokasi | Masalah | Cadangan |
|---|---|---|
| `JudgeAssignmentController` (`index` dan `store`) | Semakan "Round 1 complete" menjalankan satu query `Score::exists()` bagi setiap penugasan | Gunakan satu query, contohnya `JudgeAssignment::where(...round 1)->whereNotExists(fn ($q) => $q->from('scores')->whereColumn(...))->exists()`, dan pindahkan ke `ScoreCalculator::isRoundOneComplete()` |
| `RubricController::hasScoresForRubric` | Memuatkan semua skor ke memori | Gunakan `whereJsonContainsKey` atau simpan `rubric_id` dalam jadual `scores` (lebih bersih) |
| `HandleInertiaRequests::share` | Semua notifikasi yang belum dibaca dimuatkan pada setiap request | Hadkan kepada `->limit(20)` dan hantar `unread_count` secara berasingan. Gunakan `Inertia::optional()` atau lazy props |
| `DashboardController` (admin) | Tiga query bagi setiap kategori | Gunakan satu query `groupBy('category_id')` dengan `selectRaw('count(...)')` |
| `ScoreCalculator::getRound1Shortlist` | Mengambil semua kategori, bukan hanya kategori dalam sesi semasa; satu query bagi setiap kategori | Hadkan kepada kategori yang ada projek dalam sesi; boleh guna window function `ROW_NUMBER()` (MySQL 8) |
| `CompetitionSessionController::index` | `categories_count` sentiasa menggunakan `Category::count()` | Buang medan ini atau kira dengan betul |

**Cadangan tambahan:** simpan `rubric_id` dalam jadual `scores`. `JudgeController::rubricForScore` sekarang meneka rubrik daripada kunci `score_details`. Ini rapuh dan berat.

---

## P3: Kebersihan kod

- **Helper toast:** blok `if (class_exists(Inertia::class) && method_exists(Inertia::class, 'flash')) {...}` diulang lebih kurang 30 kali. Pindahkan ke satu trait atau helper, contohnya `$this->toast('success', $msg)` dalam `Controller` asas.
- **Form Request:** validasi projek diulang tiga kali (dalam `store`, `update` dan `updateProject` admin). Pindahkan ke `app/Http/Requests/ProjectRequest.php`.
- **Policy:** semakan pemilikan `$project->user_id !== Auth::id()` dan penapisan pensyarah (`supervisor_email`) boleh dipindahkan ke `ProjectPolicy` (`update`, `submit`, `delete`, `review`), dan dipanggil dengan `$this->authorize(...)`.
- **Status sebagai Enum:** tukar `STATUS_*` kepada `enum ProjectStatus: int` (PHP 8.1+).
- **Sesi aktif:** `CompetitionSession::where('is_active', true)->first()` diulang dalam hampir semua controller. Gunakan `CompetitionSession::active()` (scope atau helper statik), dengan cache untuk setiap request.
- **Route alias lama** (`/judge/evaluations` dan `changelog/clear-cache` di root): buang selepas semua pengguna menggunakan bundle frontend baharu.
- **Changelog:** gunakan penapis markdown yang selamat (atau escape HTML dahulu) sebelum `dangerouslySetInnerHTML` dalam `resources/js/pages/changelog/Index.tsx`.
- **Line endings:** fail bercampur CRLF dan LF. Tambah `.gitattributes` dengan `* text=auto eol=lf`.
- **Analisis statik:** jalankan `vendor/bin/pint` dan `vendor/bin/phpstan` di CI.

---

## Ujian yang perlu ditambah

- [ ] Halaman verify sijil tidak mengandungi e-mel atau nombor telefon.
- [ ] Leaderboard awam dan laman utama tidak mendedahkan `award_level` atau ranking sebelum `r2_locked`.
- [ ] Sijil pencapaian disekat sebelum `r2_locked`.
- [ ] Pensyarah atau juri tidak boleh mendaftar projek (403).
- [ ] Pensyarah tidak boleh meluluskan projek miliknya sendiri.
- [ ] Juri yang ada konflik kepentingan tidak boleh ditugaskan.
- [ ] Sesi atau kategori yang masih ada projek tidak boleh dipadam.
- [ ] Markah R1 tidak boleh diubah selepas `r1_locked`.
- [ ] Susunan leaderboard: finalis R2 sentiasa di atas projek yang hanya ada markah R1.

---

## Ringkasan pelan tindakan

| # | Item | Keutamaan | Anggaran usaha |
|---|---|---|---|
| 0 | Commit snapshot production, sediakan aliran deploy | Prasyarat | 0.5 hari |
| 1 | Kecilkan props halaman verify sijil | P0 | 1 jam |
| 2 | Sembunyikan anugerah dan ranking sebelum keputusan dikunci | P0 | 2 jam |
| 3 | Hadkan pendaftaran projek dan semakan konflik juri | P1 | 3 jam |
| 4 | Halang pemadaman sesi atau kategori yang ada data | P1 | 2 jam |
| 5 | Konfigurasi SMTP dan queue worker | P1 | 2 jam (bersama IT) |
| 6 | Aktifkan `MustVerifyEmail` | P1 | 1 jam |
| 7 | Kunci Round 1 | P1 | 3 jam |
| 8 | Betulkan susunan leaderboard | P1 | 1 jam |
| 9 | Keluarkan `r2_locked` daripada borang sesi | P1 | 30 minit |
| 10–16 | Pengerasan keselamatan dan validasi | P2 | 1 hari |
| — | Prestasi | P2 | 1 hari |
| — | Kebersihan kod | P3 | 2–3 hari (berperingkat) |
