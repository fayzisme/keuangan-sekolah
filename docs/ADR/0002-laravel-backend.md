# ADR-0002: Laravel (PHP) sebagai Backend Framework

- **Status:** Accepted
- **Tanggal:** 2026-08-28

## Konteks
- Backend API untuk SaaS manajemen keuangan sekolah.
- Kandidat dievaluasi: NestJS (Node/TS), Go (Echo/Gin), Laravel (PHP).
- Constraint user: ❌ CodeIgniter tidak boleh dipakai.
- Tim kecil, perlu produktivitas tinggi, ekosistem beatif untuk CRUD+templating+queue+auth.

## Keputusan
Memakai **Laravel** sebagai framework backend API (PHP 8.3+, strict types).
Karena arsitektur default Laravel adalah "folder per jenis" yang mudah jadi spaghetti, framework ini **dipaksa modular** lewat struktur `app/Domain/*` + Action pattern (lihat ADR-0010) dan ditegakkan oleh `CODING_GUIDELINES.md` + code review.

## Konsekuensi
Positif:
- Produktivitas tinggi: Eloquent, FormRequest, scheduler, queue, Sanctum, Spatie packages.
- POPULER di Indonesia → mudah mencari developer/bantuan.
- Performa lebih dari cukup untuk skala menengah dengan php-fpm + Redis cache.

Negatif:
- Disiplin modular harus dijaga ketat (tidak dengan sendirinya).
- Runtime PHP tidak secepat Go untuk workload super intensif (bukan kebutuhan saat ini).
- Eloquent rawan "N+1 query" → wajib perhatian (eager loading, query monitoring).

## Alternatif yang ditolak
- CodeIgniter: tidak diizinkan user, dan tidak mendukung disiplin modular.
- NestJS: bagus, mudah modular, tapi tim memilih PHP/Laravel.
- Go: performa & deploy tinggi, tapi lebih lambat untuk pengembangan fitur CRUD-heavy di tim kecil.

## Referensi
- [ARCHITECTURE.md](../ARCHITECTURE.md) §4 (keputusan #2)