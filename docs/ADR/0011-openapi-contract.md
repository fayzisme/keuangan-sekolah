# ADR-0011: Kontrak API via OpenAPI + Client Frontend di-Generate

- **Status:** Accepted
- **Tanggal:** 2026-08-28

## Konteks
- Frontend & backend terpisah total (ADR-0004). Ketidakcocokan kontrak API adalah sumber bug paling sering di arsitektur decoupled: field berubah, tipe beda, response barubah.
- Tim kecil tidak punya waktu manual men-sync spesifikasi dua repositori.

## Keputusan
**OpenAPI spec di-generate langsung dari kode Laravel** menggunakan package `dedoc/scramble` (menurunkan spec dari controller/request/resource). Dari spec itu, client frontend (fetch wrapper + TypeScript types + hooks) di-generate otomatis (mis. `openapi-typescript` + client generator), di-commit ke repo frontend.

Alur kerja: ubah backend → CI men-generate spec → regenerasi client → TypeScript langsung memberi error jika konsumsi tidak sinkron.

## Konsekuensi
Positif:
- Kontrak selalu sinkron; bug "response mismatch" tertangkap di compile time.
- Dokumentasi API gratis (bisa dipakai team/frontend/konsumen lain).
- Frontend punya types aman end-to-end.

Negatif:
- Menambah langkah generate di pipeline (otomatis, sekali setup).
- Scramble butuh pola controller yang rapi (Resource/Request terdefinisi) — sejalan dengan ADR-0010.

## Referensi
- [ARCHITECTURE.md](../ARCHITECTURE.md) §4 (keputusan #11), §11, §12