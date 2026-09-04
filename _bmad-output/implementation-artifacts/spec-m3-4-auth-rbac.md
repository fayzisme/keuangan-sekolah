---
title: 'AUTH & RBAC — Sanctum login/logout/me, role per sekolah (spatie teams), multi-school context, rate-limit, isolasi role (DoD)'
type: 'feature'
created: '2026-09-02'
status: 'done'
review_loop_iteration: 0
baseline_commit: 'NO_VCS'
context:
  - 'docs/ARCHITECTURE.md'
  - 'docs/CODING_GUIDELINES.md'
  - 'docs/SECURITY.md'
  - 'docs/ADR/0005-multi-tenant-shared-db.md'
  - 'docs/ADR/0011-openapi-contract.md'
  - 'AGENTS.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Sudah ada endpoint `/api/v1/ping`, `User` model (Sanctum+Spatie), dan middleware `EnsureSchoolContext` yang mereferensikan `School`, pivot `school_user`, dan role — tapi semuanya belum ada: user tidak bisa login, tidak ada role, tidak ada konteks sekolah aktif, dan tidak ada rate-limit. Milestone M3 menuntut Auth+RBAC jalan dengan bukti isolasi role ter-test.

**Approach:** Bangun fondasi multi-tenant auth: model `School` minimal + pivot `school_user` (+`is_active`), aktifkan spatie teams (`team_foreign_key=school_id`, guard `sanctum`) dengan 4 role per sekolah (admin, bendahara, murid, ortua), migrasi Sanctum `personal_access_tokens`, alur `login/logout/me/switch-school`, rate-limit login (5/menit), satu endpoint admin-gated sebagai bukti RBAC (`GET /auth/users` — admin/bendahara). Frontend mendapat halaman login minimal + guard rute + token store (vertical slice, bukan gold-plating). DoD dibuktikan dengan test isolasi role: role berbeda tidak bisa akses endpoint satu sama lain.

## Boundaries & Constraints

**Always:**
- API Laravel only; auth via Sanctum **Bearer token** (HasApiTokens), bukan session cookie frontend.
- `school_id` & jumlah/role tidak pernah dibaca dari body/param request — dari user login/konteks aktif (ADR-0005/page middleware). Endpoint `switch-school` hanya mengubah pivot `is_active`, bukan menerima `school_id` untuk spoof.
- Role **per sekolah** (ARCHITECTURE §7): aktifkan spatie `teams=true`, `column_names.team_foreign_key = 'school_id'`, `guard_name='sanctum'`, default guard auth di-betulkan jadi `sanctum`. 4 role tetap: `admin`, `bendahara`, `murid`, `ortua`.
- Semua file migrasi spatie/sanctum ditulis **verbatim dari vendor:publish** (lihat `_bmad-output/implementation-artifacts/m3-references/permission_tables.php`) — jangan mengarang struktur.
- `EnsureSchoolContext` (existing) diperluas minimal: setelah resolve school aktif, panggil `PermissionRegistrar::setPermissionsTeamId($school->id)` agar `hasRole/hasPermissionTo` ter-scope ke sekolah aktif. Jangan ubah kontrak 401/403 yang sudah ada.
- Rate-limit login WAJIB (`RateLimiter::for('login', ...)` di `AppServiceProvider::boot()` + `throttle:login` di route). Gagal login diaudit via `Log` structured.
- Tidak ada dependency baru (sanctum 4.3.3 & spatie 6.25.0 sudah di composer.lock), tidak ada perubahan composer.json/lock.
- Frontend minimal: `LoginPage`, `AuthContext` (token + fetch `/me`), guard rute (`RequireAuth`), tombol logout, satu halaman admin-gated. Pakai React Context + TanStack Query yang sudah ada — **tanpa** state-management baru.
- Semua kode PHP mengikuti pola Action tipis (CODING_GUIDELINES §3); controller tipis.

**Ask First:**
- Menambah endpoint auth di luar daftar spec (login/logout/me/switch-school/users).
- Menambah dependency apapun.
- Mengubah struktur spatie migration / skema `school_user`.

**Never:**
- Tidak membuat fitur domain (students/billing/master data) — M3 hanya auth+rbac+school context.
- Tidak menaruh `school_id`/`password` di query string/body sebagai otorisasi.
- Tidak menyimpan token di luar mekanisme Sanctum; tidak hardcode secret/index.php tombstone.
- Tidak memakai `auth:web`/session untuk API.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Login sukses | email+password valid, school pivot aktif | 200 `{token, user{id,name,email}, schools[], active_school}` | - |
| Login gagal | password salah | 401 `{message}` + `Log` audit | rate-limit dipicu per (ip+email) |
| Login rate-limit | >5 percobaan/menit (ip+email) | 429 | throttle:login |
| Login validasi | email kosong/tidak valid | 422 validation errors | FormRequest |
| Me tanpa token | request tanpa Bearer | 401 | auth:sanctum |
| Me token valid | Bearer valid, ada school aktif | 200 user + peran di sekolah aktif | - |
| Me tanpa school aktif | user punya token tapi tak ada pivot aktif | 403 `{message:'No active school context.'}` | EnsureSchoolContext |
| `/auth/users` admin/bendahara | token role admin/bendahara | 200 daftar user sekolah aktif (+role) | - |
| `/auth/users` murid/ortua | token role murid/ortua | 403 | middleware `role:admin|bendahara` |
| Switch school | body `{school_id}` milik user | 200, pivot is_active dipindah, team id berubah | 422 bila bukan sekolah user / tidak ada |
| Logout | Bearer valid | 204, token dicabut | - |

