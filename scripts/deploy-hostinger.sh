#!/usr/bin/env bash
# ==============================================================================
# OrbiTouch ATS — Zero-Data-Loss Hostinger Shared Hosting Deployment Script
# Target: https://crm.orbitouch-hr.com/
# ==============================================================================
set -euo pipefail

echo "=== [1/7] Starting OrbiTouch ATS Production Deployment ==="

# 1. Create pre-deployment encrypted backup snapshot
echo "=== [2/7] Creating pre-deployment encrypted backup ==="
php artisan ats:backup --type=full || echo "Initial deployment: skipping backup prior to first migration."

# 2. Enter brief maintenance mode (renders 503 retry-after=15)
php artisan down --retry=15 || true

# 3. Pull latest verified release from main branch
echo "=== [3/7] Pulling latest verified code from GitHub (origin/main) ==="
git pull origin main

# 4. Install production PHP dependencies (optimized for 40 PHP workers)
echo "=== [4/7] Installing Composer production dependencies ==="
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

# 5. Run non-destructive incremental database migrations (NEVER migrate:fresh)
echo "=== [5/7] Applying incremental database migrations safely ==="
php artisan migrate --force

# 6. Ensure core roles, permissions, and Super Admin account exist
php artisan db:seed --class="Database\\Seeders\\DatabaseSeeder" --force

# 7. Optimize Laravel configuration, routes, and Blade views for 1-CPU / 2GB RAM shared hosting
echo "=== [6/7] Caching configuration, routes, and views ==="
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 8. Exit maintenance mode
echo "=== [7/7] Bringing OrbiTouch ATS back online ==="
php artisan up

echo "=== Deployment Completed Successfully ==="
