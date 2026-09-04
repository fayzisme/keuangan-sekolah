# Deferred Work

Kumpulan temuan/tugas yang ditangguhkan dari milestone sebelumnya, untuk perhatian fokus di milestone berikutnya. Format mengikuti template BMad step-04.

## M1 → M3 (AUTH & RBAC)

- source_spec: `_bmad-output/implementation-artifacts/spec-m1-fondasi-scaffold.md`
  summary: Publikasikan migration & config Spatie permission (roles, permissions, model_has_roles, dll) + config/permission.php saat M3.
  evidence: `app/Models/User.php` memakai `HasRoles`, tapi tabel spatie belum ada di scaffold M1 (menunggu M3 Auth/RBAC).

- source_spec: `_bmad-output/implementation-artifacts/spec-m1-fondasi-scaffold.md`
  summary: Publikasikan migration Sanctum `personal_access_tokens` saat M3 Auth.
  evidence: `app/Models/User.php` memakai `HasApiTokens`, tabel token belum di-scaffold M1.

- source_spec: `_bmad-output/implementation-artifacts/spec-m1-fondasi-scaffold.md`
  summary: Buat `app/database/factories/UserFactory.php` saat M3/M5.
  evidence: `User` declarer `HasFactory`; seeder/tes data di M3/M5 butuh factory.

## M1 → umum (DEVOPS & SECURITY)

- source_spec: `_bmad-output/implementation-artifacts/spec-m1-fondasi-scaffold.md`
  summary: Tambah CI guard lock-checks: `composer validate --strict` + `composer install --dry-run` (backend) di pipeline agar lock↔composer.json selalu sinkron.
  evidence: Review verification-gap: lock hot-consistency hanya berupa warning teks, belum auto-enforced di CI.

- source_spec: `_bmad-output/implementation-artifacts/spec-m1-fondasi-scaffold.md`
  summary: Verifikasi E2E penuh (compose up + artisan migrate + pest + curl /healthz & /api/v1/ping) di environment ber-PHP — natural home: CI GitHub Actions saat PR.
  evidence: Sandbox tidak punya CLI PHP/compose-up; kredibilitas milestone bergantung CI (yang sudah menarget app/ & web/).

- source_spec: `_bmad-output/implementation-artifacts/spec-m1-fondasi-scaffold.md`
  summary: Pertimbangkan pindahkan `queue:prune-batches` scheduler agar hanya jalan di service worker (bukan scheduler satu-folder).
  evidence: Verification-gap review: 'queue:prune-batches' dijadwalkan tanpa eksposur queue-worker config; compose punya service worker & scheduler terpisah.

## M3 → M11 (LAPORAN & HARDENING)

- source_spec: `_bmad-output/implementation-artifacts/spec-m3-4-auth-rbac.md`
  summary: Respons XSS untuk token localStorage — terima risiko utk MVP (SPA bearer token), mitigasi harden di M11: CSP ketat, tanpa `dangerouslySetInnerHTML`, review XSS di semua titik render.
  evidence: Security review: token Sanctum di `localStorage` rentan XSS account-takeover; tradeoff umum SPA tanpa httpOnly-cookie.

- source_spec: `_bmad-output/implementation-artifacts/spec-m3-4-auth-rbac.md`
  summary: Keputusan konteks sekolah aktif berbasis pivot `school_user.is_active` (bukan per-token) → switch-school memengaruhi SEMUA sesi perangkat user. Terima utk MVP (sederhana, ADR-0005); opsi per-token diselidiki bila multidevice jadi kebutuhan.
  evidence: Security review: "global state confusion across sessions" — tradeoff desain yang disadari.

- source_spec: `_bmad-output/implementation-artifacts/spec-m3-4-auth-rbac.md`
  summary: LoginAction belum mengecek `email_verified_at` / status aktif akun (belum ada sistem email verifikasi). Tambah saat alur email/M-FA masuk.
  evidence: Security review: login hanya validasi email+password; akun tanpa sekolah tetap dapat token (lalu 403 di middleware).

- source_spec: `_bmad-output/implementation-artifacts/spec-m3-4-auth-rbac.md`
  summary: `/auth/users` N+1 `getRoleNames()` per user — optimasi eager-load / query roles ter-scope team saat daftar user sekolah membesar.
  evidence: Security review: mass query N+1 & konsistensi context role.

- source_spec: `_bmad-output/implementation-artifacts/spec-m3-4-auth-rbac.md`
  summary: Perilaku `setPermissionsTeamId` pada persistent workers (e.g. Octane). REJECT untuk saat ini — app TIDAK memakai Octane (`register_octane_reset_listener=false`, compose tanpa octane); hanya perlu diwaspadai bila worker persistent diperkenalkan.
  evidence: Edge-case review #7; diselidiki ulang saat arsitektur worker berubah.