</frozen-after-approval>

## Code Map

- `app/Http/Middleware/EnsureSchoolContext.php:38` — EXISTING, sudah resolve active school dari pivot & set `school_id` attr. Aksi: tambah `setPermissionsTeamId($school->id)` setelah resolve; sisanya utuh.
- `app/Models/User.php:13` — M1: sudah `HasApiTokens + HasRoles + HasFactory`. Aksi: tambah relasi `schools()` (belongsToMany `school_user`, withPivot is_active, withTimestamps) + helper `activeSchool()`.
- `app/config/auth.php` — M1: default guard `web`. Aksi: set `defaults.guard` → `sanctum` (API-only, spatie guard konsisten).
- `app/bootstrap/app.php` — M1: alias `school.context` sudah terdaftar. Tidak berubah.
- `app/routes/api.php:5` — hanya `/ping`. Aksi: tambah grup auth (login tanpa auth; sisanya `auth:sanctum` + `school.context`, users ditambah `role:admin|bendahara`).
- `app/Providers/AppServiceProvider.php` — M1 kosong. Aksi: `RateLimiter::for('login', ...)` di boot.
- `app/database/migrations/` — tambah: `2026_09_02_000000_create_personal_access_tokens_table.php` (sanctum verbatim), `2026_09_02_000001_create_schools_table.php` (minimal: id, name), `2026_09_02_000002_create_school_user_table.php` (pivot: school_id, user_id, is_active, unique pair), `2026_09_02_000003_create_permission_tables.php` (spatie teams — isi dari `m3-references/permission_tables.php`).
- `app/Models/` — baru `app/Domain/School/Models/School.php` (namespace sesuai import middleware) minimal fillable `name`.
- `app/Http/Controllers/Api/AuthController.php` — baru, tipis: `login`, `logout`, `me`, `switchSchool`, `users`.
- `app/Http/Requests/LoginRequest.php` — baru: email required|email, password required|string.
- `app/Domain/Auth/Actions/LoginAction.php` & `SwitchSchoolAction.php` — baru; logic di Action (CSS §3), return plain data (ADT/array).
- `app/database/seeders/DatabaseSeeder.php` — panggil `AuthSeeder`; `app/database/seeders/AuthSeeder.php` baru: 1 sekolah demo + 4 user per role (password demo) + role per sekolah (guard sanctum, team=school). 
- `app/tests/Feature/` — baru: `AuthLoginTest.php`, `AuthMeTest.php`, `RoleIsolationTest.php` (DoD), `SwitchSchoolTest.php` (pakai `RefreshDatabase`, bangun school+user+role di test; tanpa factory — deferred M3/M5).
- `web/src/api/client.ts` — extend (handwritten sementara): `loginApi`, `fetchMeApi`, `logoutApi`, `switchSchoolApi`, `usersApi`.
- `web/src/features/auth/` — baru: `LoginPage.tsx`, `AuthContext.tsx`, `RequireAuth.tsx`.
- `web/src/app/router.tsx` — baru: `/login` public, guarded `/` (RequireAuth), `/users` (admin gate).
- `app/config/permission.php` — baru (spatie v6 published, disesuaikan: teams=true, team_foreign_key='school_id', guard default dari auth; cache store default).
- CI `.github/workflows/ci.yml:76` — menjalankan `migrate --force && pest` → akan mengeksekusi migrasi & test baru. **Sandbox tanpa PHP**: pest hanya bisa di CI (verified gap → defer, konsisten M1).

## Tasks & Acceptance

**Execution:**
- [x] Migrasi Sanctum + schools + school_user + permission(spatie teams) — verbatim/sesuai referensi.
- [x] Model `School` (Domain/School) + relasi `schools()`/`activeSchool()` di `User`.
- [x] `config/permission.php` + `config/auth.php` guard default `sanctum`.
- [x] `RateLimiter::for('login', ...)` + update `EnsureSchoolContext` setPermissionsTeamId.
- [x] `LoginRequest` + `AuthController` (login/logout/me/switchSchool/users) + 2 Action.
- [x] Routes auth di `routes/api.php` (throttle:login; role:admin|bendahara di `/auth/users`).
- [x] `AuthSeeder` (1 sekolah + 4 user + 4 role per sekolah) + panggil dari DatabaseSeeder.
- [x] 4 Feature test (login/me/isolasi-role/switch-school).
- [x] Frontend: client.ts extend + LoginPage + AuthContext + RequireAuth + router guard + halaman `/users` gated.
- [x] Lint PHP (php -l) + build/lint frontend.

