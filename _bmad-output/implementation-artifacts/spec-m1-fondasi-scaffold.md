---
title: 'Scaffold M1 Fondasi — Monorepo Laravel + React SPA jalan'
type: 'chore'
created: '2026-09-02'
status: 'done'
review_loop_iteration: 0
baseline_commit: 'NO_VCS'
context:
  - 'docs/ARCHITECTURE.md'
  - 'docs/CODING_GUIDELINES.md'
  - 'docs/ADR/0011-openapi-contract.md'
  - 'AGENTS.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Repo belum bisa jalan sebagai aplikasi: `app/` hanya berisi 9 file Domain HTTP skeleton + Dockerfile (tidak ada `composer.json`, `artisan`, `bootstrap/`, `config/`, `routes/`, `tests/`, `Makefile`), dan `web/` (React SPA) belum ada sama sekali — padahal DoD M1 menuntut `make up` hidup, `/healthz` OK, dan pipeline OpenAPI → client jalan.

**Approach:** Scaffold `app/` menjadi root Laravel lengkap (tanpa mengubah 9 file skeleton yang sudah ada), scaffold `web/` sebagai React + Vite + TypeScript yang bisa `npm ci && npm run build`, tambah `Makefile` + script generate OpenAPI client + sisa konvensi monorepo, lalu verifikasi sejauh mungkin di lingkungan ini (Node tersedia; PHP/Composer tidak → verifikasi Laravel diserahkan ke owner via Docker/Makefile).

## Boundaries & Constraints

