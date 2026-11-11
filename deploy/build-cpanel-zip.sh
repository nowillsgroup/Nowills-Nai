#!/usr/bin/env bash
# Build the flat, prebuilt, browser-installable NAi cPanel ZIP.
# Usage: bash deploy/build-cpanel-zip.sh /absolute/path/NAi-cPanel-Direct-Install.zip
# Requires local PHP 8.3+, Composer 2, git, tar, zip, unzip. The destination server needs none of these except PHP/MySQL.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
OUT="${1:-"$ROOT/dist/NAi-cPanel-Direct-Install.zip"}"

for tool in git tar zip unzip sha256sum; do
  command -v "$tool" >/dev/null || { echo "Missing build tool: $tool" >&2; exit 1; }
done
command -v "$PHP_BIN" >/dev/null || { echo "Missing PHP_BIN: $PHP_BIN" >&2; exit 1; }
command -v "$COMPOSER_BIN" >/dev/null || { echo "Missing COMPOSER_BIN: $COMPOSER_BIN" >&2; exit 1; }
"$PHP_BIN" -r 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);' || { echo 'PHP 8.3+ is required to build.' >&2; exit 1; }

cd "$ROOT"
if [[ -n "$(git status --porcelain)" ]]; then
  echo 'Working tree is not clean. Commit the release source before building so the ZIP has an exact provenance.' >&2
  exit 1
fi

SOURCE_COMMIT="$(git rev-parse HEAD)"
SOURCE_SHORT="$(git rev-parse --short HEAD)"
BUILT_AT="$(date -u '+%Y-%m-%dT%H:%M:%SZ')"
PACKAGE_NAME="$(basename "$OUT")"
mkdir -p "$(dirname "$OUT")"
OUT="$(cd "$(dirname "$OUT")" && pwd)/$(basename "$OUT")"
STAGE="$(mktemp -d "${TMPDIR:-/tmp}/nai-cpanel-release.XXXXXX")"
trap 'rm -rf "$STAGE"' EXIT

printf '[NAi package] Exporting %s\n' "$SOURCE_SHORT"
git archive --format=tar HEAD | tar -x -C "$STAGE"
cp -a "$ROOT/deploy/cpanel-package/." "$STAGE/"

# This is a production browser-install package, not a development checkout.
rm -rf "$STAGE/.github" "$STAGE/tests" "$STAGE/deploy" "$STAGE/phpunit.xml" \
       "$STAGE/install.sh" "$STAGE/.cpanel.yml" "$STAGE/docs/HOSTINGER.md" \
       "$STAGE/docs/DEPLOYMENT.md"

mkdir -p "$STAGE/build" "$STAGE/storage/framework/cache/data" "$STAGE/storage/framework/sessions" \
         "$STAGE/storage/framework/views" "$STAGE/storage/logs" "$STAGE/storage/app/private" \
         "$STAGE/storage/app/public" "$STAGE/bootstrap/cache" "$STAGE/public/uploads"

cd "$STAGE"
printf '[NAi package] Installing locked production dependencies into build/vendor\n'
"$COMPOSER_BIN" config vendor-dir build/vendor --no-interaction
"$COMPOSER_BIN" install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts --optimize-autoloader

