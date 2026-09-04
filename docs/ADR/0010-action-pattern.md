# ADR-0010: Pola Action (Use-Case per Class) untuk Domain Logic

- **Status:** Accepted
- **Tanggal:** 2026-08-28

## Konteks
- Pola default Laravel: logic di Controller atau satu `Service` besar → mudah jadi spaghetti saat domain bertumbuh.
- Tim kecil butuh kelas kecil, fokus, dan mudah diuji.

## Keputusan
Setiap alur bisnis = **satu class Action** di `app/Domain/*/Actions/` (mis. `CreateInvoicesAction`, `ProcessSnapPaymentAction`, `VerifyManualPaymentAction`). Controller hanya: validasi (FormRequest) → panggil 1 Action → format response (API Resource).

Action adalah **unit test** utama (Pest). Action dapat memanggil Action lain / domain event, tetapi **tidak boleh** bergantung pada HTTP layer.

## Konsekuensi
Positif:
- Use-case terlihat eksplisit; mudah ditemukan & di-review.
- Test langsung menyentuh inti bisnis tanpa HTTP overhead.
- Memenuhi "1 alur = 1 tempat" → refactor lokal, bukan nuklir.

Negatif:
- Jumlah file bertambah (1 class per use-case) — diterima, harga dari kejelasan.
- Perlu disiplin: tidak boleh ada logic bisnis menyelinap ke controller/eloquent boot.

## Referensi
- [ARCHITECTURE.md](../ARCHITECTURE.md) §4 (keputusan #10), §6
- [CODING_GUIDELINES.md](../CODING_GUIDELINES.md) §Pola Action