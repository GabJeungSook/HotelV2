# POS Module Rebuild — Design Spec

**Date:** 2026-04-25
**Status:** Draft (awaiting user review)
**Related docs:** [pos-module-current-state.md](pos-module-current-state.md)

---

## 1. Why we are doing this

The POS module was deployed 2026-04-13 but **0 sales** have been recorded in production as of 2026-04-24, despite menu, categories, and inventory being configured (24 menu items, 18 inventory rows, 5 categories). Frontdesk staff is not using it.

The most likely reason: the POS today is **cash-only with no guest/room link**, so it cannot handle the most common hotel F&B workflow — charging food/drink to a guest's room. Staff fall back to Kitchen/Pub screens or skip the system.

The client's three-line ask:

1. Implement POS that frontdesk can place **with or without room number**
2. **Record stocks**
3. Generate **Inventory and Sales** to **Bigboss template**

These are not three independent features. They are one cohesive ask: **make the POS actually usable, and make its data visible to the owner.**

## 2. Goals

- Frontdesk POS can ring a sale **with** a room number (charges to guest folio) **or without** (cash walk-in).
- Stock movements (IN, OUT, ADJUST, VOID) are recorded with history so the owner can audit "what came in, what went out, what's left."
- POS sales and inventory roll up into the existing **BigBossReport** per-shift HTML export.
- Silent inventory oversell bug is eliminated.
- Kitchen and Pub flows continue to work unchanged from the user's perspective, but write to the same backend tables (so reports are unified).

## 3. Non-goals

- Thermal printer / SMS / email receipts (keep client-side `window.print()` for now)
- Promo codes, comps, multi-tier pricing
- Tax-on-top calculations (prices stay tax-inclusive)
- Kitchen/Pub UI rewrite (keep their screens)
- Variance counts at shift close (can layer on later)
- Migrating `frontdesk_menus.price` from VARCHAR to DECIMAL (separate cleanup)
- Touching `pos_transactions` table data (frozen for history, not deleted)

## 4. Key decisions

| # | Decision | Reason |
|---|---|---|
| 1 | POS sales write to **`transactions` table (type_id=9)** with nullable `guest_id` / `room_id`, not `pos_transactions` | One source of truth. BigBoss report already reads `transactions`. |
| 2 | New **`stock_movements`** table logs every IN / OUT / ADJUST / VOID | Owner needs IN/OUT/balance per shift in BigBoss; current schema has no history. |
| 3 | Add **Stock-In form** for receiving deliveries | Without it, BigBoss "what came in this shift" column is blank. |
| 4 | Block sale when stock insufficient | Today silent oversell at `PointOfSale.php:135` — owner can't trust numbers. |
| 5 | Kitchen/Pub keep their screens; share `transactions` + `stock_movements` backend | Client only asked about Frontdesk POS; don't disturb working flows. Backend share is small. |
| 6 | Payment methods: **Cash / GCash / Card** column on transaction | Standard PH hotel mix; one column is cheap. |
| 7 | Void only (same shift, same user). No partial refund. | Real frontdesk need; partial refund adds complexity for rare case. |
| 8 | Single discount per sale (amount or %), with reason text | Frontdesk gives ad-hoc discounts; promo codes are overkill. |
| 9 | No tax on top — keep prices tax-inclusive | Owner did not ask; changing display prices is risky. |
| 10 | Receipt: server-rendered HTML, still printed via `window.print()` | Keeps current UX, opens path to PDF later. |
| 11 | Promote behind feature flag `pos_v2_enabled` per branch | POS rewrite touches money flow — flag lets us pilot one branch first. |
| 12 | **Snapshot line item on every transaction** (`menu_id`, `item_name`, `unit_price`, `quantity`) — never updated after creation | Menu prices/names will change. Historical transactions must reflect the value at sale time, not the current menu value. |
| 13 | **`menu_price_changes` audit table** logs every price edit on any of the three menu tables | Owner needs to see "this Coke sold for ₱60 last month, ₱65 this month — when did it change and who changed it?" |
| 14 | **`pos_orders` header table** + nullable `order_id` on `transactions` for proper multi-item cart grouping | Replaces brittle "items created in same second = same order" timestamp grouping. Kitchen/Pub flows leave `order_id = NULL` and remain unchanged. |

