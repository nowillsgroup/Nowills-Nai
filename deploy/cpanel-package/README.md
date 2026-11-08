# NAi — direct cPanel package

Start with **[START-HERE.txt](START-HERE.txt)**.

- Full guide: **[docs/INSTALL-CPANEL.md](docs/INSTALL-CPANEL.md)**
- Module list: **[docs/MODULES.md](docs/MODULES.md)**

Extract the ZIP flat. This folder is the project root. Point the domain Document Root at `public/`; if that is not possible, the root `.htaccess` forwards into `public/`.

Create an empty MySQL database and user in cPanel, set `storage/`, `bootstrap/cache/`, and `public/uploads/` to 755, then open `https://your-domain.com/install.php` and use the private token from `SETUP-TOKEN.txt`.

The installer removes itself when finished. Add the once-per-minute cron command it prints.

**No Composer is needed:** production packages are bundled in `build/vendor`.
**No SSH is needed:** the browser installer performs the required Laravel setup.

This package contains no `.env`, passwords, application key, or database data. Keep `SETUP-TOKEN.txt` private until the installer deletes it.
