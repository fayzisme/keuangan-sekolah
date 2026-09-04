# ADR-0015: Tagihan Bulanan/Bebas + Rincian per Bulan + Multi-Invoice Payment

- **Status:** Accepted
- **Tanggal:** 2026-08-28
- **Sumber ide:** Riset `fayzisme/spp-pembayaran` (lihat `RESEARCH_SPP_REFERENCE.md`)

## Konteks
- Referensi SPP (repo `fayzisme/spp-pembayaran`) memakai **dua tipe tagihan**: `Bulanan` (SPP,
  berulang per bulan) dan `Bebas` (satu-nilai: uang gedung, kegiatan, seragam), plus **rincian per
  bulan** untuk tagihan bulanan dan kemampuan **membayar beberapa tagihan dalam satu transaksi**
  (mis. melunasi tunggakan 3 bulan sekaligus).
- Desain awal kita punya `bill_types` (periodisitas) dan invoice per periode, tapi belum tegas
  memisahkan tipe Bulanan vs Bebas, belum punya rincian per bulan, dan 1 payment hanya untuk 1 invoice.
- Fitur ini adalah **inti SPP** → masuk **MVP** (tunai + verifikasi). Midtrans (pembayaran online)
  tetap fast-follow, tapi struktur datanya sudah siap.

## Keputusan

### 1. `bill_types.tipe_bayar` — enum `monthly` | `one_time`
- `monthly`: tarif per bulan, invoice dibuat per bulan (SPP).
- `one_time`: tarif satu nilai per periode/tahun ajaran (uang gedung, kegiatan).

### 2. Invoice SPP punya periode bulan + tahun
- Kolom `periode_bulan` (1–12) + `periode_tahun` pada `invoices`.
- Unique constraint **menjadi**: `UNIQUE(school_id, student_id, bill_type_id, periode_bulan, periode_tahun)`.
- Untuk tipe `one_time`: `periode_bulan = NULL`, periode mengikuti `periode_tahun`/tahun ajaran.

### 3. Satu pembayaran dapat melunasi beberapa invoice — pivot `payment_invoice`
- `payments` ⇆ `invoices` lewat tabel pivot `payment_invoice` (banyak-ke-banyak).
- Berlaku utk tunai dan online: siswa membayar 1 nominal → dialokasikan ke N tagihan (SPP bulan
  berjalan + tunggakan). Alokasi dicatat per baris pivot (distribusi transparan).
- Untuk online (fast-follow, ADR-0007): `order_id` tetap `SCH{n}.{yyyyMMdd}.{paymentId}` (1 payment)
  yang bisa berisi banyak invoice → parser deterministik, bukan `explode('_')` dari id mentah.

### 4. Pelunasan parsial vs penuh
- Invoice tetap punya status `OPEN/PARTIAL/PAID/VOID`. `payment_invoice` mencatat
  `allocated_cents` per invoice sehingga 1 invoice bisa dibayar bertahap (valid utk SPP lunas sebagian).

## Konsekuensi
Positif:
- Satu model konsisten utk SPP (bulanan) dan iuran bebas — seperti yang lazim di sekolah nyata.
- Tunggakan mudah dihitung: `sisa = sum(amount_cents) − sum(allocated_cents)` per siswa/jenis
  (fitur laporan tunggakan, M11 MVP).
- Struktur siap utk pembayaran online multi-item tanpa perubahan skema nanti.
- Menghindari desain "top-up saldo" yg lebih rumit — cukup alokasi ke tagihan.

Negatif:
- Alokasi multi-invoice menambah sedikit kompleksitas di Action pembayaran (validasi: total alokasi
  = nominal dibayar; sisa per invoice ≥ 0). Ditangani di `ProcessManualPaymentAction`.
- Uniqueness invoice lebih spesifik (per bulan) — generate batch harus menghindari duplikasi
  (cek existing sebelum insert; anti double-invoice).

## Referensi
- [RESEARCH_SPP_REFERENCE.md](../RESEARCH_SPP_REFERENCE.md) §4–5
- [ARCHITECTURE.md](../ARCHITECTURE.md) §10 (data model)
- [ADR-0009](../ADR/0009-money-integer-cents.md) (semua nominal `*_cents`)
- [ADR-0013](../ADR/0013-payment-idempotency.md) (idempotency pembayaran)