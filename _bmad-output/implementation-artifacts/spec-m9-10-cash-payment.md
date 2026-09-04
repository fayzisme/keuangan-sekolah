---
title: 'PEMBAYARAN TUNAI — catat + bukti, verifikasi bendahara (maker-checker), alokasi 1 bayar → N tagihan (pivot payment_invoice), kuitansi, ledger append-only, audit. DoD: double-process mustahil (lock + unique) — teruji'
type: 'feature'
created: '2026-09-02'
status: 'done'
review_loop_iteration: 1
baseline_commit: 'NO_VCS'
context:
  - 'docs/ARCHITECTURE.md'
  - 'docs/CODING_GUIDELINES.md'
  - 'docs/SECURITY.md'
  - 'docs/ADR/0005-multi-tenant-shared-db.md'
  - 'docs/ADR/0008-ledger-append-only.md'
  - 'docs/ADR/0009-money-integer-cents.md'
  - 'docs/ADR/0010-action-pattern.md'
  - 'docs/ADR/0013-payment-idempotency.md'
  - 'AGENTS.md'
  - '_bmad-output/implementation-artifacts/spec-m7-8-billing-invoices.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Milestone M7 sudah punya invoice (SPP bulanan + bebas). Milestone M9-10 menuntut **pembayaran tunai end-to-end**: pencatat mencatat pembayaran + upload bukti → status `PENDING_VERIFICATION` → bendahara LAIN memverifikasi (maker-checker) → status `SETTLED` + ledger append-only + nomor kuitansi sequential. Alokasi **1 pembayaran → N tagihan** via pivot `payment_invoice`. Anti double-process (lock + unique + rekalkulasi). Pembayaran online (Midtrans) **fast-follow** — struktur datanya sudah siap.

**Approach:**
1. **`payments`** — `method` (CASH), `status` (PENDING_VERIFICATION/SETTLED/FAILED/REFUNDED), `total_cents`, `proof_path`, `created_by`, `verified_by`, `verified_at`, `cashier_name`. `gateway_trx_id` UNIQUE (siap Midtrans).
2. **`payment_invoice`** pivot — `payment_id` ⇆ `invoice_id`, `allocated_cents` (mapping 1 bayar → N tagihan). Validasi: `sum(allocated_cents) = payment.total_cents`; per invoice `sum(allocated) ≤ amount_cents`.
3. **Alur**: `POST /payments/manual` (bendahara) → `PENDING_VERIFICATION` + bukti; `POST /payments/{id}/verify` (bendahara LAIN) → `SETTLED` + ledger + kuitansi + update invoice status.
4. **Ledger append-only** (`ledger_entries`): `school_id`, `ref_type`/`ref_id`, `debit_cents`, `credit_cents`, `note`, `created_by`. Hanya INSERT. Koreksi via `ReverseLedgerEntryAction` (M11).
5. **Kuitansi** (`receipt_sequences` + `receipts`): nomor sequential per `school_id` + `academic_year_id` (lockForUpdate).
5. **Idempotency**: `Idempotency-Key` header pada `POST /payments/manual` + `gateway_trx_id` UNIQUE (siap Midtrans). Pessimistic lock `lockForUpdate` invoice di verifikasi.
6. **RBAC**: `admin|bendahara` create; `admin|bendahara` verify (tapi creator != verifier — middleware/Action enforce).

## Boundaries & Constraints

**Always:**
- `school_id` dari konteks sesi, **tidak dari body**.
- `amount_cents` integer (ADR-0009). `total_cents` = sum alokasi.
- Pembayaran tunai `method = CASH`. Midtrans = `SNAP` (fast-follow, struktur siap).
- `gateway_trx_id` UNIQUE (idempotency DB level). `Idempotency-Key` header di create.
- **Maker-checker wajib**: creator != verifier (Action enforce + middleware).
- Verifikasi pakai `lockForUpdate` pada SEMUA invoice yang teralokasi + rekalkulasi saldo di dalam transaksi (ADR-0013).
- Ledger append-only: tidak ada UPDATE/DELETE. Koreksi via reversing entry (M11).
- Kuitansi sequential per `school_id` + `academic_year_id` (lockForUpdate).
- Read: `admin|bendahara`; write/verify: `admin|bendahara` (tapi creator != verifier).

**Never:**
- `school_id` dari request.
- `total_cents` dihitung dari body tanpa validasi vs alokasi.
- Verifikasi tanpa lock (race condition).
- Update/delete ledger entries.
- Kuitansi tanpa lock (bolong/duplikat).
- Pembayaran tanpa alokasi minimal 1 invoice.

## Tasks & Acceptance

**Execution:**
- [ ] Migrasi: `payments`, `payment_invoice`, `ledger_entries`, `receipt_sequences`, `receipts`.
- [ ] Model: `Payment`, `LedgerEntry`, `ReceiptSequence`, `Receipt`, relasi pivot.
- [ ] Repository/Service: `LedgerRepository`, `ReceiptSequenceRepository` (interface + implementasi Eloquent).
- [ ] Actions: `CreateManualPaymentAction`, `VerifyManualPaymentAction`, `ReverseLedgerEntryAction` (placeholder), `NextReceiptNumberAction`.
- [ ] Requests: `StoreManualPaymentRequest` (idempotency-key, allocation array), `VerifyPaymentRequest`.
- [ ] Controllers: `PaymentController` (store, index, show, verify).
- [ ] Routes `api.php` (create/verify/index/show).
- [ ] Middleware: `EnsureDifferentVerifier` (creator != verifier) + verify action sudah pakai lockForUpdate.
- [ ] Tests: create manual payment + allocate 2 invoice; verify by different user → ledger entry + kuitansi + invoice PAID; double-verify blocked; creator verify blocked 409; idempotency-key duplicate → 409/422; alokasi > amount_cents → 422; tenant isolation (payment sekolah A tidak terlihat B).

**Definition of Done:**
- [ ] `POST /payments/manual` dengan `Idempotency-Key` + alokasi 2 invoice → 201 `PENDING_VERIFICATION`.
- [ ] `POST /payments/{id}/verify` by **different user** → 200 `SETTLED` + ledger entry (credit) + kuitansi + invoice status PAID.
- [ ] Creator verifikasi sendiri → 409 "maker-checker".
- [ ] Idempotency-Key duplikat → 409 (kunci DB `gateway_trx_id` UNIQUE + header check).
- [ ] Alokasi melebihi invoice amount → 422.
- [ ] Tenant isolation: pembayaran sekolah B tidak terlihat di sekolah A (index/show).
- [ ] Lint PHP 0 error.

## Spec Change Log

<!-- (belum ada review loopback) -->

## Design Notes

- `payment_invoice.allocated_cents` = transparansi distribusi (bisa parsial per invoice).
- `gateway_trx_id` di tunai = hash `idempotency-key` (mis. `sha256(key|school|ts)`) utk unik.
- `receipt_sequences` next number di-lock via `lockForUpdate` (bukan auto-increment global).
- `ProcessManualPaymentAction` dari M1 ditinjau ulang: repositorinya sudah dibuat (LedgerRepository, ReceiptSequenceRepository), Action dipindah ke domain Billing, dependency injection via container.