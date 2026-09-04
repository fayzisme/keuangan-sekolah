# ADR-0009: Representasi Uang sebagai Integer Cents

- **Status:** Accepted
- **Tanggal:** 2026-08-28

## Konteks
- Aplikasi menangani uang (invoice, pembayaran, ledger).
- Float (`0.1 + 0.2 !== 0.3`) menyebabkan pembulatan yang diam-diam salah — fatal untuk uang.
- DB SQL `DECIMAL` aman di level DB, tetapi rawan inkonsistensi jika diproses di PHP/JS sebagai float.

## Keputusan
Semua nilai uang disimpan & diproses sebagai **integer dalam satuan sen (cents)** — kolom bernama `*_cents` (mis. `amount_cents`) bertipe `BIGINT`/`INTEGER` di PostgreSQL, dihitung di PHP dengan integer math, ditampilkan di frontend lewat 1 util sentral (cents → format Rupiah).

Aturan:
- Tidak pernah menyimpan `float` untuk uang.
- Perhitungan (diskon, denda, proporsi) memakai integer math; jika perlu pecahan, bulatkan dengan aturan eksplisit (mis. round half-up) dan dokumentasikan.
- DTO/Resource mengirim `amount_cents` (dan klien menampilkan), bukan nilai float.

## Konsekuensi
Positif:
- Zero floating-point error.
- Konsisten antar DB, PHP, dan JS.
- Mudah di-total dan di-assert dalam test (integer equality).

Negatif:
- Developer harus ingat konversi cents ↔ rupiah → dimitigasi dengan DTO + util sentral + code review.

## Referensi
- [ARCHITECTURE.md](../ARCHITECTURE.md) §4 (keputusan #9), §10, §12