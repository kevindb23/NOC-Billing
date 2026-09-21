# Northstar ISP Billing

Northstar is a Laravel 10 API and React/Vite operations portal for ISP billing, subscriber activation, OLT/ONT, BNG, router, RADIUS, and GenieACS workflows.

## Stack

- Laravel 10 / PHP 8.1+
- MySQL 8+
- Redis-ready queues, cache, and sessions
- React / TypeScript / Vite / Tailwind CSS
- Nginx + PHP-FPM for production

Network automation services are included as systemd-managed OLT, BNG, router, and queue workers.

## Local setup

Create the MySQL database and application user first. The repository includes [`deploy/noc-billing-database.sql.example`](deploy/noc-billing-database.sql.example). Create a local copy, replace the placeholder, and run it:

```bash
cp /var/www/html/deploy/noc-billing-database.sql.example /var/www/html/deploy/noc-billing-database.sql
sed -i "s/CHANGE_ME_DATABASE_PASSWORD/your-real-password/g" /var/www/html/deploy/noc-billing-database.sql
sudo mysql < /var/www/html/deploy/noc-billing-database.sql
```

If the database or user already exists, the SQL file updates the password and privileges. The generated `.sql` file is ignored by Git because it contains credentials.

```sql
ALTER USER 'noc_billing'@'localhost' IDENTIFIED BY 'your-real-password';
```

Then configure Laravel:

```bash
cd /var/www/html/backend
cp .env.example .env
# Replace CHANGE_ME_DATABASE_PASSWORD in .env with the MySQL user's real password
composer install
php artisan key:generate
php artisan config:clear
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8000
```

The `DB_USERNAME`, `DB_PASSWORD`, `DB_DATABASE`, and `DB_HOST` values in `.env` must match the MySQL account. If MySQL is on another host, use that host instead of `127.0.0.1`.

In another terminal:

```bash
cd /var/www/html/frontend
npm install
npm run dev
```

Set `VITE_API_URL=http://127.0.0.1:8000/api/v1` in `frontend/.env.local` when Vite is not reverse-proxied through Nginx.

The seed account is controlled by `SEED_ADMIN_EMAIL` and `SEED_ADMIN_PASSWORD`. Change those values before seeding any shared environment.

## Install from a release with wget

```bash
cd /var/www
wget -O noc-billing-release.tar.gz https://github.com/kevindb23/NOC-Billing/releases/latest/download/noc-billing.tar.gz
tar -xzf noc-billing-release.tar.gz -C /var/www/html --strip-components=1
cd /var/www/html/backend
cp .env.example .env
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
```

Never commit passwords, tokens, private keys, or production `.env` files. `CHANGE_ME_DATABASE_PASSWORD` is only a placeholder and must be replaced before running migrations.

## Verification

```bash
cd /var/www/html/backend && php artisan test
cd /var/www/html/frontend && npm test && npm run lint && npm run build
```

Production should use Nginx and PHP-FPM, not `php artisan serve` or `npm run dev`. See [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) and [`docs/UNINSTALL.md`](docs/UNINSTALL.md).

## Complete Ubuntu installation

This is the recommended from-scratch installation for Ubuntu 22.04/24.04 LTS. It assumes the application will live at `/var/www/html`.

### 1. Download the repository

```bash
sudo apt update
sudo apt install -y git curl ca-certificates
sudo mkdir -p /var/www/html
sudo git clone https://github.com/kevindb23/NOC-Billing.git /var/www/html
cd /var/www/html
sudo git checkout v1.0.2
```

### 2. Create MySQL and import the empty starter database

Replace the password placeholder with a strong password before running this command:

```bash
sudo mysql <<'SQL'
CREATE DATABASE IF NOT EXISTS noc_billing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'noc_billing'@'localhost' IDENTIFIED BY 'CHANGE_ME_DATABASE_PASSWORD';
ALTER USER 'noc_billing'@'localhost' IDENTIFIED BY 'CHANGE_ME_DATABASE_PASSWORD';
GRANT ALL PRIVILEGES ON noc_billing.* TO 'noc_billing'@'localhost';
FLUSH PRIVILEGES;
SQL
sudo mysql noc_billing < deploy/noc-billing-empty.sql
```

