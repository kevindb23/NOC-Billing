#!/usr/bin/env bash
set -Eeuo pipefail

# First-boot installer. Run with:
# curl -fsSL https://raw.githubusercontent.com/kevindb23/NOC-Billing/main/deploy/bootstrap-ubuntu.sh | sudo bash

APP_ROOT="${APP_ROOT:-/var/www/html}"
REPO_URL="${REPO_URL:-https://github.com/kevindb23/NOC-Billing.git}"
BRANCH="${BRANCH:-main}"
PHP_VERSION="${PHP_VERSION:-}"
die() { echo "ERROR: $*" >&2; exit 1; }
log() { echo; echo "==> $*"; }
check() { echo "[OK] $*"; }
[[ "$(id -u)" == 0 ]] || die "Run as root."
cd /

export DEBIAN_FRONTEND=noninteractive
if [[ -z "${PHP_VERSION}" ]]; then
  if apt-cache show php8.1-cli >/dev/null 2>&1; then PHP_VERSION=8.1; else PHP_VERSION=8.3; fi
fi
export PHP_VERSION
log "Installing bootstrap packages"
apt-get update
apt-get install -y \
  git curl ca-certificates unzip rsync build-essential \
  nginx mysql-server redis-server \
  python3 python3-venv python3-pip python3-dev \
  php${PHP_VERSION}-cli php${PHP_VERSION}-fpm php${PHP_VERSION}-common php${PHP_VERSION}-mysql php${PHP_VERSION}-redis \
  php${PHP_VERSION}-xml php${PHP_VERSION}-curl php${PHP_VERSION}-mbstring php${PHP_VERSION}-zip \
  composer
if ! command -v node >/dev/null 2>&1 || [[ "$(node -p 'process.versions.node.split(".")[0]')" -lt 20 ]]; then
  log "Installing Node.js 22"
  dpkg --configure -a || true
  dpkg --purge --force-depends libnode-dev || true
  apt-get remove -y libnode-dev || true
  apt-get -f install -y
  curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
  apt-get install -y nodejs
fi
node_major="$(node -p 'process.versions.node.split(".")[0]')"
[[ "${node_major}" -ge 20 ]] || die "Node.js 20 or newer is required; found $(node --version)."
systemctl enable --now mysql redis-server "php${PHP_VERSION}-fpm" nginx

DB_NAME="${DB_NAME:-noc_billing}"
DB_USER="${DB_USER:-noc_billing}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PASSWORD="${DB_PASSWORD:-}"
ADMIN_NAME="${ADMIN_NAME:-admin}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@example.com}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-}"
[[ -n "${DB_PASSWORD}" ]] || die "Set DB_PASSWORD before running the installer."
[[ -n "${ADMIN_PASSWORD}" ]] || die "Set ADMIN_PASSWORD before running the installer."

log "Checking out the application"
if [[ -d "${APP_ROOT}/.git" ]]; then
  git -C "${APP_ROOT}" fetch origin
  git -C "${APP_ROOT}" switch "${BRANCH}" 2>/dev/null || git -C "${APP_ROOT}" switch -c "${BRANCH}" --track "origin/${BRANCH}"
  git -C "${APP_ROOT}" pull --ff-only origin "${BRANCH}"
elif [[ -z "$(find "${APP_ROOT}" -mindepth 1 -maxdepth 1 -print -quit 2>/dev/null)" ]]; then
  git clone --branch "${BRANCH}" "${REPO_URL}" "${APP_ROOT}"
else
  die "${APP_ROOT} is not empty and is not a Git checkout. Move it aside or set APP_ROOT."
fi

# A previous detached checkout may have retained Git metadata while leaving
# tracked files deleted in the worktree. Restore only when required files are
# missing; otherwise preserve the existing checkout and local configuration.
if [[ ! -f "${APP_ROOT}/backend/artisan" || ! -f "${APP_ROOT}/frontend/package.json" ]]; then
  log "Restoring incomplete working tree from origin/${BRANCH}"
  git -C "${APP_ROOT}" restore --source "origin/${BRANCH}" --worktree --staged .
fi
[[ -f "${APP_ROOT}/deploy/noc-billing-empty.sql" ]] || die "Sanitized database dump is missing."

DB_SQL_PASSWORD="$(printf '%s' "${DB_PASSWORD}" | sed "s/'/''/g")"
DB_SQL_NAME="$(printf '%s' "${DB_NAME}" | sed 's/[^A-Za-z0-9_]/_/g')"
DB_SQL_USER="$(printf '%s' "${DB_USER}" | sed "s/'/''/g")"

