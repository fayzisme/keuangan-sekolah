---
title: 'LAPORAN & HARDENING — laporan per siswa/kelas/periode + laporan tunggakan, security review, backup jalan, dokumentasi. DoD: laporan akurat + hardening selesai'
type: 'feature'
created: '2026-09-02'
status: 'done'
review_loop_iteration: 1
baseline_commit: 'NO_VCS'
context:
  - 'docs/ARCHITECTURE.md'
  - 'docs/CODING_GUIDELINES.md'
  - 'docs/SECURITY.md'
  - 'docs/DEVOPS.md'
  - 'docs/ADR/0005-multi-tenant-shared-db.md'
  - 'docs/ADR/0008-ledger-append-only.md'
  - 'docs/ADR/0009-money-integer-cents.md'
  - 'AGENTS.md'
  - '_bmad-output/implementation-artifacts/spec-m9-10-cash-payment.md'
  - '_bmad-output/implementation-artifacts/spec-m7-8-billing-invoices.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Milestone M9-10 selesai: pembayaran tunai end-to-end berjalan, ledger append-only, kuitansi. Milestone M11 menuntut **laporan keuangan siap pakai** (per siswa, per kelas, per periode, **tunggakan**) serta **hardening security & ops** (backup, dokumentasi, pengecekan SIAP produksi). MVP tujuan: sekolah pilot bisa gunakan sistem untuk tagih-bayar-laporan tanpa developer di tangan.

**Approach:**
1. **Report Service/Action** — `GenerateStudentReportAction`, `GenerateClassReportAction`, `GenerateArrearsReportAction` (tunggakan). Semua via Query + agregasi, bukan iterasi PHP berat.
2. **Laporan Tunggakan (Arrears)** — `sisa = sum(invoice.amount_cents) - sum(payment_invoice.allocated_cents where payment.status=SETTLED)` per siswa, filter `kelas_id`, `tahun_ajaran_id`, `bill_type_id`. Output: NIS, nama, total_tagihan, total_dibayar, sisa.
3. **Export CSV/JSON** (MVP). PDF/Excel **fast-follow** (stretch2).
3. **Security hardening checklist** — terapkan `SECURITY.md` §7 (security gate rilis): CSP header, HSTS, rate-limit API, secret management, audit log sampling.
4. **Backup verification** — script `backup.sh` jalan + test restore (dokumentasikan di DEVOPS.md).
5. **Dokumentasi user/admin** — README setup, API docs (OpenAPI via scramble), panduan deploy, runbook.
6. **Healthz & monitoring** — `/healthz` sudah ada; tambah Sentry DSN config (opsional), log structured JSON.

## Boundaries & Constraints

**Always:**
- `school_id` dari konteks sesi (ADR-0005).
- Laporan **hanya** data sekolah aktif (tenant scope).
- Nominal cents → format di frontend, backend return `*_cents`.
- Tunggakan = query agregasi DB, **tidak** loop PHP (performa).
- Export MVP = CSV (streaming), JSON. PDF/Excel = stretch2.

**Never:**
- Laporan tanpa tenant scope.
- Menghitung tunggakan di PHP (slow, rawan bug).
- Menyembunyikan error security di log.

## Tasks & Acceptance

**Execution:**
- [ ] Action: `GenerateStudentReportAction` (invoices + payments per murid).
- [ ] Action: `GenerateClassReportAction` (summary per kelas).
- [ ] Action: `GenerateArrearsReportAction` (tunggakan per siswa + filter kelas/tahun_ajaran).
- [ ] Controller: `ReportController` (index, download CSV, JSON).
- [ ] Routes: `/api/v1/reports/student`, `/reports/class`, `/reports/arrears`.
- [ ] Security hardening: CSP via nginx, HSTS via `https.conf.example`, rate-limit API, `SECURITY.md` §7 checklist di `Makefile`/`CI`.
- [ ] Backup verify: dokumentasikan test restore di `DEVOPS.md` + README.
- [ ] Docs: `README.md` (setup + API via scramble), `docs/RUNBOOK.md` (ops harian/mingguan/bulanan).
- [ ] Test: laporan akurat (tunggakan cocok manual), CSV export valid, security headers hadir di response.

**Definition of Done:**
- [ ] `GET /reports/arrears?kelas_id=&tahun_ajaran_id=` → CSV/JSON dengan kolom NIS,nama,total_tagihan,total_dibayar,sisa.
- [ ] Tunggakan cocok: manual hitung DB = output API.
- [ ] Security headers: `X-Frame-Options`, `Content-Security-Policy`, `Strict-Transport-Security` (nginx config).
- [ ] Rate-limit API layer hadir (nginx + Laravel throttle).
- [ ] Backup test restore terdokumentasi + script jalan.
- [ ] OpenAPI spec di-generate (`make api-generate`) tanpa error.
- [ ] Lint PHP 0 error.

## Spec Change Log

<!-- (belum ada review loopback) -->

## Design Notes

- Query tunggakan: pakai subquery `payments_invoice` join `payments` where `status=SETTLED` + sum allocated_cents. Group by student.
- `Makefile` target `reports-test` jalanin `php artisan reports:verify` (bisa manual) di CI.
- Hardening checklist di `Makefile` target `security-check` (cek header, rate-limit, secret).