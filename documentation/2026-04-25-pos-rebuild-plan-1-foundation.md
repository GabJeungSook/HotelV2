# POS Rebuild — Plan 1 of 3: Foundation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Land the data + service foundation so every stock change in the system (frontdesk POS, kitchen, pub, deliveries) writes one auditable row, and add a Stock-In form so deliveries can be recorded.

**Architecture:** New `stock_movements` table (polymorphic across the three inventory worlds: frontdesk_inventories / inventories / pub_inventories). `StockService` is the single API for `in / out / adjust / void / opening`. Existing Kitchen/Pub flows are refactored to call `StockService` instead of writing inventory directly — same UX, same money flow, just unified backend. POS UI is untouched in this plan.

**Tech Stack:** Laravel 9, PHP 8, Livewire 2, MySQL, PHPUnit.

**Spec:** [2026-04-25-pos-module-rebuild-design.md](2026-04-25-pos-module-rebuild-design.md)

**Plans 2 and 3 (deferred):** POS v2 UI rewrite; BigBoss report POS+Inventory sections.

---

## Production safety contract

**The system is live. Every task in this plan must follow these rules:**

### Before running ANY migration in production

1. **DB backup taken within the last 1 hour.** Verify the backup file exists and has non-zero size.
2. **Run the migration in a staging copy first** (or in a local copy of the production DB, which is the user's standard practice).
3. **Run during a low-traffic window** (after midnight local time). Frontdesk activity drops sharply between 1am–5am.
4. **Have the rollback command ready in another terminal** before invoking `php artisan migrate`.

### Risk classification per task

| Task | What it changes | Risk | Mitigation |
|---|---|---|---|
| 1 — `transactions` nullable | ALTER on a hot table; brief metadata lock during `->change()` | **MEDIUM** | Run off-hours; existing rows untouched (all have non-null values today); rollback is `migrate:rollback` (also brief lock) |
| 2 — `stock_movements` create | New table; no existing data touched | **NONE** | None needed |
| 3 — `StockSourceResolver` | Pure PHP class, not wired in yet | **NONE** | None needed |
| 4 — `StockService` | Pure PHP class, not wired in yet | **NONE** | None needed |
| 5 — Backfill OPENING | Reads 3 inventory tables, inserts into `stock_movements` | **LOW** | Idempotent; safe to re-run; no UPDATEs on existing rows |
| 6 — Kitchen refactor | Modifies an actively-used Livewire component | **HIGH** | **Shadow-write phase** — see Task 6 details |
| 7 — Pub refactor | Modifies an actively-used Livewire component | **HIGH** | **Shadow-write phase** — see Task 7 details |
| 8 — Stock-In form | New page only | **NONE** | Behind frontdesk role gate |
| 9 — Nav link | Sidebar template change | **LOW** | Visual-only |

### Shadow-write rule for Kitchen / Pub (Tasks 6 & 7)

The Kitchen/Pub flows must be refactored in **two commits**, not one:

**Commit A — add StockService write alongside the existing direct inventory update.** Both code paths run. If they diverge, the new `stock_movements` row's `balance_after` will mismatch the inventory's `number_of_serving`. This gives us a live, observable signal of bugs without breaking the user-facing flow.

**Commit B — remove the old direct inventory update.** Only after Commit A has run for **at least one full shift in production** AND a manual SQL spot-check confirms `stock_movements.balance_after` matches `inventories.number_of_serving` for every row touched.

Skipping the shadow-write phase would mean a silent bug in the refactor breaks live kitchen/pub flows with no warning.

### Rollback plans

| Step | Rollback command | Data impact |
|---|---|---|
| Task 1 migration | `php artisan migrate:rollback --step=1` | None (no data was changed) |
| Task 2 migration | `php artisan migrate:rollback --step=1` | Drops `stock_movements` table — only opening backfill data lost |
| Task 5 migration | `php artisan migrate:rollback --step=1` | Deletes only `OPENING` rows — safe to re-run forward |
| Task 6 / 7 (code) | `git revert <commit>` | None (Livewire reload picks up the revert) |

### Pre-flight checklist (run this before deploying any task)

```bash
# 1. Backup verified
ls -lh /path/to/latest/db-backup.sql   # confirm recent + non-zero

# 2. Tests green locally
php artisan test --filter=Pos

# 3. Tests green in CI (if applicable)

# 4. Staging migration tested
php artisan migrate --pretend           # dry-run shows expected SQL

# 5. Off-hours window for migration tasks (Tasks 1, 2, 5 only)
date  # confirm 1am–5am local
```

### Monitoring after each deploy

For 1 hour after deploying Tasks 6 or 7:
- Tail Laravel logs: `tail -f storage/logs/laravel.log`
- Watch for `InsufficientStockException` from non-test paths (means a real out-of-stock event)
- Spot-check: `SELECT id, source_type, type, balance_after FROM stock_movements ORDER BY id DESC LIMIT 20;`
- Confirm matches: for each recent movement, `inventories.number_of_serving` should equal `balance_after`.

---

## File map

**Create:**
- `database/migrations/2026_04_25_120001_make_transactions_guest_room_floor_nullable.php`
- `database/migrations/2026_04_25_120002_create_stock_movements_table.php`
- `database/migrations/2026_04_25_120003_backfill_stock_movements_opening_balances.php`
- `app/Models/StockMovement.php`
- `app/Services/Pos/StockService.php`
- `app/Services/Pos/StockSourceResolver.php`
- `app/Http/Livewire/Frontdesk/StockIn.php`
- `resources/views/livewire/frontdesk/stock-in.blade.php`
- `tests/Feature/Pos/StockServiceTest.php`
- `tests/Feature/Pos/StockSourceResolverTest.php`
- `tests/Feature/Pos/StockInTest.php`
- `tests/Feature/Pos/KitchenTransactionUsesStockServiceTest.php`
- `tests/Feature/Pos/PubTransactionUsesStockServiceTest.php`

**Modify:**
- `app/Http/Livewire/Kitchen/Transaction.php` — replace direct `Inventory::update()` with `StockService::out()`
- `app/Http/Livewire/Pub/PubTransaction.php` — same pattern
- `routes/frontdesk.php` — add `frontdesk.stock-in` route

**Untouched in this plan:**
- POS UI (`PointOfSale.php`) — Plan 2
- BigBossReport — Plan 3
- The three inventory tables — kept; `stock_movements` references them polymorphically

---

## Conventions

- Money columns on `transactions` are `integer` (matches existing schema). Use integer for any new money columns.
- Stock quantities stored as `decimal(10,2)` (matches existing `number_of_serving` doubles).
- All migrations use `2026_04_25_120NNN_*` prefix to keep ordering tight.
- Tests live under `tests/Feature/Pos/`. Use `RefreshDatabase` trait. Follow existing pattern from `tests/Feature/KioskBatch/`.
- Each task ends with a commit using the message shown in its final step.

---

## Task 1: Make `transactions.guest_id`, `room_id`, `floor_id` nullable

**Why:** Walk-in POS sales (Plan 2) need to insert a row with no guest/room. Kitchen flow always has them, so existing data is untouched. This migration adds the schema flexibility now so Plan 2 just inserts.

**Files:**
- Create: `database/migrations/2026_04_25_120001_make_transactions_guest_room_floor_nullable.php`
- Test: `tests/Feature/Pos/TransactionsNullableColumnsTest.php`

- [ ] **Step 1: Add `doctrine/dbal` if not already present**

`->change()` requires it. Check first:

```bash
grep doctrine/dbal composer.json
```

If absent:

```bash
composer require doctrine/dbal
```

- [ ] **Step 2: Write the failing test**

Create `tests/Feature/Pos/TransactionsNullableColumnsTest.php`:

```php
<?php

namespace Tests\Feature\Pos;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TransactionsNullableColumnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_transactions_guest_room_floor_columns_are_nullable(): void
    {
        $columns = ['guest_id', 'room_id', 'floor_id'];

        foreach ($columns as $col) {
            $info = DB::selectOne(
                "SELECT IS_NULLABLE FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'transactions'
                   AND COLUMN_NAME = ?",
                [$col]
            );
            $this->assertSame('YES', $info->IS_NULLABLE, "transactions.{$col} must be nullable");
        }
    }
}
```

- [ ] **Step 3: Run test — verify it fails**

```bash
php artisan test --filter=TransactionsNullableColumnsTest
```

Expected: FAIL — `transactions.guest_id must be nullable` (currently NOT NULL).

- [ ] **Step 4: Write the migration**

Create `database/migrations/2026_04_25_120001_make_transactions_guest_room_floor_nullable.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('guest_id')->nullable()->change();
            $table->unsignedBigInteger('room_id')->nullable()->change();
            $table->unsignedBigInteger('floor_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('guest_id')->nullable(false)->change();
            $table->unsignedBigInteger('room_id')->nullable(false)->change();
            $table->unsignedBigInteger('floor_id')->nullable(false)->change();
        });
    }
};
```

- [ ] **Step 5: Run test — verify it passes**

```bash
php artisan test --filter=TransactionsNullableColumnsTest
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_04_25_120001_make_transactions_guest_room_floor_nullable.php tests/Feature/Pos/TransactionsNullableColumnsTest.php
git commit -m "make transactions guest_id/room_id/floor_id nullable for walk-in POS"
```

---

## Task 2: Create `stock_movements` table + `StockMovement` model

**Files:**
- Create: `database/migrations/2026_04_25_120002_create_stock_movements_table.php`
- Create: `app/Models/StockMovement.php`
- Test: `tests/Feature/Pos/StockMovementSchemaTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Pos/StockMovementSchemaTest.php`:

```php
<?php

namespace Tests\Feature\Pos;

use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StockMovementSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_movements_table_exists_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('stock_movements'));

        $expected = [
            'id', 'branch_id',
            'source_type', 'menu_id', 'inventory_id',
            'type', 'quantity', 'balance_after',
            'reason', 'ref_type', 'ref_id',
            'user_id', 'shift_log_id',
            'created_at', 'updated_at',
        ];

        foreach ($expected as $col) {
            $this->assertTrue(
                Schema::hasColumn('stock_movements', $col),
                "stock_movements is missing column {$col}"
            );
        }
    }

    public function test_stock_movement_model_can_persist_and_read_back(): void
    {
        $movement = StockMovement::create([
            'branch_id'    => 1,
            'source_type'  => 'frontdesk',
            'menu_id'      => 1,
            'inventory_id' => 1,
            'type'         => 'OPENING',
            'quantity'     => 10,
            'balance_after'=> 10,
            'reason'       => null,
            'ref_type'     => null,
            'ref_id'       => null,
            'user_id'      => null,
            'shift_log_id' => null,
        ]);

        $this->assertNotNull($movement->id);
        $this->assertSame('OPENING', $movement->fresh()->type);
        $this->assertEquals(10, $movement->fresh()->balance_after);
    }
}
```

- [ ] **Step 2: Run test — verify it fails**

```bash
php artisan test --filter=StockMovementSchemaTest
```

Expected: FAIL — table does not exist.

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_04_25_120002_create_stock_movements_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable();

            // polymorphic source: frontdesk | kitchen | pub
            $table->string('source_type', 20);
            $table->unsignedBigInteger('menu_id');
            $table->unsignedBigInteger('inventory_id');

            $table->enum('type', ['IN', 'OUT', 'ADJUST', 'VOID', 'OPENING']);
            $table->decimal('quantity', 10, 2);
            $table->decimal('balance_after', 10, 2);

            $table->string('reason', 255)->nullable();
            $table->string('ref_type', 50)->nullable();
            $table->unsignedBigInteger('ref_id')->nullable();

            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('shift_log_id')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'menu_id']);
            $table->index('shift_log_id');
            $table->index(['branch_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
```

- [ ] **Step 4: Create the model**

Create `app/Models/StockMovement.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use HasFactory;

    public const TYPE_IN      = 'IN';
    public const TYPE_OUT     = 'OUT';
    public const TYPE_ADJUST  = 'ADJUST';
    public const TYPE_VOID    = 'VOID';
    public const TYPE_OPENING = 'OPENING';

    public const SOURCE_FRONTDESK = 'frontdesk';
    public const SOURCE_KITCHEN   = 'kitchen';
    public const SOURCE_PUB       = 'pub';

    protected $fillable = [
        'branch_id',
        'source_type',
        'menu_id',
        'inventory_id',
        'type',
        'quantity',
        'balance_after',
        'reason',
        'ref_type',
        'ref_id',
        'user_id',
        'shift_log_id',
    ];

    protected $casts = [
        'quantity'      => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];
}
```

- [ ] **Step 5: Run test — verify it passes**

```bash
php artisan test --filter=StockMovementSchemaTest
```

Expected: PASS (both tests).

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_04_25_120002_create_stock_movements_table.php app/Models/StockMovement.php tests/Feature/Pos/StockMovementSchemaTest.php
git commit -m "add stock_movements table and StockMovement model"
```

---

## Task 3: `StockSourceResolver` — map source_type to inventory model

**Why:** `StockService` needs a single way to read/write the correct inventory table given a `source_type`. Centralizing this prevents `if/elseif/elseif` chains scattering across the codebase.

**Files:**
- Create: `app/Services/Pos/StockSourceResolver.php`
- Test: `tests/Feature/Pos/StockSourceResolverTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Pos/StockSourceResolverTest.php`:

```php
<?php

namespace Tests\Feature\Pos;

use App\Models\FrontdeskInventory;
use App\Models\Inventory;
use App\Models\PubInventory;
use App\Models\StockMovement;
use App\Services\Pos\StockSourceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockSourceResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_frontdesk_to_frontdesk_inventory_model(): void
    {
        $resolver = new StockSourceResolver();
        $this->assertSame(FrontdeskInventory::class, $resolver->modelFor(StockMovement::SOURCE_FRONTDESK));
    }

    public function test_resolves_kitchen_to_inventory_model(): void
    {
        $resolver = new StockSourceResolver();
        $this->assertSame(Inventory::class, $resolver->modelFor(StockMovement::SOURCE_KITCHEN));
    }

    public function test_resolves_pub_to_pub_inventory_model(): void
    {
        $resolver = new StockSourceResolver();
        $this->assertSame(PubInventory::class, $resolver->modelFor(StockMovement::SOURCE_PUB));
    }

    public function test_unknown_source_type_throws(): void
    {
        $resolver = new StockSourceResolver();
        $this->expectException(\InvalidArgumentException::class);
        $resolver->modelFor('mystery');
    }

    public function test_findInventory_returns_correct_row_per_source(): void
    {
        $fdi = FrontdeskInventory::create([
            'branch_id' => 1,
            'frontdesk_menu_id' => 99,
            'number_of_serving' => 5,
        ]);

        $resolver = new StockSourceResolver();
        $found = $resolver->findInventory(StockMovement::SOURCE_FRONTDESK, 99, 1);
        $this->assertNotNull($found);
        $this->assertEquals($fdi->id, $found->id);
    }
}
```

- [ ] **Step 2: Run test — verify it fails**

```bash
php artisan test --filter=StockSourceResolverTest
```

Expected: FAIL — class `StockSourceResolver` not found.

- [ ] **Step 3: Implement `StockSourceResolver`**

Create `app/Services/Pos/StockSourceResolver.php`:

```php
<?php

namespace App\Services\Pos;

use App\Models\FrontdeskInventory;
use App\Models\Inventory;
use App\Models\PubInventory;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class StockSourceResolver
{
    /**
     * Inventory model class for a given source_type.
     */
    public function modelFor(string $sourceType): string
    {
        return match ($sourceType) {
            StockMovement::SOURCE_FRONTDESK => FrontdeskInventory::class,
            StockMovement::SOURCE_KITCHEN   => Inventory::class,
            StockMovement::SOURCE_PUB       => PubInventory::class,
            default => throw new InvalidArgumentException("Unknown stock source: {$sourceType}"),
        };
    }

    /**
     * Find the inventory row that holds stock for a given menu under a given source.
     * Returns null if not present.
     */
    public function findInventory(string $sourceType, int $menuId, int $branchId): ?Model
    {
        $modelClass = $this->modelFor($sourceType);
        $menuFk = $this->menuForeignKey($sourceType);

        return $modelClass::query()
            ->where('branch_id', $branchId)
            ->where($menuFk, $menuId)
            ->first();
    }

    /**
     * Foreign key column on the inventory table that links to the menu.
     */
    public function menuForeignKey(string $sourceType): string
    {
        return match ($sourceType) {
            StockMovement::SOURCE_FRONTDESK => 'frontdesk_menu_id',
            StockMovement::SOURCE_KITCHEN   => 'menu_id',
            StockMovement::SOURCE_PUB       => 'pub_menu_id',
            default => throw new InvalidArgumentException("Unknown stock source: {$sourceType}"),
        };
    }
}
```

> **Verify FK column names before committing:** Look at `FrontdeskInventory`, `Inventory`, `PubInventory` model files (or their migrations) and confirm the `menuForeignKey` returns match. If a model uses a different column name (e.g. `pub_menu_id` vs `menu_id`), update this method to match. The test uses `frontdesk_menu_id` for FrontdeskInventory which is the column from `2024_05_06_084026_create_frontdesk_inventories_table.php`.

- [ ] **Step 4: Run test — verify it passes**

```bash
php artisan test --filter=StockSourceResolverTest
```

Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/Pos/StockSourceResolver.php tests/Feature/Pos/StockSourceResolverTest.php
git commit -m "add StockSourceResolver to map source_type to inventory model"
```

---

## Task 4: `StockService` — single API for stock changes

**Why:** Every stock change in the system goes through this service. It writes the movement row AND updates the inventory `number_of_serving`, atomically. Callers never touch inventory directly.

**Files:**
- Create: `app/Services/Pos/StockService.php`
- Test: `tests/Feature/Pos/StockServiceTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Pos/StockServiceTest.php`:

```php
<?php

namespace Tests\Feature\Pos;

use App\Models\FrontdeskInventory;
use App\Models\StockMovement;
use App\Services\Pos\InsufficientStockException;
use App\Services\Pos\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeFrontdeskInventory(float $startQty = 10): FrontdeskInventory
    {
        return FrontdeskInventory::create([
            'branch_id'         => 1,
            'frontdesk_menu_id' => 100,
            'number_of_serving' => $startQty,
        ]);
    }

    public function test_in_increases_balance_and_writes_movement(): void
    {
        $inv = $this->makeFrontdeskInventory(5);
        $svc = app(StockService::class);

        $svc->in(StockMovement::SOURCE_FRONTDESK, 100, 3, [
            'branch_id' => 1,
            'reason'    => 'delivery',
            'user_id'   => null,
        ]);

        $this->assertEquals(8, $inv->fresh()->number_of_serving);

        $movement = StockMovement::latest('id')->first();
        $this->assertSame(StockMovement::TYPE_IN, $movement->type);
        $this->assertEquals(3, $movement->quantity);
        $this->assertEquals(8, $movement->balance_after);
    }

    public function test_out_decreases_balance_and_writes_movement(): void
    {
        $inv = $this->makeFrontdeskInventory(5);
        $svc = app(StockService::class);

        $svc->out(StockMovement::SOURCE_FRONTDESK, 100, 2, [
            'branch_id' => 1,
            'ref_type'  => 'transaction',
            'ref_id'    => 999,
        ]);

        $this->assertEquals(3, $inv->fresh()->number_of_serving);

        $movement = StockMovement::latest('id')->first();
        $this->assertSame(StockMovement::TYPE_OUT, $movement->type);
        $this->assertEquals(2, $movement->quantity);
        $this->assertEquals(3, $movement->balance_after);
        $this->assertSame('transaction', $movement->ref_type);
        $this->assertSame(999, $movement->ref_id);
    }

    public function test_out_throws_when_insufficient_and_writes_no_movement(): void
    {
        $inv = $this->makeFrontdeskInventory(2);
        $svc = app(StockService::class);

        try {
            $svc->out(StockMovement::SOURCE_FRONTDESK, 100, 5, ['branch_id' => 1]);
            $this->fail('Expected InsufficientStockException');
        } catch (InsufficientStockException $e) {
            $this->assertEquals(2, $e->available);
            $this->assertEquals(5, $e->requested);
        }

        $this->assertEquals(2, $inv->fresh()->number_of_serving);
        $this->assertSame(0, StockMovement::count());
    }

    public function test_out_throws_when_inventory_row_missing(): void
    {
        $svc = app(StockService::class);
        $this->expectException(InsufficientStockException::class);
        $svc->out(StockMovement::SOURCE_FRONTDESK, 9999, 1, ['branch_id' => 1]);
    }

    public function test_void_reverses_a_previous_out_via_ref(): void
    {
        $inv = $this->makeFrontdeskInventory(10);
        $svc = app(StockService::class);

        $svc->out(StockMovement::SOURCE_FRONTDESK, 100, 4, [
            'branch_id' => 1, 'ref_type' => 'transaction', 'ref_id' => 555,
        ]);
        $this->assertEquals(6, $inv->fresh()->number_of_serving);

        $svc->void(StockMovement::SOURCE_FRONTDESK, 100, 4, [
            'branch_id' => 1, 'ref_type' => 'transaction', 'ref_id' => 555,
            'reason' => 'mistake',
        ]);
        $this->assertEquals(10, $inv->fresh()->number_of_serving);

        $voidMovement = StockMovement::where('type', StockMovement::TYPE_VOID)->first();
        $this->assertNotNull($voidMovement);
        $this->assertEquals(10, $voidMovement->balance_after);
        $this->assertSame(555, $voidMovement->ref_id);
    }

    public function test_adjust_sets_absolute_balance_and_writes_movement(): void
    {
        $inv = $this->makeFrontdeskInventory(5);
        $svc = app(StockService::class);

        $svc->adjust(StockMovement::SOURCE_FRONTDESK, 100, 12, [
            'branch_id' => 1, 'reason' => 'physical count',
        ]);

        $this->assertEquals(12, $inv->fresh()->number_of_serving);
        $movement = StockMovement::latest('id')->first();
        $this->assertSame(StockMovement::TYPE_ADJUST, $movement->type);
        $this->assertEquals(12, $movement->balance_after);
    }

    public function test_concurrent_outs_do_not_oversell(): void
    {
        // Sanity check: two sequential out() calls within DB::transaction
        // should both succeed if combined qty fits, or second fails cleanly.
        $inv = $this->makeFrontdeskInventory(3);
        $svc = app(StockService::class);

        $svc->out(StockMovement::SOURCE_FRONTDESK, 100, 2, ['branch_id' => 1]);
        $this->expectException(InsufficientStockException::class);
        $svc->out(StockMovement::SOURCE_FRONTDESK, 100, 2, ['branch_id' => 1]);
    }
}
```

- [ ] **Step 2: Run test — verify it fails**

```bash
php artisan test --filter=StockServiceTest
```

Expected: FAIL — class `StockService` not found.

- [ ] **Step 3: Implement exception class**

Create `app/Services/Pos/InsufficientStockException.php`:

```php
<?php