**Always:**
- Laravel API **hanya** (❌ Next.js backend, ❌ CodeIgniter — ADR-0002); `app/` adalah root Laravel (PSR-4 `App\`), `web/` SPA murni.
- 9 file skeleton yang sudah ada (`app/Domain/...`, `app/Http/...`, `app/Infrastructure/...`) **jangan diubah isinya**; hanya boleh ditambah file di sekitarnya.
- Semua output dokumen/komentar Bahasa Indonesia; kode mengikuti PSR-12/Pint.
- Uang `*_cents` integer (ADR-0009); `school_id` dari konteks sesi, bukan request (ADR-0005).
- Tidak ada secret di repo; `.env` di-`.gitignore`; pakai `.env.example` sebagai template.
- Deps backend versi direplikasi dari ekspektasi CI (`php 8.3`, Laravel 11/12, Pest, Pint, Sanctum, spatie/permission, dedoc/scramble) & Dockerfile (php 8.3-fpm).

**Ask First:**
- Menambah/mengganti dependency Laravel selain daftar inti di atas (harus disetujui).
- Mengubah konten 9 file skeleton yang sudah ada.

**Never:**
- Membangun fitur bisnis apa pun (database model lewat migration di luar tabel minimal scaffold `healthz`/sistem; masih M1).
- Menambahkan state management berat / routing tidak perlu di `web/` (cukup shell minimal + `api/` generated-client placeholder).
- Menjalankan `composer` lokal (tidak tersedia di environment) — jangan workaround yang merusak; siapkan agar owner bisa `composer install` di tempatnya.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Build frontend | `cd web && npm ci && npm run build` | `dist/` ter-generate tanpa error TS/ESLint | CI frontend gagal → PR merah |
| Health check backend | `curl /healthz` (via `make up`) | HTTP 200 JSON `{status:ok}` (DB+Redis+queue cek) | 500 → container unhealthy, `docker compose ps` merah |
| Generate API client | `make api-generate` (tanpa OpenAPI spec dulu) | Fail-soft dengan pesan "spec belum ada" | exit non-zero + hint, bukan crash |
| Install backend | `composer install` (milik owner, PHP tersedia) | `vendor/` terisi, autoload `App\` memuat 9 skeleton file | composer error → owner lapor, bukan asumsi kita |

</frozen-after-approval>

## Code Map

- `app/` -- ROOT Laravel (Dockerfile menyalin `app/` → `/var/www/html`; CI `working-directory: app`) — TIDAK punya composer.json/skeleton → wajib di-scaffold.
- `app/Domain/Billing/Actions/*.php` + `app/Domain/Student/Contracts/*` + `app/Http/*` + `app/Infrastructure/*` -- 9 file skeleton existing, PSR-4 `App\`, jangan diubah.
- `app/Dockerfile` -- stage `vendor` menyalin `app/composer.json` + `app/composer.lock` → composer.lock WAJIB ada di commit (immutable build).
- `docker-compose.yml` -- nginx serve `./web/dist` (volume), app/worker/scheduler dari image; healthcheck app cek postgres.
- `.github/workflows/ci.yml` -- job `backend` (working-dir `app`: Pint, composer audit, migrate+pest) & `frontend` (working-dir `web`: npm ci, lint, build, audit).
- `.env.example` -- template env (DB_PASSWORD wajib, dsb).
- `docs/ARCHITECTURE.md` §12 -- struktur SPA `web/src/{app,features,components/ui,api,hooks,lib}`.
- `docs/ADR/0011-openapi-contract.md` -- pipeline `dedoc/scramble` → OpenAPI → typed client React (awal: placeholder).

## Tasks & Acceptance

**Execution:**
- [x] `app/composer.json` -- manifest Laravel 12 + deps inti (framework, sanctum, spatie/permission, scramble, pest, pint) + scripts + PSR-4 `App\` -- fondasi backend.
- [x] `app/composer.lock` -- dibuat LEWAT Docker `composer:2 update` (bukan manual) -- immutable build (Dockerfile stage vendor butuh lock).
- [x] `app/artisan` + `app/bootstrap/app.php` + `app/bootstrap/providers.php` -- entrypoint Laravel 12 minimal (renderbaru singleton app, routing API, middleware EnsureSchoolContext ter-register sebagai alias).
- [x] `app/config/{app,database,services,scramble}.php` + `app/config/auth.php` -- konfigurasi minimal; scramble pakai mode dev.
- [x] `app/routes/web.php` + `app/routes/api.php` + `app/routes/console.php` -- route `/healthz` (web) + placeholder `GET /api/v1/ping` (api, tanpa auth) -- bukti lifecycle jalan.
- [x] `app/database/migrations/0001_01_01_000000_create_users_table.php` (default Laravel) + `app/database/seeders/DatabaseSeeder.php` -- DB bisa migrate di CI/test.
- [x] `app/tests/Pest.php` + `app/tests/TestCase.php` + `app/tests/Feature/HealthzTest.php` -- 1 test `GET /healthz` 200 -- CI pest jalan.
- [x] `app/phpunit.xml` + `app/pint.json` -- konfigurasi tooling CI.
- [x] `app/.gitignore` (bila perlu) -- vendor, .env, dsb (bundle di root .gitignore).
- [x] `web/package.json` -- React 18 + Vite + TS + ESLint + Prettier + TanStack Query (deps minimal) + scripts (`dev`, `build`, `lint`, `generate:api`) -- fondasi frontend.
- [x] `web/{vite.config.ts, tsconfig*.json, index.html, .eslintrc.cjs, .prettierrc}` -- config build/lint.
- [x] `web/src/main.tsx` + `web/src/app/App.tsx` + `web/src/app/router.tsx` -- shell SPA minimal (routing React Router, guard kosong) -- CI build.
- [x] `web/src/api/` -- placeholder generated-client (README menjelaskan di-generate dari OpenAPI, JANGAN edit manual) + `web/src/lib/format-money.ts` (format Rupiah dari cents, satu util) + `web/src/components/ui/.gitkeep` -- konvensi ARCHITECTURE §12.
- [x] `Makefile` -- target `up`, `down`, `test`, `lint`, `api-generate`, `format` -- DoD M1.
- [x] `scripts/generate-api-client.sh` -- panggil scramble/OpenAPI (sementara fail-soft jika spec belum ada) -- pipeline ADR-0011 placeholder.
- [x] `AGENTS.md` (`Running and verifying` TODO line) -- tambah Makefile & web commands -- konvensi baru tervalidasi.
- [x] `docs/` -- update ARCHITECTURE/CODING_GUIDELINES bila struktur web skematis (opsional, ringan).

**Acceptance Criteria:**
- Given repo di-checkout pada commit ini, when `cd web && npm ci && npm run build`, then `web/dist/` ter-generate tanpa error TS/ESLint.
- Given `make up` berjalan (owner, PHP via Docker), when `curl -fsS http://localhost/healthz`, then HTTP 200 `{"status":"ok"}`.
- Given PR dibuka, when CI backend & frontend jalan, then kedua job hijau (Pint, composer audit, pest; npm lint, build, audit).
- Given `make api-generate` dijalankan, when belum ada spec OpenAPI, then me-render pesan fail-soft "spec belum ada" tanpa crash.
- Given repo berisi 9 skeleton file existing, when migrasi ke Laravel 12 root, then semua file tetap kompatibel PSR-4 `App\` (tidak refactor isi).

## Spec Change Log

- `2026-09-02` (review loopback, patch findings): `web/src/lib/format-money.ts` diubah agar menerima `number | string` (numeric string dari JSON API) — temuan Blind Hunter: guard `Number.isInteger` menolak numeric string. Fungsi kini meng-coerce `Number(amountCents)` sebelum guard, tetap throw bila non-integer. Build + lint hijau (91 modul, 0 error).
- `2026-09-02` (review, defer): Spatie/Sanctum migrations + config/permission.php + UserFactory ditangguhkan ke M3 (AUTH & RBAC); CI lock-check + verifikasi E2E compose/migrate/pest ditangguhkan ke DevSec/CI — lihat `deferred-work.md`.
- `2026-09-02` (review, reject): klaim reviewer soal `EnsureSchoolContext` tidak ada / `.env.example` tidak ada / worker tidak ada — terbukti salah (file & service tersebut sudah ada di baseline). Di-drop tanpa tindakan.

## Design Notes

Laravel 12 dipilih (rilis stabil 2025, kompatibel PHP 8.3 di Dockerfile & CI). `app/composer.lock` Wajib di-commit sebagai artefak immutable (Dockerfile stage vendor). Di lingkungan ini PHP/Composer tidak tersedia → lock dibuat via `docker run --rm -v "app/:/app" -w /app composer:2 composer update` (hanya jika Docker tersedia & jaringan OK; jika gagal, owner menjalankan `composer update` sekali di lokal dan commit lock). Frontend minimal: TanStack Query terpasang tapi belum dipakai di komponen — siap untuk milestone AUTH/MASTER.

## Verification

**Commands:**
- `cd web && npm ci && npm run build` -- expected: exit 0, `dist/` ada
- `cd web && npm run lint` -- expected: exit 0
- `docker run --rm -v "$(pwd)/app:/app" -w /app composer:2 composer validate --no-check-publish` -- expected: valid (jika composer lock dibuat)
- `docker run --rm -v "$(pwd)/app:/app" -w /app composer:2 composer install --dry-run` -- expected: lock resolve (owner-side verification)

**Manual checks (if no CLI):**
- Struktur `app/` memiliki `artisan`, `bootstrap/`, `config/`, `routes/`, `database/migrations`, `tests/`.
- `web/src/` menampilkan folder `app|features|components/ui|api|hooks|lib`.
- 9 file skeleton existing tidak berubah (diff kosong untuk file itu).

## Suggested Review Order

**Backend entry point — bootstrap Laravel 12**

- Application::configure + middleware alias school.context — inti topology API
  [`bootstrap/app.php:9`](../../app/bootstrap/app.php#L9)

**Routing & health**

- Custom /healthz cek DB+Cache, 200/503
  [`routes/web.php:5`](../../app/routes/web.php#L5)

- Placeholder api/v1/ping bukti lifecycle
  [`routes/api.php:5`](../../app/routes/api.php#L5)

**Models & config**

- User memakai Sanctum+Spatie+Factory (defer M3)
  [`Models/User.php:13`](../../app/Models/User.php#L13)

- providers=[] & arena Scramble — konsisten Laravel 12
  [`config/app.php:18`](../../app/config/app.php#L18)

**Frontend shell & money invariant**

- formatRupiahFromCents menerima number|string — invariant cents (ADR-0009)
  [`web/src/lib/format-money.ts:1`](../../web/src/lib/format-money.ts#L1)

- Shell SPA + QueryClient/Router wiring
  [`web/src/app/App.tsx:3`](../../web/src/app/App.tsx#L3)

**Tooling & verification**

- Makefile targets untuk up/test/lint/api-generate
  [`Makefile:1`](../../Makefile#L1)

- Fail-soft OpenAPI client pipeline
  [`scripts/generate-api-client.sh:1`](../../scripts/generate-api-client.sh#L1)

- Healthz test (verification gap ke-3 ter-defer ke CI)
  [`tests/Feature/HealthzTest.php:3`](../../app/tests/Feature/HealthzTest.php#L3)

> Ctrl+click (Cmd+click on macOS) the links above to jump to each stop.