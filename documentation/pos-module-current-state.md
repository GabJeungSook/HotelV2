# POS Module — Current State Reference

**Captured:** 2026-04-24
**Purpose:** Complete picture of the POS module as it exists today, before any client-requested updates. Use this to avoid rediscovering the system when changes are proposed.

---

## 1. What POS is (concept)

The POS (Point of Sale) is a **frontdesk-only cash register** for selling food, drinks, or small items directly to customers at the counter. It's tied to the frontdesk shift and the cash drawer. It records sales in its own `pos_transactions` table — completely separate from the main hotel billing (`transactions` table used for room charges, deposits, etc.).

Think of it as: the hotel also runs a mini convenience store, and POS is the register for it.

---

## 2. Usage in production

- **Created:** 2026-04-13 (migrations: `create_pos_transactions_table`, `add_column_total_pos_to_shift_logs_table`)
- **Live status:** Routes exist, menu items configured, but **0 sales recorded** in production database as of 2026-04-24
- **Interpretation:** Feature was built and deployed but either not rolled out to frontdesk staff, or staff isn't using it, or training didn't happen

---

## 3. Components map

### Frontdesk POS (the actual POS)

| File | Purpose |
|------|---------|
| `app/Http/Livewire/Frontdesk/PointOfSale.php` | Main register: cart logic, checkout, inventory decrement |
| `app/Http/Livewire/Frontdesk/Food/Menu.php` | Admin: CRUD for POS menu items |
| `app/Http/Livewire/Frontdesk/Food/Category.php` | Admin: CRUD for item categories |
| `app/Http/Livewire/Frontdesk/Food/Inventory.php` | Admin: manage stock levels |
| `app/Http/Livewire/Frontdesk/FoodInventory.php` | Frontdesk view of inventory (stub — renders view only) |
| `app/Http/Livewire/Frontdesk/CashOnHand.php` | Shift end: rolls up POS total into `shift_log.total_pos` |

### Kitchen / Pub (NOT POS — they're billing, not register)

| File | Purpose |
|------|---------|
| `app/Http/Livewire/Kitchen/Transaction.php` | Kitchen staff adds food to guest's room bill (posts to `transactions` table, type_id=9) |
| `app/Http/Livewire/Pub/PubTransaction.php` | Pub staff adds drinks to guest's room bill (same pattern) |
| `app/Http/Livewire/Kitchen/Menu.php` + `Pub/PubMenu.php` | Separate menu management for each |

**Critical distinction:** Kitchen/Pub post charges to a GUEST'S ROOM BILL. Frontdesk POS is CASH ONLY, no guest link.

---

## 4. Data flow

```
FRONTDESK POS (cash-only)
=========================

  [FrontdeskCategory] ─→ [FrontdeskMenu] ─→ [FrontdeskInventory]
                              │
                              ↓
                       PointOfSale.php
                              │
                   User adds to cart (in-memory array)
                              │
                     Clicks "Checkout"
                              │
                ┌─────────────┴──────────────┐
                ↓                            ↓
    Creates PosTransaction per item   Decrements FrontdeskInventory
    (shift_log_id, user_id,            (silent skip if out of stock)
     branch_id, menu_id, price,
     qty, total)
                              │
                              ↓
            At shift end (CashOnHand.php line 124):
            shift_log.total_pos = SUM(PosTransaction.total)


KITCHEN / PUB (charge to room — different system)
=================================================

  [Menu] ─→ Transaction table (transaction_type_id = 9)
           with guest_id + room_id attached
           (guest pays at checkout — included in room total)
```

---

## 5. What POS connects to

- ✅ `ShiftLog` — each sale belongs to a shift (`shift_log_id`)
- ✅ `User` — records which frontdesk staff rang the sale
- ✅ `Branch` — multi-tenant filtering
- ✅ `FrontdeskMenu` — the item sold
- ✅ `FrontdeskInventory` — decrements `number_of_serving` on sale
- ✅ `CashDrawer` — user must have active `cash_drawer_id` to use POS
- ✅ `shift_logs.total_pos` column — rolled up at shift end

## 6. What POS does NOT connect to

- ❌ **`transactions` table** — main hotel billing ledger. POS is its own silo.
- ❌ **Guest / Room** — no `guest_id` or `room_id` on `pos_transactions`. Can't bill to a room.
- ❌ **BackOffice reports** — 0 references to `total_pos` or `PosTransaction` in `app/Http/Livewire/BackOffice/`. POS revenue is **invisible** to `SalesReportV2`, `FrontdeskReportV2`, and all admin-side reports.
- ❌ **`cash_on_drawers`** table — POS totals don't flow through the manual cash entries table
- ❌ **Remittance reconciliation** — POS totals are not compared against cash remittances
- ❌ **Server-side receipt generation** — only client-side `window.print()` in blade view

