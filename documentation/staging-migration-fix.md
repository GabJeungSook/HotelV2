# Staging Migration Fix Guide

## Issue
The staging server has already run migration `2026_04_21_115528_add_cleaning_by_user_id_to_rooms_table.php` which added the `cleaning_by_user_id` column to the `rooms` table. However, we are rolling back the code to commit `52b06d9` which does not include this migration or the code that uses it.

## Current State

| Environment | Code Commit | Database State |
|-------------|-------------|----------------|
| **Staging** | Rolling back to `52b06d9` | Has `cleaning_by_user_id` column |
| **Server/Production** | `52b06d9` | Does NOT have `cleaning_by_user_id` column |

## Migration Details

**File:** `database/migrations/2026_04_21_115528_add_cleaning_by_user_id_to_rooms_table.php`

```php
Schema::table('rooms', function (Blueprint $table) {
    $table->foreignId('cleaning_by_user_id')
        ->nullable()
        ->after('started_cleaning_at')
        ->constrained('users')
        ->nullOnDelete();
});
```

**Column Added:**
- Name: `cleaning_by_user_id`
- Type: `BIGINT UNSIGNED` (foreign key to `users.id`)
- Nullable: Yes
- Position: After `started_cleaning_at`
- On Delete: SET NULL

---

## Options to Fix

### Option A: Keep the Column (Recommended)

**Do nothing.** The column can stay in the database because:
- It's `nullable` so it won't cause any errors
- No code references it after rollback
- When you deploy the feature later, skip the migration or handle "column already exists"

**Pros:**
- Zero risk
- No database changes needed
- Easy to deploy feature later

**Cons:**
- Unused column in database (minimal impact)

---

### Option B: Rollback Migration via Artisan

**On staging server, run:**
```bash
cd /var/www/HotelV2
php artisan migrate:rollback --step=1
```

**Note:** This only works if the migration was the last one run. Check with:
```bash
php artisan migrate:status
```

**Pros:**
- Clean database state

**Cons:**
- Risk if other migrations were in the same batch

---

### Option C: Manual SQL Drop

**On staging server, connect to MySQL and run:**
```sql
-- First drop the foreign key constraint
ALTER TABLE rooms DROP FOREIGN KEY rooms_cleaning_by_user_id_foreign;

-- Then drop the column
ALTER TABLE rooms DROP COLUMN cleaning_by_user_id;

-- Remove from migrations table
DELETE FROM migrations WHERE migration = '2026_04_21_115528_add_cleaning_by_user_id_to_rooms_table';
```

**Pros:**
- Full control
- Works regardless of batch

**Cons:**
- Manual work
- Need database access

---

## Recommended Steps for Staging

1. **SSH into staging server:**
   ```bash
   ssh root@homiStagingApp
   cd /var/www/HotelV2
   ```

2. **Check current migration status:**
   ```bash
   php artisan migrate:status | grep cleaning
   ```

3. **Choose your fix option (A, B, or C)**

4. **Rollback the code:**
   ```bash
   git fetch origin
   git reset --hard 52b06d9
   ```

5. **Clear caches:**
   ```bash
   php artisan cache:clear
   php artisan config:clear
   php artisan view:clear
   php artisan route:clear
   ```

---

## When Deploying the Feature Later

If you kept the column (Option A), when deploying the Roomboy feature later:

1. **Check if column exists:**
   ```sql
   SHOW COLUMNS FROM rooms LIKE 'cleaning_by_user_id';
   ```

2. **If column exists, skip migration:**
   - Add migration to `migrations` table manually:
   ```sql
   INSERT INTO migrations (migration, batch)
   VALUES ('2026_04_21_115528_add_cleaning_by_user_id_to_rooms_table',
           (SELECT MAX(batch) + 1 FROM migrations m));
   ```

3. **Or modify migration to check first:**
   ```php
   public function up()
   {
       if (!Schema::hasColumn('rooms', 'cleaning_by_user_id')) {
           Schema::table('rooms', function (Blueprint $table) {
               $table->foreignId('cleaning_by_user_id')
                   ->nullable()
                   ->after('started_cleaning_at')
                   ->constrained('users')
                   ->nullOnDelete();
           });
       }
   }
   ```

---

## Related Files (to be deployed with this migration)

When deploying this feature later, ensure these files are included:

- `database/migrations/2026_04_21_115528_add_cleaning_by_user_id_to_rooms_table.php`
- `app/Models/Room.php` (cleaningBy relationship, scopeBeingCleanedBy)
- `app/Http/Livewire/Roomboy/Index.php`
- `app/Http/Livewire/Roomboy/Main.php`
- `app/Http/Livewire/Roomboy/CleaningHistory.php`
- `resources/views/livewire/roomboy/index.blade.php`
- `resources/views/livewire/roomboy/main.blade.php`
- `resources/views/livewire/roomboy/cleaning-history.blade.php`

---

## Contact

If issues arise, refer to `documentation/future-updates-apr21.md` for full feature documentation.

**Created:** April 21, 2026
**Reason:** Rolling back staging to match production while preserving future work
