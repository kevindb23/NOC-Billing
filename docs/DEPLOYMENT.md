# Native Linux Deployment

This application is designed for Nginx, PHP-FPM, MySQL 8, and Redis on a native Linux host.

## Build

```bash
cd /var/www/html/frontend
npm ci
npm run build

cd /var/www/html/backend
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

The example configuration is in [`deploy/nginx-noc-billing.conf`](../deploy/nginx-noc-billing.conf). It serves `/var/www/html/frontend/dist`, routes unknown frontend paths to `index.html`, and passes `/api` to Laravel through PHP-FPM. Keep `.env` outside the public document root and restrict its permissions.

Run queue workers through systemd when queued jobs are enabled and schedule `php artisan schedule:run` through a systemd timer or cron. Back up MySQL and application storage before migrations.