`deploy/noc-billing-empty.sql` contains the current schema, migration history, permissions, the Administrator role, and one administrator user. It contains no customer, billing, network, device, token, or operational records. The included login is `admin@example.com`; its password is stored only as a hash and must be reset immediately after first login.

### 3. Configure Laravel

```bash
cd /var/www/html/backend
cp .env.example .env
nano .env
```

Set at least:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=http://YOUR_SERVER_IP
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=noc_billing
DB_USERNAME=noc_billing
DB_PASSWORD=YOUR_DATABASE_PASSWORD
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
SEED_ADMIN_EMAIL=admin@example.com
SEED_ADMIN_PASSWORD=CHANGE_THIS_PASSWORD
```

Never commit `.env`, passwords, private keys, device credentials, or API tokens.

### 4. Run the installer

The installer installs PHP/Composer, Node, Python/Netmiko, MySQL, Redis, Nginx, PHP-FPM, frontend dependencies, backend dependencies, and all application services. It does not replace `.env` or delete database records.

```bash
cd /var/www/html
sudo chmod +x deploy/install.sh
sudo ./deploy/install.sh
```

### 5. Services

The installer creates and enables:

```text
noc-billing-api.service       Laravel API on port 8000, five workers
noc-billing-vite.service      Vite frontend on port 3000
noc-billing-queue.service     Laravel Redis queue worker
olt-session.service           Persistent OLT/Netmiko sessions
bng-session.service           Persistent BNG sessions
router-session.service        Persistent router sessions
nginx.service                 Frontend and reverse proxy
php8.1-fpm.service             Laravel FastCGI runtime
redis-server.service           Cache, sessions, and queues
mysql.service                 Database
```

Verify them:

```bash
sudo systemctl --failed
sudo systemctl status noc-billing-api noc-billing-vite noc-billing-queue
sudo systemctl status olt-session bng-session router-session
sudo ss -ltnp | grep -E ':3000|:8000|:80'
```

Do not run `php artisan serve` or `npm run dev` manually after enabling these services. Manual processes can occupy ports 8000/3000 and cause proxy errors.

### 6. Open and configure the system

For setup and development:

```text
Frontend: http://YOUR_SERVER_IP:3000
API:      http://YOUR_SERVER_IP:8000
```

For production, serve `frontend/dist` through Nginx on port 80/443. The installer builds the frontend and installs `deploy/nginx-noc-billing.conf`. Update its `server_name`, then run `sudo nginx -t && sudo systemctl reload nginx`.

After login, configure OLT endpoints and credentials, VLAN/QinQ/TR-069 profiles, BNG interfaces and RADIUS, router sessions, ACS/GenieACS credentials, and subscriber PPP credentials. The installer intentionally provides no device credentials.

### Logs and troubleshooting

```bash
sudo journalctl -u noc-billing-api -f
sudo journalctl -u noc-billing-vite -f
sudo tail -f /var/www/html/backend/storage/logs/laravel.log
sudo journalctl -u olt-session -f
sudo journalctl -u bng-session -f
sudo journalctl -u router-session -f
```

If Vite reports `ECONNREFUSED 127.0.0.1:8000`, run `sudo systemctl restart noc-billing-api`. If a port is occupied, identify the process first with `sudo ss -ltnp 'sport = :8000'` or `sudo ss -ltnp 'sport = :3000'`.

Laravel runtime permissions can be repaired with:

```bash
sudo chown -R www-data:www-data backend/storage backend/bootstrap/cache
sudo chmod -R u+rwX,g+rwX backend/storage backend/bootstrap/cache
```

### Backup and update

```bash
sudo mysqldump --single-transaction --routines --triggers noc_billing > /root/noc_billing-$(date +%F).sql
sudo cp backend/.env /root/noc_billing.env-$(date +%F)
git fetch --tags origin
sudo git checkout v1.0.2
sudo ./deploy/install.sh
sudo systemctl restart noc-billing-api noc-billing-vite noc-billing-queue
```

### Security

- Change the initial administrator password immediately.
- Keep `APP_DEBUG=false` outside development.
- Use HTTPS and firewall ports 3000 and 8000 when production traffic is served by Nginx.
- Keep `.env` and live database dumps out of Git.
- Use separate credentials for MySQL, OLTs, BNGs, routers, RADIUS, and GenieACS.
- The sanitized SQL dump contains an administrator password hash, never a plaintext password.