namespace App\Services\Pos;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public float $available;
    public float $requested;
    public int $menuId;
    public string $sourceType;

    public function __construct(string $sourceType, int $menuId, float $available, float $requested)
    {
        parent::__construct(
            "Insufficient stock for {$sourceType}#{$menuId}: have {$available}, need {$requested}"
        );
        $this->sourceType = $sourceType;
        $this->menuId     = $menuId;
        $this->available  = $available;
        $this->requested  = $requested;
    }
}
```

- [ ] **Step 4: Implement `StockService`**

Create `app/Services/Pos/StockService.php`:

```php
<?php

namespace App\Services\Pos;

use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class StockService
{
    public function __construct(private StockSourceResolver $resolver) {}

    public function in(string $sourceType, int $menuId, float $qty, array $context): StockMovement
    {
        return $this->apply($sourceType, $menuId, $qty, StockMovement::TYPE_IN, $context);
    }

    public function out(string $sourceType, int $menuId, float $qty, array $context): StockMovement
    {
        return $this->apply($sourceType, $menuId, $qty, StockMovement::TYPE_OUT, $context);
    }

    public function void(string $sourceType, int $menuId, float $qty, array $context): StockMovement
    {
        return $this->apply($sourceType, $menuId, $qty, StockMovement::TYPE_VOID, $context);
    }

    public function adjust(string $sourceType, int $menuId, float $absoluteBalance, array $context): StockMovement
    {
        return DB::transaction(function () use ($sourceType, $menuId, $absoluteBalance, $context) {
            $branchId = $context['branch_id'] ?? auth()->user()?->branch_id;
            $inventory = $this->resolver->findInventory($sourceType, $menuId, $branchId);

            if ($inventory === null) {
                throw new InsufficientStockException($sourceType, $menuId, 0, $absoluteBalance);
            }

            $delta = $absoluteBalance - (float) $inventory->number_of_serving;
            $inventory->number_of_serving = $absoluteBalance;
            $inventory->save();

            return StockMovement::create([
                'branch_id'    => $branchId,
                'source_type'  => $sourceType,
                'menu_id'      => $menuId,
                'inventory_id' => $inventory->id,
                'type'         => StockMovement::TYPE_ADJUST,
                'quantity'     => abs($delta),
                'balance_after'=> $absoluteBalance,
                'reason'       => $context['reason'] ?? null,
                'ref_type'     => $context['ref_type'] ?? null,
                'ref_id'       => $context['ref_id'] ?? null,
                'user_id'      => $context['user_id'] ?? auth()->id(),
                'shift_log_id' => $context['shift_log_id'] ?? null,
            ]);
        });
    }

    private function apply(string $sourceType, int $menuId, float $qty, string $type, array $context): StockMovement
    {
        return DB::transaction(function () use ($sourceType, $menuId, $qty, $type, $context) {
            $branchId = $context['branch_id'] ?? auth()->user()?->branch_id;

            // Lock the inventory row to prevent concurrent oversell.
            $modelClass = $this->resolver->modelFor($sourceType);
            $menuFk     = $this->resolver->menuForeignKey($sourceType);

            $inventory = $modelClass::query()
                ->where('branch_id', $branchId)
                ->where($menuFk, $menuId)
                ->lockForUpdate()
                ->first();

            $available = $inventory ? (float) $inventory->number_of_serving : 0.0;

            $isOut = $type === StockMovement::TYPE_OUT;
            if ($isOut && ($inventory === null || $available < $qty)) {
                throw new InsufficientStockException($sourceType, $menuId, $available, $qty);
            }

            // Compute new balance.
            $newBalance = match ($type) {
                StockMovement::TYPE_IN, StockMovement::TYPE_VOID => $available + $qty,
                StockMovement::TYPE_OUT                          => $available - $qty,
                default => $available,
            };

            if ($inventory === null) {
                // Only IN/VOID can create a new inventory row implicitly.
                $inventory = $modelClass::create([
                    'branch_id'        => $branchId,
                    $menuFk            => $menuId,
                    'number_of_serving'=> $newBalance,
                ]);
            } else {
                $inventory->number_of_serving = $newBalance;
                $inventory->save();
            }

            return StockMovement::create([
                'branch_id'    => $branchId,
                'source_type'  => $sourceType,
                'menu_id'      => $menuId,
                'inventory_id' => $inventory->id,
                'type'         => $type,
                'quantity'     => $qty,
                'balance_after'=> $newBalance,
                'reason'       => $context['reason'] ?? null,
                'ref_type'     => $context['ref_type'] ?? null,
                'ref_id'       => $context['ref_id'] ?? null,
                'user_id'      => $context['user_id'] ?? auth()->id(),
                'shift_log_id' => $context['shift_log_id'] ?? null,
            ]);
        });
    }
}
```

- [ ] **Step 5: Run tests — verify they pass**

```bash
php artisan test --filter=StockServiceTest
```

Expected: PASS (7 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Services/Pos/StockService.php app/Services/Pos/InsufficientStockException.php tests/Feature/Pos/StockServiceTest.php
git commit -m "add StockService with row-locking IN/OUT/ADJUST/VOID"
```

