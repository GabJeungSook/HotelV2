# Future-Updates Branch Deployment Guide

This document outlines critical steps and warnings for deploying the `future-updates` branch to production.

---

## Pre-Deployment Checklist

### 1. Production Database State (as of Apr 27, 2026)

**Last migration in production:**
```
2026_04_21_115528_add_cleaning_by_user_id_to_rooms_table (batch 8)
```

**Tables that do NOT exist in production yet:**
- `override_requests`
- `kiosk_current_batch`
- `stock_movements`
- `pos_orders`
- `menu_price_changes`

**Pending migrations to run:**
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

---

## Seeders Warning

### DO NOT RUN in Production:

| Seeder | Risk | Reason |
|--------|------|--------|
| `SupervisorAccountSeeder` | HIGH | Creates test account `supervisor@test.com` with password `password` |
| `DatabaseSeeder` | HIGH | Calls SupervisorAccountSeeder among others |

### Safe to Run in Production:

| Seeder | Notes |
|--------|-------|
| `SupervisorRoleSeeder` | Has existence check - only creates 'supervisor' role if not exists |

### Production Deployment Command:
```bash
# Run ONLY the role seeder, NOT the account seeder
php artisan db:seed --class=SupervisorRoleSeeder
```

**DO NOT RUN:**
```bash
php artisan db:seed  # This runs ALL seeders including test accounts!
```

---

## Local Development: Importing Production Database

When importing a production SQL dump to local development:

### Problem
If you import production SQL on top of an existing local database:
1. The `migrations` table gets replaced (showing production state)
2. But extra tables from development work remain (e.g., `override_requests`)
3. Running `php artisan migrate` will fail with "Table already exists"

### Solution 1: Clean Import (Recommended)
```bash
# Drop and recreate database
mysql -u root -e "DROP DATABASE hotel_v2; CREATE DATABASE hotel_v2;"

# Import production SQL
mysql -u root hotel_v2 < production_dump.sql

# Run migrations (will create missing tables)
php artisan migrate
```

### Solution 2: Sync Migrations Table
If you have local tables that already exist, manually mark migrations as run:
```php
php artisan tinker

// Insert records for migrations whose tables already exist
DB::table('migrations')->insert([
    ['migration' => '2026_04_23_100002_create_override_requests_table', 'batch' => 10],
    // ... add all pending migrations that already have tables
]);
```

---

## Disabled Features Awaiting Re-enable

### Ghost Check-in Guards (TEMPORARILY DISABLED 2026-04-24)

**Files with disabled guards:**
1. `app/Http/Livewire/Kiosk/CheckIn.php:333`
2. `app/Http/Livewire/Roomboy/Main.php:243`
3. `app/Http/Livewire/Roomboy/Index.php:152`

**What they do:**
- Prevent kiosk check-in if room has unresolved previous guest
- Prevent roomboy from marking room clean if room has unresolved guest

**Re-enable when:**
1. All ghost check-in records are resolved (currently 8 records)
2. All features in `future-updates` branch are deployed and stable

**See:** `documentation/unresolved-checkins-report.md`

---

## Deployment Steps

### Phase 1: Merge & Deploy Code
```bash
# On production server
cd /var/www/HotelV2
git fetch origin
git checkout master
git pull origin master
git merge origin/future-updates  # Or create PR and merge via GitHub
```

### Phase 2: Run Migrations
```bash
php artisan migrate --force
```

### Phase 3: Run ONLY Safe Seeders
```bash
# Create supervisor role (safe - has existence check)
php artisan db:seed --class=SupervisorRoleSeeder
```

### Phase 4: Clear Caches
```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
```

### Phase 5: Post-Deploy Verification
- [ ] Check all frontdesk functions work
- [ ] Verify kiosk check-in/check-out works
- [ ] Test supervisor override system
- [ ] Confirm POS transactions working
- [ ] Verify room monitoring displays correctly

---

## Rollback Plan

If deployment fails:
```bash
# Rollback last batch of migrations
php artisan migrate:rollback --step=1

# Or rollback specific batches
php artisan migrate:rollback --batch=10

# Revert to previous commit
git checkout <previous-commit-hash>
```

---

## Contact

For deployment issues, check:
- Laravel logs: `storage/logs/laravel.log`
- Migration status: `php artisan migrate:status`

---

*Last updated: April 27, 2026*
