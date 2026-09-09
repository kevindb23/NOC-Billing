# Uninstall and Purge

The application and database are separate. Confirm the target host and backup requirements before purging production data.

## Stop dedicated processes

```bash
sudo systemctl disable --now nginx
sudo systemctl disable --now php8.1-fpm
sudo systemctl disable --now redis-server
```

Stop only services dedicated to this installation.

## Remove application files

```bash
sudo rm -rf /var/www/html/backend
sudo rm -rf /var/www/html/frontend
sudo rm -rf /var/www/html/frontend/dist
```

Review the paths before deleting. Documentation and `MASTER.md` can be retained separately.

## Purge the database

Back up first, then replace the database name as necessary:

```bash
mysqldump --single-transaction --routines --events noc_billing > noc_billing-backup.sql
mysql -u root -p -e "DROP DATABASE IF EXISTS noc_billing;"
mysql -u root -p -e "DROP USER IF EXISTS 'noc_billing'@'localhost';"
```

Database deletion is irreversible unless the backup is retained. Do not run these commands against a shared database.