---

## Task 5: Backfill `OPENING` movements from current inventory tables

**Why:** Before any new movements get logged, every existing inventory row needs an `OPENING` row in `stock_movements`. Without this, `balance_after` history will start mid-flight and the BigBoss "what came in this shift" column will show wrong totals.

**Files:**
- Create: `database/migrations/2026_04_25_120003_backfill_stock_movements_opening_balances.php`
- Test: `tests/Feature/Pos/BackfillOpeningBalancesTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Pos/BackfillOpeningBalancesTest.php`:

```php
<?php

namespace Tests\Feature\Pos;

use App\Models\FrontdeskInventory;
use App\Models\Inventory;
use App\Models\PubInventory;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BackfillOpeningBalancesTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_creates_opening_movement_per_inventory_row(): void
    {
        // Roll the schema back BEFORE the backfill migration so we can seed
        // pre-existing inventory rows, then run the backfill in isolation.
        Artisan::call('migrate:rollback', ['--step' => 1]);

        FrontdeskInventory::create(['branch_id' => 1, 'frontdesk_menu_id' => 1, 'number_of_serving' => 12]);
        Inventory::create(['branch_id' => 1, 'menu_id' => 2, 'number_of_serving' => 7]);
        PubInventory::create(['branch_id' => 1, 'pub_menu_id' => 3, 'number_of_serving' => 4]);

        Artisan::call('migrate');

        $this->assertSame(3, StockMovement::where('type', StockMovement::TYPE_OPENING)->count());

        $fd = StockMovement::where('source_type', StockMovement::SOURCE_FRONTDESK)->first();
        $this->assertEquals(12, $fd->balance_after);

        $kt = StockMovement::where('source_type', StockMovement::SOURCE_KITCHEN)->first();
        $this->assertEquals(7, $kt->balance_after);

        $pb = StockMovement::where('source_type', StockMovement::SOURCE_PUB)->first();
        $this->assertEquals(4, $pb->balance_after);
    }

    public function test_backfill_is_idempotent_when_re_run(): void
    {
        FrontdeskInventory::create(['branch_id' => 1, 'frontdesk_menu_id' => 50, 'number_of_serving' => 8]);

        // Re-run the backfill migration body manually
        $migration = require database_path('migrations/2026_04_25_120003_backfill_stock_movements_opening_balances.php');
        $migration->up();
        $migration->up(); // run twice

        $this->assertSame(
            1,
            StockMovement::where('source_type', StockMovement::SOURCE_FRONTDESK)
                ->where('menu_id', 50)
                ->where('type', StockMovement::TYPE_OPENING)
                ->count(),
            'Backfill must not insert duplicate OPENING rows on re-run'
        );
    }
}
```

