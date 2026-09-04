---
title: 'STRETCH-2: EXPORT PDF & EXCEL ADVANCE — Laporan per siswa, per kelas, dan tunggakan via Queue'
type: 'feature'
created: '2026-09-02'
status: 'done'
review_loop_iteration: 1
baseline_commit: 'NO_VCS'
context:
  - 'docs/ARCHITECTURE.md'
  - 'docs/CODING_GUIDELINES.md'
  - 'docs/ADR/0005-multi-tenant-shared-db.md'
  - 'docs/ADR/0012-async-queue.md'
  - 'AGENTS.md'
  - '_bmad-output/implementation-artifacts/spec-m11-reports-hardening.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Milestone M11 sudah memiliki laporan JSON dan CSV (streaming). Namun untuk kebutuhan cetak formal sekolah dan arsip bendahara/yayasan, dibutuhkan ekspor dokumen berformat PDF (layout resmi invoice/rekap) dan Excel (.xlsx dengan formula & formatting). Ekspor file besar/kompleks tidak boleh memblokir thread HTTP utama (ADR-0012) dan harus aman dari kebocoran tenant (ADR-0005).

**Approach:**
1. **Paket & Lib:** Gunakan `barryvdh/laravel-dompdf` untuk PDF dan `maatwebsite/excel` (atau generator native PHP/HTML-table-to-excel yang ringan) untuk XLSX.
2. **Ekspor On-Demand & Async:** 
   - **Direct Download (Small/Fast):** HTTP Response Stream untuk laporan kecil (misal PDF kuitansi/laporan 1 siswa).
   - **Queue Job (Large/Batch):** Endpoint `POST /api/v1/reports/export-jobs` memicu Queue Job (`GenerateReportExportJob`) -> menyimpan file di storage terisolasi per sekolah -> mengembalikan job status / download URL.
3. **Format & Styling:**
   - **PDF:** Header sekolah (nama, alamat), tabel rincian tagihan/pembayaran, total sisa, tanda tangan bendahara/kepala sekolah.
   - **Excel:** Kolom terstruktur, format angka/Rupiah, header terpisah.
4. **Isolasi Tenant:** File disimpan di path `storage/app/exports/{school_id}/{file_name}` dan hanya bisa diunduh oleh user dari sekolah yang sama via endpoint bertoken Sanctum.

## Boundaries & Constraints

**Always:**
- `school_id` dari konteks sesi (middleware `school.context`).
- File ekspor disimpan di folder khusus per sekolah (`storage/app/exports/{school_id}/`).
- RBAC: `admin|bendahara` yang boleh mengekspor laporan.
- Format file: PDF dan XLSX.

**Never:**
- Mengizinkan unduhan file dari `school_id` lain (IDOR di endpoint download file).
- Hardcode URL/path publik tanpa proteksi auth.

## Tasks & Acceptance

**Execution:**
- [ ] Install / scaffold helper export PDF (`barryvdh/laravel-dompdf` / View HTML PDF) & Excel (Spreadsheet/HTML export).
- [ ] View Blade template untuk PDF: `reports/pdf/student.blade.php`, `reports/pdf/class.blade.php`, `reports/pdf/arrears.blade.php`.
- [ ] Export Classes / Actions: `ExportStudentReportAction`, `ExportClassReportAction`, `ExportArrearsReportAction`.
- [ ] Queue Job: `GenerateReportExportJob` (menggenerasi file di background & update status).
- [ ] Controller & Routes: `GET /api/v1/reports/pdf/student/{id}`, `GET /api/v1/reports/pdf/arrears`, `GET /api/v1/reports/excel/arrears`, `GET /api/v1/exports/download/{filename}`.
- [ ] Tests: Export PDF mengembalikan `application/pdf`, Export Excel mengembalikan `.xlsx`/header spreadsheet, proteksi IDOR download file.

**Definition of Done:**
- [ ] Endpoint `GET /api/v1/reports/pdf/arrears` mengembalikan PDF laporan tunggakan valid.
- [ ] Endpoint `GET /api/v1/reports/excel/arrears` mengembalikan spreadsheet `.xlsx` valid.
- [ ] User dari sekolah B menembak URL download file sekolah A -> 403 / 404.
- [ ] Lint PHP 0 error & web build/lint tetap hijau.

## Spec Change Log

<!-- (kosong) -->

