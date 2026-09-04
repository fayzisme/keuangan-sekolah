# ADR — Architecture Decision Records

Folder ini menyimpan **keputusan arsitektur** (Architecture Decision Records) untuk Sistem Manajemen Keuangan Sekolah. Setiap keputusan penting didokumentasikan agar alasan di balik pilihan teknis tidak hilang — siapa pun yang membaca repositori ini paham *kenapa* arsitekturnya seperti ini.

## Format

Setiap ADR mengikuti format Michael Nygard:

- **Status** — Accepted / Proposed / Superseded
- **Tanggal**
- **Konteks** — latar belakang & forces
- **Keputusan** — apa yang diputuskan
- **Konsekuensi** — dampak positif & negatif
- **Referensi** — tautan ke dokumen terkait

## Daftar ADR

| ID | Judul | Ringkasan |
|---|---|---|
| [0001](0001-modular-monolith.md) | Modular Monolith | Satu backend berbatas modul ketat; siap ekstraksi nanti |
| [0002](0002-laravel-backend.md) | Laravel sebagai backend | PHP 8.3+, dipaksa modular (bukan CodeIgniter) |
| [0003](0003-react-vite-spa.md) | React + TS + Vite | SPA murni, static di CDN |
| [0004](0004-frontend-backend-decoupled.md) | FE/BE terpisah total | API-only Laravel; bukan Next.js/BLade sebagai backend |
| [0005](0005-multi-tenant-shared-db.md) | Multi-tenant shared DB | `school_id` + enforcement berlapis |
| [0006](0006-postgresql-redis.md) | PostgreSQL + Redis | SSOT + cache/queue/session |
| [0007](0007-payment-gateway-adapter.md) | Payment adapter | Midtrans via port/adapter, config per sekolah |
| [0008](0008-ledger-append-only.md) | Ledger append-only | Pembukuan immutable, koreksi lewat reversing entry |
| [0009](0009-money-integer-cents.md) | Uang = integer cents | Tanpa float |
| [0010](0010-action-pattern.md) | Pola Action | 1 use-case = 1 class |
| [0011](0011-openapi-contract.md) | Kontrak OpenAPI | Spec di-generate → client TS di-generate |
| [0012](0012-async-queue.md) | Queue worker | Tugas lambat tak memblokir request |
| [0013](0013-payment-idempotency.md) | Idempotency payment | Anti double-charge berlapis |
| [0014](0014-anti-over-engineering.md) | Anti over-engineering | Tanpa microservices/K8s/event sourcing di fase awal |
| [0015](0015-tagihan-bulanan-bebas.md) | Tagihan Bulanan/Bebas | `tipe_bayar` (monthly/one_time), rincian per bulan, pivot `payment_invoice` (1 bayar → N tagihan) |
| [0016](0016-notifikasi-wa-adapter.md) | Notifikasi WhatsApp | Adapter + queue + consent UU PDP; fast-follow post-MVP |

## Cara Menambah ADR Baru

1. Nomor berikutnya (`0015-...`) sesuai urutan.
2. Ikuti format di atas; jujur soal trade-off.
3. Update daftar di file ini **dan** tabel Decision Log di `../ARCHITECTURE.md`.

> Kapan menulis ADR? Saat pilihan yang diambil **signifikan, sulit dibalik, atau menyangkut biaya jangka panjang** — bukan untuk setiap keputusan kecil.