- [ ] **Step 2: Run test — verify it fails**

```bash
php artisan test --filter=BackfillOpeningBalancesTest
```

Expected: FAIL — migration file does not exist.

- [ ] **Step 3: Implement the backfill migration**

Create `database/migrations/2026_04_25_120003_backfill_stock_movements_opening_balances.php`:

```php
<?php

use App\Models\FrontdeskInventory;
use App\Models\Inventory;
use App\Models\PubInventory;
use App\Models\StockMovement;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        $this->backfillFor(
            FrontdeskInventory::query()->where('number_of_serving', '>', 0)->get(),
            StockMovement::SOURCE_FRONTDESK,
            'frontdesk_menu_id'
        );

        $this->backfillFor(
            Inventory::query()->where('number_of_serving', '>', 0)->get(),
            StockMovement::SOURCE_KITCHEN,
            'menu_id'
        );

        $this->backfillFor(
            PubInventory::query()->where('number_of_serving', '>', 0)->get(),
            StockMovement::SOURCE_PUB,
            'pub_menu_id'
        );
    }

    public function down(): void
    {
        StockMovement::where('type', StockMovement::TYPE_OPENING)->delete();
    }

    private function backfillFor($inventoryRows, string $sourceType, string $menuFk): void
    {
        foreach ($inventoryRows as $row) {
            $exists = StockMovement::where('source_type', $sourceType)
                ->where('inventory_id', $row->id)
                ->where('type', StockMovement::TYPE_OPENING)
                ->exists();

            if ($exists) {
                continue;
            }

            StockMovement::create([
                'branch_id'    => $row->branch_id,
                'source_type'  => $sourceType,
                'menu_id'      => $row->{$menuFk},
                'inventory_id' => $row->id,
                'type'         => StockMovement::TYPE_OPENING,
                'quantity'     => (float) $row->number_of_serving,
                'balance_after'=> (float) $row->number_of_serving,
                'reason'       => 'opening balance backfill',
                'ref_type'     => null,
                'ref_id'       => null,
                'user_id'      => null,
                'shift_log_id' => null,
            ]);
        }
    }
};
```

