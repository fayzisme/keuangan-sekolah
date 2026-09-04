# CODING_GUIDELINES.md — Panduan Implementasi

| | |
|---|---|
| **Versi** | 1.1 |
| **Berlaku untuk** | Backend Laravel (`app/`) · Frontend React (`web/`) of Sistem Manajemen Keuangan Sekolah |
| **Dokumen terkait** | [ARCHITECTURE.md](ARCHITECTURE.md) · [ADR/](ADR/) · [RESEARCH_SPP_REFERENCE.md](RESEARCH_SPP_REFERENCE.md) |

> 🎯 Panduan ini ada supaya Laravel (yang default-nya "folder aneka barang") dan React SPA tetap **rapi, aman, dan bisa dirawat tim kecil**. Aturan yang tertulis di sini **wajib** diikuti; code review menegakkannya.

---

## 1. Prinsip Umum

1. **Satu alur bisnis = satu Action** (lihat §3). Tidak ada logika bisnis di Controller.
2. **Uang selalu integer cents** (lihat §4).
3. **Mutasi data keuangan selalu lewat Action** → otomatis tercatat ke audit & ledger.
4. **Multi-tenant jangan dilawan** — jangan pernah melewati Global Scope atau meng-hardcode `school_id`.
5. **Kode harus diuji** — Action punya unit test, endpoint punya feature test (Pest).
6. **Bahasa Indonesia untuk komentar/penamaan domain yang bermakna**, kode tetap berpola PSR-12/TS convention.

---

## 2. Struktur Direktori Laravel

```
app/
├─ Domain/
│  ├─ Billing/
│  │  ├─ Actions/               # use-case
│  │  ├─ Models/                # Eloquent
│  │  ├─ Contracts/             # interface (port)
│  │  ├─ Data/                  # DTO
│  │  └─ Events/                # domain events
│  ├─ Finance/                  # ledger, jurnal, rekonsiliasi
│  ├─ Student/
│  ├─ Auth/
│  └─ School/
├─ Infrastructure/
│  ├─ PaymentGateways/          # adapter Midtrans, dll
│  ├─ Notifications/
│  └─ Repositories/
├─ Http/
│  ├─ Controllers/Api/V1/
│  ├─ Middleware/
│  ├─ Requests/                 # FormRequest
│  └─ Resources/                # API Resource
└─ Console/Commands/
```

**Aturan:** modul baru = folder baru di `Domain/` yang berisi Actions/Models/Contracts-nya sendiri. Modul **tidak boleh** mengimpor model internal modul lain secara langsung; gunakan Action/event modul tersebut.

---

## 3. Pola Action (Use-Case)

- Nama: kata kerja + konteks → `CreateInvoicesAction`, `ProcessSnapPaymentAction`, `VerifyManualPaymentAction`.
- Action **invokable** (`__invoke`) atau method eksplisit `handle()` — pilih **satu konvensi** dan konsisten (`__invoke` disarankan).
- Action menerima DTO/param primitif, **bukan** HTTP Request.
- Action boleh memanggil Action lain / dispatch event — tidak boleh bergantung pada HTTP layer.
- Controller = maksimal ~10 baris logika: FormRequest → Action → Resource.

```php
final class VerifyManualPaymentAction
{
    public function __construct(
        private readonly PaymentGatewayInterface $gateway,
    ) {}

    public function __invoke(Payment $payment): Payment
    {
        // ... validasi status, lock, settle, tulis ledger + receipt
    }
}
```

### 3.1 Alokasi multi-invoice (1 pembayaran → N tagihan)
- `payments` ⇆ `invoices` lewat pivot `payment_invoice` (ADR-0015).
- **Invarian wajib di Action pembayaran:**
  1. `SUM(payment_invoice.allocated_cents) == payments.total_cents` (alokasi = nominal dibayar).
  2. `allocated_cents ≤ invoices.amount_cents − (alokasi sudah ada)` → tidak boleh over-pay 1 tagihan.
  3. Seluruh alokasi dilakukan dalam **satu DB transaction + `lockForUpdate`** pada semua invoice terkait.
- Tagihan `monthly` dibuat per bulan (`periode_bulan` + `periode_tahun`); `one_time` → `periode_bulan = NULL`.
- Generate batch harus cek existing invoice (unique `school_id, student_id, bill_type_id, periode_bulan, periode_tahun`) — **jangan double-invoice**.

---

## 4. Aturan Uang (Integer Cents)

- Semua kolom uang: `*_cents` bertipe integer (`BIGINT`).
- Tidak pernah `float` untuk uang, di PHP maupun JSON.
- Konversi hanya di dua tempat: input DTO (rupiah→cents, via cast) dan API Resource/display (cents→rupiah).
- Frontend: **hanya satu util** format uang (`lib/money.ts`) yang boleh menampilkan Rupiah.
- Nilai dari client (amount) **tidak pernah dipercaya** untuk bank amount — tarif selalu dari master (`bill_types`), jumlah settle dari response gateway.

---

## 5. Database & Migration

