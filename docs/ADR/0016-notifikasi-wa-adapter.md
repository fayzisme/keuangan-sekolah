# ADR-0016: Notifikasi WhatsApp — Pola Adapter + Queue + Consent (UU PDP)

- **Status:** Accepted
- **Tanggal:** 2026-08-28
- **Sumber ide:** Riset `fayzisme/spp-pembayaran` (WhatsApp broadcast via Fonnte)

## Konteks
- Referensi SPP mengirim **pengingat tagihan via WhatsApp ke orang tua** (`no_hp`) — efektif untuk
  sekolah Indonesia. Alasan kami adopsi: nilai tinggi, biaya rendah.
- Di repo referensi: token API **hardcoded di source code**, request dikirim **inline di controller**
  (memblokir response), dan tanpa persetujuan penerima.
- Proyek kita wajib patuh **UU PDP No. 27/2022** → komunikasi kampanye/pengingat butuh consent.
- Banyak iuran/tunggakan → **reminder H-3/H-1/H+3** sudah direncanakan (ARCHITECTURE.md §13).

## Keputusan
1. **Adapter notifikasi** — `NotificationChannelInterface` (port) + implementasi `WhatsAppFonnteChannel`
   (infrastruktur), analog pola Midtrans (ADR-0007). Channel lain (email, push) tinggal tambah adapter.
2. **Semua pengiriman lewat queue** (ADR-0012) — job `SendReminderNotification` di-`dispatch()`,
   tidak pernah kirim inline di controller.
3. **Secrets di config terenkripsi**, bukan di code: `notification_provider` key di DB config per
   sekolah (terenkripsi), fallback env. **Tidak ada token hardcoded** (perbaikan eksplisit atas repo).
4. **Consent wajib** — `guardians.no_hp` + `guardians.notif_consent_at`. Kirim pengingat HANYA jika
   consent terisi. Endpoint opt-in/opt-out milik orang tua (self-service, tercatat di `audit_logs`).
5. Pesan terkirim dicatat: `notification_logs (school_id, guardian_id, channel, template, phone,
   status, provider_ref, sent_at)` → audit & metrik terkirim/gagal.

## Konsekuensi
Positif:
- Pengingat tagihan meningkatkan ketepatan bayar SPP (rasa memiliki orang tua).
- Adapter → ganti provider (WhatsApp Business API, Fonnte, Wablas, email) murah.
- Queue → request cepat, retry otomatis utk provider down.
- Consent + log = kepatuhan UU PDP & bisa dibuktikan.

Negatif:
- Perlu akun provider WhatsApp (biaya nominal per pesan) — **non-MVP**.
- Template pesan harus disetujui & dirawat (hindari spam → risiko diblokir provider).
- Consent management menambah item kerja (halaman profil ortu + audit).

## Status
**Fast-follow (post-MVP)** — infrastruktur queue + kolom consent sudah disiapkan di M1/M5,
fitur kirim pesan dikerjakan setelah MVP tunai live.

## Referensi
- [RESEARCH_SPP_REFERENCE.md](../RESEARCH_SPP_REFERENCE.md) §2.4, §3 (row #7), §4
- [ARCHITECTURE.md](../ARCHITECTURE.md) §13 (scheduler reminder)
- [SECURITY.md](../SECURITY.md) — kepatuhan UU PDP No. 27/2022
- [ADR-0012](../ADR/0012-async-queue.md)