- [ ] **Step 4: Run test — verify it passes**

```bash
php artisan test --filter=BackfillOpeningBalancesTest
```

Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_04_25_120003_backfill_stock_movements_opening_balances.php tests/Feature/Pos/BackfillOpeningBalancesTest.php
git commit -m "backfill stock_movements OPENING balances from existing inventory tables"
```

---

## Task 6: Kitchen `Transaction.php` — shadow-write to StockService

**Why:** Kitchen flow is live. We refactor in TWO commits to make a silent bug observable instead of explosive.

**Commit 6A (this task):** ADD a `StockService::out()` call alongside the existing `$inventory->update()`. Both run. The two writes should agree on the resulting balance — if they ever disagree, the next page load shows the discrepancy.

**Commit 6B (Task 6.5, after at least one shift in production):** Remove the old direct update.

**Files:**
- Modify: `app/Http/Livewire/Kitchen/Transaction.php`
- Test: `tests/Feature/Pos/KitchenTransactionUsesStockServiceTest.php`

- [ ] **Step 1: Read the current Kitchen `submit()` to know exact context**

Open `app/Http/Livewire/Kitchen/Transaction.php` and read the method that creates the transaction. The existing handler decrements `Inventory::update(['number_of_serving' => $new_stock])` after creating the transaction (lines ~100–127 in the current file).

- [ ] **Step 2: Write the failing test**

Create `tests/Feature/Pos/KitchenTransactionUsesStockServiceTest.php`:

```php
<?php

namespace Tests\Feature\Pos;

use App\Models\Branch;
use App\Models\CheckinDetail;
use App\Models\Floor;
use App\Models\Guest;
use App\Models\Inventory;
use App\Models\Menu; // kitchen menu
use App\Models\Room;
use App\Models\StockMovement;
use App\Models\Type;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KitchenTransactionUsesStockServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_kitchen_food_submit_writes_stock_movement_and_decrements_inventory(): void
    {
        // Minimum fixture wiring — adjust factory/relations to match local schema.
        $branch = Branch::factory()->create();
        $user   = User::factory()->create(['branch_id' => $branch->id]);
        $floor  = Floor::factory()->create(['branch_id' => $branch->id]);
        $room   = Room::factory()->create(['branch_id' => $branch->id, 'floor_id' => $floor->id]);
        $guest  = Guest::factory()->create(['branch_id' => $branch->id]);

        CheckinDetail::factory()->create([
            'branch_id'    => $branch->id,
            'guest_id'     => $guest->id,
            'room_id'      => $room->id,
            'is_check_out' => false,
        ]);

        $menu = Menu::create([
            'branch_id' => $branch->id,
            'name'      => 'Test Burger',
            'price'     => 100,
        ]);

        Inventory::create([
            'branch_id' => $branch->id,
            'menu_id'   => $menu->id,
            'number_of_serving' => 5,
        ]);

        $this->actingAs($user);

        Livewire::test(\App\Http\Livewire\Kitchen\Transaction::class)
            ->set('food_id', $menu->id)
            ->set('food_quantity', 2)
            ->set('food_total_amount', 200)
            ->set('guest_id', $guest->id)
            ->set('assigned_frontdesk', [$user->id])
            ->call('submit');

        $this->assertEquals(3, Inventory::first()->number_of_serving, 'inventory must decrement');

        $movement = StockMovement::where('source_type', StockMovement::SOURCE_KITCHEN)->latest('id')->first();
        $this->assertNotNull($movement, 'kitchen submit must write a stock_movements row');
        $this->assertSame(StockMovement::TYPE_OUT, $movement->type);
        $this->assertEquals(2, $movement->quantity);
        $this->assertEquals(3, $movement->balance_after);
        $this->assertSame('transaction', $movement->ref_type);
    }
}
```

> **Note:** This test depends on existing factories (Branch, User, Room, Guest, Floor, CheckinDetail). If a factory does not exist in the codebase, create a minimal one inline within the test (using `Model::create([...])` with literal IDs is acceptable for this test). Do NOT add new factories as part of this task — out of scope.

- [ ] **Step 3: Run test — verify it fails**

```bash
php artisan test --filter=KitchenTransactionUsesStockServiceTest
```

Expected: FAIL — no stock_movements row written.

- [ ] **Step 4: Modify `Kitchen/Transaction.php` (shadow-write)**

Open `app/Http/Livewire/Kitchen/Transaction.php`. Find the block (around lines 105–127) that does `TransactionModel::create([...])` followed by `$inventory->update(['number_of_serving' => $new_stock])`.

**Keep the existing logic.** Add the `StockService::out()` call AFTER the existing `$inventory->update()` so both writes happen.

Assign the transaction to `$transaction` (one-line change):

```php
$transaction = TransactionModel::create([ /* keys unchanged */ ]);
```

Then immediately after the existing `$inventory->update([...])` block, add the shadow-write:

```php
//update stock (legacy path — kept during shadow-write phase)
$new_stock = $inventory->number_of_serving - $this->food_quantity;
$inventory->update(['number_of_serving' => $new_stock]);

// SHADOW-WRITE: also log to stock_movements via StockService.
// Wrapped in try/catch so any service bug never blocks the live flow.
try {
    app(\App\Services\Pos\StockService::class)->out(
        \App\Models\StockMovement::SOURCE_KITCHEN,
        (int) $this->food_id,
        (float) $this->food_quantity,
        [
            'branch_id' => auth()->user()->branch_id,
            'ref_type'  => 'transaction',
            'ref_id'    => $transaction->id,
            'user_id'   => auth()->id(),
        ]
    );
} catch (\Throwable $e) {
    \Log::warning('StockService shadow-write failed (kitchen)', [
        'menu_id' => $this->food_id,
        'qty'     => $this->food_quantity,
        'error'   => $e->getMessage(),
    ]);
    // do NOT rethrow — shadow phase must not break the live flow
}
```

**Important:** the legacy `$inventory->update(...)` runs FIRST. The `StockService::out()` call will then DECREMENT AGAIN from the already-decremented `number_of_serving`. To avoid double-decrement, the shadow service call must NOT update `number_of_serving` again — it should only LOG.

→ Modify the call to use a new shadow flag on `StockService`. Update `StockService::apply()` to accept a `'shadow' => true` context that **skips the inventory write** and snapshots the CURRENT `number_of_serving` (already mutated by the legacy code path) into `balance_after`:

```php
// in StockService::apply():
$shadow = ($context['shadow'] ?? false) === true;

// Lock + read inventory as before
// $available = current number_of_serving (post-legacy-update if shadow)

