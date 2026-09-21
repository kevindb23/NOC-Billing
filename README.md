# Northstar ISP Billing

Northstar is an ISP billing and network operations system with subscriber activation, OLT/ONT, BNG, router, RADIUS, and GenieACS integrations.

## Current technology stack

- Ubuntu 22.04/24.04 LTS
- Laravel/PHP API running behind Nginx and PHP-FPM
- MySQL database
- Redis queues, cache, and sessions
- React application built with Vite
- Tailwind CSS for the frontend interface
- Python services using Netmiko for persistent OLT, BNG, and router sessions
- FreeRADIUS-compatible subscriber synchronization through `isp_subscribers` and `radcheck`
- GenieACS integration for TR-069/ONT provisioning

The production runtime is Nginx plus PHP-FPM serving the compiled frontend assets. The frontend is not run with the Vite development server in production.

## Release v1.0.3

This release includes:

- Superadmin-only PPP password reveal from subscriber details.
- Immediate subscriber credential synchronization to the RADIUS database.
- Cleanup of `isp_subscribers` and `radcheck` when a subscriber is permanently deleted.
- Improved RADIUS backfill and subscriber status handling.
- Activation, QinQ, BNG VLAN, and provisioning fixes included in the current deployment.

## Supported installation method

Use the single bootstrap installer below on a fresh Ubuntu 22.04/24.04 LTS server. Do not manually run `composer`, `npm`, `php artisan serve`, or `npm run dev` during installation.

### 1. Run the bootstrap installer

```bash
wget -qO /tmp/noc-billing-bootstrap.sh https://raw.githubusercontent.com/kevindb23/NOC-Billing/main/deploy/bootstrap-ubuntu.sh
sudo env DB_PASSWORD='replace-with-db-password' ADMIN_PASSWORD='replace-with-admin-password' \
  bash /tmp/noc-billing-bootstrap.sh
rm -f /tmp/noc-billing-bootstrap.sh
```

The installer will:

- Install MySQL, Redis, Nginx, PHP, PHP-FPM, Composer, Python, and build tools.
- Install the Laravel and Netmiko dependencies.
- Clone or repair the repository in `/var/www/html`.
- Create the `noc_billing` database and users.
- Import the empty starter database.
- Create and configure Laravel `.env`.
- Configure Nginx and PHP-FPM as the application runtime.
- Install and enable the application services.
- Verify that the API and frontend are accessible before reporting success.

The installer is non-interactive. `DB_NAME`, `DB_USER`, `DB_HOST`, `ADMIN_NAME`, and `ADMIN_EMAIL` have safe defaults; `DB_PASSWORD` and `ADMIN_PASSWORD` must be supplied at runtime and are never stored in this public repository. You can override any of these variables with `sudo env` before the script path.

### 2. Open the application

After the installer completes, it prints the server IP and URLs:

```text
Application: http://SERVER_IP
```

Log in with the administrator account retained in the empty database, then change its password immediately.

### Services installed

The installer creates and starts:

```text
noc-billing-queue.service     Laravel Redis queue worker
olt-session.service           Persistent OLT/Netmiko session service
bng-session.service           Persistent BNG session service
router-session.service        Persistent router session service
nginx.service                 Web server and reverse proxy
php8.1/8.3-fpm.service        Laravel PHP runtime
redis-server.service           Cache, sessions, and queues
mysql.service                 Database
```

Check the installation:

```bash
sudo systemctl --failed
sudo systemctl status nginx php8.1-fpm noc-billing-queue
sudo systemctl status olt-session bng-session router-session
```

Nginx serves the compiled React/Vite assets on port 80 and forwards `/api` requests to PHP-FPM. Do not run `php artisan serve` or the Vite preview/development server as a production service.

## Logs and troubleshooting

```bash
sudo journalctl -u nginx -f
sudo tail -f /var/www/html/backend/storage/logs/laravel.log
sudo journalctl -u olt-session -f
sudo journalctl -u bng-session -f
sudo journalctl -u router-session -f
```

If the application reports an API connection error:

```bash
sudo systemctl restart nginx php8.1-fpm
sudo systemctl status nginx php8.1-fpm
```

If a port is already occupied:

```bash
  sudo ss -ltnp 'sport = :80'
```

## Database and credentials

The installer imports `deploy/noc-billing-empty.sql`. It contains the schema, migration history, permissions, the Administrator role, and one administrator user. It does not contain customer, billing, network, device, token, or operational records.

The dump contains only a password hash, never a plaintext password. Change the administrator password immediately after first login. Device credentials for OLTs, BNGs, routers, RADIUS, and GenieACS must be configured in the application.

## Uninstall

The uninstaller is a dry run unless `--confirm` is supplied:

```bash
wget -qO- https://raw.githubusercontent.com/kevindb23/NOC-Billing/main/deploy/uninstaller.sh | sudo bash
```

To remove the application services and Nginx configuration:

```bash
wget -qO- https://raw.githubusercontent.com/kevindb23/NOC-Billing/main/deploy/uninstaller.sh | sudo bash -s -- --confirm
```

To also remove application files and the database:

```bash
wget -qO- https://raw.githubusercontent.com/kevindb23/NOC-Billing/main/deploy/uninstaller.sh | sudo bash -s -- --confirm --remove-app --remove-database
```

Shared Ubuntu packages are preserved because other applications may use MySQL, Redis, PHP, Node.js, Nginx, or Python.

## Security

- Use HTTPS for production access.
- Keep internal service ports restricted when Nginx is the public entry point.
- Never commit `.env`, passwords, private keys, API tokens, or device credentials.
- Set `APP_DEBUG=false` outside development.
- Back up the database before uninstalling or upgrading.
