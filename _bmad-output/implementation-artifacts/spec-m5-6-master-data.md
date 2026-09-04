---
title: 'MASTER DATA — onboarding sekolah, tahun ajaran, kelas, murid, guardian. Tenant scope aktif (DoD isolasi tenant)'
type: 'feature'
created: '2026-09-02'
status: 'done'
review_loop_iteration: 1
baseline_commit: 'NO_VCS'
context:
  - 'docs/ARCHITECTURE.md'
  - 'docs/CODING_GUIDELINES.md'
  - 'docs/SECURITY.md'
  - 'docs/ADR/0005-multi-tenant-shared-db.md'
  - 'docs/ADR/0009-money-integer-cents.md'
  - 'docs/ADR/0010-action-pattern.md'
  - 'docs/ADR/0014-anti-over-engineering.md'
  - 'AGENTS.md'
  - '_bmad-output/implementation-artifacts/spec-m3-4-auth-rbac.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Milestone M3 sudah menghasilkan auth (Sanctum) + RBAC (spatie teams) + konteks sekolah aktif per user. Namun belum ada entitas bisnis apa pun: sekolah tidak bisa di-onboard (hanya via seeder demo), tidak ada tahun ajaran, kelas, murid, maupun guardian. Milestone M5-6 menuntut data master berfungsi penuh, **ter-scope ke konteks sekolah aktif** (ADR-0005), dengan bukti isolasi tenant ter-test.

**Approach:** Bangun resource master data dalam domain `School`/`Student`:
1. **Onboarding sekolah** — endpoint membuat sekolah baru + user admin + pivot `school_user` aktif + role `admin` di team sekolah tsb (jalur registrasi sekolah nyata; seeder demo tetap ada utk testing).
2. **CRUD tenant-scoped** — `academic_years`, `classes`, `students`, `guardians` (+pivot `student_guardian` many-to-many w/ `relation` & `is_primary`). Semua daftar/rinci/mutasi hanya di sekolah aktif (school_id dari konteks sesi, TIDAK dari body/param — ADR-0005); pencarian menyertakan pagination sederhana (`per_page`, `search`).
3. **RBAC** — `admin` boleh CRUD penuh; `bendahara` baca-saja (index/show); `murid`/`ortua` dilarang (sesuai matriks peran ARCHITECTURE). Guard via middleware `school.context` + `role:admin`/`role:admin|bendahara`.
4. **Konsistensi data** — unique per sekolah (nis, nama tahun ajaran+semester, nama kelas per tahun ajaran), soft delete `deleted_at` untuk masters (mencegah referensi invoice/history putus di M7+), relasi FK `cascade`/`nullOnDelete` yang aman.
5. **Tests isolasi tenant hijau** — murid sekolah A tidak terlihat dari sekolah B; admin B tidak bisa mengubah murid A (404/403); kedua tenant berjalan di satu DB.

## Boundaries & Constraints

**Always:**
- API Laravel only; semua endpoint di bawah `school.context` (kecuali onboarding yang bekerja di level platform).
- `school_id` TIDAK pernah diterima dari request body/param — selalu dari konteks sesi aktif (middleware `EnsureSchoolContext`), kecuali onboarding (sekolah baru belum ada konteks).
- Uang integer cents (tidak relevan di M5 — tidak ada kolom uang selain menjaga tidak ada float).
- Mutasi via Domain Action (ADR-0010); controller tipis. CRUD sederhana boleh 1 action per use-case: Create/Update/Delete per entitas.
- Soft delete untuk `students`, `guardians`, `classes`, `academic_years`, `schools`; hard delete TIDAK untuk entitas master yang bisa dirujuk invoice (students/classes) — alasan: integritas dengan M7 invoice.
- Penggunaan factory/prisma tidak wajib; data test via seeder + factory minimal.
- Pagination: pakai `simplePaginate` + `per_page` max 100.

**Never:**
- Jangan membaca `school_id` dari request; jangan expose endpoint yang mereturn data tanpa scope sekolah.
- Jangan menambah tabel tanpa migration rapi + index FK `school_id`.
- Jangan menulis test yang hanya memastikan 200 tanpa cek body/scope.
- Jangan menambah field yang tidak dipakai M7+ (anti over-engineering, ADR-0014).

## Tasks & Acceptance