if ($shadow) {
    // Legacy path already mutated inventory. Snapshot its current state.
    $newBalance = $available;
    // SKIP: $inventory->save()
    // SKIP: stock validation (legacy path already passed/blocked)
} else {
    // existing branch — compute newBalance from $type and write inventory
}
```

The shadow-mode rules:
- Do not throw `InsufficientStockException` (legacy path already validated)
- Do not save inventory
- Insert the movement row with `balance_after = $available` (the post-legacy current state)

Pass `'shadow' => true` from the Kitchen call:

```php
app(\App\Services\Pos\StockService::class)->out(
    \App\Models\StockMovement::SOURCE_KITCHEN,
    (int) $this->food_id,
    (float) $this->food_quantity,
    [
        'branch_id' => auth()->user()->branch_id,
        'ref_type'  => 'transaction',
        'ref_id'    => $transaction->id,
        'user_id'   => auth()->id(),
        'shadow'    => true,
    ]
);
```

> **Update `StockServiceTest`** to add a shadow-mode test. Add to `tests/Feature/Pos/StockServiceTest.php`:
>
> ```php
> public function test_shadow_mode_writes_movement_without_touching_inventory(): void
> {
>     // Simulate the production scenario: legacy path already decremented from 5 to 3.
>     $inv = $this->makeFrontdeskInventory(3);
>     $svc = app(StockService::class);
>
>     $svc->out(StockMovement::SOURCE_FRONTDESK, 100, 2, [
>         'branch_id' => 1, 'shadow' => true,
>     ]);
>
>     $this->assertEquals(3, $inv->fresh()->number_of_serving, 'shadow must NOT change inventory');
>     $this->assertSame(1, StockMovement::count());
>     $this->assertEquals(3, StockMovement::first()->balance_after, 'balance_after must snapshot current state');
> }
>
> public function test_shadow_mode_does_not_throw_when_insufficient_stock(): void
> {
>     // Legacy path may have left inventory low; shadow must not block.
>     $this->makeFrontdeskInventory(0);
>     $svc = app(StockService::class);
>
>     $svc->out(StockMovement::SOURCE_FRONTDESK, 100, 5, [
>         'branch_id' => 1, 'shadow' => true,
>     ]);
>
>     $this->assertSame(1, StockMovement::count(), 'shadow must always log');
> }
> ```

- [ ] **Step 5: Run test — verify it passes**

```bash
php artisan test --filter=KitchenTransactionUsesStockServiceTest
```

Expected: PASS.

- [ ] **Step 6: Smoke-test the existing kitchen flow**

Manual check via tinker that the kitchen flow still creates a transaction and decrements stock correctly with a real fixture. If you cannot easily spin up the UI, skip and rely on the test.

- [ ] **Step 7: Commit (Commit 6A — shadow-write)**

```bash
git add app/Http/Livewire/Kitchen/Transaction.php app/Services/Pos/StockService.php tests/Feature/Pos/StockServiceTest.php tests/Feature/Pos/KitchenTransactionUsesStockServiceTest.php
git commit -m "kitchen: shadow-write to stock_movements alongside legacy inventory update"
```

- [ ] **Step 8: Deploy 6A and observe for at least one full shift**

After deploy, run this query a few times during the shift:

```sql
SELECT sm.id, sm.menu_id, sm.quantity, sm.balance_after,
       i.number_of_serving AS inv_serving,
       (sm.balance_after = i.number_of_serving) AS matches
FROM stock_movements sm
JOIN inventories i ON i.id = sm.inventory_id
WHERE sm.source_type = 'kitchen'
  AND sm.created_at >= NOW() - INTERVAL 4 HOUR
ORDER BY sm.id DESC
LIMIT 50;
```

`matches = 1` for every recent kitchen movement → safe to proceed to Task 6.5. Any `matches = 0` row → investigate before proceeding.

---

## Task 6.5: Kitchen — flip from shadow to authoritative (Commit 6B)

**Pre-condition:** Task 6 has been live for at least one full shift; spot-check query above shows `matches = 1` for all recent kitchen movements.

**Files:**
- Modify: `app/Http/Livewire/Kitchen/Transaction.php`

- [ ] **Step 1: Remove the legacy direct inventory update**

Open `app/Http/Livewire/Kitchen/Transaction.php`. Delete the legacy block:

```php
//update stock (legacy path — kept during shadow-write phase)
$new_stock = $inventory->number_of_serving - $this->food_quantity;
$inventory->update(['number_of_serving' => $new_stock]);
```

- [ ] **Step 2: Remove `'shadow' => true` from the StockService call**

In the same file, change:

```php
'shadow'    => true,
```

to delete that line entirely. The service now writes both the movement AND updates inventory.

- [ ] **Step 3: Wrap in try/catch for the now-authoritative path**

Now that StockService is authoritative, an exception MUST surface to the user. Replace the existing `try { ... } catch (\Throwable $e) { Log::warning(...) }` shadow handler with:

```php
try {
    app(\App\Services\Pos\StockService::class)->out(
        \App\Models\StockMovement::SOURCE_KITCHEN,
        (int) $this->food_id,
        (float) $this->food_quantity,
        [
            'branch_id' => auth()->user()->branch_id,
            'ref_type'  => 'transaction',
            'ref_id'    => $transaction->id,
            'user_id'   => auth()->id(),
        ]
    );
} catch (\App\Services\Pos\InsufficientStockException $e) {
    DB::rollBack();
    $this->dialog()->error('Out Of Stock', 'This item is out of stock');
    return;
}
```

- [ ] **Step 4: Run tests**

```bash
php artisan test --filter=KitchenTransactionUsesStockServiceTest
php artisan test --filter=StockServiceTest
```

Both must pass.

- [ ] **Step 5: Commit (Commit 6B)**

```bash
git add app/Http/Livewire/Kitchen/Transaction.php
git commit -m "kitchen: remove legacy direct inventory update; StockService is authoritative"
```

---

## Task 7: Pub `PubTransaction.php` — shadow-write to StockService

**Why:** Same shadow-write pattern as Task 6, applied to the Pub flow.

**Why:** Same as Task 6, for the Pub flow. Ends the era of two parallel direct-inventory-update paths.

**Files:**
- Modify: `app/Http/Livewire/Pub/PubTransaction.php`
- Test: `tests/Feature/Pos/PubTransactionUsesStockServiceTest.php`

- [ ] **Step 1: Read the current Pub `submit()`**

Open `app/Http/Livewire/Pub/PubTransaction.php`. Confirm the method shape mirrors Kitchen's: it queries `PubInventory` (or whatever pub uses) and updates `number_of_serving` directly. Note any differences in field names (e.g., `drink_id` vs `food_id`, `pub_menu_id` vs `menu_id`).

- [ ] **Step 2: Write the failing test**

Create `tests/Feature/Pos/PubTransactionUsesStockServiceTest.php`:

```php
<?php

namespace Tests\Feature\Pos;

use App\Models\Branch;
use App\Models\CheckinDetail;
use App\Models\Floor;
use App\Models\Guest;
use App\Models\PubInventory;
use App\Models\PubMenu;
use App\Models\Room;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PubTransactionUsesStockServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_pub_submit_writes_stock_movement_and_decrements_inventory(): void
    {
        $branch = Branch::factory()->create();
        $user   = User::factory()->create(['branch_id' => $branch->id]);
        $floor  = Floor::factory()->create(['branch_id' => $branch->id]);
        $room   = Room::factory()->create(['branch_id' => $branch->id, 'floor_id' => $floor->id]);
        $guest  = Guest::factory()->create(['branch_id' => $branch->id]);

        CheckinDetail::factory()->create([
            'branch_id'    => $branch->id,
            'guest_id'     => $guest->id,
            'room_id'      => $room->id,
            'is_check_out' => false,
        ]);

        $menu = PubMenu::create([
            'branch_id' => $branch->id,
            'name'      => 'Beer',
            'price'     => 75,
        ]);

        PubInventory::create([
            'branch_id'   => $branch->id,
            'pub_menu_id' => $menu->id,
            'number_of_serving' => 10,
        ]);

        $this->actingAs($user);

        // Adjust property names to match the actual PubTransaction component:
        Livewire::test(\App\Http\Livewire\Pub\PubTransaction::class)
            ->set('drink_id', $menu->id)
            ->set('drink_quantity', 3)
            ->set('drink_total_amount', 225)
            ->set('guest_id', $guest->id)
            ->set('assigned_frontdesk', [$user->id])
            ->call('submit');

        $this->assertEquals(7, PubInventory::first()->number_of_serving);

        $movement = StockMovement::where('source_type', StockMovement::SOURCE_PUB)->latest('id')->first();
        $this->assertNotNull($movement);
        $this->assertSame(StockMovement::TYPE_OUT, $movement->type);
        $this->assertEquals(3, $movement->quantity);
        $this->assertEquals(7, $movement->balance_after);
    }
}
```

> **Note:** In Step 1 you confirmed the actual property names. If they differ from `drink_id` / `drink_quantity` / `drink_total_amount`, adjust the `Livewire::test(...)->set(...)` calls to match.

- [ ] **Step 3: Run test — verify it fails**

```bash
php artisan test --filter=PubTransactionUsesStockServiceTest
```

Expected: FAIL.

- [ ] **Step 4: Modify `Pub/PubTransaction.php` (shadow-write)**

Apply the same shadow-write pattern as Task 6 Step 4.

Keep the existing `$inventory->update(...)` line. Assign the transaction creation to `$transaction` so its id is available. Add the shadow `StockService::out()` call AFTER the legacy update, with `'shadow' => true` and a try/catch that logs without rethrowing:

```php
$transaction = TransactionModel::create([ /* unchanged keys */ ]);

