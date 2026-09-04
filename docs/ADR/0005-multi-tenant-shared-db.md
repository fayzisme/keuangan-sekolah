# ADR-0005: Multi-Tenant Shared Database dengan `school_id`

- **Status:** Accepted
- **Tanggal:** 2026-08-28

## Konteks
- Produk SaaS untuk **banyak sekolah**.
- Opsi: (A) satu database, semua tenant berbagi, di-scope `school_id`; (B) satu database/schema per sekolah.
- Tim kecil: operasional mengelola banyak database/schema (migrasi N kali, backup N kali) sangat berat.

## Keputusan
Memakai **Opsi A: shared database**, semua tabel domain punya kolom `school_id`, dilindungi **berlapis**:
1. Global Scope Eloquent → otomatis menyaring semua query model domain.
2. Middleware `EnsureSchoolContext` → `school_id` aktif diambil dari user login, **bukan** dari body/param (anti-spoof).
3. Unique constraint gabungan `UNIQUE(school_id, ...)` di level database.
4. Policy + FormRequest per resource.
5. Test isolasi lintas tenant sebagai regresi wajib.

## Konsekuensi
Positif:
- Operasional ringan: 1 database, 1 migrasi, 1 backup.
- Efisien untuk ratusan ribu user dengan volume per-sekolah normal.
- Jalur migrasi ke Opsi B tetap terbuka (proyeksi `school_id` sudah ada).

Negatif:
- Jika ada satu sekolah dengan volume ekstrem, bisa mengganggu tenant lain (noisy neighbor) → mitigasi: index, cache, query budget.
- Isolasi bergantung sepenuhnya pada enforcement; karena itu dibuat *teknis* (scope + DB constraint), bukan niat baik.

## Referensi
- [ARCHITECTURE.md](../ARCHITECTURE.md) §4 (keputusan #5), §7