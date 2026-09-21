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
[[ "$(id -u)" == 0 ]] || die "Run as root."

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
  composer nodejs npm
systemctl enable --now mysql redis-server "php${PHP_VERSION}-fpm" nginx

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

read -r -s -p "MySQL password for noc_billing: " DB_PASSWORD
echo
[[ -n "${DB_PASSWORD}" ]] || die "Database password cannot be empty."
DB_SQL_PASSWORD="$(printf '%s' "${DB_PASSWORD}" | sed "s/'/''/g")"

log "Creating database and users"
mysql <<SQL
CREATE DATABASE IF NOT EXISTS noc_billing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'noc_billing'@'localhost' IDENTIFIED BY '${DB_SQL_PASSWORD}';
ALTER USER 'noc_billing'@'localhost' IDENTIFIED BY '${DB_SQL_PASSWORD}';
CREATE USER IF NOT EXISTS 'noc_billing'@'%' IDENTIFIED BY '${DB_SQL_PASSWORD}';
ALTER USER 'noc_billing'@'%' IDENTIFIED BY '${DB_SQL_PASSWORD}';
GRANT ALL PRIVILEGES ON noc_billing.* TO 'noc_billing'@'localhost';
GRANT ALL PRIVILEGES ON noc_billing.* TO 'noc_billing'@'%';
FLUSH PRIVILEGES;
SQL

if ! mysql -NBe "SELECT 1 FROM information_schema.tables WHERE table_schema='noc_billing' AND table_name='migrations' LIMIT 1" | grep -q 1; then
  mysql noc_billing < "${APP_ROOT}/deploy/noc-billing-empty.sql"
else
  echo "Database already has a migrations table; preserving existing data."
fi

log "Writing Laravel environment"
cd "${APP_ROOT}/backend"
[[ -f .env ]] || cp .env.example .env
set_env() {
  local key="$1" value="$2" temp
  temp="$(mktemp)"
  awk -v key="$key" -v value="$value" 'index($0, key"=") == 1 { print key"="value; found=1; next } { print } END { if (!found) print key"="value }' .env > "$temp"
  install -m 0600 "$temp" .env
  rm -f "$temp"
}
set_env APP_ENV production
set_env APP_DEBUG false
set_env DB_DATABASE noc_billing
set_env DB_USERNAME noc_billing
set_env DB_PASSWORD "$DB_PASSWORD"
set_env CACHE_DRIVER redis
set_env SESSION_DRIVER redis
set_env QUEUE_CONNECTION redis
set_env REDIS_HOST 127.0.0.1
set_env REDIS_PORT 6379

chmod +x "${APP_ROOT}/deploy/install.sh"
"${APP_ROOT}/deploy/install.sh"

log "Checking application services"
services=(
  noc-billing-api.service
  noc-billing-vite.service
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

for port in 3000 8000; do
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