**Execution:**
- [x] Migrasi: `academic_years`, `classes`, `students`, `guardians`, `student_guardian` — FK + unique per sekolah + soft deletes.
- [x] Model + relasi: `AcademicYear`, `ClassRoom`, `Student`, `Guardian` (+pivot relation/is_primary).
- [x] Actions: `OnboardSchoolAction` + Create/Update per entitas + `AttachGuardianAction` (10 action).
- [x] Controllers: Onboard, AcademicYear, Class, Student, Guardian — index(filter+page)/show/store/update/destroy.
- [x] Routes `api.php`: read `role:admin|bendahara`, write `role:admin`, onboarding `platform.key`; semua tenant-scoped.
- [x] FormRequests: validasi unique per sekolah (name+semester, class name per tahun, nis), exists dtype.
- [x] Middleware `EnsurePlatformKey` (X-Platform-Key header, 503 bila belum dikonfigurasi) + alias `platform.key`.
- [x] Tests: Onboard (3), AcademicYear (3), ClassRoom (2), Student (4), RBAC (2), TenantIsolation (2) + helper `makeScopedUser` di Pest.php.
- [x] Lint PHP (php -l 0 error) + build/lint frontend (halaman MasterData + client API).

**Definition of Done:**
- [x] `GET /api/v1/academic-years?search=&per_page=` → hanya data sekolah aktif, ter-BEARER-token (test).
- [x] `POST /api/v1/academic-years` → 201 + terlihat di index; duplikat nama+semester per sekolah → 422 (test).
- [x] `PUT/DELETE /api/v1/academic-years/{id}` dari sekolah lain → 404; dari sekolah yang sama → sukses soft (test).
- [x] `GET /api/v1/students` hanya murid sekolah aktif; `POST /api/v1/students/{id}/guardians` menautkan guardian; `DELETE` detach (test).
- [x] Test isolasi tenant hijau: dua sekolah di DB yang sama, cross-access ditolak (test TenantIsolationTest).
- [x] RBAC: bendahara index OK tapi store 403; murid semua 403 (test MasterDataRbacTest).
- [x] Lint PHP 0 error + build/lint frontend hijau.

## Spec Change Log

- 2026-09-02: implementasi penuh master data (migrasi 5 tabel, 4 model, 10 action, 6 request, 5 controller, routes, middleware platform.key).
- 2026-09-02: REVIEW 3 lensa (security/edge/verification-gap) — temuan + perbaikan: (1) **stale soft-delete → 500**: rule `unique` tidak meng-exclude `deleted_at`; hapus siswa lalu re-create NIS yang sama (atau hapus tahun ajaran/kelas lalu buat ulang nama sama) memicu ConstraintViolation, bukan 422. Fix: `whereNull('deleted_at')` di unique rules AcademicYear/Class/Student. (2) **cross-tenant guardian attach**: `exists:guardians,id` mengizinkan admin sekolah A menautkan guardian (name+no_hp — data pribadi) milik sekolah B ke murid A. Fix: rule tambahan di `AttachGuardianRequest` — guardian hanya boleh ditautkan jika orphan (tanpa student) ATAU sudah terhubung ke ≥1 student di sekolah aktif (kasus ortu 2 anak tetap valid). (3) **`PLATFORM_KEY` tidak terdokumentasi** di `.env.example` → fail-safe 503 sudah benar, tapi env example tidak lengkap. Fix: tambah kolom + catatan.
- 2026-09-02: bug lintas-M3/M5 yang dicegah — normalisasi email (lowercase+trim) di `LoginRequest::prepareForValidation` & `OnboardSchoolRequest` (email disimpan lowercase di OnboardSchoolAction; tanpa normalisasi, variasi kapital gagal login / lolos unique).
- Test baru ditambahkan untuk ketiga fix: `MasterDataEdgeFixTest` (reuse NIS setelah soft-delete 201, reuse nama tahun ajaran, tolak attach guardian sekolah lain 422, izinkan orphan, izinkan ortu 2 anak sekolah sama).

## Design Notes

- Onboarding sekolah = action `OnboardSchoolAction` (bukan controller tebal): buat school, user, role admin di team, pivot aktif — SATU transaksi, idempotent guard (cek email belum ada).
- `class` adalah kata kunci PHP → model `ClassRoom`, tabel `classes`, relasi `classRoom()`.
- Guardian many-to-many pivots: `relation` (ayah/ibu/wali) + `is_primary`. Satu guardian bisa punya >1 murid (ortua dengan 2 anak) — tanpa unique school_id di guardians (cross-school guardian dibolehkan tapi akses tetap lewat murid).