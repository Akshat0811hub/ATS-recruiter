#!/usr/bin/env bash
# ==============================================================================
# OrbiTouch ATS — Production Rollback Script for Hostinger Shared Hosting
# Usage: bash scripts/rollback-hostinger.sh <git-tag-or-commit-sha> [--rollback-migration]
# ==============================================================================
set -euo pipefail

TARGET_REF="${1:-HEAD~1}"
ROLLBACK_MIGRATION="${2:-false}"

if [ -x "/opt/alt/php83/usr/bin/php" ]; then
    PHP_BIN="/opt/alt/php83/usr/bin/php"
elif [ -x "/opt/alt/php84/usr/bin/php" ]; then
    PHP_BIN="/opt/alt/php84/usr/bin/php"
else
    PHP_BIN="$(command -v php)"
fi

COMPOSER_PHAR="$(command -v composer2 || command -v composer)"

echo "=== [1/5] Entering maintenance mode for rollback to ${TARGET_REF} ==="
"${PHP_BIN}" artisan down --retry=15 || true

if [ "${ROLLBACK_MIGRATION}" = "--rollback-migration" ]; then
    echo "=== [2/5] Rolling back latest migration batch ==="
    "${PHP_BIN}" artisan migrate:rollback --step=1 --force
fi

echo "=== [3/5] Checking out target Git reference: ${TARGET_REF} ==="
git checkout "${TARGET_REF}"

echo "=== [4/5] Re-installing production Composer dependencies and refreshing caches ==="
"${PHP_BIN}" -d memory_limit=-1 "${COMPOSER_PHAR}" install --no-dev --no-scripts --prefer-dist --optimize-autoloader --no-interaction
"${PHP_BIN}" artisan package:discover --ansi
"${PHP_BIN}" artisan optimize:clear
"${PHP_BIN}" artisan config:cache
"${PHP_BIN}" artisan route:cache
"${PHP_BIN}" artisan view:cache

echo "=== [5/5] Exiting maintenance mode ==="
"${PHP_BIN}" artisan up || true

echo "=== Rollback to ${TARGET_REF} completed safely ==="