## 5. Architecture

```
                 ┌─────────────────────────┐
                 │  Unified Frontdesk POS  │  ← rewritten PointOfSale.php
                 │  (cash + room-charge)   │
                 └────────────┬────────────┘
                              │
        ┌─────────────────────┼─────────────────────┐
        ▼                     ▼                     ▼
  transactions         stock_movements       Receipt (HTML)
  (type_id=9,            (IN / OUT /          server-rendered,
   guest_id?,             ADJUST /             window.print()
   room_id?,              VOID)
   payment_method,
   discount_amount,
   discount_reason,
   voided_at,
   voided_by_user_id,
   user_id,
   shift_log_id)
        │                     ▲
        │                     │ also written by
        │                     │
        ▼                     │
  shift_logs.total_pos    Kitchen/Pub
  (rolled up)             Transaction.php
                          (small refactor —
                           write to same tables)
        │
        ▼
  BigBossReport
  (POS Sales + Inventory sections added)
```

## 6. Components

### 6.1 Rewritten

| File | Change |
|---|---|
| `app/Http/Livewire/Frontdesk/PointOfSale.php` | Checkout writes to `transactions` (type_id=9) instead of `pos_transactions`. Adds: "Attach to room" toggle, guest search, payment method picker, discount field, void button. |
| `resources/views/livewire/frontdesk/point-of-sale.blade.php` | Toggle UI, payment method, discount, void confirm modal, server-rendered receipt |
| `app/Http/Livewire/BackOffice/Reports/BigBossReport.php` | Add `posSalesRows()` + `inventoryRows()` to `generateReport()`. Read from `transactions` + `stock_movements`. |
| `resources/views/livewire/back-office/reports/big-boss-report-export.blade.php` | New POS Sales and Inventory sections |

### 6.2 Small refactor (no UX change)

| File | Change |
|---|---|
| `app/Http/Livewire/Kitchen/Transaction.php` | Stock change goes through `StockService::out()` (writes movement + updates balance) instead of direct `Inventory::update()` |
| `app/Http/Livewire/Pub/PubTransaction.php` | Same |

### 6.3 New

| File | Purpose |
|---|---|
| `app/Http/Livewire/Frontdesk/StockIn.php` (+ blade) | Receiving form: pick item, qty, optional supplier/note → writes IN movement |
| `app/Models/StockMovement.php` | Eloquent model |
| `app/Services/Pos/CheckoutService.php` | Pulls cart logic, transactions write, stock movement write, shift roll-up into one place. Easier to test. |
| `app/Services/Pos/StockService.php` | Single API for `in()`, `out()`, `adjust()`, `void()`. Both POS and Kitchen/Pub call this. |
| `database/migrations/...create_stock_movements_table.php` | New table |
| `database/migrations/...add_pos_columns_to_transactions_table.php` | Adds payment_method, discount_amount, discount_reason, voided_at, voided_by_user_id |
| `database/migrations/...backfill_stock_movements_opening_balances.php` | Replays current inventory state into stock_movements as opening row per item. Idempotent. |

### 6.4 Deprecated (kept readable, marked)

- `pos_transactions` table — frozen. Read-only for history. New code never writes.
- The three inventory tables (`frontdesk_inventories`, `inventories`, `pub_inventories`) **stay** for now — unifying them is a separate, riskier task. `stock_movements` references them polymorphically.

## 7. Data flow — one checkout

