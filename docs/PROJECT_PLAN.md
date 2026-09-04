# PROJECT_PLAN.md — Rencana Manajemen Proyek (PM)

| | |
|---|---|
| **Versi** | 1.1 |
| **Status** | Active |
| **Tanggal mulai** | 2026-08-28 (kickoff) · Minggu kerja pertama: 31 Agu 2026 |
| **Target MVP live** | Akhir November 2026 (pilot 1 sekolah nyata) |
| **PM** | AI Project Manager (Hermes) |
| **Owner / Developer** | Solo, part-time (~10–15 jam/minggu) |
| **Referensi** | [ARCHITECTURE.md](ARCHITECTURE.md) · [ADR/](ADR/) · [CODING_GUIDELINES.md](CODING_GUIDELINES.md) · [RESEARCH_SPP_REFERENCE.md](RESEARCH_SPP_REFERENCE.md) |

> 📌 **28 Agu 2026 — Integrasi riset:** fitur SPP dari repo referensi `fayzisme/spp-pembayaran`
> ditelaah & disempurnakan ke desain (lihat `RESEARCH_SPP_REFERENCE.md`): `tipe_bayar`
> monthly/one_time, rincian per bulan, bayar multi-invoice, laporan tunggakan (masuk MVP);
> notifikasi WA + export advance (fast-follow). Keputusan Midtrans **tetap** fast-follow.

---

## 1. Tujuan & Kriteria Sukses

**Tujuan MVP:** 1 sekolah nyata bisa mengelola pembayaran muridnya dengan sistem ini — dari tagihan, pembayaran, sampai laporan — **dengan uang yang aman dan jejak audit yang lengkap.**

**Kriteria sukses (Definition of Done MVP):**
- Staf sekolah (bendahara/admin) bisa: kelola murid, buat tagihan, catat & verifikasi pembayaran tunai, cetak kuitansi, lihat laporan per siswa.
- Orang tua bisa: lihat tagihan & riwayat bayar anaknya.
- Tidak mungkin terjadi double-charge / edit data keuangan diam-diam (idempotency + append-only ledger + maker-checker).
- Data sekolah A tidak bisa dibaca sekolah B (isolasi tenant teruji).
- Deployed ke VPS produksi, backup berjalan, dimonitor.

---

## 2. Realita & Anggaran Waktu

| Faktor | Nilai |
|---|---|
| Durasi | 12 minggu kerja (~31 Agu – 22 Nov 2026) + 1 minggu buffer |
| Kapasitas | Part-time 10–15 jam/minggu |
| Total estimasi | ~120–180 jam |
| Ekuivalen | ~3–4 minggu kerja full-time |

👉 **Kesimpulan PM:** scope MVP harus dipangkas tegas. Fitur keren (online payment, notifikasi otomatis, dashboard super admin) **didefer** ke fase setelah MVP live.

---

## 3. Scope MVP — IN vs OUT

### ✅ MASUK MVP (prioritas, urut eksekusi)
1. Auth + RBAC (admin, bendahara, murid, orang tua)
2. Master: sekolah, tahun ajaran, kelas, murid, guardian
3. Master tagihan (**SPP bulanan + tagihan sekali** — `tipe_bayar` monthly/one_time, ADR-0015)
4. Generate invoice + daftar invoice per murid (**rincian per bulan**; bayar tunggakan multi-invoice)
5. **Pembayaran tunai** + verifikasi bendahara + kuitansi (bisa alokasikan 1 pembayaran ke N tagihan)
6. Ledger append-only + audit log
7. Laporan dasar (per siswa, kelas, periode) + **laporan tunggakan/sisa tagihan**
8. Deploy produksi + pilot 1 sekolah + backup

### ❌ DITUNDA (post-MVP / fast-follow)
- Pembayaran online Midtrans (VA/QRIS/e-wallet) — *lihat §4, keputusan PM*
- Reminder jatuh tempo, notifikasi WA/email otomatis (**ada adapter + consent**, ADR-0016)
- Dashboard super admin per-platform
- Export laporan PDF/Excel advance (dompdf/excel via queue), PDF invoice digital

---

## 4. Keputusan PM Kunci: Pembayaran Online

**Situasi:** integrasi Midtrans = ketergantungan eksternal (akun, sandbox, webhook, settlement) yang rawan molor dan menghabiskan banyak jam — berisiko MVP tidak pernah rilis.

**Rekomendasi PM (default):** Midtrans **TIDAK masuk MVP**. Alur uang MVP memakai **pembayaran tunai + verifikasi** (sudah memenuhi kebutuhan nyata sekolah; aman secara desain). Online payment menjadi **sprint fast-follow** setelah MVP live.

**Alternatif (jika pemilik produk menuntut online payment di MVP):** scope lain dipangkas lebih dalam — mis. hanya jenis tagihan SPP, laporan sangat sederhana, master data minimal. **Keputusan ini harus eksplisit dari pemilik produk.**

**✅ KEPUTUSAN TERKONFIRMASI (Product Owner, 28 Agu 2026):** Midtrans **ditunda ke fast-follow post-MVP**. MVP memakai pembayaran tunai + verifikasi. Task tercatat sebagai milestone STRETCH di task board.

---

## 5. Timeline 12 Minggu

