# ADR-0004: Frontend & Backend Terpisah Total (Decoupled)

- **Status:** Accepted
- **Tanggal:** 2026-08-28

## Konteks
- Ada godaan server-side rendering (mis. Blade, Next.js) supaya "satu codebase".
- Constraint user: ❌ Next.js tidak boleh sebagai backend.
- Tim kecil butuh batas yang jelas agar tidak ada jebakan "logic tersebar di template".

## Keputusan
**Laravel = API murni (`/api/v1`), tanpa render Blade untuk halaman aplikasi.**
**React SPA = frontend murni, tanpa akses database.**
Kontrak antara keduanya adalah spesifikasi API (OpenAPI, lihat ADR-0011). Server-side rendering hanya untuk halaman non-aplikasi jika suatu saat perlu (landing page/SEO), dan itu pun lewat layanan terpisah — bukan menjadikan Laravel sekaligus backend-logic-heavy.

## Konsekuensi
Positif:
- Satu sumber kebenaran API; frontend & backend bisa dikembangkan/deploy/skalakan independen.
- Memenuhi constraint "no Next.js as backend".
- Tim kecil: dua area jelas, tidak saling merusak.

Negatif:
- Overhead komunikasi HTTP + token auth (diterima; kecil).
- Perlu disiplin menjaga kontrak API agar tidak berubah liar → solved by ADR-0011.

## Referensi
- [ARCHITECTURE.md](../ARCHITECTURE.md) §4 (keputusan #4), §11, §12