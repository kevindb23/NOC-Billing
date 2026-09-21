#!/usr/bin/env bash
set -Eeuo pipefail

# Northstar ISP Billing installer.
# Run from the application checkout as root. Existing .env and databases are preserved.

APP_ROOT="${APP_ROOT:-/var/www/html}"
BACKEND="${APP_ROOT}/backend"
FRONTEND="${APP_ROOT}/frontend"
PYTHON_VENV="${BACKEND}/.venv"
PHP_VERSION="${PHP_VERSION:-8.1}"

die() { echo "ERROR: $*" >&2; exit 1; }
log() { echo; echo "==> $*"; }

[[ "$(id -u)" == 0 ]] || die "Run this installer as root."
[[ -f "${BACKEND}/artisan" ]] || die "Laravel backend not found at ${BACKEND}. Set APP_ROOT if needed."
[[ -f "${FRONTEND}/package.json" ]] || die "Frontend not found at ${FRONTEND}."

export DEBIAN_FRONTEND=noninteractive

log "Installing OS packages"
apt-get update
apt-get install -y \
  nginx mysql-server redis-server curl ca-certificates git unzip rsync \
  python3 python3-venv python3-pip python3-dev build-essential \
  php${PHP_VERSION}-fpm php${PHP_VERSION}-cli php${PHP_VERSION}-common \
  php${PHP_VERSION}-mysql php${PHP_VERSION}-redis php${PHP_VERSION}-xml \
  php${PHP_VERSION}-curl php${PHP_VERSION}-mbstring php${PHP_VERSION}-zip \
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

log "Enabling infrastructure services"
systemctl enable --now nginx mysql redis-server "php${PHP_VERSION}-fpm"

log "Installing backend dependencies"
cd "${BACKEND}"
[[ -f .env ]] || cp .env.example .env
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan key:generate --force
php artisan storage:link || true
php artisan migrate --force
php artisan optimize

log "Installing Python network automation dependencies"
python3 -m venv "${PYTHON_VENV}"
"${PYTHON_VENV}/bin/pip" install --upgrade pip
if [[ -f "${BACKEND}/requirements.txt" ]]; then
  "${PYTHON_VENV}/bin/pip" install -r "${BACKEND}/requirements.txt"
else
  "${PYTHON_VENV}/bin/pip" install netmiko paramiko
fi

log "Building frontend"
cd "${FRONTEND}"
npm ci
# Vite's optimized dependency cache is tied to the installed lockfile. Remove
# stale optimized modules after npm ci so browsers do not receive 504 responses.
rm -rf "${FRONTEND}/node_modules/.vite" "${FRONTEND}/node_modules/.vite-temp"
install -d -o www-data -g www-data -m 0775 "${FRONTEND}/node_modules/.vite-temp"
npm run build

log "Installing Nginx site"
install -m 0644 "${APP_ROOT}/deploy/nginx-noc-billing.conf" /etc/nginx/sites-available/noc-billing.conf
sed -i "s#root /var/www/html/frontend/dist;#root ${FRONTEND}/dist;#; s#SCRIPT_FILENAME /var/www/html/backend/public/index.php;#SCRIPT_FILENAME ${BACKEND}/public/index.php;#; s#DOCUMENT_ROOT /var/www/html/backend/public#DOCUMENT_ROOT ${BACKEND}/public#" /etc/nginx/sites-available/noc-billing.conf
ln -sfn /etc/nginx/sites-available/noc-billing.conf /etc/nginx/sites-enabled/noc-billing.conf
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx

log "Installing session and queue services"
install -d -o www-data -g www-data -m 0750 "${BACKEND}/storage" "${BACKEND}/bootstrap/cache"
install -d -o root -g www-data -m 0770 /var/log/noc-billing
for unit in olt-session.service bng-session.service router-session.service; do
  install -m 0644 "${BACKEND}/systemd/${unit}" "/etc/systemd/system/${unit}"
done

cat > /etc/systemd/system/noc-billing-queue.service <<EOF
[Unit]
Description=Northstar ISP Billing Laravel queue worker
After=redis-server.service mysql.service php${PHP_VERSION}-fpm.service
Requires=redis-server.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=${BACKEND}
ExecStart=/usr/bin/php ${BACKEND}/artisan queue:work redis --sleep=3 --tries=3 --timeout=120 --max-time=3600
Restart=always
RestartSec=5
KillSignal=SIGTERM
TimeoutStopSec=30

[Install]
WantedBy=multi-user.target
EOF

cat > /etc/systemd/system/noc-billing-api.service <<EOF
[Unit]
Description=Northstar ISP Billing Laravel API
After=network.target mysql.service redis-server.service
Wants=mysql.service redis-server.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=${BACKEND}
Environment=APP_ENV=production
Environment=PHP_CLI_SERVER_WORKERS=5
ExecStart=/usr/bin/php ${BACKEND}/artisan serve --host=0.0.0.0 --port=8000
Restart=always
RestartSec=5
KillSignal=SIGTERM
TimeoutStopSec=30

[Install]
WantedBy=multi-user.target
EOF

cat > /etc/systemd/system/noc-billing-vite.service <<EOF
[Unit]
Description=Northstar ISP Billing frontend server
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=${FRONTEND}
Environment=NODE_ENV=production
ExecStart=/usr/bin/npm run preview -- --host 0.0.0.0 --port 3000
Restart=always
RestartSec=5
KillSignal=SIGTERM
TimeoutStopSec=30

[Install]
WantedBy=multi-user.target
EOF

chown -R www-data:www-data "${BACKEND}/storage" "${BACKEND}/bootstrap/cache"
systemctl daemon-reload
systemctl enable --now olt-session.service bng-session.service router-session.service noc-billing-queue.service noc-billing-api.service noc-billing-vite.service

log "Validating services"
systemctl --no-pager --full --failed || true
systemctl is-active --quiet nginx || die "nginx is not active"
systemctl is-active --quiet "php${PHP_VERSION}-fpm" || die "PHP-FPM is not active"
systemctl is-active --quiet redis-server || die "Redis is not active"

cat <<EOF

Installation completed.

Application: ${APP_ROOT}
Web root:    ${FRONTEND}/dist
Laravel:     ${BACKEND}
Session services:
  olt-session.service
  bng-session.service
  router-session.service
Queue:       noc-billing-queue.service
API:         noc-billing-api.service (port 8000, five workers)
Frontend:    noc-billing-vite.service (port 3000)

Before exposing the system, review ${BACKEND}/.env and set APP_ENV=production,
APP_DEBUG=false, database credentials, mail settings, and trusted application URL.
EOF
