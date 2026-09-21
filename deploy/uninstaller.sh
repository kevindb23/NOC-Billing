#!/usr/bin/env bash
set -Eeuo pipefail

APP_ROOT="${APP_ROOT:-/var/www/html}"
REMOVE_APP=false
REMOVE_DATABASE=false
CONFIRM=false

usage() {
  cat <<EOF
Northstar ISP Billing uninstaller

Default behavior is a dry run. Nothing is changed without --confirm.

Usage:
  sudo bash deploy/uninstaller.sh --confirm
  sudo bash deploy/uninstaller.sh --confirm --remove-app
  sudo bash deploy/uninstaller.sh --confirm --remove-app --remove-database

Options:
  --confirm          Perform the uninstall.
  --remove-app      Remove the application directory.
  --remove-database Remove the noc_billing database and users.
  --app-root PATH   Use a different application directory.
  --help            Show this help.
EOF
}

log() { echo "==> $*"; }
die() { echo "ERROR: $*" >&2; exit 1; }

while [[ $# -gt 0 ]]; do
  case "$1" in
    --confirm) CONFIRM=true ;;
    --remove-app) REMOVE_APP=true ;;
    --remove-database) REMOVE_DATABASE=true ;;
    --app-root) shift; APP_ROOT="${1:?Missing value for --app-root}" ;;
    --help|-h) usage; exit 0 ;;
    *) die "Unknown option: $1" ;;
  esac
  shift
done

UNITS=(noc-billing-api.service noc-billing-vite.service noc-billing-queue.service olt-session.service bng-session.service router-session.service)
echo "Northstar ISP Billing uninstall plan"
echo "Application root: ${APP_ROOT}"
echo "Remove app files: ${REMOVE_APP}"
echo "Remove database:   ${REMOVE_DATABASE}"

if ! $CONFIRM; then
  echo "Dry run only. Add --confirm to perform the uninstall."
  exit 0
fi

[[ "$(id -u)" == 0 ]] || die "Run as root."
[[ "$APP_ROOT" != "/" && "$APP_ROOT" != "" ]] || die "Refusing to use / as APP_ROOT."

log "Stopping application services"
for unit in "${UNITS[@]}"; do systemctl disable --now "$unit" 2>/dev/null || true; done

log "Removing application systemd units"
for unit in "${UNITS[@]}"; do rm -f "/etc/systemd/system/${unit}"; done
systemctl daemon-reload
systemctl reset-failed 2>/dev/null || true

log "Removing Nginx application site"
rm -f /etc/nginx/sites-enabled/noc-billing.conf /etc/nginx/sites-available/noc-billing.conf
if command -v nginx >/dev/null 2>&1; then nginx -t && systemctl reload nginx || true; fi

if $REMOVE_DATABASE; then
  command -v mysql >/dev/null 2>&1 || die "mysql client is not installed."
  log "Removing application database and users"
  mysql <<'SQL'
DROP DATABASE IF EXISTS noc_billing;
DROP USER IF EXISTS 'noc_billing'@'localhost';
DROP USER IF EXISTS 'noc_billing'@'%';
FLUSH PRIVILEGES;
SQL
fi

if $REMOVE_APP; then
  [[ "$APP_ROOT" == /var/www/html || "$APP_ROOT" == /var/www/html/* ]] || die "Refusing to remove an unapproved application path."
  [[ -d "$APP_ROOT" ]] || die "Application directory does not exist: $APP_ROOT"
  log "Removing application files"
  rm -rf -- "$APP_ROOT"
else
  echo "Application files preserved at ${APP_ROOT}."
fi

echo "Uninstall completed. Shared OS packages and their data were preserved."