| Minggu | Tanggal | Milestone | Deliverable & DoD |
|---|---|---|---|
| **M1–2** | 31 Agu – 13 Sep | Fondasi | Repo mono, docker-compose, CI, skeleton modular Laravel + Pest, React scaffold, pipeline OpenAPI → client. **DoD:** `make up` hidup, `/healthz` OK, CI hijau |
| **M3–4** | 14 – 27 Sep | Auth & RBAC | Sanctum login/logout/me, role (spatie), konteks multi-sekolah. **DoD:** test "role lain dilarang" hijau |
| **M5–6** | 28 Sep – 11 Okt | Master data | Onboarding sekolah, tahun ajaran, kelas, murid, guardian, tenant scope. **DoD:** CRUD + test isolasi tenant hijau |
| **M7–8** | 12 – 25 Okt | Tagihan & invoice | Jenis tagihan, generate invoice batch, daftar tagihan murid. **DoD:** tarif dari master, input client tak dipercaya |
| **M9–10** | 26 Okt – 8 Nov | Pembayaran tunai | Catat + bukti, verifikasi (maker-checker), kuitansi, ledger, audit. **DoD:** uji idempotency & lock hijau |
| **M11** | 9 – 15 Nov | Laporan & hardening | Laporan per siswa/kelas/periode, security review, backup+tes-restore |
| **M12** | 16 – 22 Nov | Pilot & go-live | Deploy VPS, pilot sekolah nyata, bug-fix, serah terima |
| Buffer | 23 – 30 Nov | Cadangan | Untuk keterlambatan apa pun — jangan dipakai duluan |

---

## 6. Ritme Check-in Mingguan (Komitmen Bersama)

Setiap minggu — **jam senin** (atau kesepakatan) — PM meminta & mengecek ke owner:

**Format check-in (3 pertanyaan):**
1. **Progress:** Apa yang selesai minggu lalu? (bandingkan dengan rencana)
2. **Blocker:** Ada hambatan apa? (teknis, akun Midtrans, keputusan produk, waktu)
3. **Rencana:** Apa yang akan dikerjakan minggu ini?

**PM akan:**
- Memperbarui task board & timeline sesuai realita.
- Menandai risiko lebih awal (bukan menunggu telat).
- Menjaga scope dari *scope creep*.
- Mereview keputusan teknis bila diminta.

> Aturan PM: **kalau progress tertinggal 2 minggu berturut-turut, kita bicarakan dan sesuaikan scope** — bukan menambah jam kerja fantasi.

---

## 7. Task Board (Live)

Dikelola di task board sesi (tool `todo`). Status: `pending → in_progress → completed / cancelled`. Diperbarui setiap check-in.

---

## 8. Register Risiko

| # | Risiko | Dampak | Mitigasi |
|---|---|---|---|
| 1 | Solo & part-time → progress lambat | Molor | Scope MVP ketat (§3), buffer minggu, check-in rutin, deteksi telat sejak dini |
| 2 | Tidak ada sekolah pilot untuk validasi | Fitur tak sesuai kebutuhan | Inisiasi sekolah pilot sejak M5, validasi alur tagihan/bayar dengan mereka |
| 3 | Keputusan produk berubah di tengah | Rework | Tulis keputusan di ADR/plan; scope change wajib disetujui PM |
| 4 | Eloquent N+1 / query berat saat data banyak | Aplikasi melambat | Disiplin eager loading di code review; test query |
| 5 | Data uang salah / double-charge | Trust hilang, kerugian | Desain idempotency + append-only + maker-checker (sudah di ADR) diuji ketat di M9–10 |
| 6 | (Jika online payment masuk MVP) Akun Midtrans/webhook molor | Molor MVP | Di-defer post-MVP (rekomendasi); jika masuk, mulai proses akun sejak M1 |

---

## 9. Artefak & Komunikasi

- Dokumen: `ARCHITECTURE.md`, `ADR/`, `CODING_GUIDELINES.md`, `PROJECT_PLAN.md`, `DEVOPS.md`, `SECURITY.md`
- Deploy kit (scaffold M1–2): `docker-compose.yml`, `app/Dockerfile`, `deploy/nginx/default.conf` (+`https.conf.example`), `deploy/backup.sh`, `.env.example`, `.github/workflows/ci.yml`, `.gitignore`
- Task board: tool `todo` (update tiap check-in)
- Check-in mingguan: format §6
- Keputusan baru yang signifikan → ADR baru (lihat `ADR/README.md`)

---

## 10. Workstream DevOps & Security (tambahan PM)

**Owner juga berperan DevOps/Security engineer** (didukung PM). Deliverable utama:

| Area | Artefak | Kapan |
|---|---|---|
| Infra (1 VPS + Docker Compose) | `docker-compose.yml`, `app/Dockerfile`, `deploy/nginx/*`, `deploy/backup.sh` | Scaffold siap di M1–2 · hrefup & live di M12 |
| CI/CD | `.github/workflows/ci.yml` (lint → test → audit → build → deploy), `ghcr.io` image immutabel | CI aktif sejak M1 · deploy auto di M12 |
| Keamanan aplikasi | `SECURITY.md` (threat model, OWASP, UU PDP), checklist §7 security-gate, `CODING_GUIDELINES.md` §10 | Berlaku tiap PR sejak awal |
| Keamanan infra | Bootstrap server (ssh key, UFW, fail2ban), headers CSP/HSTS, Redis DB internal-only, non-root container | M11 hardening + M12 go-live |
| Backup & DR | `deploy/backup.sh` harian terenkripsi + uji-restore bulanan (RPO ≤24j, RTO ≤4j) | M9–10 aktif · uji M11 |
| Monitoring | `/healthz`, Sentry, uptime, alert Telegram | M12 + operasional |

**Gate rilis aman:** setiap PR lolos ceklis `SECURITY.md` §7 — PM menjaganya.

---

*Dokumen ini hidup — diperbarui PM setiap check-in mingguan. Owner wajib jujur soal progress & blocker agar rencana tetap realistis.*