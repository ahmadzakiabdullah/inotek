# TODO: Pembetulan Segera INOTEK

Disediakan daripada semakan sistem pada 15 November 2026. Fail ini hanya untuk pembetulan segera. Roadmap jangka panjang kekal di [docs/todos.md](docs/todos.md), dan audit sebelum ini di [fix.md](fix.md).

**Petunjuk keutamaan:**
- **P0**: production sedang terjejas, atau pengguna mendapat maklumat yang salah. Betulkan dahulu.
- **P1**: betulkan sebelum pusingan penjurian seterusnya.

**Aliran kerja:**
- Buat satu branch, contohnya `fix/urgent-2026-11`.
- Jalankan ujian penuh pada MySQL setempat. Jangan sekali-kali pada DB production, kerana `RefreshDatabase` akan memadam data.
- Build frontend di mesin dev, kemudian salin `public/build` ke server (`assets` dahulu, `manifest.json` paling akhir). `npm run build` terus melalui `Z:` sangat perlahan dan pernah tersekat.
- Backup DB production sebelum `php artisan migrate --force`. Server tiada `mysqldump`; guna phpMyAdmin. Jalankan migrate sebagai langkah berasingan, hanya selepas backup disahkan.
- IIS menolak `PUT`/`PATCH`/`DELETE` (ralat 405). Frontend mesti menghantar `POST` dengan `_method`, atau guna route alias `POST`. Butiran di [docs/development.md](docs/development.md) seksyen 2.4.

---

## Status keseluruhan (dikemas kini 15 November 2026)

Semua item P0 dan P1 dalam fail ini sudah siap dan di-deploy ke production. Versi semasa: **v1.9.0**. Semua 197 ujian lulus.

| PR | Versi | Kandungan | Migration |
|---|---|---|---|
| #6 | v1.7.1 | P0 #1–#3: kolum lama `users`, toast, tarikh sesi | Ya (sudah dijalankan) |
| #7 | v1.7.1 | P1 #4–#13 | Tiada |
| #8 | v1.8.0 | Modul baharu: Laporan & Eksport (CSV/PDF), auto-assign juri (boleh on/off), maklum balas juri kepada peserta (tanpa nama), Analitik | Tiada |
| #9 | v1.9.0 | Juri R1 bagi sesuatu projek tidak boleh ditugaskan atau memberi markah projek yang sama di R2 | Tiada |
| #10 | v1.9.0 | Reka bentuk semula halaman Judge Assignments (ruang kerja ikut projek, carian, penapis, bulk assign) | Tiada |
| #11 | v1.9.0 | Ralat 405 semasa simpan draf projek: semua `PUT`/`DELETE` kini dihantar sebagai `POST` + `_method` | Tiada |

