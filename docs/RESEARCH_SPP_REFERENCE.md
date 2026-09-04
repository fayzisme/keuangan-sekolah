# RESEARCH — Referensi Feature SPP: `fayzisme/spp-pembayaran`

| | |
|---|---|
| **Versi** | 1.0 |
| **Tanggal** | 2026-08-28 |
| **Repo diteliti** | https://github.com/fayzisme/spp-pembayaran |
| **Tujuan** | Menjadi referensi feature pembayaran SPP siswa → disempurnakan ke dalam desain `school-finance-system` |

---

## 1. Ringkasan Eksekutif

Repo `fayzisme/spp-pembayaran` adalah aplikasi **Laravel monolitik (single-school, blade views)** untuk
manajemen SPP & pembayaran sekolah. Bukan codebase yang akan di-copy — tetapi **fitur-fitur domainnya
sangat baik sebagai referensi** untuk menyempurnakan desain arsitektur kita (yang sudah multi-tenant,
API-first, dan berdisiplin arsitektur).

Hasil riset ini **tidak mengubah keputusan scope MVP yang sudah terkunci** (tunai + verifikasi +
kuitansi + laporan dasar). Riset menambah detail pada desain dan memindahkan beberapa fitur ke
fast-follow post-MVP.

---

## 2. Fitur yang Ditemukan di Repo

### 2.1 Model tagihan dua tipe — `tipe_bayar`
- `jenis_pembayaran` (master tagihan) punya `tipe_bayar`: **`Bulanan`** (berulang per bulan, mis. SPP)
  dan **`Bebas`** (satu-nilai, mis. uang gedung, uang kegiatan, uang seragam).
- `transaksi` = tagihan milik satu siswa utk satu jenis pembayaran (master; `total_bayar`, status Lunas/Belum Lunas).
- `detail_transaksi` = rincian per bulan (enum bulan Januari–Desember) untuk tipe Bulanan.

### 2.2 Alur pembayaran
- Tunai: `metode_transaksi = 'Tunai'` + mencatat **petugas** yang menerima.
- Online: **Midtrans Snap** (`snap_url` disimpan, token di `MidtransService`).
- `order_id` Midtrans bisa memuat **beberapa id detail_transaksi** sekaligus (bayar beberapa bulan
  dalam satu transaksi), dengan penanda `_BEBAS` bila tipe bebas.
- `notificationHandler` meng-parse `order_id` → update status tiap detail (Sukses/Pending/Gagal).
- `generateInvoice(id)` → **PDF invoice** per detail pembayaran (barryvdh/laravel-dompdf).

### 2.3 Laporan & ekspor
- Laporan pembayaran per jenis/bulan; `exportBulanan` (PDF) & `exportMonthly`/`export` (Excel via Maatwebsite).
- **Tunggakan**: `sisa_tanggungan = total_bayar - total_dibayar` per siswa, filter kelas & tahun
  ajaran, ekspor PDF/Excel.
- Filter laporan: kelas, tahun ajaran, jenis pembayaran.

### 2.4 Broadcast WhatsApp ke orang tua
- `WhatsappController` memanggil **API Fonnte** (`api.fonnte.com/send`) utk kirim pesan tagihan/
  pemberitahuan ke `no_hp` orang tua — single target maupun broadcast per kelas/jenis pembayaran.

### 2.5 Data pendukung
- `thn_ajaran` (tahun ajaran + semester), `kelas` (tingkat + nama_kelas), siswa dengan **NIS** unik,
  `petugas` (staf yang menerima tunai).

---

## 3. Kelemahan Repo (yang justru menjadi "sempurnaan" dalam desain kita)

| # | Kelemahan di repo | Perbaikan di `school-finance-system` |
|---|---|---|
| 1 | Semua logika di **fat controllers** (ribuan baris) | Controller tipis → **pola Action** (ADR-0010) |
| 2 | Uang disimpan sebagai angka biasa (`number_format`, kemungkinan float/string) | **Integer cents** `*_cents` (ADR-0009) |
| 3 | Single-school, tanpa isolasi data | **Multi-tenant** shared DB + `school_id` dipaksa teknis (ADR-0005) |
| 4 | `notificationHandler` **tanpa verifikasi signature** Midtrans — siapa pun bisa set status | **Verifikasi `signature_key` sha512** + baca nominal dari gateway (ADR-0007) |
| 5 | **Tanpa idempotency** — webhook ganda bisa memproses dua kali | `UNIQUE(gateway_trx_id)` + `lockForUpdate` (ADR-0013) |
| 6 | **Tanpa ledger/audit trail** — data bisa diubah diam-diam | `ledger_entries` append-only + `audit_logs` (ADR-0008) |
| 7 | Token API WhatsApp **hardcoded di source code** | Secrets via env + config terenkripsi per sekolah; **tidak pernah di codebase** |
| 8 | Order_id di-parse dengan `explode('_')` — rapuh & pembayaran ke multi-siswa | `order_id` terstruktur `SCH{n}.{yyyyMMdd}.{paymentId}` → mapping deterministik ke 1 sekolah (ADR-0007) |
| 9 | Tanpa maker-checker | Tunai: pencatat ≠ verifikator (sudah di desain) |
| 10 | Tugas lambat (broadcast, export) jalan inline memblokir request | Semua via **queue** (ADR-0012) |
| 11 | WA broadcast tanpa mekanisme persetujuan | Wajib **consent orang tua** (UU PDP No. 27/2022, lihat SECURITY.md) |
| 12 | Blade views digabung dgn backend | **SPA React + API** terpisah total (ADR-0004) |

