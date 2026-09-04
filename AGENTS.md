<!-- bmad:context -->
<!-- Verified 2026-08-28 against <no git history yet — set on first commit>. Managed by bmad-project-context; edits inside this block are replaced on refresh. Keep anything you want preserved outside the markers. -->

## school-finance-system

Platform keuangan sekolah (multi-school SaaS): tagihan, pembayaran, pembukuan. Stack: Laravel API + React/Vite SPA, PostgreSQL + Redis, Docker Compose. Planning & dokumen arsitektur/keamanan/ops di `docs/`; artefak BMad di `_bmad-output/`.

## Policy

- Patuh metod BMad: untuk perubahan kode jalankan `bmad-build`; artefak planning di `_bmad-output/planning-artifacts/`, artefak implementasi di `_bmad-output/implementation-artifacts/`.
- Dilarang commit `.env`/secret ke git; sebelum `docker compose` dulu `cp .env.example .env` + set `DB_PASSWORD` (`.env` di-gitignore).
- Uang selalu integer cents (kolom `*_cents`), tidak pernah float (ADR-0009).
- Mutasi data keuangan sempre lewat Domain Action → audit log; ledger append-only, tidak diedit/dihapus (ADR-0008).
- Rilis wajib lolos security gate `SECURITY.md` §7.
- `school_id` & jumlah pembayaran tidak pernah dibaca dari request — dari konteks sesion & response gateway (ADR-0005, ADR-0013).
- Larangan stack: ❌ Next.js sebagai backend, ❌ CodeIgniter — API Laravel hanya (ADR-0002).

## Where things are

- Arsitektur/ADR/keamanan/ops/guideline/rencana: `docs/` — lihat `ARCHITECTURE.md` dulu, ada 14 ADR.
- Skeleton Laravel modular: `app/` (`Domain/.../Actions` = 1 class 1 use-case, thin `Http/Controllers`); **frontend scaffold `web/` (React+Vite) masih pending** — lihat `docs/ARCHITECTURE.md` §12.
- Deploy kit: `deploy/` (bootstrap.sh, backup.sh, nginx/), `docker-compose.yml`; CI `.github/workflows/ci.yml`.
- Artefak BMad: `_bmad-output/`.

## Running and verifying

- Migrasi DB: `docker compose run --rm app artisan migrate --force`.
- Test (Pest): `docker compose run --rm app vendor/bin/pest` — di-container, bukan bare `php artisan`.
- Lint backend: `vendor/bin/pint --test`.
- `composer audit` / `npm audit` exit non-zero even clean — CI guard dengan `|| true`; treat warning bukan stakan.
- Health: `curl -fsS <APP_URL>/healthz`.
- Frontend `web/`: `cd web && npm install && npm run dev` (dev) / `npm run build` (prod). Build hasil di `web/dist/` diserve nginx.
- Generik: `make up` / `make down` / `make test` / `make lint` / `make api-generate` (lihat Makefile root).

## Conventions that differ from defaults

- Laravel dibuilt modular, bukan `app/Controllers`-centric: business logic di `app/Domain/.../Actions`; controller tipis (validasi + panggil Action) — `CODING_GUIDELINES.md` §3.
- Semua model domain tenant-scoped: `school_id` dari konteks sesion via Global Scope, tidak pernah dari request input (ADR-0005).
- API versioned `/api/v1` JSON-only; client `web/` di-generate dari OpenAPI spec Laravel — tidak hand-maintain response shape (ADR-0011).
- Format uang display only lewat frontend util `lib/format-money`; backend selalu `*_cents`.

## Known pitfalls

- `composer audit` exit 1 dengan output "No known vulnerabilities" — bek awaited stakan, bukan gagal CI (observed session ini).
- Perintah artisan/product jalan di-container `docker compose run --rm app`; bare `php artisan`/`pest` jalan forat environment container-app (observed session ini).

<!-- /bmad:context -->