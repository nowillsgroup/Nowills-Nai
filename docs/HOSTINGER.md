# Installing NAi on Hostinger (VPS or cPanel) — direct from Git

NAi is a standard Laravel 12 app (PHP 8.2+, MySQL). There is **no Node build step**
and **no Docker** — you clone the repo, run one installer, and point your domain
at the `public/` folder. This guide covers both Hostinger products:

- **Hostinger VPS** (full SSH/root) → use `install.sh` (Part A).
- **Hostinger cPanel / shared hosting with Git™ Version Control** → use `.cpanel.yml` (Part B).

> **The one rule that matters:** the web document root must be the app's
> `public/` directory, never the repository root. Everything else is routine.

---

## 0. Requirements

| Requirement | Value |
|---|---|
| PHP | 8.2, 8.3, or 8.4 |
| PHP extensions | `pdo_mysql`, `mbstring`, `openssl`, `xml`, `xmlwriter`, `curl`, `zip`, `ctype`, `fileinfo`, `tokenizer` |
| Database | MySQL 8 / MariaDB 10.6+ |
| Composer | 2.x (the installer downloads it locally if missing) |
| Disk | ~100 MB for the app + dependencies |

On cPanel, enable the extensions in **MultiPHP Manager → PHP Extensions** and set
the domain to PHP 8.2+ in **MultiPHP Manager**. On a VPS, install them with your
package manager (e.g. `apt install php8.3-{fpm,mysql,mbstring,xml,curl,zip}`).

---

## Part A — Hostinger VPS (SSH, recommended)

### A1. Create the database

```bash
mysql -u root -p
```
```sql
CREATE DATABASE nai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'nai'@'localhost' IDENTIFIED BY 'CHANGE-ME-strong-password';
GRANT ALL PRIVILEGES ON nai.* TO 'nai'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### A2. Clone and install

```bash
cd /var/www           # or your preferred location
git clone https://github.com/nowillsgroup/Nowills-Nai.git nai
cd nai
bash install.sh
```

The installer will:
1. Find a PHP 8.2+ binary and verify extensions.
2. Install Composer dependencies (`--no-dev`).
3. Create `.env`, generate `APP_KEY`, and set production defaults.
4. Ask for your `APP_URL` and database credentials (or read them from env vars).
5. Run migrations + baseline seed.
6. Optionally create your first admin account.
7. Cache config/routes/views and run `nai:check`.

**Non-interactive install** (e.g. cloud-init / script):

```bash
APP_URL=https://nai.yourdomain.com \
DB_HOST=127.0.0.1 DB_DATABASE=nai DB_USERNAME=nai DB_PASSWORD='CHANGE-ME' \
ADMIN_NAME='NAi Admin' ADMIN_EMAIL=admin@yourdomain.com ADMIN_PASSWORD='CHANGE-ME-12+' \
WEB_USER=www-data \
bash install.sh
```

### A3. Point the web server at `public/`

**Nginx** — set `root /var/www/nai/public;` (see `docs/DEPLOYMENT.md` for a full
server block), then `nginx -t && systemctl reload nginx`.

**Apache** — set `DocumentRoot /var/www/nai/public` and allow `Override All` so the
bundled `public/.htaccess` works. Enable `mod_rewrite`.

Set ownership so PHP can write runtime files:

```bash
sudo chown -R www-data:www-data /var/www/nai/storage /var/www/nai/bootstrap/cache
```

### A4. TLS + scheduler

- Issue a certificate (Hostinger panel, or `certbot --nginx` / `certbot --apache`).
- Set `APP_URL=https://nai.yourdomain.com` in `.env`, then `php artisan config:cache`.
- Add the scheduler cron (crontab -e):

```cron
* * * * * cd /var/www/nai && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

### A5. Sign in

Open `https://nai.yourdomain.com/login` and sign in with the admin you created.
Configure the NAi engine endpoint in **Settings** when ready.

**Updates:** `cd /var/www/nai && git pull && bash install.sh` (safe to re-run).

---

## Part B — Hostinger cPanel (Git™ Version Control)

Use this when you have cPanel (shared or VPS-with-cPanel) rather than full root.

### B1. Prepare the database and PHP