Kerja yang masih tinggal disenaraikan di bahagian [Seterusnya](#seterusnya) di hujung fail ini.

---

## Status P1 (dikemas kini 15 November 2026)

P1 #4–#13 siap dalam branch `fix/p1-2026-11`. Semua 177 ujian lulus (11 ujian baharu). Tiada migration.

| # | Item | Pembetulan |
|---|---|---|
| 4 | Kod projek bertembung | `ProjectCodeGenerator` kini menggunakan `withTrashed()`. |
| 5 | Projek/juri dipadam | Dashboard juri dan halaman penugasan admin mengabaikan penugasan bagi projek/juri yang dipadam. `isRoundOneComplete` juga mengabaikannya, jadi R2 tidak tersekat. Tiada data dipadam secara automatik. |
| 6 | Dashboard juri ranap | Kunci prop `assignments` dibetulkan. |
| 7 | Kuasa melalui e-mel | Pensyarah dan juri tidak boleh menukar e-mel sendiri; hanya admin (melalui Users). Medan e-mel di profil dijadikan baca-sahaja dengan penerangan. Konflik kepentingan disemak semula semasa juri menyimpan markah. `supervisor_id` jangka panjang masih belum dibuat. |
| 8 | Markah lapuk selepas buka R1 | Penugasan yang sudah ada markah tidak boleh dipadam. R1 hanya boleh dibuka semula sebelum ada markah R2, jadi markah dan anugerah R2 tidak boleh tertinggal. |
| 9 | Juri lihat markah juri lain | **Keputusan: markah sendiri sahaja.** Sebelum R2 dikunci, juri hanya nampak markah dan komen mereka sendiri di leaderboard; tiada purata, anugerah atau undian best presenter. Selepas dikunci, mereka nampak semua. |
| 10 | Data berlebihan ke borang juri | Hanya medan yang dipaparkan dihantar; tiada e-mel/telefon, `admin_comments`, `certificate_hash` atau `award_level`. |
| 11 | Ralat borang | Ralat `scores` dipaparkan di borang juri. Ralat `team_members.*` dipaparkan di borang peserta, beserta `aria-label`. |
| 12 | Butang Reject pada projek lulus | Dibuang. `aria-label` ditambah pada butang ikon. |
| 13 | Pengerasan kecil | Semakan sijil kini menyemak `r2_locked` dahulu. Finalize R2 menghantar keadaan sasaran. `ScoreUpdated` hanya menyiarkan `project_id`. Had 5 pendaftaran/jam setiap IP. Admin terakhir tidak boleh memadam akaun sendiri. |

## Status P0 (dikemas kini 15 November 2026)

P0 #1–#3 di-deploy ke production pada 15 Nov (PR #6). Migration dijalankan, dan pendaftaran disahkan berfungsi.

| # | Item | Status | Nota |
|---|---|---|---|
| 1 | Kolum lama `nama` | ✅ Kod siap | Migration `2026_11_15_000001_make_legacy_user_columns_nullable` menjadikan **semua** kolum lama NOT NULL tanpa default dalam `users` nullable. Kolum milik aplikasi tidak disentuh. Jenis, collation dan komen dikekalkan, dan tiada data diubah. Diuji pada DB simulasi dengan `nama`, `nokp`, `jantina`, `alamat`. |
| 2 | Mesej tidak dipaparkan | ✅ Kod siap | Helper baharu `backWithError()` dan `backWithSuccess()` dalam `Controller`. Toast pendua di 5 halaman sudah dibuang. Ujian ditambah. |
| 2b | **Ditemui semasa pembetulan:** helper `toast()` tidak pernah berfungsi | ✅ Kod siap | Semakan `method_exists(Inertia::class, 'flash')` pada facade sentiasa `false`, jadi **semua** toast `$this->toast()` (kategori, sesi, role, rubrik, pengguna, projek peserta, kelulusan) disimpan ke `session('toast')`, yang tidak dibaca oleh sesiapa. Kini memanggil `Inertia::flash` terus. |
| 3 | Tarikh sesi | ✅ Kod siap | Cast `date:Y-m-d`. Ujian ditambah. |
| — | Bonus (P1 #13, item kecil) | ✅ | Dropdown saiz shortlist R2 kembali ke nilai asal jika server menolak. |

**Langkah deploy:**
1. Backup DB production.
2. `git pull`.
3. `php artisan migrate --force`. Migration #1 menjalankan `ALTER TABLE users`.
4. `php artisan optimize`.
5. `npm run build` dari `Z:`.
6. Ujian manual:
   - daftar akaun baharu;
   - admin cipta pengguna;
   - tugaskan juri yang ada konflik kepentingan (mesti nampak toast merah);
   - edit sesi (tarikh mesti terisi dan betul).

---

## P0: Betulkan segera

### [x] 1. Pendaftaran pengguna gagal di production (kolum lama `nama`)

**Bukti:**
- `storage/logs/laravel-2026-11-13.log` mencatat 5 kali `SQLSTATE[HY000] 1364 Field 'nama' doesn't have a default value` pada `insert into users`.
- Jadual `users` di production masih mempunyai kolum sistem lama `nama` (NOT NULL, tiada default). Tiada migration yang mengendalikannya.

**Terjejas:**
- Pendaftaran awam melalui Fortify (`app/Actions/Fortify/CreateNewUser.php`).
- Admin cipta pengguna di [UserController.php:45](app/Http/Controllers/Admin/UserController.php#L45). Perlu disahkan, tetapi `INSERT` yang sama tanpa `nama` dijangka gagal juga.

**Langkah:**
1. Di production, jalankan `SHOW CREATE TABLE users;`. Senaraikan **semua** kolum NOT NULL yang tiada default dan tiada dalam `$fillable` model `User`, bukan `nama` sahaja.
2. Buat migration yang menjadikan kolum lama itu `nullable()`. Jaga-jaga supaya ia tidak gagal pada DB baharu: semak dahulu dengan `Schema::hasColumn('users', 'nama')`, kerana kolum ini tiada pada DB ujian.
3. Pilihan: isi `nama` = `name` untuk rekod sedia ada yang kosong. Fallback di [AppServiceProvider.php:46](app/Providers/AppServiceProvider.php#L46) masih membaca `nama`.
4. Siasat akaun "Hermes Probe" (`@fldubai.com`) dalam log. Kemungkinan ia bot atau ujian luar. Lihat juga P1 #13 (throttle pendaftaran).

**Ujian penerimaan:**
- Daftar akaun baharu di production berjaya.
- Admin boleh mencipta pengguna.
- Tiada lagi ralat 1364 dalam log.

---

### [x] 2. Mesej `back()->with('error' | 'success')` tidak pernah dipaparkan

**Masalah:**
- `HandleInertiaRequests` tidak berkongsi kunci `error`/`success`. [use-flash-toast.ts](resources/js/hooks/use-flash-toast.ts) hanya membaca `flash.toast`.
- Ralat yang dipulangkan tanpa validation errors menyebabkan Inertia memanggil `onSuccess`, jadi UI memaparkan "berjaya".

**Kesan:**
- Admin melihat "Judge assigned successfully!" ([assignments/Index.tsx:119](resources/js/pages/admin/assignments/Index.tsx#L119)) walaupun penugasan ditolak kerana konflik kepentingan, pendua, atau pusingan dikunci.
- Juri menyimpan markah selepas pusingan dikunci dan tidak melihat apa-apa mesej, jadi mereka menyangka markah sudah disimpan.

**Fail yang perlu ditukar kepada `$this->toast([...])`** (31 tempat):

| Fail | Bil. |
|---|---|
| `app/Http/Controllers/Admin/JudgeAssignmentController.php` | 15 |
| `app/Http/Controllers/Admin/Round2Controller.php` | 8 |
| `app/Http/Controllers/Judge/JudgeController.php` | 4 |
| `app/Http/Controllers/Admin/JudgingNudgeController.php` | 1 |
| `app/Http/Controllers/Admin/ProjectApprovalController.php` | 1 |
| `app/Http/Controllers/Admin/SystemSettingsController.php` | 1 |
| `app/Http/Controllers/ChangelogController.php` | 1 |
| `app/Http/Controllers/NotificationController.php` | 1 |

Senarai ini dijana dengan: `grep -rn "with('error'\|with('success'" app/Http/Controllers`.

**Langkah:**
1. Tukar setiap `->with('error', $msg)` kepada `$this->toast(['type' => 'error', 'message' => $msg])`, kemudian `return back()`.
2. Untuk ralat yang menghalang tindakan juri (pusingan dikunci), lebih baik guna `back()->withErrors([...])` supaya `onSuccess` tidak dipanggil.
3. Semak `onSuccess` dalam [assignments/Index.tsx](resources/js/pages/admin/assignments/Index.tsx), [round2/Index.tsx](resources/js/pages/admin/round2/Index.tsx) dan [EvaluationForm.tsx:257](resources/js/pages/judge/EvaluationForm.tsx#L257). Jangan paparkan mesej kejayaan sendiri jika server menghantar toast.

**Ujian penerimaan:** tambah feature test untuk menugaskan juri yang ada konflik kepentingan. Respons mesti mengandungi flash `toast` jenis `error`, dan tiada `JudgeAssignment` dicipta.

---

### [x] 3. Tarikh sesi dipaparkan sehari awal, dan borang edit kehilangan tarikh

**Masalah:**
- Cast `'date'` di [CompetitionSession.php:26-29](app/Models/CompetitionSession.php#L26-L29) disiri sebagai UTC, contohnya `2026-11-14T16:00:00.000000Z` untuk 15/11.
- `formatDateOnly` ([sessions/index.tsx:65-73](resources/js/pages/admin/sessions/index.tsx#L65-L73)) mengambil 10 aksara pertama, jadi tarikh dipaparkan sebagai **14/11**.
- `handleOpenEdit` ([sessions/index.tsx:148-151](resources/js/pages/admin/sessions/index.tsx#L148-L151)) memasukkan string ISO penuh ke `<input type="date">`, jadi medan itu kosong. Medan tersebut `required`, jadi admin terpaksa menaip semula semua tarikh.

**Langkah:**
1. Tukar cast kepada `'date:Y-m-d'` untuk keempat-empat kolum.
2. Cari tempat lain yang membaca tarikh sesi, contohnya dengan `grep -rn "session_starts_at\|registration_closes_at" resources/js`, dan pastikan semuanya menerima `Y-m-d`.
3. Semak semula logik "pendaftaran dibuka/ditutup" di backend. Perbandingan tarikh mesti dibuat dalam zon `Asia/Kuala_Lumpur`.

**Ujian penerimaan:**
- Sesi yang bermula pada 15/11 dipaparkan sebagai 15/11.
- Borang edit dibuka dengan tarikh terisi dan boleh disimpan tanpa ditaip semula.

---

## P1: Sebelum pusingan penjurian seterusnya

### [x] 4. Kod projek bertembung dengan projek yang telah dipadam (soft-delete)

**Masalah:**
- [ProjectCodeGenerator.php:22](app/Services/ProjectCodeGenerator.php#L22) menggunakan `Project::where(...)->exists()`. Skop SoftDeletes menyembunyikan rekod yang dipadam.
- Indeks unik `(session_id, pcode)` masih mengira rekod yang dipadam.
- Akibatnya, selepas satu projek ber-pcode dipadam, setiap submit atau approve dalam kategori itu gagal dengan ralat 500.

**Langkah:** tukar kepada `Project::withTrashed()->where(...)`.

**Ujian:** cipta projek dengan `ICT-01`, soft-delete projek itu, kemudian submit projek ICT baharu. Projek baharu mesti mendapat `ICT-02` tanpa ralat.

---

### [x] 5. Projek atau juri yang di-soft-delete merosakkan penjurian

**Masalah:**
- [JudgeController.php:66-69](app/Http/Controllers/Judge/JudgeController.php#L66-L69): `$assignment->project` bernilai null, jadi dashboard juri memulangkan 500.
- `ScoreCalculator::isRoundOneComplete` ([ScoreCalculator.php:133-146](app/Services/ScoreCalculator.php#L133-L146)) mengira penugasan untuk projek atau juri yang telah dipadam. Round 1 kekal "belum lengkap", jadi Round 2 tidak boleh ditugaskan.
- Halaman penugasan admin ([JudgeAssignmentController.php:56](app/Http/Controllers/Admin/JudgeAssignmentController.php#L56)) mungkin ranap kerana `project`/`judge` bernilai null. Perlu disahkan.

**Langkah:**
1. Apabila projek atau juri di-soft-delete, padam penugasan mereka yang **belum** bermarkah. Jika penugasan sudah ada markah, halang pemadaman.
2. Tambah `whereHas('project')` (dan `whereHas('judge')`) dalam `index` juri dan admin.
3. Dalam `isRoundOneComplete`, join dengan `projects`/`users` yang `deleted_at IS NULL`.

---

### [x] 6. Dashboard juri ranap jika tiada sesi aktif

[JudgeController.php:37-40](app/Http/Controllers/Judge/JudgeController.php#L37-L40) menghantar `'projects' => []`, tetapi [judge/Dashboard.tsx:49](resources/js/pages/judge/Dashboard.tsx#L49) membaca `assignments.length`.

**Langkah:** tukar kunci kepada `'assignments' => []`.

---

### [x] 7. Kuasa pensyarah bergantung pada `supervisor_email` (teks bebas)

**Masalah:**
- Pelajar boleh menaip e-mel mana-mana pensyarah dalam `supervisor_email`.
- Pensyarah boleh menukar e-mel sendiri di halaman profil tanpa pengesahan ([Settings/ProfileController.php:31-39](app/Http/Controllers/Settings/ProfileController.php#L31-L39)). Dengan itu, mereka boleh mendapat akses kepada projek pelajar lain dan meluluskan atau menolaknya ([ProjectApprovalController.php:330-348](app/Http/Controllers/Admin/ProjectApprovalController.php#L330-L348)).
- Ini juga melemahkan semakan konflik kepentingan juri, kerana semakan itu berasaskan e-mel.

**Langkah segera (kecil):**
1. Halang pengguna ber-role `lecturer` dan `judge` daripada menukar e-mel sendiri. Hanya admin boleh menukarnya.
2. Ulang semakan `ScoreCalculator::hasConflictOfInterest` dalam `JudgeController::store`, bukan hanya semasa penugasan.

**Langkah jangka panjang (keputusan reka bentuk):** tambah `supervisor_id` (FK ke `users`) yang disahkan oleh admin, atau padankan dengan akaun pensyarah yang wujud semasa submit.

---

### [x] 8. Buka semula Round 1 meninggalkan markah dan anugerah R2 yang lapuk

**Masalah:**
- `JudgeAssignmentController::destroy` ([JudgeAssignmentController.php:193-217](app/Http/Controllers/Admin/JudgeAssignmentController.php#L193-L217)) memadam penugasan tetapi tidak memadam markahnya.
- Projek yang terkeluar daripada shortlist kekal dengan markah R2 dan `award_level`. Ia masih disusun di atas leaderboard ([LeaderboardController.php:151-153](app/Http/Controllers/LeaderboardController.php#L151-L153)).

**Langkah:**
1. Halang pemadaman penugasan yang sudah ada markah, dengan mesej yang jelas. Jika admin benar-benar perlu memadamnya, padam markah sekali dan rekod dalam audit log.
2. Apabila Round 1 dibuka semula (semua penugasan R2 dipadam), kosongkan `award_level` untuk sesi itu.
3. Purata R2 di leaderboard mesti hanya mengira projek yang ada dalam shortlist semasa.

---

### [x] 9. Keputusan dasar: juri melihat markah juri lain sebelum keputusan diumumkan

[LeaderboardController.php](app/Http/Controllers/LeaderboardController.php) (sekitar baris 61-63 dan 195-246) memberi juri akses kepada nama, markah, komen dan undian best presenter juri lain semasa penjurian masih berjalan. Ini boleh mempengaruhi penilaian.

- [x] Dapatkan keputusan pihak urus setia. Keputusan: markah sendiri sahaja.
- [x] Tunjukkan hanya markah juri itu sendiri sehingga `r2_locked`.

---

### [x] 10. Borang penilaian juri menerima data projek yang berlebihan

[JudgeController.php:134](app/Http/Controllers/Judge/JudgeController.php#L134) memanggil `$project->load(['category','teamMembers','user'])` dan menghantar seluruh model. Ini termasuk:
- e-mel dan telefon peserta, ahli pasukan dan penyelia;
- `admin_comments`;
- `certificate_hash`;
- `award_level` sebelum diumumkan.

**Langkah:** hantar array eksplisit yang mengandungi medan dalam interface `Project` di [EvaluationForm.tsx:38](resources/js/pages/judge/EvaluationForm.tsx#L38) sahaja.

---

### [x] 11. Ralat markah dan ahli pasukan tidak dipaparkan dalam borang

- [EvaluationForm.tsx](resources/js/pages/judge/EvaluationForm.tsx) hanya memaparkan ralat `comments` dan `best_presenter`. Tambah paparan untuk `scores` dan `scores.*`.
- [participant/project/index.tsx:972-1050](resources/js/pages/participant/project/index.tsx#L972-L1050):
  - Tambah `InputError` untuk `team_members` dan `team_members.N.*`.
  - Tukar `key={idx}` kepada ID yang stabil.
  - Tambah `aria-label` pada butang padam.

---

### [x] 12. Butang "Reject" pada projek yang sudah diluluskan memulangkan 403

[approvals/index.tsx:925-940](resources/js/pages/admin/approvals/index.tsx#L925-L940) memaparkan butang ini untuk `status === 4`, tetapi `ProjectApprovalController::reject` ([ProjectApprovalController.php:226](app/Http/Controllers/Admin/ProjectApprovalController.php#L226)) hanya menerima projek berstatus SUBMITTED.

**Langkah:** sembunyikan butang untuk projek yang sudah diluluskan.

---

### [x] 13. Pengerasan kecil (cepat dibuat)

- [x] **Sijil pencapaian:** alihkan semakan `r2_locked` ke atas semakan `award_level` ([CertificateController.php:42-49](app/Http/Controllers/CertificateController.php#L42-L49)). Sekarang, respons yang berbeza (redirect atau 403) mendedahkan sama ada projek mendapat anugerah.
- [x] **Finalize R2:** `Round2Controller::lockToggle` ([Round2Controller.php:106](app/Http/Controllers/Admin/Round2Controller.php#L106)) perlu menerima keadaan sasaran yang eksplisit (`locked: true/false`), bukan toggle buta. Double-click sekarang akan mengunci, kemudian membuka semula.
- [x] **Siaran markah:** tambah `broadcastWith()` dalam [ScoreUpdated.php](app/Events/ScoreUpdated.php) yang memulangkan `['project_id' => …]` sahaja, sebelum broadcasting diaktifkan.
- [x] **Throttle:** tambah had kadar pada route pendaftaran Fortify.
- [x] **Admin terakhir:** halang admin terakhir daripada memadam akaun sendiri melalui halaman profil ([Settings/ProfileController.php:49](app/Http/Controllers/Settings/ProfileController.php#L49)).

---

## Selepas semua di atas

- [x] Jalankan ujian penuh pada MySQL setempat dan pastikan semua lulus. Ujian baharu ditambah untuk #1–#8 (kini 197 ujian, semua lulus).
- [x] Build frontend di mesin dev dan salin `public/build` ke server (bukan `npm run build` dari `Z:`).
- [x] Backup DB production (phpMyAdmin), kemudian `php artisan migrate --force`.
- [ ] Ujian manual di production:
  - [x] daftar akaun (disahkan 15 Nov);
  - [ ] simpan draf projek peserta selepas pembetulan 405 (nampak berjaya pada 15 Nov; semak `updated_at` projek 407 untuk pengesahan);
  - [ ] tugaskan juri yang ada konflik kepentingan (mesti nampak mesej ralat);
  - [ ] edit sesi (tarikh mesti terisi dan betul);
  - [ ] dashboard juri;
  - [ ] halaman Judge Assignments baharu: tambah/buang juri, bulk assign.
- [x] Kemas kini `docs/changelog.md` dan tandakan item dalam fail ini.

---

## Seterusnya

Tidak segera. Senarai yang sama dalam bahasa Inggeris ada di [docs/todos.md](docs/todos.md) seksyen 9 dan 11.

- [ ] Judge Assignments fasa 2: paparan "Ikut Juri" (satu baris setiap juri, dengan beban kerja dan projek yang ditugaskan).
- [ ] `supervisor_id` (FK ke `users`) yang disahkan, menggantikan `supervisor_email` teks bebas.
- [ ] Halang edit rubrik semasa pusingan sedang dinilai.
- [ ] Jana semula kod projek apabila projek yang dipulangkan bertukar kategori.
- [ ] Amaran seri pada had shortlist R2.
- [ ] Kekangan DB "satu projek setiap peserta setiap sesi".
- [x] SMTP (relay UTeM, 15 Nov) dan queue worker (tugasan berjadual "INOTEK Queue Worker", 15 Nov).
- [x] Halaman Email & Notifications (`/dashboard/email`) dan e-mel untuk notifikasi (v1.10.0, Fasa 1).
- [ ] Email & Notifications Fasa 2: template boleh disunting (`/dashboard/email/templates`) dan log e-mel (`/dashboard/email/logs`).
- [ ] `MustVerifyEmail`: tandakan akaun sedia ada sebagai disahkan dahulu, kemudian aktifkan.
- [ ] Buang baris extension lapuk dalam `php.ini` server (`php_gd2.dll`, `php_xmlrpc.dll`) yang menyebabkan amaran semasa PHP bermula.
- [ ] Aktifkan semula peraturan ESLint (`exhaustive-deps`, `no-unused-vars`) dan bersihkan 33 ralat `tsc` sedia ada.
- [ ] Ralat CORS daripada firewall Fortinet (`cookiesession…`, `about:srcdoc`) semasa simpan projek: nampaknya tidak menghalang simpanan. Jika ia mula menyekat, minta PTM semak polisi FortiWeb untuk `/dashboard/*`.
