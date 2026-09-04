# ADR-0001: Modular Monolith sebagai Arsitektur Awal

- **Status:** Accepted
- **Tanggal:** 2026-08-28

## Konteks
- Tim kecil (1–3 orang), sistem greenfield, skala menengah (ratusan ribu pengguna terdaftar).
- Microservices populer, tetapi membawa beban operasional besar: jaringan, retry, observability, deploy sinkron, data consistency lintas service — terlalu berat untuk tim kecil di fase awal.
- Tuntutan domain (uang) tidak terlalu beragam sehingga memang tidak butuh isolasi service sejak awal.

## Keputusan
Mulai sebagai **Modular Monolith**: satu codebase backend dengan modul-modul bisnis yang berbatas tegas (masing-masing punya konteks, model, dan API internal sendiri). Modul **tidak boleh** saling mengakses internal secara langsung — komunikasi lewat Action atau domain event.

Arsitektur ini didesain agar modul tersibuk (mis. `Billing`) **bisa diekstrak** menjadi service terpisah nanti tanpa rewrite, jika skala/kebutuhan benar-benar menuntut.

## Konsekuensi
Positif:
- Deploy & operasi sederhana (1 aplikasi, 1 pipeline).
- Latensi internal rendah (in-process call), cepat ke produksi.
- Transaksi ACID lintas modul mudah (satu database).

Negatif:
- Batas antar modul harus dijaga disiplin dengan code review + test.
- Tak bisa auto-scaling per-modul (hanya per-instance).
- Jika diekstrak di masa depan, dibutuhkan usaha refaktor — dilema yang sudah diterima secara sadar.

## Referensi
- [ARCHITECTURE.md](../ARCHITECTURE.md) §1, §5, §6