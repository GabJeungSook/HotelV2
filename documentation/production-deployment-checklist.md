# Production Deployment Checklist

This document outlines the complete deployment process for merging `future-updates` branch to production.

---

## Server Information

| Item | Value |
|------|-------|
| Server | `root@HomiApp` |
| Path | `/var/www/HotelV2` |
| Branch | `master` |
| Web Server | Nginx |
| PHP | 8.2.8 (php8.2-fpm) |
| OPcache | Enabled (Zend OPcache v8.2.8) |
| Redis | Installed & Running |

---

## Pre-Deployment (Before Merging)

### 1. Verify Production State
```bash
ssh root@HomiApp
cd /var/www/HotelV2

# Check current branch and status
git status
git log -1

# Check current migration state
php artisan migrate:status | tail -20

# Verify Redis is running
sudo systemctl status redis-server
```

### 2. Backup Production Database
```bash
# Create backup before any changes
mysqldump -u root -p hotel_v2 > /root/backups/hotel_v2_$(date +%Y%m%d_%H%M%S).sql
```

### 3. Notify Users (Optional)
- Consider deploying during low-traffic hours
- Session driver change will logout all users

---

## Deployment Steps

### Step 1: Pull Latest Code
```bash
cd /var/www/HotelV2

# Fetch and merge
git fetch origin
git pull origin master

# Or if merging future-updates branch
git merge origin/future-updates
```

### Step 2: Install Dependencies (if composer.json changed)
```bash
composer install --no-dev --optimize-autoloader
npm install && npm run build
```

### Step 3: Run Migrations
```bash
php artisan migrate --force
```

**Expected migrations to run:**
```
2026_04_23_100001_add_force_auto_override_to_branches_table
2026_04_23_100002_create_override_requests_table
2026_04_23_100003_add_override_request_id_to_transactions_table
2026_04_23_170000_update_override_requests_foreign_keys
2026_04_24_191441_create_kiosk_current_batch_table
2026_04_25_120001_make_transactions_guest_room_floor_nullable
2026_04_25_120002_create_stock_movements_table
2026_04_25_120003_backfill_stock_movements_opening_balances
2026_04_25_120004_add_snapshot_columns_to_transactions_table
2026_04_25_120005_create_menu_price_changes_table
2026_04_25_120006_create_pos_orders_table
2026_04_25_120007_add_order_id_to_transactions_table
2026_04_26_120001_add_pos_v2_enabled_to_branches_table
2026_04_26_120002_add_void_columns_for_pos_v2
2026_04_26_120003_drop_pos_v2_enabled_make_v2_default
```

### Step 4: Run Safe Seeders Only
```bash
# ONLY run role seeder (creates supervisor role if not exists)
php artisan db:seed --class=SupervisorRoleSeeder

# DO NOT RUN:
# php artisan db:seed  ← This creates test accounts!
```

### Step 5: Install & Verify PHP Redis Client (REQUIRED before .env change)

Redis being installed at the OS level (`redis-server`) is **not enough**. Laravel/PHP needs its own Redis client to talk to the daemon. Without this, the moment you set `SESSION_DRIVER=redis` the app will crash with `Class 'Redis' not found`.

**5a. Install the PhpRedis extension (recommended — native C, faster)**
```bash
sudo apt install php8.2-redis
sudo systemctl restart php8.2-fpm

# Verify the extension loaded
php -m | grep redis
# Expected output: redis
```

**Alternative — Predis (pure PHP, slower but no apt needed)**
```bash
cd /var/www/HotelV2
composer require predis/predis
```

**5b. Verify Laravel can talk to Redis (BEFORE flipping the SESSION_DRIVER)**
```bash
cd /var/www/HotelV2
php artisan tinker
```

In the tinker shell:
```php
>>> Redis::set('homi:smoketest', 'hello');
>>> Redis::get('homi:smoketest');   // must return "hello"
>>> Redis::del('homi:smoketest');
>>> exit
```

If any of those fail, **STOP** — do not change `SESSION_DRIVER` yet. Common fixes:
- Extension not loaded: rerun `sudo systemctl restart php8.2-fpm` after `apt install`
- Connection refused: check `sudo systemctl status redis-server`
- Auth failed: align `.env` `REDIS_PASSWORD` with `/etc/redis/redis.conf` `requirepass`

### Step 6: Update .env for Redis (Session Driver)
```bash
nano /var/www/HotelV2/.env
```