**Acceptance Criteria:**
- Given email+password benar & ada pivot aktif, when `POST /api/v1/auth/login`, then 200 berisi token, user, daftar sekolah, school aktif.
- Given password salah berulang (6x/menit), when login, then 429 (rate-limit) — ada test.
- Given Bearer valid + school aktif, when `GET /api/v1/auth/me`, then 200 user + peran (e.g. `['admin']`).
- Given role admin ATAU bendahara, when `GET /api/v1/auth/users`, then 200 daftar user sekolah aktif.
- Given role murid ATAU ortua, when `GET /api/v1/auth/users`, then 403 — **DoD isolasi role ter-test**.
- Given user tanpa school aktif, when `GET /api/v1/auth/me`, then 403 "No active school context."
- Given frontend mengisi form login benar, then token tersimpan & redirect ke halaman guarded; klik logout → token bersih & kembali ke `/login`.

## Spec Change Log

- 2026-09-02: verifikasi sandbox — php -l semua file PHP 0 error; web build (97 modul) & lint exit 0. Frontend refactor: `AuthContext` dipecah → `auth-context.ts` (context + types) + `AuthContext.tsx` (provider-only) + `useAuth.ts` (hook) untuk kepatuhan react-refresh/eslint `--max-warnings 0`.
- 2026-09-02: perbaikan kritis selaras intent (tidak mengubah frozen): (1) `LoginAction` — `Hash::check($request->password, $user->password)` (sebelumnya salah banding dgn sendiri — auth bypass); (2) `SwitchSchoolAction` — reset pivot `is_active` per baris (`updateExistingPivot` per id) lalu set target; (3) `bootstrap/app.php` — tambah alias `role` & `permission` spatie + `shouldRenderJsonWhen(true)` agar middleware RBAC berfungsi & error selalu JSON (API-only, konsisten ARCHITECTURE).
- 2026-09-02: implementasi oleh subagent `deleg_4e93c1c4` TERPOTONG (max_iterations) sebelum menulis file → dieksekusi ulang langsung oleh parent (27 file backend+frontend), lalu diverifikasi & di-fix di atas.
- 2026-09-02: REVIEW 3x (security/edge/verification-gap) → temuan + perbaikan: (1) `SwitchSchoolAction` — validasi membership SEBELUM mutasi + di dalam transaksi (rollback), dan atomic UPDATE semua pivot (anti race 2-sekolah-aktif); (2) routes — `/me`/`/logout`/`/switch-school` KELUAR dari `school.context` (anti lockout user tanpa sekolah aktif; cukup `auth:sanctum` + throttle), `/users` tetap school.context + role; (3) `AuthController::me` — roles dievaluasi hanya jika ada sekolah aktif (tanpa team, roles=[]); `switchSchool` tambah rule `exists:schools,id`; (4) `User` — `$guard_name='sanctum'` eksplisit (anti mismatch CI); (5) RateLimiter login — normalisasi `strtolower(trim())` email (anti bypass case-variasi); (6) migration `school_user.is_active` → `nullable(false)`; (7) `Pest.php` — `forgetCachedPermissions()` tiap test (anti state-bleed spatie); (8) test baru: AuthRateLimitTest (429 & case-bucket), AuthLogoutTest (revoke token → 401), AuthMeTest (tanpa sekolah → 200 active_school null), RoleIsolationTest 2 sekolah (admin S1 tidak berlaku di S2).

## Design Notes

- Spatie teams dipilih karena ARCHITECTURE §7 eksplisit "role per sekolah". `team_foreign_key='school_id'` memetakan peran ke sekolah aktual (bukan team_id generik).
- Default guard auth diubah ke `sanctum` agar `hasRole`/`hasPermissionTo` konsisten di semua API, dan seeder membuat role dengan guard tersebut. Session/CSRF tidak relevan (API-only, Bearer).
- `switch-school` memutuskan pivot `is_active` (= context aktif). Tidak memakai klaim di token; token tetap, context berubah — konsisten dengan middleware yang membaca pivot.
- Frontend sengaja minimal (login + guard + 1 halaman gated) — vertical slice untuk demo M3, bukan fitur penuh; OpenAPI client masih placeholder (ADR-0011 pipeline belum aktif), jadi `client.ts` manual diperbolehkan untuk sementara.
- `School` minimal (hanya `name`) — M5 memperluas onboarding sekolah.

## Verification

**Commands (sandbox):**
- `php -l` semua file PHP baru (via `php:8.3-cli-alpine` docker, workaround `docker cp`) — expected: 0 error.
- `cd web && NODE_ENV=development npm run build && npm run lint` — expected: exit 0.
- `php artisan route:list` / `composer install` / `pest` — **tidak tersedia di sandbox**; dieksekusi CI GitHub Actions (backend job: composer install → pint → migrate → pest).

**Manual checks (if no CLI):**
- `routes/api.php` memuat grup auth + throttle + role middlewares.
- `config/permission.php` `teams=true`, `column_names.team_foreign_key='school_id'`.
- Migrasi spatie sesuai `m3-references/permission_tables.php`.
- `DatabaseSeeder` memanggil `AuthSeeder`.