# ADR-0007: Payment Gateway via Pola Adapter (Midtrans)

- **Status:** Accepted
- **Tanggal:** 2026-08-28

## Konteks
- Pembayaran online untuk iuran sekolah (VA, QRIS, e-wallet, kartu).
- Provider utama dipilih: **Midtrans (Snap)** — paling populer di Indonesia, dukungan VA/QRIS/e-wallet.
- Ganti provider di masa depan harus murah (biaya, negosiasi, kebutuhan sekolah).

## Keputusan
Memakai **pola Adapter (Ports & Adapters)**:
- `PaymentGatewayInterface` (port) di `app/Domain/Billing/Contracts/`.
- `MidtransGateway` (adapter) di `app/Infrastructure/PaymentGateways/`.
- Alur: `pay-snap` membuat transaksi → redirect/hosted payment → webhook notification → idempotent settle.
- Konfigurasi per sekolah di tabel `school_gateway_configs` (key di-enkripsi) → siap memakai akun per sekolah (settlement masuk ke rekening sekolah masing-masing).

## Konsekuensi
Positif:
- Mengganti/gabung provider (Xendit, dll) = tambah 1 adapter, tanpa menyentuh domain.
- Business logic (invoice, ledger, kuitansi) tidak bergantung pada SDK provider.
- Dukungan per-sekolah config → fleksibel bisnis.

Negatif:
- Ada lapisan abstraksi (biaya kecil, sebanding dengan fleksibilitas).
- Integrasi webhook tetap harus diuji end-to-end dengan kartu/sandbox Midtrans.
- Settlement & fee provider berbeda-beda → ditangani di rekonsiliasi harian.

## Referensi
- [ARCHITECTURE.md](../ARCHITECTURE.md) §4 (keputusan #7), §8