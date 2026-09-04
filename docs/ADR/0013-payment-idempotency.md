# ADR-0013: Idempotency & Race-Condition Guard pada Pembayaran

- **Status:** Accepted
- **Tanggal:** 2026-08-28

## Konteks
- Webhook payment bisa dikirim ganda/berulang (retry). Dua request bayar bisa datang bersamaan. Double-charge = bencana reputasi & keuangan.
- Garis pertahanan harus ada di level aplikasi **dan** database.

## Keputusan
Berlapis, semuanya non-negotiable:
1. **Unique constraint DB:** kolom `gateway_trx_id` punya `UNIQUE index` → webhook ganda otomatis ditolak, bukan sekadar cek di kode.
2. **Pessimistic lock:** `lockForUpdate` pada baris invoice saat memproses pembayaran → dua request konkuren tidak bisa settle invoice yang sama.
3. **Rekalkulasi di dalam transaksi DB:** saldo dibaca ulang di dalam transaction, tidak mempercayai angka dari request body.
4. **Idempotency-Key header** pada endpoint yang membuat resource (pay-snap, manual payment) → request ulang dengan key sama mengembalikan hasil sama.
5. **Nomor kuitansi** dari `receipt_sequences` dengan `lockForUpdate` per sekolah+tahun → unik tanpa bolong.

## Konsekuensi
Positif:
- Double-charge / double-settle tercegah secara struktural.
- Aman untuk retry webhook & user yang menekan tombol ganda.

Negatif:
- Lock artinya serialisasi pada invoice yang sama (baik; insiden sangat jarang).
- Semua jalur pembayaran wajib lewat Action yang sama (ADR-0010) agar guard tidak terlewat.

## Referensi
- [ARCHITECTURE.md](../ARCHITECTURE.md) §4 (keputusan #13), §8, §9