1. **MySQL® Databases**: create a database (e.g. `cpuser_nai`) and a user, then
   **Add User To Database** with ALL PRIVILEGES. Note the full names — cPanel
   prefixes them with your account username.
2. **MultiPHP Manager**: set your domain to **PHP 8.2+**.
3. **MultiPHP Manager → PHP Extensions**: enable `pdo_mysql`, `mbstring`, `openssl`,
   `xml`, `xmlwriter`, `curl`, `zip`, `ctype`, `fileinfo`, `tokenizer`.

### B2. Clone the repo in cPanel

1. **Git™ Version Control → Create**.
2. Clone URL: `https://github.com/nowillsgroup/Nowills-Nai.git`
   (for a private repo, use the SSH URL and add the cPanel SSH key as a deploy key).
3. **Repository Path:** `/home/CPUSER/nai`  ← choose this exact path.
4. Branch: `main` (or your deployment branch). Create the repository.

### B3. Create `.env` once (the deploy preserves it)

In **File Manager**, open `/home/CPUSER/nai/` (after the first clone/checkout),
copy `.env.example` to `.env`, and edit:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://nai.yourdomain.com
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cpuser_nai
DB_USERNAME=cpuser_nai
DB_PASSWORD=your-db-password
SESSION_SECURE_COOKIE=true
NAI_DEMO_MODE=false
```

Generate the key over SSH (or cPanel Terminal):

```bash
cd ~/nai
/opt/cpanel/ea-php83/root/usr/bin/php artisan key:generate
```

### B4. Deploy

In **Git™ Version Control**, click **Manage → Deploy HEAD Commit**. cPanel reads
`.cpanel.yml` and runs `deploy/cpanel.sh`, which:

- syncs the app into `/home/CPUSER/nai` (preserving your `.env` and `storage/`),
- installs Composer dependencies,
- runs migrations + seed,
- caches config/routes/views.

### B5. Point the domain at `public/`

In **Domains** (or **Subdomains**), set the domain/subdomain **Document Root** to:

```
/home/CPUSER/nai/public
```

> Do **not** point it at `/home/CPUSER/nai` — that would expose `.env` and source.
> If cPanel won't let you change the primary domain's root, create a subdomain
> (e.g. `nai.yourdomain.com`) with that document root instead.

### B6. TLS, admin, scheduler

- **SSL/TLS Status**: run **AutoSSL** for the domain.
- Create the admin (cPanel Terminal):
  ```bash
  cd ~/nai && /opt/cpanel/ea-php83/root/usr/bin/php artisan nai:create-admin
  ```
- **Cron Jobs**: add
  ```cron
  * * * * * /opt/cpanel/ea-php83/root/usr/bin/php /home/CPUSER/nai/artisan schedule:run >> /dev/null 2>&1
  ```

**Updates:** push to your branch, then **Deploy HEAD Commit** again. The deploy
preserves `.env`, `storage/`, and the database.

---

## Troubleshooting

| Symptom | Fix |
|---|---|
| Blank page / 500 | Check `storage/logs/laravel.log`. Ensure `storage/` and `bootstrap/cache/` are writable by PHP. |
| "No application encryption key" | Run `php artisan key:generate`, then `php artisan config:cache`. |
| 404 on every route except `/` | Document root must be `public/`; enable `mod_rewrite` (Apache) or the `try_files` rule (Nginx). |
| `.env` values ignored | You cached config before editing. Run `php artisan config:clear && php artisan config:cache`. |
| `could not find driver` | Enable `pdo_mysql` in MultiPHP / install `php-mysql`. |
| Mixed content / insecure cookies | Set `APP_URL=https://…` and `SESSION_SECURE_COOKIE=true`, then re-cache config. |
| cPanel deploy fails on PHP version | Set the domain to PHP 8.2+ in MultiPHP Manager; the script auto-detects `ea-php8x`. |

## Verify the install

```bash
php artisan nai:check        # runtime, extensions, DB, engine config
php artisan about            # framework + environment summary
curl -fsS https://nai.yourdomain.com/up   # Laravel health endpoint -> HTTP 200
```

See also: `docs/DEPLOYMENT.md` (generic Linux/Nginx), `docs/ARCHITECTURE.md`,
`docs/ARENA-INTEGRATION.md` (connecting the engine).
