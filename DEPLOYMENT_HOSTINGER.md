# OrbiTouch ATS — Hostinger Premium Web Hosting Deployment & Rollback Guide

**Target Production URL:** `https://crm.orbitouch-hr.com/`  
**Existing Main Website (Must Remain Unaffected):** `https://www.orbitouch-hr.com/`  
**Hosting Plan:** Hostinger Premium Web Hosting (20 GB NVMe, ~19.51 GB free, 2048 MB RAM, 1 CPU Core, 40 PHP Workers, 80 Max Processes, 400,000 Inodes, India Server Location)

---

## 1. Pre-Deployment Verification & Safety Rules

1. **Preserve Existing Website & DNS:**
   - Do **not** modify `www.orbitouch-hr.com` or root `@` A/CNAME records.
   - Only after explicit owner approval, add a dedicated `A` or `CNAME` DNS record for the subdomain `crm.orbitouch-hr.com` pointing to the Hostinger India server IP.
2. **hPanel Subdomain & Document Root Configuration:**
   - In Hostinger hPanel → **Websites / Subdomains**, create subdomain `crm.orbitouch-hr.com`.
   - Deploy the Laravel application outside the public web root (e.g., `/home/uXXXXXXXXX/domains/orbitouch-hr.com/ats_app`) and symlink or point the subdomain document root `/home/uXXXXXXXXX/domains/orbitouch-hr.com/public_html/crm` directly to `ats_app/public`:
     ```bash
     ln -s /home/uXXXXXXXXX/domains/orbitouch-hr.com/ats_app/public /home/uXXXXXXXXX/domains/orbitouch-hr.com/public_html/crm
     ```
   - This guarantees `.env`, `storage/app/private`, `database/`, and `app/` are completely unreachable via public HTTP requests.

---

## 2. Database & Environment Setup on Hostinger

1. In Hostinger hPanel → **Databases → MySQL Databases**, create:
   - Database: `uXXXXXXXXX_orbitouch_ats`
   - User: `uXXXXXXXXX_ats_user` with a strong generated password.
2. In Hostinger hPanel → **Advanced → PHP Configuration**, select **PHP 8.3** (or PHP 8.4).
3. Copy `.env.example` to `.env` inside `ats_app/` and configure production variables:
   ```env
   APP_NAME="OrbiTouch ATS"
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://crm.orbitouch-hr.com
   APP_TIMEZONE="Asia/Kolkata"
   APP_CURRENCY="INR"

   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=uXXXXXXXXX_orbitouch_ats
   DB_USERNAME=uXXXXXXXXX_ats_user
   DB_PASSWORD=YOUR_SECURE_DB_PASSWORD

   SESSION_DRIVER=database
   SESSION_ENCRYPT=true
   SESSION_SECURE_COOKIE=true
   QUEUE_CONNECTION=database
   CACHE_STORE=database
   ATS_REQUIRE_SUPER_ADMIN_2FA=true
   ```

---

## 3. Initial Production Deployment Steps

Run [`scripts/deploy-hostinger.sh`](file:///c:/Users/D552266/Desktop/ATS/scripts/deploy-hostinger.sh) via Hostinger SSH:

```bash
cd /home/uXXXXXXXXX/domains/orbitouch-hr.com/ats_app
git pull origin main
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

> **Note:** Pre-compiled frontend assets in `public/build/` are committed and verified by GitHub Actions CI so no Node.js daemon or production build server is required on Hostinger shared hosting.
> Never run `migrate:fresh` or `db:wipe` in production; `php artisan migrate --force` applies only new incremental migrations while preserving all existing candidate records, CVs, client data, and recruitment history.

---

## 4. Hostinger Shared-Hosting Cron Configuration

In Hostinger hPanel → **Advanced → Cron Jobs**, add a single 1-minute cron job:

```cron
* * * * * /usr/bin/php /home/uXXXXXXXXX/domains/orbitouch-hr.com/ats_app/artisan schedule:run >> /dev/null 2>&1
```

This single cron entry orchestrates:
- **Bounded Queue Processing:** `queue:work --stop-when-empty --max-time=50` (with `withoutOverlapping(2)`, preventing permanent background worker process exhaustion on the 80-process limit).
- **Daily Encrypted Backups:** `ats:backup --type=full` at `02:00 AM IST`.
- **Hourly Storage Quota Monitoring:** `ats:storage-check` (70% / 85% / 95% alerts).
- **Daily Follow-up & Interview Reminders:** `ats:send-reminders` at `09:45 AM IST`.

---

## 5. Production Rollback Procedure

Before applying any software update, `scripts/deploy-hostinger.sh` automatically triggers an encrypted backup (`php artisan ats:backup --type=full`). If a release needs to be rolled back:
1. Execute [`scripts/rollback-hostinger.sh`](file:///c:/Users/D552266/Desktop/ATS/scripts/rollback-hostinger.sh):
   ```bash
   bash scripts/rollback-hostinger.sh <previous-git-tag-or-commit>
   ```
2. The rollback script puts the app in maintenance mode, reverts thelast migration batch safely if needed (`php artisan migrate:rollback --step=1 --force`), checks out the verified stable commit, rebuilds framework caches, and brings the application back online without losing candidate records or private documents.
