#!/usr/bin/env bash
#
# NAi — direct installer for a Hostinger VPS (SSH) or any Linux host.
#
# Usage:
#   git clone https://github.com/nowillsgroup/Nowills-Nai.git nai
#   cd nai
#   bash install.sh
#
# Optional environment variables (skip the matching prompt):
#   PHP_BIN          Path to a PHP 8.2+ binary (auto-detected otherwise)
#   COMPOSER_BIN     Path to composer (auto-detected otherwise)
#   APP_URL          Public URL, e.g. https://nai.yourdomain.com
#   DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD
#   ADMIN_NAME ADMIN_EMAIL ADMIN_PASSWORD   Initial admin (optional)
#   WEB_USER         Web server user for chown (e.g. www-data). Optional.
#   SKIP_ADMIN=1     Do not create an admin account
#   NO_CACHE=1       Skip artisan optimize (useful while debugging)
#
# Safe to re-run: existing .env and APP_KEY are preserved; migrations are
# additive; the admin step is skipped if the email already exists.

set -euo pipefail

cd "$(dirname "$0")"
ROOT="$(pwd)"

say()  { printf '\n\033[1;35m==>\033[0m %s\n' "$*"; }
ok()   { printf '    \033[1;32m✓\033[0m %s\n' "$*"; }
warn() { printf '    \033[1;33m!\033[0m %s\n' "$*"; }
die()  { printf '\n\033[1;31mERROR:\033[0m %s\n' "$*" >&2; exit 1; }

# ---------------------------------------------------------------------------
# 1. Locate a suitable PHP binary (>= 8.2)
# ---------------------------------------------------------------------------
say "Checking PHP"
php_ok() {
  local bin="$1"
  command -v "$bin" >/dev/null 2>&1 || return 1
  "$bin" -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);' >/dev/null 2>&1
}

PHP_BIN="${PHP_BIN:-}"
if [ -n "$PHP_BIN" ]; then
  php_ok "$PHP_BIN" || die "PHP_BIN=$PHP_BIN is not PHP 8.2+."
else
  for c in php8.4 php8.3 php8.2 php \
           /opt/cpanel/ea-php84/root/usr/bin/php \
           /opt/cpanel/ea-php83/root/usr/bin/php \
           /opt/cpanel/ea-php82/root/usr/bin/php \
           /usr/local/bin/php; do
    if php_ok "$c"; then PHP_BIN="$c"; break; fi
  done
fi
[ -n "$PHP_BIN" ] || die "No PHP 8.2+ binary found. Install PHP 8.2/8.3/8.4 (cPanel: MultiPHP Manager) or set PHP_BIN."
ok "Using $PHP_BIN ($("$PHP_BIN" -r 'echo PHP_VERSION;'))"

# Required extensions (pdo_mysql is needed for the MySQL production target).
MISSING=""
for ext in pdo mbstring openssl tokenizer xml ctype fileinfo curl zip pdo_mysql; do
  "$PHP_BIN" -r "exit(extension_loaded('$ext') ? 0 : 1);" || MISSING="$MISSING $ext"
done
if [ -n "$MISSING" ]; then
  warn "Missing PHP extensions:$MISSING"
  warn "Enable them in cPanel MultiPHP / php.ini or your package manager, then re-run."
  die "Cannot continue without the required extensions."
fi
ok "All required PHP extensions present"

# ---------------------------------------------------------------------------
# 2. Locate Composer (or download a local copy)
# ---------------------------------------------------------------------------
say "Checking Composer"
COMPOSER_BIN="${COMPOSER_BIN:-}"
if [ -z "$COMPOSER_BIN" ]; then
  if command -v composer >/dev/null 2>&1; then COMPOSER_BIN="composer";
  elif [ -f "$ROOT/composer.phar" ]; then COMPOSER_BIN="$PHP_BIN $ROOT/composer.phar";
  else
    warn "Composer not found — downloading composer.phar locally."
    "$PHP_BIN" -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    "$PHP_BIN" composer-setup.php --quiet --install-dir="$ROOT" --filename=composer.phar
    rm -f composer-setup.php
    COMPOSER_BIN="$PHP_BIN $ROOT/composer.phar"
  fi
fi
ok "Using $COMPOSER_BIN"

# ---------------------------------------------------------------------------
# 3. Install PHP dependencies (production)
# ---------------------------------------------------------------------------
say "Installing dependencies"
# shellcheck disable=SC2086
COMPOSER_ALLOW_SUPERUSER=1 $COMPOSER_BIN install --no-dev --optimize-autoloader --no-interaction --prefer-dist
ok "Dependencies installed"

# ---------------------------------------------------------------------------
# 4. Environment file
# ---------------------------------------------------------------------------
say "Configuring environment"
if [ ! -f .env ]; then
  cp .env.example .env
  ok "Created .env from .env.example"
else
  ok "Keeping existing .env"
fi

set_env() {
  # set_env KEY VALUE — replace or append a key in .env (GNU/BSD sed safe).
  local key="$1" val="$2"
  if grep -qE "^${key}=" .env; then
    "$PHP_BIN" -r '$f=".env";$k=$argv[1];$v=$argv[2];$s=file_get_contents($f);$s=preg_replace("/^".preg_quote($k,"/)."=.*$/m",$k."=".$v,$s);file_put_contents($f,$s);' "$key" "$val"
  else
    printf '%s=%s\n' "$key" "$val" >> .env
  fi
}

