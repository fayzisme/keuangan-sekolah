# M1 Fondasi Scaffold — Review Content (baseline NO_VCS)

Semua perubahan milestone M1 Fondasi (skeleton Laravel 12 di `app/`, React+Vite SPA di `web/`, tooling monorepo). Baseline = file skeleton lama di `app/Domain|Http|Infrastructure` + `app/Dockerfile` + compose + CI + docs — tidak diubah (kecuali 1 fix parse error).

## Files created (48)

### Backend Laravel 12 root (`app/`)
- `app/composer.json` — Laravel ^12, PHP ^8.3, deps: sanctum ^4, spatie/laravel-permission ^6, dedoc/scramble, pest ^3, pint. PSR-4 `"App\\": "./"` (root Laravel terletak di `app/`). Scripts: test/lint/format.
- `app/composer.lock` — di-generate via composer:2 container (344KB), untuk immutable build (Dockerfile stage vendor membutuhkan lock).
- `app/artisan` — entrypoint CLI Laravel.
- `app/bootstrap/app.php` — Laravel 12 `Application::configure`, routing web/api/commands, apiPrefix `api/v1`, alias middleware `school.context` → `EnsureSchoolContext`.
- `app/bootstrap/providers.php` — daftar provider `App\Providers\AppServiceProvider`.
- `app/Providers/AppServiceProvider.php` — kosong/minimal.
- `app/Models/User.php` — Sanctum HasApiTokens + Spatie HasRoles + HasFactory.
- `app/config/{app,database,services,scramble,cache,queue,session,auth}.php` — konfigurasi minimal + placeholder Midtrans.
- `app/routes/web.php` — `GET /healthz` → JSON {status, checks} cek DB+Cache (200/503).
- `app/routes/api.php` — `GET /ping` → JSON {status:"ok"} (prefix api/v1 dari bootstrap).
- `app/routes/console.php` — Schedule `queue:prune-batches --hours=48` daily.
- `app/database/migrations/0001_01_01_000000_create_users_table.php`, `...000001_create_cache_table.php`, `...000002_create_jobs_table.php` — default Laravel.
- `app/database/seeders/DatabaseSeeder.php` — kosong (seeder domain nanti).
- `app/tests/Pest.php` + `app/tests/TestCase.php` + `app/tests/Feature/HealthzTest.php` — test `GET /healthz` 200 & status 'ok'.
- `app/phpunit.xml` — bootstrap vendor/autoload, env testing (CACHE array, SESSION array, QUEUE sync), APP_KEY tetap.
- `app/pint.json` — preset laravel, exclude vendor/storage/bootstrap/cache.
- `app/.gitignore` — vendor, .env, storage/*, bootstrap/cache.

### Frontend React+Vite+TS (`web/`)
- `web/package.json` — React 18, Vite 6, TS 5.7, ESLint 9 flat config, Prettier, TanStack Query 5, react-router-dom 7, openapi-typescript. Scripts: dev/build(`tsc -b && vite build`)/lint/format/generate:api. Engines node >=20.
- `web/package-lock.json` — di-generate npm install.
- `web/{vite.config.ts, tsconfig.json, tsconfig.app.json, tsconfig.node.json, index.html, eslint.config.js, .prettierrc}` — config build/lint.
- `web/src/main.tsx` — React root + QueryClientProvider + RouterProvider.
- `web/src/app/router.tsx` — createBrowserRouter, route `/` → App.
- `web/src/app/App.tsx` — shell SPA minimal (hero card + format-money demo).
- `web/src/styles.css` — styling minimal.
- `web/src/lib/format-money.ts` — `formatRupiahFromCents` (Number.isInteger guard, Intl id-ID IDR).
- `web/src/api/README.md` — folder generated-client, jangan edit manual.
- `web/src/api/client.ts` — placeholder `pingApi()` typed manual (sementara, sebelum generate).
- `web/src/{features,hooks,components/ui}/.gitkeep` + `web/src/api/generated/.gitkeep` — struktur ARCHITECTURE §12.

### Monorepo tooling
- `Makefile` — targets: up/down/logs/test/lint/format/api-generate/web-install/web-build/web-lint/backend-validate.
- `scripts/generate-api-client.sh` — fail-soft jika `app/storage/app/openapi.json` belum ada; `openapi-typescript` generate `web/src/api/generated/schema.ts`.

## Files modified
- `app/Domain/Billing/Actions/CreateInvoicesAction.php` — fix parse error: ternary terpotong `is_null(...) ? []` tanpa cabang `:` → `... ?? []` (line 41-44). Satu-satunya modifikasi ke file skeleton lama.
- `AGENTS.md` — update Running/verifying: perintah `web/` + Makefile.
- `_bmad-output/implementation-artifacts/spec-m1-fondasi-scaffold.md` — spec (checklist [x]).

## Verification evidence
- `web`: `npm ci && npm run build` → OK (`dist/` 91 modul); `npm run lint` → 0 error. **Catatan sandbox:** `NODE_ENV=production` di environment menyebabkan `devDependencies` ter-skip — dibutuhkan `NODE_ENV=development npm install --include=dev` (lingkungan dev lokal normal tidak terpengaruh).
- PHP: `php -l` → 0 parse error dari 24 file (via `php:8.3-cli-alpine` container, docker cp workaround).
- `composer.lock`: di-generate via `composer:2` container (docker cp workaround karena bind-mount tidak melihat filesystem sandbox).
- **TIDAK terverifikasi:** `composer install`, `php artisan migrate`, `pest` — PHP CLI tidak tersedia di sandbox; butuh `docker compose up` milik owner.

## Known constraints / things to scrutinize
- `config/app.php` key `providers` = `[]` (Laravel 12 pakai `bootstrap/providers.php`).
- `bootstrap/app.php` health check dinonaktifkan (`health: null`) karena `/healthz` custom di routes/web.php.
- `web/vite.config.ts` proxy `/api` → `http://localhost:80`.
- Skeleton file lama mengandung typo (mis. "transaksionali", "alla") — hanya file yang parse-error yang difix, typo lain dibiarkan (defer).
- composer.lock content-hash vs future edits: setiap tambah dep butuh update lock.