#!/usr/bin/env bash
#
# NAi — cPanel Git™ Version Control deploy task.
#
# Invoked by .cpanel.yml on every "Deploy HEAD Commit". cPanel runs this from
# the repository checkout (e.g. /home/$USER/repositories/nai). We sync the app
# into DEPLOYPATH (default /home/$USER/nai), preserving .env, storage, and the
# SQLite file if present, then run the production build with the account's PHP.
#
# You can also run it manually over SSH:
#   DEPLOYPATH=/home/$USER/nai bash deploy/cpanel.sh
#
# Required cPanel setup (one time):
#   - MultiPHP Manager: set the domain to PHP 8.3+ with pdo_mysql, mbstring,
#     openssl, xml, curl, zip, ctype, fileinfo, tokenizer enabled.
#   - MySQL Databases: create a database + user, add user to database.
#   - Create /home/$USER/nai/.env (copy of .env.example) with DB credentials,
#     APP_URL, and APP_KEY (php artisan key:generate). The deploy preserves it.

set -euo pipefail

SRC="$(cd "$(dirname "$0")/.." && pwd)"
DEPLOYPATH="${DEPLOYPATH:-$HOME/nai}"

say()  { printf '\n[NAi deploy] %s\n' "$*"; }
die()  { printf '\n[NAi deploy] ERROR: %s\n' "$*" >&2; exit 1; }

# ---------------------------------------------------------------------------
# Locate the account PHP (cPanel EasyApache) and Composer
# ---------------------------------------------------------------------------
PHP_BIN="${PHP_BIN:-}"
if [ -z "$PHP_BIN" ]; then
  for c in /opt/cpanel/ea-php84/root/usr/bin/php \
           /opt/cpanel/ea-php83/root/usr/bin/php \
           /usr/local/bin/php php; do
    if command -v "$c" >/dev/null 2>&1 && "$c" -r 'exit(version_compare(PHP_VERSION,"8.3.0",">=")?0:1);' 2>/dev/null; then
      PHP_BIN="$c"; break
    fi
  done
fi
[ -n "$PHP_BIN" ] || die "No PHP 8.3+ found. Set the domain to PHP 8.3+ in MultiPHP Manager."

COMPOSER_BIN="${COMPOSER_BIN:-}"
if [ -z "$COMPOSER_BIN" ]; then
  if command -v composer >/dev/null 2>&1; then COMPOSER_BIN="composer";
  elif command -v composer.phar >/dev/null 2>&1; then COMPOSER_BIN="$(command -v composer.phar)";
  elif [ -f "$SRC/composer.phar" ]; then COMPOSER_BIN="$PHP_BIN $SRC/composer.phar";
  else
    say "Downloading composer.phar"
    "$PHP_BIN" -r "copy('https://getcomposer.org/installer','composer-setup.php');"
    "$PHP_BIN" composer-setup.php --quiet --install-dir="$SRC" --filename=composer.phar
    rm -f composer-setup.php
    COMPOSER_BIN="$PHP_BIN $SRC/composer.phar"
  fi
fi

# ---------------------------------------------------------------------------
# Sync application files into DEPLOYPATH (preserve runtime state).
# When the repo is cloned straight into DEPLOYPATH, build in place instead.
# ---------------------------------------------------------------------------
mkdir -p "$DEPLOYPATH"
SRC_REAL="$(cd "$SRC" && pwd -P)"
DEST_REAL="$(cd "$DEPLOYPATH" && pwd -P)"
if [ "$SRC_REAL" = "$DEST_REAL" ]; then
  say "Repository is already at $DEPLOYPATH — building in place"
else
  say "Syncing to $DEPLOYPATH"
  if command -v rsync >/dev/null 2>&1; then
    rsync -a --delete \
      --exclude '.git' \
      --exclude '.env' \
      --exclude 'storage' \
      --exclude 'vendor' \
      --exclude 'node_modules' \
      --exclude 'database/*.sqlite*' \
      --exclude 'bootstrap/cache/*.php' \
      "$SRC/" "$DEPLOYPATH/"
  else
    # Fallback: copy everything except runtime state.
    tar -C "$SRC" \
      --exclude='.git' --exclude='.env' --exclude='storage' --exclude='vendor' \
      --exclude='node_modules' --exclude='database/*.sqlite*' --exclude='bootstrap/cache/*.php' \
      -cf - . | tar -C "$DEPLOYPATH" -xf -
  fi
fi

# Ensure runtime directories exist (storage is excluded from the sync).
mkdir -p "$DEPLOYPATH/storage/app/private" \
         "$DEPLOYPATH/storage/app/public" \
         "$DEPLOYPATH/storage/framework/cache/data" \
         "$DEPLOYPATH/storage/framework/sessions" \
         "$DEPLOYPATH/storage/framework/views" \
         "$DEPLOYPATH/storage/logs" \
         "$DEPLOYPATH/bootstrap/cache"

cd "$DEPLOYPATH"

# ---------------------------------------------------------------------------
# Install dependencies FIRST — artisan cannot run without vendor/autoload.php
# on a fresh checkout (vendor/ is excluded from the sync).
# ---------------------------------------------------------------------------
say "Installing dependencies"
# shellcheck disable=SC2086
COMPOSER_ALLOW_SUPERUSER=1 $COMPOSER_BIN install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# ---------------------------------------------------------------------------
# Environment bootstrap. On the very first deploy we create .env, generate the
# key, then STOP before touching the database — the account owner must fill in
# the cPanel DB credentials and APP_URL first. The next deploy finishes the build.
# ---------------------------------------------------------------------------
FIRST_RUN=0
if [ ! -f .env ]; then
  cp .env.example .env
  FIRST_RUN=1
fi
if ! grep -qE '^APP_KEY=base64:' .env; then
  "$PHP_BIN" artisan key:generate --force
fi

if [ "$FIRST_RUN" = "1" ]; then
  chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true
  say "First deploy complete — dependencies installed and .env created."
  say "NEXT: edit $DEPLOYPATH/.env with your database credentials and APP_URL,"
  say "then click 'Deploy HEAD Commit' again to run migrations and cache."
  say "After that, create an admin: $PHP_BIN artisan nai:create-admin"
  exit 0
fi

# ---------------------------------------------------------------------------
# Build (subsequent deploys — .env is already configured)
# ---------------------------------------------------------------------------
say "Migrating"
"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan db:seed --force || true
"$PHP_BIN" artisan storage:link || true

say "Caching"
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache

chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true

say "Deploy complete. Document root must be: $DEPLOYPATH/public"