# APP_KEY (only generate when empty so re-runs don't invalidate encrypted data)
if ! grep -qE '^APP_KEY=base64:' .env; then
  "$PHP_BIN" artisan key:generate --force
  ok "Generated APP_KEY"
else
  ok "APP_KEY already set"
fi

# Production defaults
set_env APP_ENV production
set_env APP_DEBUG false
set_env NAI_DEMO_MODE false
set_env NAI_PREVIEW_AUTO_DEMO false

# Interactive prompts only when a TTY is available and values are not preset.
prompt() {
  # prompt VAR "Question" "default"
  local var="$1" question="$2" default="${3:-}" current val
  current="$(printenv "$var" || true)"
  if [ -n "$current" ]; then return; fi
  if [ -t 0 ]; then
    if [ -n "$default" ]; then read -r -p "    $question [$default]: " val; val="${val:-$default}";
    else read -r -p "    $question: " val; fi
    printf -v "$var" '%s' "$val"
    export "$var"
  else
    printf -v "$var" '%s' "$default"
    export "$var"
  fi
}

prompt APP_URL "Public APP_URL" "https://your-domain.com"
[ -n "${APP_URL:-}" ] && set_env APP_URL "$APP_URL"

prompt DB_HOST "DB host" "127.0.0.1"
prompt DB_PORT "DB port" "3306"
prompt DB_DATABASE "DB database" "nai"
prompt DB_USERNAME "DB username" "nai"
prompt DB_PASSWORD "DB password" ""
set_env DB_CONNECTION mysql
set_env DB_HOST "${DB_HOST:-127.0.0.1}"
set_env DB_PORT "${DB_PORT:-3306}"
set_env DB_DATABASE "${DB_DATABASE:-nai}"
set_env DB_USERNAME "${DB_USERNAME:-nai}"
set_env DB_PASSWORD "${DB_PASSWORD:-}"

# Use HTTPS cookies automatically when the URL is https.
case "${APP_URL:-}" in
  https://*) set_env SESSION_SECURE_COOKIE true ;;
  *) set_env SESSION_SECURE_COOKIE false ;;
esac
ok "Environment configured"

# ---------------------------------------------------------------------------
# 5. Database schema + baseline data
# ---------------------------------------------------------------------------
say "Running migrations"
"$PHP_BIN" artisan migrate --force
ok "Migrations complete"

say "Seeding baseline settings"
"$PHP_BIN" artisan db:seed --force
ok "Baseline seeded"

# ---------------------------------------------------------------------------
# 6. Storage symlink + permissions
# ---------------------------------------------------------------------------
say "Linking storage"
"$PHP_BIN" artisan storage:link || warn "storage:link skipped (already linked or unsupported)"

if [ -n "${WEB_USER:-}" ]; then
  chown -R "$WEB_USER":"$WEB_USER" storage bootstrap/cache 2>/dev/null || warn "chown to $WEB_USER failed (run as root to set ownership)"
fi
chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true
ok "Storage writable"

# ---------------------------------------------------------------------------
# 7. Initial admin account
# ---------------------------------------------------------------------------
if [ "${SKIP_ADMIN:-0}" != "1" ]; then
  say "Initial administrator"
  prompt ADMIN_NAME "Admin name" "NAi Admin"
  prompt ADMIN_EMAIL "Admin email" ""
  prompt ADMIN_PASSWORD "Admin password (min 12 chars)" ""
  if [ -n "${ADMIN_EMAIL:-}" ] && [ -n "${ADMIN_PASSWORD:-}" ]; then
    if "$PHP_BIN" artisan nai:create-admin --name="${ADMIN_NAME:-NAi Admin}" --email="$ADMIN_EMAIL" --password="$ADMIN_PASSWORD"; then
      ok "Administrator created"
    else
      warn "Admin not created (email may already exist). Create one later: php artisan nai:create-admin"
    fi
  else
    warn "Skipped admin creation. Run: $PHP_BIN artisan nai:create-admin"
  fi
fi

# ---------------------------------------------------------------------------
# 8. Cache for production + health check
# ---------------------------------------------------------------------------
if [ "${NO_CACHE:-0}" != "1" ]; then
  say "Optimizing for production"
  "$PHP_BIN" artisan config:cache
  "$PHP_BIN" artisan route:cache
  "$PHP_BIN" artisan view:cache
  ok "Config, routes, and views cached"
fi

say "Platform health check"
"$PHP_BIN" artisan nai:check || warn "Health check reported issues — review the output above."

# ---------------------------------------------------------------------------
# 9. Next steps
# ---------------------------------------------------------------------------
say "Installation complete"
cat <<NEXT

  Application root : $ROOT
  Web document root: $ROOT/public   <-- point your domain HERE, not the repo root

  1. Point your domain/subdomain document root to: $ROOT/public
     (cPanel: Domains -> document root; or see docs/HOSTINGER.md)
  2. Enable TLS (cPanel AutoSSL / Let's Encrypt) and set APP_URL=https://...
  3. Add the scheduler cron entry:
       * * * * * cd $ROOT && $PHP_BIN artisan schedule:run >> /dev/null 2>&1
  4. Sign in at: ${APP_URL:-https://your-domain.com}/login
  5. Configure the Arena AI engine in Settings (optional).

  Re-run this script any time to apply updates (it is safe to repeat).
  Full guide: docs/HOSTINGER.md
NEXT
