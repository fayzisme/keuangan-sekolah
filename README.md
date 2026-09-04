# School Finance System

Platform keuangan sekolah (multi-school SaaS): tagihan SPP, pembayaran tunai/online, pembukuan, laporan.

**Stack:** Laravel 12 API + React 18 + Vite 6 + TypeScript SPA, PostgreSQL 16 + Redis 7, Docker Compose.

---

## Quick Start (Development)

```bash
# 1. Clone & setup env
cp .env.example .env
# Edit .env: DB_PASSWORD, APP_KEY, PLATFORM_KEY, dll

# 2. Build & run
make up

# 3. Health check
curl http://localhost/healthz
# {"status":"ok","db":true,"cache":true}

# 4. Frontend dev (opsional)
make web-install
cd web && npm run dev
```

---

## API Documentation

OpenAPI spec di-generate otomatis via `dedoc/scramble`:

```bash
make api-generate
# Output: web/src/api/generated/*.ts (typed client)
```

---

## Milestone Progress

| # | Milestone | Status |
|---|-----------|--------|
| M1-2 | Fondasi (Laravel + React + Docker + CI) | ✅ |
| M3-4 | Auth & RBAC (Sanctum + Spatie teams) | ✅ |
| M5-6 | Master Data (sekolah, kelas, murid, guardian) | ✅ |
| M7-8 | Tagihan & Invoice (monthly/one_time, anti-double) | ✅ |
| M9-10 | Pembayaran Tunai (maker-checker, ledger, kuitansi) | ✅ |
| M11 | Laporan & Hardening | 🔄 In Progress |
| M12 | Pilot & Go-Live | ⏳ Pending |

---

## Testing

```bash
# Backend (Pest)
docker compose run --rm app vendor/bin/pest

# Frontend
cd web && npm run lint && npm run build
```

---

## Deployment (Production)

Lihat [`docs/DEVOPS.md`](docs/DEVOPS.md) untuk:
- Bootstrap server (`deploy/bootstrap.sh`)
- Docker Compose + Nginx + HTTPS (Let's Encrypt)
- Backup harian (`deploy/backup.sh`)
- CI/CD GitHub Actions
- Security hardening checklist (`docs/SECURITY.md`)

---

## Security

- Multi-tenant shared DB + `school_id` isolation (Global Scope + middleware)
- Uang selalu **integer cents** (`*_cents`)
- Ledger **append-only** (reversing entry untuk koreksi)
- Maker-checker pembayaran (creator ≠ verifier)
- Idempotency: `gateway_trx_id` UNIQUE + `Idempotency-Key` header
- Rate-limit login + API
- CSP, HSTS, X-Frame-Options via Nginx

---

## License

Proprietary — School Finance System.