```
User clicks Checkout in PointOfSale
        │
        ▼
CheckoutService::checkout(cart, paymentMethod, discount, guestId?, roomId?)
        │
        ├─► validateStock(cart)  ─── insufficient ──► throw, no DB writes
        │
        ▼  (DB transaction)
        │
        ├─► For each line item:
        │     ├── Transaction::create(type_id=9, guest_id?, room_id?,
        │     │                       user_id, shift_log_id, branch_id,
        │     │                       amount, payment_method,
        │     │                       discount_amount, discount_reason,
        │     │                       menu_id, item_name, qty)
        │     └── StockService::out(menu_id, qty, ref=transaction_id)
        │            └── StockMovement::create(type='OUT', qty,
        │                                      balance_after, ref_type, ref_id)
        │            └── inventories.number_of_serving = balance_after
        │
        ├─► If guest_id present: append to guest's open folio (existing pattern)
        │   Else: cash sale → shift_log totals
        │
        └─► Return transaction group → render receipt blade → window.print()
```

## 8. Error handling

| Scenario | Behavior |
|---|---|
| Insufficient stock | Block sale, show item list with current vs requested qty. **Never silent oversell.** |
| No active shift / no cash drawer | Existing redirect to dashboard — keep |
| Network drop mid-checkout | DB transaction rolls back — cart still in memory, user retries |
| Voiding a sale | Soft-delete via `voided_at` + reverse stock movement (IN with type=VOID, ref=original tx). Original row stays for audit. |
| Discount > subtotal | Validation error before DB write |
| Room # given but guest already checked out | Block, show "guest is no longer checked in" |
| Two clicks on Checkout (idempotency) | Disable button on first click + shift_log_id + cart hash check within 1-second window |

## 9. Database changes

### 9.1 New table — `stock_movements`

Polymorphic across the three inventory worlds (frontdesk POS, kitchen, pub). Every IN/OUT/ADJUST/VOID/OPENING in any of the three is one row here.

```
id                bigint pk
branch_id         bigint nullable, fk → branches

-- polymorphic source: which menu + inventory the movement belongs to
source_type       varchar(20)          -- 'frontdesk' | 'kitchen' | 'pub'
menu_id           bigint               -- id in frontdesk_menus | (kitchen) menus | pub_menus
inventory_id      bigint               -- id in frontdesk_inventories | inventories | pub_inventories

type              enum('IN','OUT','ADJUST','VOID','OPENING')
quantity          decimal(10,2)        -- always positive; type indicates direction
balance_after     decimal(10,2)        -- snapshot for audit / reporting

reason            varchar(255) null    -- e.g., supplier name, void reason
ref_type          varchar(50) null     -- 'transaction', 'stock_in_form', 'manual'
ref_id            bigint null          -- id of source row

user_id           bigint nullable, fk → users
shift_log_id      bigint nullable, fk → shift_logs
created_at, updated_at

-- indexes on (source_type, menu_id), (shift_log_id), (branch_id, created_at)
```

`StockService` is the single API that resolves `source_type` to the correct inventory table and updates `number_of_serving` atomically with the movement insert.

### 9.2 Columns added to `transactions`

Two groups: payment/void/discount metadata, and the **line-item snapshot** that freezes what was sold at the price/name in effect at sale time.

```
-- payment + discount + void metadata
payment_method      varchar(20) null      -- 'cash' | 'gcash' | 'card'
discount_amount     integer default 0
discount_reason     varchar(255) null
voided_at           timestamp null
voided_by_user_id   bigint null, fk → users

-- LINE-ITEM SNAPSHOT (frozen at sale; never updated)
source_type         varchar(20) null      -- 'frontdesk' | 'kitchen' | 'pub'
menu_id             bigint null           -- reference only; do not join for amount
item_name           varchar(255) null     -- snapshot of menu name at sale time
unit_price          integer null          -- snapshot of menu price at sale time
quantity            decimal(10,2) null
```

**Why both `unit_price` and `payable_amount`:** `payable_amount` is the line total (already exists). `unit_price` is the per-unit price snapshot. Storing both lets us prove `payable_amount = unit_price × quantity − discount_amount` and lets the BigBoss report show per-unit pricing without joining the (mutable) menu table.

### 9.4 New table — `menu_price_changes`

