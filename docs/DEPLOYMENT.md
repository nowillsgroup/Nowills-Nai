# Deployment (PHP + MySQL, no Docker)

## 1. Server requirements

- Ubuntu/Debian or similar Linux host
- Nginx or Apache
- PHP-FPM 8.2+ with `pdo_mysql`, `mbstring`, `openssl`, `xml`, `xmlwriter`, `curl`, `zip`, `ctype`, `fileinfo`, `tokenizer`
- MySQL 8 or MariaDB 10.6+
- Composer 2

## 2. Database

```sql
CREATE DATABASE nai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'nai'@'localhost' IDENTIFIED BY 'strong-password';
GRANT ALL PRIVILEGES ON nai.* TO 'nai'@'localhost';
FLUSH PRIVILEGES;
```

## 3. Application

```bash
git clone <your-repo-url> nai
cd nai
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

Configure `.env` for MySQL, set `APP_ENV=production`, `APP_DEBUG=false`, `NAI_DEMO_MODE=false`, `SESSION_SECURE_COOKIE=true`, and your Arena AI endpoint. Enable scheduled monitoring with `NAI_MONITORING_ENABLED=true` if appropriate.

```bash
php artisan migrate --force
php artisan nai:create-admin
php artisan nai:check
php artisan optimize
```

Set permissions:

```bash
chown -R www-data:www-data storage bootstrap/cache
find storage bootstrap/cache -type d -exec chmod 775 {} \;
```

## 4. Nginx example

```nginx
server {
    listen 80;
    server_name nai.nowills.group;
    root /var/www/nai/public;
    index index.php;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
```

Enable TLS with your certificate manager. Set `APP_URL=https://nai.nowills.group`.

## 5. Arena AI

Configure `ARENA_BASE_URL` and `ARENA_API_KEY`, then:

```bash
php artisan arena:test
```

## 6. Scheduler and monitoring

Add the Laravel scheduler cron entry:

```cron
* * * * * cd /var/www/nai && php artisan schedule:run >> /dev/null 2>&1
```

When `NAI_MONITORING_ENABLED=true`, NAi probes registered endpoints every five minutes. You can also run:

```bash
php artisan nai:monitor
php artisan nai:monitor --dry-run
```

## 7. Operations

- Back up MySQL regularly and test restores.
- Rotate `APP_KEY` only with a planned re-encryption process for stored Arena keys.
- Keep `NAI_DEMO_MODE=false`.
- Monitor `storage/logs/laravel.log`.
- If you switch `QUEUE_CONNECTION` from `sync` to `database`, run a queue worker under systemd/supervisor.
- Export operational backups with `php artisan nai:export` or Settings → Export workspace JSON.