// existing legacy direct inventory update — keep during shadow phase
$new_stock = $inventory->number_of_serving - $this->drink_quantity;
$inventory->update(['number_of_serving' => $new_stock]);

// SHADOW-WRITE: log to stock_movements without modifying inventory.
try {
    app(\App\Services\Pos\StockService::class)->out(
        \App\Models\StockMovement::SOURCE_PUB,
        (int) $this->drink_id,
        (float) $this->drink_quantity,
        [
            'branch_id' => auth()->user()->branch_id,
            'ref_type'  => 'transaction',
            'ref_id'    => $transaction->id,
            'user_id'   => auth()->id(),
            'shadow'    => true,
        ]
    );
} catch (\Throwable $e) {
    \Log::warning('StockService shadow-write failed (pub)', [
        'menu_id' => $this->drink_id,
        'qty'     => $this->drink_quantity,
        'error'   => $e->getMessage(),
    ]);
}
```

Use the actual property names confirmed in Step 1.

- [ ] **Step 5: Run test — verify it passes**

```bash
php artisan test --filter=PubTransactionUsesStockServiceTest
```

Expected: PASS.

- [ ] **Step 6: Commit (Commit 7A — shadow-write)**

```bash
git add app/Http/Livewire/Pub/PubTransaction.php tests/Feature/Pos/PubTransactionUsesStockServiceTest.php
git commit -m "pub: shadow-write to stock_movements alongside legacy inventory update"
```

- [ ] **Step 7: Deploy 7A and observe for at least one full shift**

Spot-check query (analogous to Task 6 Step 8):

```sql
SELECT sm.id, sm.menu_id, sm.quantity, sm.balance_after,
       pi.number_of_serving AS inv_serving,
       (sm.balance_after = pi.number_of_serving) AS matches
FROM stock_movements sm
JOIN pub_inventories pi ON pi.id = sm.inventory_id
WHERE sm.source_type = 'pub'
  AND sm.created_at >= NOW() - INTERVAL 4 HOUR
ORDER BY sm.id DESC
LIMIT 50;
```

All `matches = 1` → proceed to Task 7.5.

---

## Task 7.5: Pub — flip from shadow to authoritative (Commit 7B)

**Pre-condition:** Task 7 has been live for at least one full shift; spot-check query shows `matches = 1`.

**Files:**
- Modify: `app/Http/Livewire/Pub/PubTransaction.php`

- [ ] **Step 1: Remove the legacy direct inventory update**

Delete:

```php
$new_stock = $inventory->number_of_serving - $this->drink_quantity;
$inventory->update(['number_of_serving' => $new_stock]);
```

- [ ] **Step 2: Remove `'shadow' => true`**

Delete that line from the StockService call.

- [ ] **Step 3: Replace the catch-all logging with `InsufficientStockException` handling**

```php
try {
    app(\App\Services\Pos\StockService::class)->out(
        \App\Models\StockMovement::SOURCE_PUB,
        (int) $this->drink_id,
        (float) $this->drink_quantity,
        [
            'branch_id' => auth()->user()->branch_id,
            'ref_type'  => 'transaction',
            'ref_id'    => $transaction->id,
            'user_id'   => auth()->id(),
        ]
    );
} catch (\App\Services\Pos\InsufficientStockException $e) {
    DB::rollBack();
    $this->dialog()->error('Out Of Stock', 'This item is out of stock');
    return;
}
```

- [ ] **Step 4: Run tests**

```bash
php artisan test --filter=PubTransactionUsesStockServiceTest
php artisan test --filter=StockServiceTest
```

Both pass.

- [ ] **Step 5: Commit (Commit 7B)**

```bash
git add app/Http/Livewire/Pub/PubTransaction.php
git commit -m "pub: remove legacy direct inventory update; StockService is authoritative"
```

---

## Task 8: Stock-In Livewire form (frontdesk)

**Why:** This is the missing UI from the spec — frontdesk staff can record deliveries / restocks, and movements show up in `stock_movements` with `type=IN`. Without this, the BigBoss "what came in this shift" column has no source data.

**Files:**
- Create: `app/Http/Livewire/Frontdesk/StockIn.php`
- Create: `resources/views/livewire/frontdesk/stock-in.blade.php`
- Modify: `routes/frontdesk.php`
- Test: `tests/Feature/Pos/StockInTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Pos/StockInTest.php`:

```php
<?php

namespace Tests\Feature\Pos;

use App\Models\Branch;
use App\Models\FrontdeskInventory;
use App\Models\FrontdeskMenu;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StockInTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_in_records_an_IN_movement_and_increments_balance(): void
    {
        $branch = Branch::factory()->create();
        $user   = User::factory()->create(['branch_id' => $branch->id]);
        $user->assignRole('frontdesk');

        $menu = FrontdeskMenu::create([
            'branch_id'              => $branch->id,
            'frontdesk_category_id'  => 1,
            'name'                   => 'Coke',
            'price'                  => '60',
        ]);

        FrontdeskInventory::create([
            'branch_id'         => $branch->id,
            'frontdesk_menu_id' => $menu->id,
            'number_of_serving' => 4,
        ]);

        $this->actingAs($user);

        Livewire::test(\App\Http\Livewire\Frontdesk\StockIn::class)
            ->set('source_type', StockMovement::SOURCE_FRONTDESK)
            ->set('menu_id', $menu->id)
            ->set('quantity', 12)
            ->set('reason', 'delivery — supplier ABC')
            ->call('submit');

        $this->assertEquals(16, FrontdeskInventory::first()->number_of_serving);

        $movement = StockMovement::where('type', StockMovement::TYPE_IN)->first();
        $this->assertNotNull($movement);
        $this->assertEquals(12, $movement->quantity);
        $this->assertEquals(16, $movement->balance_after);
        $this->assertSame('delivery — supplier ABC', $movement->reason);
        $this->assertSame('stock_in_form', $movement->ref_type);
    }

    public function test_quantity_must_be_positive(): void
    {
        $branch = Branch::factory()->create();
        $user   = User::factory()->create(['branch_id' => $branch->id]);
        $user->assignRole('frontdesk');
        $this->actingAs($user);

        Livewire::test(\App\Http\Livewire\Frontdesk\StockIn::class)
            ->set('source_type', StockMovement::SOURCE_FRONTDESK)
            ->set('menu_id', 1)
            ->set('quantity', 0)
            ->set('reason', 'x')
            ->call('submit')
            ->assertHasErrors(['quantity']);

        $this->assertSame(0, StockMovement::count());
    }
}
```

- [ ] **Step 2: Run test — verify it fails**

```bash
php artisan test --filter=StockInTest
```

Expected: FAIL — class `StockIn` not found.

- [ ] **Step 3: Implement `StockIn` component**

Create `app/Http/Livewire/Frontdesk/StockIn.php`:

```php
<?php

namespace App\Http\Livewire\Frontdesk;