---

## 4. Fitur yang Diadopsi → Status di Proyek Kita

| Fitur dari repo | Adopsi | Status |
|---|---|---|
| `tipe_bayar` **Bulanan / Bebas** di master tagihan | ✅ Disempurnakan: `bill_types.tipe_bayar` (enum `monthly`/`one_time`) | **MVP** (inti SPP) |
| **Rincian per bulan** utk tagihan bulanan | ✅ Disempurnakan: invoice SPP punya `bulan` + `tahun_periode` | **MVP** |
| **Bayar beberapa tagihan dalam 1 transaksi** (multi-invoice) | ✅ Disempurnakan: pivot `payment_invoice` (1 payment → N invoice; lunasi tunggakan) | **MVP** (tunai: bayar sisa 2–3 bulan sekaligus) |
| **Tunggakan / sisa tagihan** per siswa | ✅ `GET /api/v1/reports/tunggakan` + filter kelas/tahun ajaran | **MVP** (M11 laporan) |
| PDF invoice + kuitansi | ✅ Kuitansi bernomor sudah di desain; PDF invoice fast-follow | Kuitansi **MVP**; PDF invoice fast-follow |
| **Export Excel/PDF** laporan | ✅ Ekspor via queue + unduh link | **Fast-follow** (post-MVP) |
| **Broadcast/reminder WA** ke orang tua | ✅ Adapter notifikasi (Fonnte/alternatif) via queue; wajib opt-in | **Fast-follow** (post-MVP) |
| NIS unik per siswa | ✅ Sudah di desain (`students.nis` UNIQUE per sekolah+aktif) | Sudah ada |
| Tahun ajaran + semester | ✅ `academic_years` (tahun + semester) | Sudah ada |

> Catatan scope: **Midtrans tetap fast-follow** (keputusan terkunci). Desain pembayaran online di
> semester-2 mengadopsi pola "multi-item order" dari repo, tapi dengan `order_id` terstruktur dan
> verifikasi signature yang benar (lihat ADR-0007).

---

## 5. Implikasi ke Data Model

Perubahan minimal pada desain (detail di ADR-0015):

```diff
- bill_types (Jenis: SPP/gedung/kegiatan, tarif, periodisitas)
+ bill_types (Jenis: SPP/gedung/kegiatan, tipe_bayar: monthly|one_time, tarif_cents)

- invoices (school_id, student_id, bill_type_id, periode, ...)
+ invoices (school_id, student_id, bill_type_id,
+           periode_bulan, periode_tahun,         # utk monthly
+           amount_cents, status, UNIQUE(school_id, student_id, bill_type_id, periode_bulan, periode_tahun))

+ payment_invoice (pivot: payment_id, invoice_id)  # 1 pembayaran → N tagihan
```

`guardians` bertambah kolom `no_hp` + `notif_consent_at` (persetujuan WA/email, UU PDP).
`payments.method` memakai enum `CASH`/`SNAP` (bukan teks bebas). `payments.cashier_name` snapshot
nama petugas utk alur tunai (selain `created_by` relasi).

---

## 6. Referensi Detail

- Desain akhir yang disempurnakan: [`ARCHITECTURE.md`](ARCHITECTURE.md)
- Keputusan arsitektur baru: [`ADR/0015-tagihan-bulanan-bebas.md`](ADR/0015-tagihan-bulanan-bebas.md), [`ADR/0016-notifikasi-wa-adapter.md`](ADR/0016-notifikasi-wa-adapter.md)
- Aturan implementasi: [`CODING_GUIDELINES.md`](CODING_GUIDELINES.md)
- Sumber: github.com/fayzisme/spp-pembayaran (ditelaah 2026-08-28, `--depth 1`)