- Setiap tabel domain punya `school_id` (FK → schools) **kecuali** tabel global sistem (users, roles).
- Unique constraint **harus menyertakan** `school_id`: `UNIQUE(school_id, ...)`.
- Migration harus **backward-compatible** (deploy dulu, baru data, baru drop) — jangan men-drop kolom yang masih dipakai versi lama.
- Gunakan nama kolom konsisten: `created_by`, `verified_by`, `*_at` timestamp, `*_cents`.
- Index untuk kolom yang dipakai filter: `status`, `(school_id, status)`, `student_id`, `period`.
- Tabel `ledger_entries`: **tidak pernah** ada migration yang men-drop/mengubah baris; hanya INSERT.

### Multi-tenant
- Model domain pakai Global Scope (trait `BelongsToSchool`).
- Jangan pernah: `withoutGlobalScope` kecuali untuk super admin dengan alasan tertulis + pihak berwenang.
- `school_id` berasal dari konteks aktif (middleware/token), **bukan** dari request body.

---

## 6. API & Kontrak (OpenAPI)

- Semua endpoint di bawah `/api/v1`; resource/response memakai API Resource.
- Validasi: `FormRequest` untuk semua input; pesan error konsisten (`{ message, errors }`).
- `dedoc/scramble` menghasilkan OpenAPI. **Jangan** menulis spec manual.
- Setelah mengubah endpoint, regenerasi client frontend (CI) — jangan commit perubahan API tanpa regenerate.
- Webhook (`/webhooks/midtrans`): tanpa auth normal, tapi **verifikasi signature WAJIB**.

---

## 7. Test (Pest)

Wajib ada:
- **Unit test setiap Action** — happy path + semua branch penting (failure, double-process, rejected).
- **Feature test setiap endpoint API** — auth, validasi, output Resource.
- **Test isolasi tenant** — user sekolah A tidak bisa baca sekolah B (harus error).
- **Test idempotency** — webhook kedua tidak memproses ulang.
- **Test maker-checker** — verifikator ≠ pencatat.

Standar: `vendor/bin/pest`. Jangan merge PR yang memecah test.

---

## 8. Frontend React (TypeScript)

```
web/src/
├─ app/            # routing, guards role, providers
├─ features/       # per fitur bisnis
├─ components/ui   # design system
├─ api/            # client generated + types (JANGAN edit manual)
├─ hooks/          # TanStack Query wrappers
└─ lib/            # format uang, date, util
```

- Server state di **TanStack Query**; UI state ringan di Zustand. Hindari menyimpan data server di store.
- Semua akses API lewat **client API yang di-generate** — tidak ada `fetch`/`axios` manual tersebar.
- Type safety: `strict` mode; `any` dilarang kecuali dengan `// eslint-disable` + alasan.
- Naming: komponen `PascalCase.tsx`, hooks `useX.ts`, util `camelCase.ts`.
- Format uang/date hanya lewat `lib/` — dilarang format inline di komponen.

---

## 9. Git Workflow

- Branch: `feature/<nama>`, `fix/<nama>`, `chore/<nama>`; protected `main`.
- PR wajib: ≤ 400 baris perubahan, deskripsi jelas, test hijau, review; label `breaking-api` jika mengubah kontrak.
- Commit message: konvensional (`feat:`, `fix:`, `test:`, `docs:`, `refactor:`, `chore:`).
- Secrets dilarang masuk git (env disimpan di server/CI).

## 10. Security Checklist (setiap fitur)

- [ ] Validasi semua input (FormRequest) — reject unknown fields
- [ ] Rate limit (`throttle`) pada endpoint sensitif (login, payment, generate)
- [ ] RBAC: endpoint dicek dengan Policy/Permission
- [ ] Tidak percaya `school_id`/amount dari client
- [ ] SQL lewat Eloquent (parameterized) — tidak ada raw string query dari input user
- [ ] File upload: validasi mime/type + ukuran + random filename; disimpan di storage pribadi (bukan public) untuk bukti pembayaran
- [ ] Idempotency & lock untuk semua jalur pembayaran
- [ ] Audit log untuk mutasi keuangan & admin action
- [ ] Log/error tidak menampilkan data sensitif (key, token, NIK)
- [ ] Notifikasi WA/email hanya ke penerima dgn **consent tercatat** (`guardians.notif_consent_at`) — UU PDP

## 11. Checklist Code Review

1. Logika bisnis ada di Action (bukan controller/model boot)?
2. Uang integer cents & tidak percaya input client?
3. `school_id` tidak bisa di-spoof; Global Scope tidak dilewati?
4. Multi-request aman (lock/idempotency) untuk mutasi uang?
5. Test sesuai §7 ada & hijau?
6. Tidak ada secret/credential hard-coded?
7. OpenAPI/client frontend sudah diregenerate jika kontrak berubah?
8. N+1 query dihindari (eager loading)?
9. Kode mengikuti format lint (Pint / eslint / prettier)?

---

## 12. Alat yang Digunakan

| Alat | Fungsi |
|---|---|
| PHP 8.3 + Laravel 11/12 | Backend |
| Pest | Testing backend |
| Pint (Laravel) | Code style PHP |
| dedoc/scramble | Generate OpenAPI |
| openapi-typescript + generator | Client frontend |
| ESLint + Prettier + TypeScript strict | Frontend quality |
| Docker Compose | Local & stage env |
| GitHub Actions | CI/CD |

---

*Panduan ini hidup — perbarui lewat PR ke `docs/CODING_GUIDELINES.md` jika konvensi berubah, dan catat alasannya di ADR bila bersifat keputusan arsitektur.*