use App\Models\FrontdeskMenu;
use App\Models\Menu as KitchenMenu;
use App\Models\PubMenu;
use App\Models\StockMovement;
use App\Services\Pos\StockService;
use Livewire\Component;
use WireUi\Traits\Actions;

class StockIn extends Component
{
    use Actions;

    public string $source_type = StockMovement::SOURCE_FRONTDESK;
    public ?int   $menu_id     = null;
    public float  $quantity    = 0;
    public string $reason      = '';

    protected $rules = [
        'source_type' => 'required|in:frontdesk,kitchen,pub',
        'menu_id'     => 'required|integer',
        'quantity'    => 'required|numeric|gt:0',
        'reason'      => 'nullable|string|max:255',
    ];

    public function submit(): void
    {
        $this->validate();

        app(StockService::class)->in(
            $this->source_type,
            (int) $this->menu_id,
            (float) $this->quantity,
            [
                'branch_id' => auth()->user()->branch_id,
                'reason'    => $this->reason ?: null,
                'ref_type'  => 'stock_in_form',
                'user_id'   => auth()->id(),
            ]
        );

        $this->reset(['menu_id', 'quantity', 'reason']);
        $this->notification()->success('Stock recorded', 'Stock-in saved.');
    }

    public function render()
    {
        $branchId = auth()->user()->branch_id;

        return view('livewire.frontdesk.stock-in', [
            'menus' => match ($this->source_type) {
                StockMovement::SOURCE_FRONTDESK => FrontdeskMenu::where('branch_id', $branchId)->orderBy('name')->get(),
                StockMovement::SOURCE_KITCHEN   => KitchenMenu::where('branch_id', $branchId)->orderBy('name')->get(),
                StockMovement::SOURCE_PUB       => PubMenu::where('branch_id', $branchId)->orderBy('name')->get(),
            },
        ]);
    }
}
```

- [ ] **Step 4: Implement the blade view**

Create `resources/views/livewire/frontdesk/stock-in.blade.php`:

```blade
<div class="p-6">
    <h2 class="text-xl font-semibold mb-4">Record Stock In</h2>

    <form wire:submit.prevent="submit" class="space-y-4 max-w-md">
        <div>
            <label class="block text-sm font-medium">Stock Source</label>
            <select wire:model="source_type" class="mt-1 block w-full rounded border-gray-300">
                <option value="frontdesk">Frontdesk POS</option>
                <option value="kitchen">Kitchen</option>
                <option value="pub">Pub</option>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium">Item</label>
            <select wire:model="menu_id" class="mt-1 block w-full rounded border-gray-300">
                <option value="">— pick one —</option>
                @foreach ($menus as $m)
                    <option value="{{ $m->id }}">{{ $m->name }}</option>
                @endforeach
            </select>
            @error('menu_id') <p class="text-red-600 text-sm">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium">Quantity received</label>
            <input type="number" step="0.01" min="0.01"
                   wire:model.defer="quantity"
                   class="mt-1 block w-full rounded border-gray-300" />
            @error('quantity') <p class="text-red-600 text-sm">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium">Reason / Note (supplier, PO #, etc.)</label>
            <input type="text" wire:model.defer="reason"
                   class="mt-1 block w-full rounded border-gray-300" />
        </div>

        <button type="submit"
                class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">
            Record Stock In
        </button>
    </form>
</div>
```

- [ ] **Step 5: Register the route**

Open `routes/frontdesk.php`. Find the existing `frontdesk.point-of-sale` route registration block (around line 160). Add a sibling route in the same middleware group:

```php
Route::get('/stock-in', \App\Http\Livewire\Frontdesk\StockIn::class)->name('frontdesk.stock-in');
```

Place it adjacent to the point-of-sale route so it inherits the same `role:frontdesk` middleware.

- [ ] **Step 6: Run test — verify it passes**

```bash
php artisan test --filter=StockInTest
```

Expected: PASS (2 tests).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Livewire/Frontdesk/StockIn.php resources/views/livewire/frontdesk/stock-in.blade.php routes/frontdesk.php tests/Feature/Pos/StockInTest.php
git commit -m "add Stock-In livewire form for frontdesk receiving"
```

---

## Task 9: Add a navigation link to Stock-In

**Why:** Without a menu link, the form is dead in the water — staff cannot reach it.

**Files:**
- Modify: the frontdesk layout / sidebar that already links to `frontdesk.point-of-sale`. Inspect first to find the actual file.

- [ ] **Step 1: Find the existing POS link**

```bash
grep -rn "frontdesk.point-of-sale" resources/views/
```

Open the file(s) returned and identify the existing link block.

- [ ] **Step 2: Add the Stock-In link adjacent to POS**

Mirror the existing link's markup but point at `route('frontdesk.stock-in')` with label "Stock In".

Example shape (adjust to match the actual layout):

```blade
<a href="{{ route('frontdesk.stock-in') }}"
   class="{{ request()->routeIs('frontdesk.stock-in') ? 'bg-gray-200' : '' }} block px-4 py-2 hover:bg-gray-100">
    <span>Stock In</span>
</a>
```

- [ ] **Step 3: Manual verification**

Start dev server (`php artisan serve` + `npm run dev`), log in as a frontdesk user, click Stock-In, fill the form, submit, confirm:
- Movement appears in `stock_movements` (use tinker: `App\Models\StockMovement::latest()->first()`)
- Inventory `number_of_serving` increases.

If the dev server cannot easily be started, note this manual test as deferred and proceed.

- [ ] **Step 4: Commit**

```bash
git add resources/views/
git commit -m "add Stock-In nav link in frontdesk sidebar"
```

---

## Plan 1 acceptance criteria

After all tasks ship (1, 2, 3, 4, 5, 6, 6.5, 7, 7.5, 8, 9):

- [ ] `php artisan test --filter=Pos` is green.
- [ ] `stock_movements` exists with one `OPENING` row per non-zero inventory row across all three inventory tables.
- [ ] Kitchen and Pub flows still work UX-wise; each successful submit writes one `stock_movements` row (`type=OUT`, `ref_type=transaction`).
- [ ] Spot-check SQL shows `balance_after = number_of_serving` for every recent kitchen/pub movement.
- [ ] Frontdesk users have a "Stock In" page that creates `IN` movements.
- [ ] No POS UI changes; Plan 2 picks up there.

## Known audit gaps (NOT in Plan 1)

Plan 1 makes **stock** and **sales** transparent, but the following remain unaudited and should be addressed in a separate plan if the owner wants full traceability:

- **Menu price edits** — `frontdesk_menus.price`, kitchen menu price, pub menu price. Today: silent overwrite. Recommended: a `menu_price_history` table that snapshots old/new price + user + reason on every update. Out of scope for Plan 1.
- **Direct DB / tinker edits** — anything that bypasses StockService or Eloquent observers won't appear in `stock_movements`. Operational discipline only.
- **Transaction voids in non-POS flows** — Plan 1 does not add void support to Kitchen/Pub flows (only POS in Plan 2).

These are logged here so they don't get forgotten.

## Plan 1 self-review (notes for the implementer)

If you're working through this plan and hit any of the following, stop and resolve before proceeding:

1. **Factory mismatch** — the kitchen/pub feature tests assume factories exist for `Branch`, `User`, `Floor`, `Room`, `Guest`, `CheckinDetail`. If a factory is missing, prefer `Model::create([...])` with literal IDs over creating new factories in this plan. Adding factories is out of scope.
2. **`Inventory` model name collision** — the kitchen inventory model is `App\Models\Inventory` (no namespacing prefix). Make sure imports match.
3. **`PubMenu` foreign key column** — the test assumes `pub_menu_id` on `pub_inventories`. Confirm by reading the create migration; if it's named differently, update `StockSourceResolver::menuForeignKey()` AND the test fixtures.
4. **Field name drift in `PubTransaction`** — Step 1 of Task 7 calls for confirming `drink_id` etc. Don't skip it.
5. **`->change()` requires `doctrine/dbal`** — Task 1 Step 1 covers this.

---

*Plans 2 and 3 will be drafted after Plan 1 ships. Each builds on the foundation laid here.*
