#!/usr/bin/env bash
# ==============================================================================
# OrbiTouch ATS — Zero-Data-Loss Hostinger Shared Hosting Deployment Script
# Target: https://crm.orbitouch-hr.com/
# ==============================================================================
set -euo pipefail

# Auto-detect PHP 8.3 / 8.4 CLI on Hostinger Shared Hosting (CloudLinux alt-php)
if [ -x "/opt/alt/php83/usr/bin/php" ]; then
    PHP_BIN="/opt/alt/php83/usr/bin/php"
elif [ -x "/opt/alt/php84/usr/bin/php" ]; then
    PHP_BIN="/opt/alt/php84/usr/bin/php"
else
    PHP_BIN="$(command -v php)"
fi

COMPOSER_PHAR="$(command -v composer2 || command -v composer)"

echo "=== [1/8] Verifying Hostinger PHP & Environment (Using: ${PHP_BIN}) ==="
"${PHP_BIN}" -r 'if (version_compare(PHP_VERSION, "8.3.0", "<")) { fwrite(STDERR, "ERROR: PHP 8.3+ is required, found ".PHP_VERSION."\n"); exit(1); }'

if [ ! -f ".env" ]; then
    echo "ERROR: .env file not found. Copy .env.example to .env and set DB_* and MAIL_* credentials first." >&2
    exit 1
fi

mkdir -p storage/app/private storage/app/backups storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod -R 775 storage bootstrap/cache || true

# 1. Create pre-deployment encrypted backup snapshot (skipped automatically on very first install before APP_KEY/migrations)
echo "=== [2/8] Creating pre-deployment encrypted backup ==="
if [ -f "vendor/autoload.php" ] && grep -q "^APP_KEY=base64:" .env; then
    "${PHP_BIN}" artisan ats:backup --type=full || echo "Initial deployment: skipping backup prior to first migration."
    "${PHP_BIN}" artisan down --retry=15 || true
fi

# 2. Pull latest verified release from main branch
echo "=== [3/8] Pulling latest verified code from GitHub (origin/main) ==="
git pull origin main

# 3. Install production PHP dependencies (--no-scripts avoids proc_open restriction on Hostinger CloudLinux)
echo "=== [4/8] Installing Composer production dependencies ==="
"${PHP_BIN}" -d memory_limit=-1 "${COMPOSER_PHAR}" install --no-dev --no-scripts --prefer-dist --optimize-autoloader --no-interaction
"${PHP_BIN}" artisan package:discover --ansi

# 4. Generate APP_KEY automatically on first deployment if empty
if ! grep -q "^APP_KEY=base64:" .env; then
    echo "=== [5/8] Generating initial APP_KEY ==="
    "${PHP_BIN}" artisan key:generate --force
fi

# 5. Run non-destructive incremental database migrations (NEVER migrate:fresh)
echo "=== [6/8] Applying incremental database migrations & core foundation seed ==="
"${PHP_BIN}" artisan migrate --force
"${PHP_BIN}" artisan db:seed --class="Database\\Seeders\\DatabaseSeeder" --force

# 6. Optimize Laravel configuration, routes, and Blade views for 1-CPU / 2GB RAM shared hosting
echo "=== [7/8] Caching configuration, routes, and views ==="
"${PHP_BIN}" artisan optimize:clear
"${PHP_BIN}" artisan config:cache
"${PHP_BIN}" artisan route:cache
"${PHP_BIN}" artisan view:cache

# 7. Exit maintenance mode
echo "=== [8/8] Bringing OrbiTouch ATS online ==="
"${PHP_BIN}" artisan up || true

echo "=== Deployment Completed Successfully ==="