log "Creating database and users"
mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_SQL_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_SQL_USER}'@'localhost' IDENTIFIED BY '${DB_SQL_PASSWORD}';
ALTER USER '${DB_SQL_USER}'@'localhost' IDENTIFIED BY '${DB_SQL_PASSWORD}';
CREATE USER IF NOT EXISTS '${DB_SQL_USER}'@'%' IDENTIFIED BY '${DB_SQL_PASSWORD}';
ALTER USER '${DB_SQL_USER}'@'%' IDENTIFIED BY '${DB_SQL_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_SQL_NAME}\`.* TO '${DB_SQL_USER}'@'localhost';
GRANT ALL PRIVILEGES ON \`${DB_SQL_NAME}\`.* TO '${DB_SQL_USER}'@'%';
FLUSH PRIVILEGES;
SQL

if ! mysql -NBe "SELECT 1 FROM information_schema.tables WHERE table_schema='${DB_SQL_NAME}' AND table_name='migrations' LIMIT 1" | grep -q 1; then
  mysql "${DB_NAME}" < "${APP_ROOT}/deploy/noc-billing-empty.sql"
  check "Imported empty database"
else
  echo "Database already has a migrations table; preserving existing data."
  check "Existing database preserved"
fi
for table in migrations permissions roles role_permissions role_assignments users; do
  mysql -NBe "SELECT COUNT(*) FROM \`${DB_SQL_NAME}\`.\`${table}\`" >/dev/null
  check "Verified ${table}"
done

log "Writing Laravel environment"
cd "${APP_ROOT}/backend"
[[ -f .env ]] || cp .env.example .env
set_env() {
  local key="$1" value="$2" temp
  local escaped="${value//\\/\\\\}"
  escaped="${escaped//\"/\\\"}"
  temp="$(mktemp)"
  awk -v key="$key" 'index($0, key"=") != 1 { print }' .env > "$temp"
  printf '%s="%s"\n' "$key" "$escaped" >> "$temp"
  install -m 0600 "$temp" .env
  rm -f "$temp"
}
set_env APP_ENV production
set_env APP_DEBUG false
set_env DB_HOST "$DB_HOST"
set_env DB_DATABASE "$DB_NAME"
set_env DB_USERNAME "$DB_USER"
set_env DB_PASSWORD "$DB_PASSWORD"
set_env CACHE_DRIVER redis
set_env SESSION_DRIVER redis
set_env QUEUE_CONNECTION redis
set_env REDIS_HOST 127.0.0.1
set_env REDIS_PORT 6379
set_env SEED_ADMIN_NAME "$ADMIN_NAME"
set_env SEED_ADMIN_EMAIL "$ADMIN_EMAIL"
set_env SEED_ADMIN_PASSWORD "$ADMIN_PASSWORD"

chmod +x "${APP_ROOT}/deploy/install.sh"
"${APP_ROOT}/deploy/install.sh"

log "Applying super-admin account"
ADMIN_HASH="$(php -r 'echo password_hash($argv[1], PASSWORD_BCRYPT);' "${ADMIN_PASSWORD}")"
ADMIN_SQL_NAME="$(printf '%s' "${ADMIN_NAME}" | sed "s/'/''/g")"
ADMIN_SQL_EMAIL="$(printf '%s' "${ADMIN_EMAIL}" | sed "s/'/''/g")"
ADMIN_SQL_HASH="$(printf '%s' "${ADMIN_HASH}" | sed "s/'/''/g")"
mysql "${DB_NAME}" <<SQL
UPDATE users SET name='${ADMIN_SQL_NAME}', email='${ADMIN_SQL_EMAIL}', password='${ADMIN_SQL_HASH}', status='active' WHERE id=1;
SQL
check "Super-admin account configured"

log "Checking application services"
services=(
  noc-billing-api.service
  noc-billing-queue.service
  olt-session.service
  bng-session.service
  router-session.service
)
for service in "${services[@]}"; do
  systemctl is-active --quiet "$service" || {
    systemctl --no-pager --full status "$service" || true
    die "$service did not start successfully. Check: journalctl -u $service -n 100 --no-pager"
  }
done

for port in 80 3000 8000; do
  for attempt in $(seq 1 20); do
    if (echo > "/dev/tcp/127.0.0.1/${port}") 2>/dev/null; then break; fi
    [[ "$attempt" == 20 ]] && die "Nothing is listening on port ${port}."
    sleep 1
  done
done

api_status="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 10 http://127.0.0.1:8000/api/v1/acs-servers || true)"
[[ "$api_status" == "401" || "$api_status" == "403" ]] || die "Laravel API health check failed with HTTP ${api_status:-000}."
curl -fsS --max-time 10 http://127.0.0.1:3000/ >/dev/null || die "Frontend health check failed."

SERVER_IP="$(hostname -I | awk '{print $1}')"

cat <<EOF

Installation completed.
Use: sudo systemctl --failed
Frontend: http://${SERVER_IP}:3000
API:      http://${SERVER_IP}:8000
Nginx:    http://${SERVER_IP}:80
EOF
