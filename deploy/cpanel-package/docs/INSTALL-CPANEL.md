# NAi — direct cPanel installation

This is the **prebuilt, browser-installable** NAi package. It is different from the Git/VPS installation path in `HOSTINGER.md`.

- **No Composer on the server:** production dependencies are already in `build/vendor`.
- **No SSH required:** `public/install.php` creates `.env`, runs the migrations and essential seeders, creates the administrator, and builds production caches.
- **No Node build:** NAi serves its CSS/JS/fonts locally from `public/`.
- **Flat ZIP:** extracting the archive produces the project root directly; there is no extra wrapper folder.

> NAi is an orchestration workspace, not a property-management system. The installer’s “Workspace / property name” field sets the NAi workspace name; it does not add PMS modules.

## 1. Upload and extract

In **cPanel → File Manager**, upload the ZIP outside `public_html` when possible, for example:

```text
/home/YOURUSER/nowills-nai
```

Extract it there. After extraction, that folder must contain `install.php`, `app/`, `bootstrap/`, `build/`, `public/`, `storage/`, `artisan`, and `.htaccess` directly.

The PHP installer file is `public/install.php`. A root-level `install.php` launcher is also included so the installer is visible immediately after extraction and can act as a fallback when the project root itself is served. Both are removed after successful installation.

Enable **Settings → Show Hidden Files** so you can see `.htaccess` and, later, `.env`.

## 2. Document Root

Preferred:

**cPanel → Domains → your domain → Document Root**

Set it to the `public/` folder inside the project:

```text
/home/YOURUSER/nowills-nai/public
```

If the Document Root cannot be changed, leave the upload where it is. The project-root `.htaccess` forwards requests into `public/` and blocks private source/configuration paths. Apache/LiteSpeed `mod_rewrite` must be enabled. If rewrite is unavailable, the fallback fails closed rather than exposing source code.

Never copy the whole project into `public_html` when a separate document root is available.

## 3. PHP version and extensions

In **MultiPHP Manager / Select PHP Version**, select PHP **8.2+** (8.3 or 8.4 recommended) and enable:

```text
pdo_mysql, mbstring, openssl, ctype, fileinfo, tokenizer, dom, xml, xmlwriter,
curl, zip, session, filter, iconv
```

The installer displays a live server check and disables submission until the checks pass.

## 4. Create the database

**cPanel → MySQL Databases**:

1. Create a new **empty** database.
2. Create a database user with a strong password.
3. Add the user to the database with **ALL PRIVILEGES**.
4. Copy the **FULL prefixed names** shown by cPanel, for example `myacct_nai` and `myacct_naiuser` — not only the suffix you typed.

The host is usually `localhost` and port `3306`. Use the values your host provides if different. MySQL 8.0+ or MariaDB 10.6+ is supported.

The installer refuses a database that already contains unrelated tables. It never drops or overwrites an existing installation.

## 5. Permissions

Set these directories to **755**, owned by the cPanel account/PHP user:

```text
storage/                  (and everything inside)
bootstrap/cache/
public/uploads/
```

The project root must also allow PHP to create `.env`, and `public/` must allow the installer to delete itself. On normal cPanel suEXEC/LSAPI hosting, account-owned 755 directories are writable. Do not use 777.

## 6. Open the installer

Visit:

```text
https://your-domain.com/install.php
```

For the prepared Nowills target this is:

```text
https://nai.nowills.com/install.php
```

Open `SETUP-TOKEN.txt` in the **project root** with cPanel File Manager. Paste that one-time token into the installer. The token is unique to this ZIP and prevents a stranger from claiming the site between upload and installation.

Fill in:

- site URL and workspace/property name;
- full database name, full database user, password, host, and port;
- administrator name, email, password, and confirmation.

The installer then:

1. checks PHP, extensions, bundled packages, and writable directories;
2. verifies the database connection and empty/pending database state;
3. writes a private `.env` with a fresh application key;
4. runs Laravel migrations and the production workspace seeder;
5. creates the administrator with a hashed password;
6. caches configuration, routes, and views;
7. writes `storage/app/installed.lock`;
8. removes `install.php`, `public/install.php`, `SETUP-TOKEN.txt`, and `build/installer-config.php`.

