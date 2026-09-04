#!/usr/bin/env bash
# =====================================================================
# bootstrap.sh — Setup server produksi satu-klik (mutlak pada VPS novo).
# Jangan panik: script idempotent (jalankan ulang = jalan tanpa breaking).
#
# PREREQUISITES:
#   - SSH public key kamu DULU ditambahkan di authorized_keys saat
#     provision VPS (mis. lewat cloud provider), ATAU isi manual di bawah.
#   - Jalankan sebagai root:  sudo bash deploy/bootstrap.sh
#
# SCOPE: user deploy + SSH key-only + UFW (22/80/443) + fail2ban +
#        unattended-upgrades + Docker Engine + Compose plugin.
#
# ⚠️ Keamanan: script di-tara over-mutlak; aturan safety:
#    1. SSH hardening (PasswordAuthentication no, PermitRootLogin no)
#       HANYA aktifkan bila user deploy punya authorized_keys non-empty.
#    2. UFW enable i-WAIT trade. Jika SSH drop, aman-aman recover via
#       provider console (VNC/console) dulu.
# =====================================================================
set -euo pipefail

GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; NC='\033[0m'

info()  { echo -e "${GREEN}[INFO]${NC}  $*"; }
warn()  { echo -e "${YELLOW}[WARN]${NC}  $*"; }
err()   { echo -e "${RED}[ERROR]${NC} $*" >&2; }

[[ $EUID -eq 0 ]] || { err "Jalankan sebagai root (sudo bash $0)."; exit 1; }

info "=== Bootstrap server produksi — School Finance ==="

# ---------------------------------------------------------------- 1. deploy user
if ! id deploy &>/dev/null; then
  info "Buat user 'deploy' (non-root)..."
  useradd -m -s /bin/bash deploy
  mkdir -p /home/deploy/.ssh
  touch /home/deploy/.ssh/authorized_keys
  chown -R deploy:deploy /home/deploy/.ssh
  chmod 700 /home/deploy/.ssh
  chmod 600 /home/deploy/.ssh/authorized_keys
else
  info "User 'deploy' sudah ada."
fi

# Diberi sudo NOPASSWD hanyal singhatan docker compose (least privilege)
if ! grep -q '^deploy ' /etc/sudoers.d/deploy-docker 2>/dev/null; then
  info "Grant sudo scoped untuk docker compose (deploy)..."
  echo 'deploy ALL=(ALL) NOPASSWD: docker compose' > /etc/sudoers.d/deploy-docker
  sed -i 's/^deploy ALL=(ALL) NOPASSWD: docker compose$/deploy ALL=(ALL) NOPASSWD: docker compose, docker compose pull, docker compose up -d, docker compose down, docker compose logs, docker compose exec -T, docker compose run --rm/' /etc/sudoers.d/deploy-docker
  chmod 440 /etc/sudoers.d/deploy-docker
fi

# ---------------------------------------------------------------- 2. packages
info "Update system packages..."
apt update -y && apt upgrade -y

info "Install tools: docker, compose plugin, fail2ban, unattended-upgrades, curl..."
apt install -y --no-install-recommends \
  docker.io docker-compose-v2 \
  fail2ban unattended-upgrades curl ca-certificates

# ---------------------------------------------------------------- 3. UFW
if ! command -v ufw &>/dev/null; then
  apt install -y --no-install-recommends ufw
fi
info "Config UFW: hanya 22/80/443..."
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
# Jangan expose DB/Redis ke publik — internal Docker network semata.
ufw --force enable

# ---------------------------------------------------------------- 4. fail2ban
info "Enable fail2ban untuk SSH (ban brute-force)..."
fail2ban-client set sshd enabled true &>/dev/null || true
systemctl enable --now fail2ban || true

# ---------------------------------------------------------------- 5. unattended-upgrades
info "Activate unattended-upgrades (keamanan OS otomatis)..."
sed -i 's/^\/\/\s*"\${distro_id}:\${distro_codename}";/"${distro_id}:${distro_codename}";/' /etc/apt/apt.conf.d/50unattended-upgrades
sed -i 's/^\/\/\s*Unattended-Upgrade::Automatic-Reboot "false";/Unattended-Upgrade::Automatic-Reboot "false";/' /etc/apt/apt.conf.d/50unattended-upgrades
systemctl enable --now unattended-upgrades || true

# ---------------------------------------------------------------- 6. SSH hardening (GUARDED)
info "SSH hardening..."
if [[ -s /home/deploy/.ssh/authorized_keys ]]; then
  sed -i \
    -e 's/^#\?PermitRootLogin.*/PermitRootLogin no/' \
    -e 's/^#\?PasswordAuthentication.*/PasswordAuthentication no/' \
    -e 's/^#\?PubkeyAuthentication.*/PubkeyAuthentication yes/' \
    -e 's/^#\?ChallengeResponseAuthentication.*/ChallengeResponseAuthentication no/' \
    ${SSHD_CONFIG:-/etc/ssh/sshd_config}
  systemctl restart ssh
  info "SSH hardening aktif: root-password login matikan, key-only."
  warn "⚠️  TEST SSH dari laptop kamu DULU:  ssh deploy@<IP>"
else
  warn "skipal SSH hardening: /home/deploy/.ssh/authorized_keys EMPTI."
  warn "Isi public key kamu dulu:"
  warn "  echo 'ssh-ed25519 AAAA...' > /home/deploy/.ssh/authorized_keys"
  warn "  chown deploy:deploy /home/deploy/.ssh/authorized_keys"
  warn "dan jalankan script ulang."
fi

# ---------------------------------------------------------------- 7. docker verify
info "Verify Docker..."
systemctl enable --now docker || true
docker --version && docker compose version

info "=== BOOTSTRAP SELESAI ==="
info "Langkah nanti:"
info "  1. Clone repo ke /srv/school-finance (membangun lewat CI immutabel image)."
info "  2. Salin .env.example -> .env, isi nilai (DB_PASSWORD kuat)."
info "  3. docker compose up -d"
info "  4. Generate SSL: cf. deploy/nginx/https.conf.example (Let's Encrypt)."