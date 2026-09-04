# ADR-0006: PostgreSQL (SSOT) + Redis (Cache/Queue/Session)

- **Status:** Accepted
- **Tanggal:** 2026-08-28

## Konteks
- Data keuangan butuh integritas transaksional (ACID) dan constraint yang kuat.
- Aplikasi butuh cache (data panas) + queue (tugas lambat) + session store.

## Keputusan
- **PostgreSQL** sebagai satu-satunya source of truth (SSOT), termasuk ledger/invoice/payment.
- **Redis** untuk: cache, queue (database queue driver `redis`), session, rate-limit counter, dan lock (distributed lock untuk anti race condition).
- `pgbouncer` (connection pooling) di depan PostgreSQL untuk menjaga pool koneksi php-fpm.

## Konsekuensi
Positif:
- PostgreSQL: integrity (FK, unique, check, transaction), JSONB untuk data fleksibel, reliable.
- Redis cepat untuk hot data; queue memisahkan tugas lambat dari request.
- Satu database → transaksi lintas modul mudah (penting untuk payment + ledger).

Negatif:
- Dua komponen infra tambahan (dikelola via Docker Compose).
- Redis bersifat in-memory → tidak boleh dijadikan SSOT; hanya cache/queue yang bisa di-rebuild.
- Perlu kehati-hatian saat migrasi skema di database besar (gunakan teknik backward-compatible migration).

## Referensi
- [ARCHITECTURE.md](../ARCHITECTURE.md) §4 (keputusan #6), §5, §13