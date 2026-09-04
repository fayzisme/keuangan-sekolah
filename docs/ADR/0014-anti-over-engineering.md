# ADR-0014: Anti Over-Engineering — Scope Fase Awal

- **Status:** Accepted
- **Tanggal:** 2026-08-28

## Konteks
- Tim kecil, greenfield, skala menengah. Godaan memakai teknologi "keren" sejak hari pertama sangat besar.

## Keputusan
Secara eksplisit **TIDAK memakai di fase 1**:
- ~~Microservices~~ → karena ADR-0001 modular monolith sudah memenuhi kebutuhan, jalur ekstraksi terbuka.
- ~~Kubernetes~~ → Docker Compose + 1 VPS cukup; K8s datang hanya jika perlu orkestrasi multi-node sungguhan.
- ~~Event sourcing / CQRS penuh~~ → append-only ledger (ADR-0008) memberi audit; CQRS penuh menunggu bukti kebutuhan baca/tulis terpisah.
- ~~Multiple database~~ → satu PostgreSQL (ADR-0006); split hanya jika benar-benar ada modul dengan pola akses ekstrem.
- ~~AI/ML~~ → bukan kebutuhan inti saat ini.

Keputusan ini di-review ulang (revisit) saat indikator berikut terpenuhi: paging/satu modul butuh skala independen, tim tumbuh > 5 dev, atau ada data/eksploitasi yang menuntut isolasi.

## Konsekuensi
Positif:
- Fokus ke nilai bisnis & correctness uang.
- Biaya operasional & ongkos belajar tim tetap rendah.
- Arsitektur tetap "naik kelas" tanpa rewrite berkat batas modul yang dijaga.

Negatif:
- Merasa "tertinggal" teknologi — diterima secara sadar; di-defer, bukan ditolak permanen.

## Referensi
- [ARCHITECTURE.md](../ARCHITECTURE.md) §15