Every UPDATE to a menu's `price` (or `name`) writes one row.

```
id                bigint pk
source_type       varchar(20)            -- 'frontdesk' | 'kitchen' | 'pub'
menu_id           bigint
field             varchar(50)            -- 'price' | 'name'
old_value         varchar(255) null
new_value         varchar(255) null
changed_by_user_id bigint nullable, fk → users
reason            varchar(255) null
created_at, updated_at

-- index (source_type, menu_id, created_at)
```

Wired via Eloquent `updating` observer on each of: `FrontdeskMenu`, `Menu` (kitchen), `PubMenu`. If the model is dirty on `price` or `name`, write a row before save.

### 9.5 New table — `pos_orders` (POS cart header)

One row per POS checkout. Used only by the new POS (Plan 2). Kitchen/Pub leave `transactions.order_id = NULL`.

```
id                 bigint pk
branch_id          bigint
user_id            bigint                 -- frontdesk who rang the sale
shift_log_id       bigint nullable
guest_id           bigint nullable        -- room-charge sale
room_id            bigint nullable        -- room-charge sale
payment_method     varchar(20) null       -- 'cash' | 'gcash' | 'card' | NULL for room-charge
subtotal           integer                -- sum of line item totals before discount
discount_amount    integer default 0
discount_reason    varchar(255) null
total              integer                -- subtotal - discount
paid_amount        integer default 0
change_amount      integer default 0
voided_at          timestamp null
voided_by_user_id  bigint null, fk → users
created_at, updated_at

-- index (branch_id, created_at), (shift_log_id), (guest_id)
```

### 9.6 Add `order_id` column to `transactions`

```
order_id  bigint nullable, fk → pos_orders.id
```

Nullable because Kitchen/Pub flows continue creating transactions without an order header.

### 9.3 Backfill migration (idempotent)

For each row in `frontdesk_inventories`, `inventories`, and `pub_inventories` with `number_of_serving > 0`, insert one `OPENING` movement with the correct `source_type` and matching `menu_id` / `inventory_id`. Skip if an `OPENING` row for that `(source_type, inventory_id)` pair already exists. Safe to re-run.

## 10. Testing

Feature tests:
- `PointOfSaleCheckoutTest` — walk-in cash sale writes correct transaction + stock movement
- `PointOfSaleRoomChargeTest` — room sale attaches guest_id/room_id and posts to folio
- `PointOfSaleStockBlockTest` — insufficient stock blocks sale (no transaction, no stock change)
- `PointOfSaleVoidTest` — void reverses stock and marks transaction voided
- `StockInTest` — stock-in form creates IN movement and bumps balance
- `BigBossReportPosSectionTest` — POS sales and inventory rows render in HTML export
- `KitchenTransactionUsesStockMovementTest` — kitchen flow writes to stock_movements (no UX change)

## 11. Migration / rollout plan

| Step | Risk | Mitigation |
|---|---|---|
| Add columns to `transactions` | Low (additive) | Nullable defaults |
| Create `stock_movements` | None | New table |
| Backfill opening balances | Medium (one-time) | Idempotent; safe to re-run |
| Frontdesk POS rewrite | **High — money flow** | Feature flag `pos_v2_enabled` per branch; old route alive until flipped |
| Kitchen/Pub stock refactor | Medium — used today | Same flag; validate stock_movements count after one shift before promoting |
| BigBoss report sections | Low | Conditional render until POS v2 flag is on |

## 12. Open follow-ups (not in this spec)

- Migrate `frontdesk_menus.price` from VARCHAR to DECIMAL (separate task)
- Decide whether to retire `pos_transactions` after one full month of v2 in production
- Unify the three inventory tables (`frontdesk_inventories` / `inventories` / `pub_inventories`) into one — separate, riskier task
- Variance count at shift close (BigBoss may ask after seeing the new report)
- Receipt as PDF / thermal printer (revisit after staff actually use the v2 POS)

---

*Review gate: user must approve this spec before writing-plans skill is invoked.*
