---
title: 'TAGIHAN & INVOICE — jenis tagihan tipe_bayar (monthly/one_time, ADR-0015), generate invoice batch anti double-invoice, daftar per murid. Tarif dari master (DoD)'
type: 'feature'
created: '2026-09-02'
status: 'in-progress'
review_loop_iteration: 0
baseline_commit: 'NO_VCS'
context:
  - 'docs/ARCHITECTURE.md'
  - 'docs/CODING_GUIDELINES.md'
  - 'docs/SECURITY.md'
  - 'docs/ADR/0005-multi-tenant-shared-db.md'
  - 'docs/ADR/0009-money-integer-cents.md'
  - 'docs/ADR/0010-action-pattern.md'
  - 'docs/ADR/0015-tagihan-bulanan-bebas.md'
  - 'AGENTS.md'
  - '_bmad-output/implementation-artifacts/spec-m5-6-master-data.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Master data (M5) sudah ada: sekolah, tahun ajaran, kelas, murid, guardian. Milestone M7-8 menuntut mesin **tagihan & invoice** inti SPP berfungsi: jenis tagihan dengan `tipe_bayar` (bulanan vs bebas, ADR-0015), pembuatan batch invoice (anti double-invoice), dan daftar invoice per murid — semua ter-scope ke sekolah aktif.

**Approach:**
1. **`bill_types`** — CRUD per sekolah: `name`, `tipe_bayar` (`monthly`|`one_time`), `tarif_cents` (integer cents, ADR-0009), `is_active`. Unique `(school_id, name)` (exclude soft-deleted).
2. **`invoices`** — `school_id`, `student_id`, `bill_type_id`, `periode_bulan` (1–12; NULL untuk one_time), `periode_tahun`, `amount_cents`, `status` (OPEN/PARTIAL/PAID/VOID). Unique **anti double-invoice**:
   - monthly → `UNIQUE(school_id, student_id, bill_type_id, periode_bulan, periode_tahun)`
   - one_time → periode_bulan NULL → butuh `NULLS NOT DISTINCT` (Postgres 16) agar (school,student,bill_type,periode_tahun) tetap unik — karena secara default Postgres menganggap NULL berbeda.
3. **Generate batch** — `GenerateInvoicesAction`: tarif SELALU dari `bill_types.tarif_cents` (master), JANGAN dari request (DoD). Idempotent & race-safe via `createOrFirst` (anti double-invoice di level DB). Output `{generated, skipped}`. Scope siswa = aktif di sekolah aktif (option filter `student_ids`).
4. **Void invoice** — admin hanya bisa void invoice status `OPEN` (belum ada pembayaran — pembayaran baru ada di M9).
5. **Daftar per murid** — `GET /invoices` filter `student_id`, `bill_type_id`, `status`, `periode_tahun`, `periode_bulan`, `search` (nama murid), pagination.

## Boundaries & Constraints

**Always:**
- `school_id` dari konteks sesi (middleware `school.context`), TIDAK pernah dari body/param (ADR-0005).
- `amount_cents` invoice = `bill_types.tarif_cents` saat generate — input client TIDAK bisa memengaruhi nominal.
- Uang integer cents di semua kolom (ADR-0009).
- Mutasi via Domain Action (ADR-0010) — controller tipis.
- Read: `admin|bendahara`; write: `admin` (konsisten pola M5).
- Status invoice hanya OPEN/PARTIAL/PAID/VOID.
- Pagination `per_page` max 100, `simplePaginate`.
- Rewrite stub M1 `CreateInvoicesAction` (desain usang: `academic_year_id`+`period`) → model ADR-0015 (`periode_bulan`+`periode_tahun`). Stub `ProcessManualPaymentAction` di-keep untuk M9.

**Never:**
- Jangan terima nominal/tarif dari request body generate.
- Jangan generate invoice untuk siswa non-aktif atau luar sekolah aktif.
- Jangan void invoice berstatus selain OPEN (belum ada relasi payment di M7, tapi patuh aturan).
- Jangan tanpa unique DB (validasi request saja tidak cukup — race 2 request paralel).
- Jangan ubah schema invoice untuk M9 (payment) — M9 akan menambah kolom/pivot, bukan mengubah.

## Tasks & Acceptance

**Execution:**
- [ ] Migrasi `bill_types` + `invoices` (unique nullsNotDistinct utk one_time; index FK).
- [ ] Model `BillType` + `BillingInvoice` (+relasi student/billType/school).
- [ ] Actions: `CreateBillType`/`UpdateBillType`, `GenerateInvoicesAction` (rewrite stub), `VoidInvoiceAction`.
- [ ] Requests: `BillTypeRequest` (unique per school exclude soft-delete), `GenerateInvoicesRequest` (bill_type exists+scoped; periode_bulan wajib utk monthly, dilarang utk one_time; student_ids exists+scoped).
- [ ] Controllers: `BillTypeController` (CRUD), `InvoiceController` (index/show/generate/void).
- [ ] Routes `api.php` (grupp role, scoped).
- [ ] Tests: CRUD bill type + unique; generate monthly all-active; idempotent double-generate (anti double); one_time generate 2x; tarif dari master (body tak berpengaruh); filter student_ids; void hanya OPEN; isolasi tenant (siswa luar sekolah tidak ter-invoice; list invoice tidak bocor).

**Definition of Done:**
- [ ] `POST /bill-types` → 201; duplikat nama per sekolah → 422; tarif harus integer cents.
- [ ] `POST /invoices/generate` monthly untuk seluruh murid aktif → created = n, skipped = 0. Generate ulang periode sama → created = 0, skipped = n (anti double-invoice, idempotent).
- [ ] Nominal invoice = `tarif_cents` master; percobaan mengirim `amount_cents` di body TIDAK mengubah invoice.
- [ ] one_time: generate 2x periode_tahun sama → hanya 1 invoice per murid (NULLS NOT DISTINCT bekerja).
- [ ] `GET /invoices?student_id=` hanya murid sekolah aktif; akses invoice murid sekolah lain → 404.
- [ ] Void hanya invoice OPEN; void invoice PAID/VOID/asing → 404/409.
- [ ] Lint PHP 0 error.

## Spec Change Log

<!-- (belum ada review loopback) -->

## Design Notes

- `BillingInvoice` table `invoices` (nama mengikuti ARCHITECTURE §10). Model di `Domain/Billing`.
- `nullsNotDistinct()`: Laravel Blueprint mendukung pada Postgres 15+. Compose & CI memakai postgres:16-alpine → aman. Fallback bila tidak didukung: partial unique index; diverifikasi di implement.
- Periode validasi: `periode_tahun` integer 2000–2100, `periode_bulan` 1–12.
- `createOrFirst` (Laravel 10.47+) menangani unique violation dengan re-fetch → race-safe tanpa try/catch manual.