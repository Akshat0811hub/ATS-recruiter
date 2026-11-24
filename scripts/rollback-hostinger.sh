#!/usr/bin/env bash
# ==============================================================================
# OrbiTouch ATS — Production Rollback Script for Hostinger Shared Hosting
# Usage: bash scripts/rollback-hostinger.sh <git-tag-or-commit-sha> [--rollback-migration]
# ==============================================================================
set -euo pipefail

TARGET_REF="${1:-HEAD~1}"
ROLLBACK_MIGRATION="${2:-false}"

echo "=== [1/5] Entering maintenance mode for rollback to ${TARGET_REF} ==="
php artisan down --retry=15 || true

if [ "${ROLLBACK_MIGRATION}" = "--rollback-migration" ]; then
    echo "=== [2/5] Rolling back latest migration batch ==="
    php artisan migrate:rollback --step=1 --force
fi

echo "=== [3/5] Checking out target Git reference: ${TARGET_REF} ==="
git checkout "${TARGET_REF}"

echo "=== [4/5] Re-installing production Composer dependencies and refreshing caches ==="
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "=== [5/5] Exiting maintenance mode ==="
php artisan up

echo "=== Rollback to ${TARGET_REF} completed safely ==="
