# ADR-0003: Frontend React + TypeScript + Vite (SPA Murni)

- **Status:** Accepted
- **Tanggal:** 2026-08-28

## Konteks
- Frontend web untuk aplikasi yang dipakai orang tua murid & staf sekolah (dashboard, daftar tagihan, pembayaran).
- Constraint user: Next.js tidak boleh dipakai sebagai backend.
- Frontend butuh responsif, modern, dan mudah dirawat tim kecil.

## Keputusan
Memakai **React + TypeScript + Vite** sebagai **SPA murni** (pure client-side). Build menghasilkan static asset murni (HTML/JS/CSS) yang disajikan CDN. Frontend berkomunikasi dengan backend **hanya** lewat HTTP `/api/v1`.

## Konsekuensi
Positif:
- Pemisahan absolut frontend/backend → kontrak API yang bersih.
- Static asset bisa di-CDN-kan global → latency rendah.
- TypeScript: keamanan tipe end-to-end (berpasangan dengan client OpenAPI, ADR-0011).
- Vite: dev experience cepat (HMR).

Negatif:
- Bukan SEO-friendly oleh default → tidak relevan untuk aplikasi yang butuh login (dashboard/payment), jadi diterima.
- SPA bergantung sepenuhnya pada API → butuh error handling/UX loading yang baik.

## Catatan
Next.js **diizinkan** hanya sebagai *build/react framework alternatif*, tetapi karena backend tetap Laravel terpisah, tidak ada alasan memakai Next.js di sini.

## Referensi
- [ARCHITECTURE.md](../ARCHITECTURE.md) §4 (keputusan #3), §12