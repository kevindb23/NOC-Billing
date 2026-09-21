#!/usr/bin/env bash
set -Eeuo pipefail

# First-boot installer. Run with:
# curl -fsSL https://raw.githubusercontent.com/kevindb23/NOC-Billing/main/deploy/bootstrap-ubuntu.sh | sudo bash

APP_ROOT="${APP_ROOT:-/var/www/html}"
REPO_URL="${REPO_URL:-https://github.com/kevindb23/NOC-Billing.git}"
BRANCH="${BRANCH:-main}"
die() { echo "ERROR: $*" >&2; exit 1; }
log() { echo; echo "==> $*"; }
[[ "$(id -u)" == 0 ]] || die "Run as root."

export DEBIAN_FRONTEND=noninteractive
log "Installing bootstrap packages"
apt-get update
apt-get install -y git curl ca-certificates mysql-server
systemctl enable --now mysql

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

cat <<EOF

Installation completed.
Use: sudo systemctl --failed
Frontend: http://$(hostname -I | awk '{print $1}'):3000
API:      http://$(hostname -I | awk '{print $1}'):8000
EOF
