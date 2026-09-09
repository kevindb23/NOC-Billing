# Native Linux Deployment

This application is designed for Nginx, PHP-FPM, MySQL 8, and Redis on a native Linux host.

## Build

Provision the database before running migrations:

```sql
CREATE DATABASE noc_billing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'noc_billing'@'localhost' IDENTIFIED BY 'your-real-password';
GRANT ALL PRIVILEGES ON noc_billing.* TO 'noc_billing'@'localhost';
FLUSH PRIVILEGES;
```

Set matching `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` values in `backend/.env`, then clear cached configuration.

```bash
cd /var/www/html/frontend
npm ci
npm run build

cd /var/www/html/backend
composer install --no-dev --optimize-autoloader
php artisan config:clear
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

The example configuration is in [`deploy/nginx-noc-billing.conf`](../deploy/nginx-noc-billing.conf). It serves `/var/www/html/frontend/dist`, routes unknown frontend paths to `index.html`, and passes `/api` to Laravel through PHP-FPM. Keep `.env` outside the public document root and restrict its permissions.

Run queue workers through systemd when queued jobs are enabled and schedule `php artisan schedule:run` through a systemd timer or cron. Back up MySQL and application storage before migrations.