# Discover Laravel package providers without needing .env or a database.
rm -f "$STAGE"/bootstrap/cache/*.php
"$PHP_BIN" artisan package:discover --no-interaction

printf '[NAi package] Generating private one-time setup token\n'
"$PHP_BIN" -r '
$token = bin2hex(random_bytes(24));
$config = ["setup_token_hash" => hash("sha256", $token), "package" => $argv[3], "built_at" => $argv[4], "source_commit" => $argv[5]];
file_put_contents($argv[1], "NAi ONE-TIME SETUP TOKEN\n========================\n\n".$token."\n\nOpen this file only in cPanel File Manager. Paste the token into /install.php.\nThe installer deletes this file after successful installation. Do not share it.\n");
file_put_contents($argv[2], "<?php\n\nreturn ".var_export($config, true).";\n");
chmod($argv[1], 0600); chmod($argv[2], 0600);
' "$STAGE/SETUP-TOKEN.txt" "$STAGE/build/installer-config.php" "$PACKAGE_NAME" "$BUILT_AT" "$SOURCE_COMMIT"

"$PHP_BIN" -r '
$path = $argv[1]; $text = file_get_contents($path);
$text = strtr($text, ["{{PACKAGE_NAME}}" => $argv[2], "{{BUILT_AT}}" => $argv[3], "{{SOURCE_COMMIT}}" => $argv[4]]);
file_put_contents($path, $text);
' "$STAGE/START-HERE.txt" "$PACKAGE_NAME" "$BUILT_AT" "$SOURCE_COMMIT"

LOCK_HASH="$(sha256sum composer.lock | awk '{print $1}')"
cat > "$STAGE/PACKAGE-INFO.txt" <<EOF
NAi direct cPanel installation package
Package: $PACKAGE_NAME
Built: $BUILT_AT
Source repository: nowillsgroup/Nowills-Nai
Source commit: $SOURCE_COMMIT
composer.lock SHA-256: $LOCK_HASH
Installer: App\\Support\\WebInstaller

Layout: flat project root; web root is public/
Dependencies: production only, bundled in build/vendor
Database: create an empty MySQL/MariaDB database in cPanel
Setup: https://your-domain.com/install.php with SETUP-TOKEN.txt
No Composer, SSH, Node.js, npm, Vite, or Docker is required on the destination server.

Excluded: .env, real passwords, APP_KEY, database data, logs, sessions, cached views,
development dependencies, tests, CI, Git metadata, and alternative VPS/Git installers.
The one-time setup token plaintext is intentionally present only in SETUP-TOKEN.txt
until the installer deletes it; build/installer-config.php stores only its SHA-256 hash.
EOF

# Remove runtime state that may have been created during package discovery.
find "$STAGE/storage/framework/sessions" -type f ! -name '.gitignore' -delete
find "$STAGE/storage/framework/views" -type f ! -name '.gitignore' -delete
find "$STAGE/storage/framework/cache/data" -type f ! -name '.gitignore' -delete
find "$STAGE/storage/logs" -type f ! -name '.gitignore' -delete
rm -f "$STAGE/.env" "$STAGE/storage/app/installed.lock" "$STAGE/storage/app/installing.lock"
find "$STAGE" -type d -exec chmod 755 {} +
find "$STAGE" -type f -exec chmod 644 {} +
chmod 755 "$STAGE/artisan"
chmod 600 "$STAGE/SETUP-TOKEN.txt" "$STAGE/build/installer-config.php"

printf '[NAi package] Validating release\n'
"$PHP_BIN" -l public/install.php >/dev/null
"$PHP_BIN" -l app/Support/WebInstaller.php >/dev/null
for required in .htaccess install.php START-HERE.txt README.md SETUP-TOKEN.txt build/installer-config.php \
                build/vendor/autoload.php public/install.php public/index.php public/.htaccess \
                public/uploads/.htaccess public/css/nai.css public/css/arena-browser.css \
                public/js/arena-browser.js docs/INSTALL-CPANEL.md docs/MODULES.md docs/WORKFORCE.md artisan composer.lock; do
  [[ -f "$required" ]] || { echo "Missing release file: $required" >&2; exit 1; }
done
if find "$STAGE" -type f \( -name '.env' -o -name '*.sqlite' -o -name '*.sqlite3' -o -name '*.log' \) | grep -q .; then
  echo 'Forbidden runtime/secret file found in release.' >&2; exit 1
fi
if [[ -d "$STAGE/.git" || -d "$STAGE/node_modules" || -d "$STAGE/vendor" ]]; then
  echo 'Forbidden development/runtime directory found in release.' >&2; exit 1
fi

printf '[NAi package] Writing flat ZIP\n'
rm -f "$OUT"
(cd "$STAGE" && zip -qr -9 "$OUT" .)
unzip -t "$OUT" >/dev/null
sha256sum "$OUT" > "$OUT.sha256"
printf '[NAi package] Ready: %s\n' "$OUT"
printf '[NAi package] Bytes: '
wc -c < "$OUT"
cat "$OUT.sha256"
printf '[NAi package] Setup token is inside the ZIP at SETUP-TOKEN.txt (not printed here).\n'
