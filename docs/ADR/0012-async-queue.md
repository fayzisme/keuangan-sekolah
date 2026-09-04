# ADR-0012: Tugas Lambat Dipindah ke Queue Worker

- **Status:** Accepted
- **Tanggal:** 2026-08-28

## Konteks
- Ada pekerjaan yang lama/berat: generate invoice bulanan batch, kirim reminder, kirim email/WA, export laporan PDF/CSV, proses webhook lanjutan.
- Membiarkannya sinkron di request akan membuat API lambat dan timeout (php-fpm).

## Keputusan
Semua tugas lama/berat dikirim ke **queue** (driver `redis`), diproses oleh worker terpisah (`php artisan queue:work`). Request API hanya melakukan kerja cepat (validasi → action ringan → response). Notifikasi dikirim asinkron pasca event.

Aturan:
- Generate invoice batch → dispatch job chunked (per N siswa, transaction per siswa).
- Email/WA/reminder → queue (bisa retry dengan backoff).
- Export laporan → job menghasilkan file, notif saat selesai.

## Konsekuensi
Positif:
- API tetap responsif walau beban batch besar.
- Worker bisa diskalakan terpisah dari web instance.
- Retry mekanisme bawaan → toleransi error tinggi.

Negatif:
- Kompleksitas ekstra: monitoring antrean (failure, backlog) wajib ada.
- Hasil tidak instan untuk operasi async (diterima; UX di-design dengan status pending).

## Referensi
- [ARCHITECTURE.md](../ARCHITECTURE.md) §4 (keputusan #12), §13