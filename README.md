# Northstar ISP Billing

The first milestone of ISP-in-a-Box: a tenant-safe billing and operations portal built around [`MASTER.md`](MASTER.md) and [`docs/DATABASE-DESIGN.md`](docs/DATABASE-DESIGN.md).

## Stack

- Laravel 10 / PHP 8.1+
- MySQL 8+
- Redis-ready queues, cache, and sessions
- React / TypeScript / Vite / Tailwind CSS
- Nginx + PHP-FPM for production

Network automation, FreeRADIUS, BNG, OLT/ONU, ACS, IPAM, and provisioning are intentionally outside this first billing milestone.

## Local setup

Create the MySQL database and application user first. Use a strong password and replace the example value everywhere below:

```bash
sudo mysql
```

```sql
CREATE DATABASE noc_billing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'noc_billing'@'localhost' IDENTIFIED BY 'CHANGE_ME_DATABASE_PASSWORD';
GRANT ALL PRIVILEGES ON noc_billing.* TO 'noc_billing'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

If the database or user already exists, update the password instead of rerunning `CREATE`:

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
