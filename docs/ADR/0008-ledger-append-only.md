# ADR-0008: Ledger Append-Only (Immutable Financial Records)

- **Status:** Accepted
- **Tanggal:** 2026-08-28

## Konteks
- Pembukuan sekolah: penerimaan, piutang, mutasi per sekolah.
- Prinsip akuntansi: jejak keuangan tidak boleh hilang/diubah; koreksi bukanlah hapus data.
- Kesalahan manusia (salah nominal, salah input) akan terjadi → harus ada mekanisme koreksi yang terdokumentasi.

## Keputusan
Tabel `ledger_entries` bersifat **append-only**: tidak ada UPDATE atau DELETE. Setiap mutasi adalah baris baru. Koreksi dilakukan dengan **reversing entry** (jurnal balik), bukan mengedit baris lama.

Setiap entry menyimpan: `school_id`, `ref_type`/`ref_id` (link ke payment/invoice), `debit_cents`, `credit_cents`, `note`, `created_by`. Laporan keuangan selalu dihitung dari agregasi baris (sum), sehingga konsisten dan bisa di-replay kapan pun.

## Konsekuensi
Positif:
- Audit trail lengkap & tidak dapat dimanipulasi diam-diam.
- Saldo bisa di-rekonsiliasi kapan saja & direkonstruksi.
- Mendukung kepercayaan sekolah sebagai custodian uang.

Negatif:
- Tabel tumbuh tak pernah menghapus → butuh arsip/partisi jangka panjang (di-defer, tidak mengubah desain).
- Setiap koreksi butuh alur khusus (reversing entry) — dipastikan lewat Action baku `ReverseLedgerEntry`.

## Referensi
- [ARCHITECTURE.md](../ARCHITECTURE.md) §4 (keputusan #8), §10