---

## 7. Database schema

### `pos_transactions`
```
id, shift_log_id (nullable), user_id (nullable), branch_id (nullable),
frontdesk_menu_id, item_name, price (decimal 15,2), quantity (int),
total (decimal 15,2), created_at, updated_at
```

### `frontdesk_menus`
```
id, branch_id, frontdesk_category_id, name,
price (varchar — NOT decimal ⚠️), image, item_code,
created_at, updated_at
```
⚠️ `price` is stored as VARCHAR/string, not DECIMAL. Potential conversion/precision issues.

### `frontdesk_inventories`
```
id, branch_id, frontdesk_menu_id, number_of_serving (double),
created_at, updated_at
```

### `shift_logs` (relevant column)
```
total_pos (decimal 15,2, added 2026-04-13)
```

---

## 8. Access control

- **Route:** `frontdesk.point-of-sale` (defined in `routes/frontdesk.php:160-166`)
- **Role gate:** `role:frontdesk` middleware
- **Active shift required:** user must have an open `ShiftLog` (time_out null)
- **Cash drawer required:** user must have `cash_drawer_id` assigned
- **Without these:** POS redirects to frontdesk dashboard

---

## 9. Known gaps and issues

1. **Silent inventory oversell** (`PointOfSale.php:135`)
   - If stock is less than cart quantity, decrement is just skipped — sale still goes through.
   - Can lead to negative or inaccurate inventory.

2. **No payment method tracking**
   - All sales treated as cash. No cash/card/GCash/etc. distinction.

3. **No guest / room billing**
   - If a guest wants to charge food/drink to their room, POS can't do it. (Kitchen/Pub can.)

4. **Invisible to BackOffice reports**
   - POS revenue doesn't appear in `SalesReportV2`, `FrontdeskReportV2`, or any admin dashboard.
   - Only visible inside the POS screen's "Purchase History" modal.

5. **No refund / void flow**
   - If a sale is a mistake, no UI to reverse it.

6. **No discount support**
   - No promo, no bulk discount, no comped items.

7. **No tax calculation**
   - Price is flat. No tax added on top.

8. **Receipt printing is client-side `window.print()`**
   - No thermal printer integration, no PDF, no email, no SMS.

9. **Cart is in-memory only**
   - Lost on disconnect or page refresh. No saved drafts.

10. **Price stored as string, not decimal**
    - `frontdesk_menus.price` is VARCHAR. Potential precision / comparison bugs.

11. **Inventory auto-creation commented out** (`Food/Menu.php:87-91`)
    - New menu items don't get a default inventory row. Admin must use the separate Inventory screen.

12. **Order grouping by timestamp** (`PointOfSale.php:183`)
    - Items created in the same second are grouped as one "order" in the history view. Two separate checkouts within 1 second merge incorrectly (rare edge case).

---

## 10. Questions to clarify when client requests updates

Before implementing any POS update, ask the client:

1. **Should POS revenue appear in BackOffice reports?** (Currently invisible there.)
2. **Should POS be able to charge to a guest's room bill?** (Kitchen/Pub can; POS can't.)
3. **Do you need payment method tracking?** (Cash / card / GCash / etc.)
4. **What should happen when stock runs out?** (Block sale? Warn? Allow oversell?)
5. **Do you need discounts / promos / comps?**
6. **Tax calculation — needed?**
7. **Receipt requirements — thermal printer, PDF, email, SMS?**
8. **Refund / void flow — needed?**
9. **Should POS work without an active shift?** (Currently requires one.)
10. **Audit trail on menu price changes — needed?**
11. **Shared menu across branches, or per-branch?**
12. **Inventory variance at shift close — should the system flag if physical count ≠ expected count?**

---

## 11. Files referenced

**Core:**
- `app/Http/Livewire/Frontdesk/PointOfSale.php`
- `resources/views/livewire/frontdesk/point-of-sale.blade.php`
- `app/Models/PosTransaction.php`

**Supporting:**
- `app/Http/Livewire/Frontdesk/CashOnHand.php`
- `app/Http/Livewire/Frontdesk/Food/{Menu,Category,Inventory}.php`

**Kitchen/Pub (for contrast):**
- `app/Http/Livewire/Kitchen/Transaction.php`
- `app/Http/Livewire/Pub/PubTransaction.php`

**Routes:**
- `routes/frontdesk.php:160-166`

**Migrations:**
- `2026_04_13_222910_create_pos_transactions_table.php`
- `2026_04_13_222917_add_column_total_pos_to_shift_logs_table.php`
- `2024_05_06_084015_create_frontdesk_menus_table.php`
- `2024_05_06_084026_create_frontdesk_inventories_table.php`

---

*This doc is a snapshot of the POS module state as of 2026-04-24. Update it when the module structure changes significantly.*