**Change these values:**
```env
# Before (file-based sessions)
SESSION_DRIVER=file
CACHE_DRIVER=file
QUEUE_CONNECTION=sync

# After (Redis - better performance)
SESSION_DRIVER=redis
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis

# Pick the client you installed in Step 5a
REDIS_CLIENT=phpredis        # if you installed php8.2-redis
# REDIS_CLIENT=predis        # if you installed predis/predis via composer

# Redis connection (usually default)
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

**WARNING:** Changing `SESSION_DRIVER` will logout ALL currently logged-in users. Deploy during low-traffic hours.

### Step 7: Clear All Caches (Including Redis)
```bash
# Clear Laravel caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# Clear Redis data (fresh start)
redis-cli FLUSHALL

# Optimize
php artisan optimize
```

### Step 8: Restart Services
```bash
# Restart PHP-FPM
sudo systemctl restart php8.2-fpm

# Restart Nginx
sudo systemctl restart nginx
```

---

## Server Optimization

### Performance Improvements

| Optimization | Status | Impact |
|--------------|--------|--------|
| Redis for Sessions | Pending | Faster session read/write, reduces disk I/O |
| Redis for Cache | Pending | Faster cache operations |
| Laravel Config Cache | Do after deploy | Faster boot time |
| Laravel Route Cache | Do after deploy | Faster routing |
| Laravel View Cache | Do after deploy | Pre-compiled views |
| PHP OPcache | Already enabled | Faster PHP execution |

### Enable Redis (Sessions + Cache)
```bash
# Check Redis status
sudo systemctl status redis-server

# Redis memory optimization (edit redis.conf)
sudo nano /etc/redis/redis.conf
```

**Recommended settings:**
```conf
maxmemory 256mb
maxmemory-policy allkeys-lru
```

### Laravel Optimization
```bash
# Cache configuration (faster boot)
php artisan config:cache
php artisan route:cache
php artisan view:cache

# If using queue workers
php artisan queue:restart
```

### Database Optimization
```bash
# Check slow queries (if enabled)
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf

# Add indexes if needed (already done in migration 2026_03_28)
# Migration: add_performance_indexes
```

### Monitor Performance
```bash
# Check server load
htop

# Check PHP-FPM status
sudo systemctl status php8.2-fpm

# Check Nginx connections
sudo nginx -T | grep worker_connections

# Check Redis memory usage
redis-cli INFO memory | grep used_memory_human
```

---

## Post-Deployment Verification

### 1. Check Application
- [ ] Homepage loads correctly
- [ ] Login works (frontdesk, admin, supervisor)
- [ ] Kiosk check-in/check-out works
- [ ] Room monitoring displays correctly
- [ ] POS transactions work
- [ ] Supervisor override system works

### 2. Check Logs
```bash
# Check Laravel logs for errors
tail -100 /var/www/HotelV2/storage/logs/laravel.log

# Check nginx/apache error logs
tail -100 /var/log/nginx/error.log
```

### 3. Verify Redis Sessions
```bash
# Connect to Redis CLI
redis-cli

# Check if sessions are being stored
KEYS laravel_session:*
```

---

## Rollback Plan

### If Migration Fails
```bash
# Rollback last batch
php artisan migrate:rollback --step=1

# Or rollback to specific batch
php artisan migrate:rollback --batch=10
```

### If Application Breaks
```bash
# Revert to previous commit
git log --oneline -5  # Find previous commit hash
git checkout <previous-commit-hash>

# Clear caches
php artisan config:clear
php artisan cache:clear
```

### If Redis Causes Issues
```bash
# Revert to file sessions
nano /var/www/HotelV2/.env
# Change SESSION_DRIVER=file

php artisan config:clear
php artisan cache:clear
```

---

## Known Issues & Warnings

### 1. SupervisorAccountSeeder
**DO NOT RUN** `php artisan db:seed` - it creates test account `supervisor@test.com`

### 2. Ghost Check-in Guards (Disabled)
Guards in these files are currently disabled:
- `app/Http/Livewire/Kiosk/CheckIn.php:333`
- `app/Http/Livewire/Roomboy/Main.php:243`
- `app/Http/Livewire/Roomboy/Index.php:152`

Re-enable after resolving existing ghost records (see `unresolved-checkins-report.md`)

### 3. Session Logout
Changing `SESSION_DRIVER` from `file` to `redis` will logout all users immediately.

---

## Quick Reference Commands

```bash
# SSH to server
ssh root@HomiApp

# Navigate to project
cd /var/www/HotelV2

# Full deployment sequence
git pull origin master && \
composer install --no-dev --optimize-autoloader && \
php artisan migrate --force && \
php artisan db:seed --class=SupervisorRoleSeeder && \
php artisan config:clear && \
php artisan cache:clear && \
php artisan view:clear && \
php artisan route:clear && \
redis-cli FLUSHALL && \
php artisan optimize

# Check status
php artisan migrate:status | tail -10
tail -50 storage/logs/laravel.log
```

---

*Last updated: April 27, 2026*