Demo mode and preview auto-login are **off**. The Arena AI launcher points to the real external `https://arena.ai`; the machine engine connection remains disconnected until an administrator configures a real endpoint.

## 7. Delete the installer

Cleanup is automatic. If the success page reports a cleanup warning, manually remove:

```text
install.php
public/install.php
SETUP-TOKEN.txt
build/installer-config.php
```

The installed lock prevents the installer from being run again even if a file could not be deleted. Do not delete `storage/app/installed.lock` on a live site.

## 8. Cron entry

**cPanel → Cron Jobs → Once Per Minute**:

```cron
/usr/local/bin/php /home/YOURUSER/nowills-nai/artisan schedule:run >> /dev/null 2>&1
```

Replace the project path with your actual upload path. Replace `/usr/local/bin/php` with the host’s real CLI PHP for the selected version if it differs. Common cPanel examples:

```text
/opt/cpanel/ea-php83/root/usr/bin/php
/opt/cpanel/ea-php84/root/usr/bin/php
/usr/local/bin/ea-php84
```

The success page prints the exact detected command. Do not paste the leading five cron stars into the cPanel command box when the schedule is already set to Once Per Minute.

The schedule runs monitoring when enabled, prunes old monitoring history, prunes expired import reviews, and prunes old notifications according to the application’s configured commands.

## 9. SSL and first sign-in

1. Issue **AutoSSL** in cPanel → SSL/TLS Status.
2. Enable **Force HTTPS Redirect** once the certificate is active.
3. Visit `https://your-domain.com/up` — it should return HTTP 200.
4. Sign in at `https://your-domain.com/login` with the administrator created by the installer.
5. In **Settings**, configure the real NAi engine endpoint/key only if you have an approved endpoint. NAi does not fabricate engine replies.

## Recovery and updates

- **Interrupted before completion:** the installer leaves a private `storage/app/installing.lock` marker. Reopen `install.php` with the same setup token, site/database identity, and administrator email. It can resume its own pending schema. If an administrator was already created, use the same administrator password.
- **Wrong database selected:** stop. The installer refuses unrelated non-empty databases. Create a new empty database rather than deleting tables blindly.
- **Cleanup failed:** remove the three files listed above manually; the installed lock remains authoritative.
- **Updating an installed site:** this one-time installer is not an update tool. Preserve `.env`, `storage/`, and the database. Deploy application updates through the normal Git/cPanel deploy path or a maintenance release, then run migrations with the host’s PHP CLI. Do not re-extract a fresh installer over a live site.

## Troubleshooting

| Symptom | Check |
|---|---|
| Installer 404 | Document Root points to `public/`, or project-root `.htaccess` rewrite is active; the file is `public/install.php`. |
| 403 at project root | `mod_rewrite` is disabled or a private path was requested. Enable rewrite or set Document Root to `public/`. |
| 500 / blank page | PHP version/extensions, `storage/logs/laravel.log`, directory ownership, and `.htaccess` syntax. |
| `build/vendor` missing | The ZIP was only partially extracted. Re-upload/extract the complete archive. |
| Database connection failed | Full prefixed names, password, host/port, and user-to-database ALL PRIVILEGES assignment. |
| Database is not empty | Use a new empty database. The installer will not overwrite existing tables. |
| Permission/write error | `storage/`, `bootstrap/cache/`, `public/uploads/`, project root, and `public/` ownership/755. |
| Login cookie / mixed content | Use the HTTPS site URL, active AutoSSL, and Force HTTPS Redirect. |
| Engine shows Disconnected | Expected until a real upstream endpoint is configured. This is not a local AI model. |

## Security notes

- `.env` is written with 0600 permissions where the host honors chmod.
- Passwords are never reflected back into the form or included in package metadata.
- The setup token is stored as a SHA-256 hash in the package; the plaintext exists only in root `SETUP-TOKEN.txt` until cleanup.
- The installer uses a session CSRF token and refuses rerun after `installed.lock` exists.
- `public/uploads/.htaccess` denies script execution. NAi’s normal filesystem disks remain under `storage/app`.
- Keep the ZIP and `SETUP-TOKEN.txt` private. The Google Drive package is not made public by the build process.
