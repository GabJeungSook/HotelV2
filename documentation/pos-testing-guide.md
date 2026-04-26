# POS — Full Testing Guide

This guide walks the rebuilt POS system end-to-end so you can test every
flow and verify what's happening in the database at each step. The goal is:
**no surprises in production.**

---

## 1. Who does what (roles)

| Role | What they can do | Where |
|---|---|---|
| **Admin** | Set up the catalog: categories, menu items + prices, initial stock levels. Audit price changes. | `/admin/food/category`, `/admin/food/menu`, `/admin/food/inventory/{menu_id}` |
| **Frontdesk** | Receive deliveries (Stock-In). Ring sales at the register. Void same-shift mistakes. Print receipts. | `/frontdesk/stock-in`, `/frontdesk/frontdesk-point-of-sale` |
| **BackOffice / Owner ("Big Boss")** | Read POS Sales + Inventory movement per shift. | `/back-office/report-hub` → "Big Boss POS Report" |
| **Kitchen / Pub** | Charge food/drinks to a guest's room (separate flow, unchanged). | Existing kitchen / pub pages |

POS is owned by **Frontdesk**. Admin manages the catalog. Owner reads reports.

---

## 2. First-time setup (admin)

### 2.1 Create categories

Navigate: **Admin → Food → Category** (`/admin/food/category`)

Add categories like "Drinks", "Snacks", "Toiletries", etc.

**Backend effect:** `INSERT INTO frontdesk_categories (branch_id, name)`.

**Verify:**
```sql
SELECT * FROM frontdesk_categories WHERE branch_id = <your_branch_id>;
```

### 2.2 Create menu items + price

Navigate: **Admin → Food → Menu** (`/admin/food/menu`)

Add items, pick category, set price.

**Backend effect:** `INSERT INTO frontdesk_menus (branch_id, frontdesk_category_id, name, price, image, item_code)`.

> ⚠️ `frontdesk_menus.price` is stored as **VARCHAR**, not DECIMAL. Don't put symbols or letters in the price field — use raw numbers like `60` or `60.00`.

**Verify:**
```sql
SELECT id, name, price, frontdesk_category_id FROM frontdesk_menus WHERE branch_id = <your_branch_id>;
```

### 2.3 Set initial inventory

Navigate: **Admin → Food → Inventory** (from the Menu page, click the inventory icon for an item — opens `/admin/food/inventory/{menu_id}`)

Set the initial stock level for each menu item.

**Backend effect:** `INSERT/UPDATE frontdesk_inventories (branch_id, frontdesk_menu_id, number_of_serving)`.

> 💡 If you skip this, the item will show as "Out of stock" on the kiosk. Use Stock-In (frontdesk side) to add stock instead — that creates an audit row in `stock_movements`.

**Verify:**
```sql
SELECT m.name, i.number_of_serving
FROM frontdesk_inventories i
JOIN frontdesk_menus m ON m.id = i.frontdesk_menu_id
WHERE i.branch_id = <your_branch_id>;
```

### 2.4 Change a price later (and audit)

Edit the menu item from `/admin/food/menu` and change the price.

**Backend effect (TWO writes):**
1. `UPDATE frontdesk_menus SET price = <new>` — current price changes
2. `INSERT INTO menu_price_changes (source_type='frontdesk', menu_id, field='price', old_value, new_value, changed_by_user_id)` — audit row

**Verify the audit:**
```sql
SELECT mpc.*, fm.name
FROM menu_price_changes mpc
JOIN frontdesk_menus fm ON fm.id = mpc.menu_id
WHERE mpc.source_type = 'frontdesk'
ORDER BY mpc.created_at DESC
LIMIT 10;
```

> 🔒 **Important:** historical receipts and reports show the price **at the
> time of the sale**, not the current price. The transaction's `unit_price`
> snapshot is frozen. So changing a menu price never affects past sales.

---

## 3. Receiving deliveries — Stock-In (frontdesk)

Navigate: **Frontdesk → POS → Stock In** (button on the POS page header) or `/frontdesk/stock-in`.

Fill in:
- **Item** (searchable picker)
- **Quantity received**
- **Reason / Note** (e.g. supplier, PO number — optional)

Click "Record Stock In".

**Backend effect (atomic, single DB transaction via `StockService::in()`):**
1. `INSERT INTO stock_movements` row with `type='IN'`, `quantity=<qty>`, `balance_after=<old + qty>`, `ref_type='stock_in_form'`, `user_id`, `branch_id`
2. `UPDATE frontdesk_inventories SET number_of_serving = number_of_serving + <qty>` (or `INSERT` if no inventory row existed)

**Verify:**
```sql
SELECT type, quantity, balance_after, reason, created_at
FROM stock_movements
WHERE source_type='frontdesk' AND menu_id=<menu_id>
ORDER BY id DESC LIMIT 5;
```
After the IN, `frontdesk_inventories.number_of_serving` should equal the latest `stock_movements.balance_after`.

---

## 4. Daily flow — selling at the register (frontdesk)

Navigate: **Frontdesk → POS** (`/frontdesk/frontdesk-point-of-sale`).

You must have an **active shift** and an assigned **cash drawer** to use POS. If not, you'll be redirected to the dashboard.

The POS UI is split into two panels:
- **Left:** menu tile grid (filterable by category + search)
- **Right:** cart panel (Charge to Room toggle, items, totals, checkout)

### 4.1 Add items to cart

Click a menu tile → adds 1 to cart. Click again → quantity goes up. Use the +/− buttons in the cart, or the trash icon to remove a line.

**Backend effect:** none yet. Cart is in-memory (Livewire component state). It's lost on page refresh.

### 4.2 Cash walk-in sale (no room number)

1. Add items to cart.
2. Leave the **"Charge to a room?"** toggle OFF.
3. (Optional) Enter a **Discount** in pesos. If > 0, a "Discount reason" field appears below.
4. Click **"Review & Checkout"** → confirm modal opens showing items, subtotal, discount, total, and a green "Cash Sale" banner.
5. Click **"Confirm & Submit"**.

**Backend effect (atomic, single DB transaction via `CheckoutService::checkout()`):**
1. `INSERT INTO pos_orders`:
   - `payment_method='cash'`
   - `guest_id=NULL`, `room_id=NULL`
   - `subtotal`, `discount_amount`, `discount_reason`, `total`
   - `paid_amount` = total, `change_amount` = 0
   - `shift_log_id`, `user_id`, `branch_id`
2. For each cart line, `INSERT INTO transactions`:
   - `transaction_type_id=9` (food)
   - `order_id` → the new pos_order
   - **Snapshot fields frozen at sale time:** `source_type='frontdesk'`, `menu_id`, `item_name`, `unit_price`, `quantity`
   - `payable_amount` = unit_price × quantity
3. For each cart line, `StockService::out()` writes:
   - `INSERT INTO stock_movements` row with `type='OUT'`, `balance_after = current - qty`, `ref_type='transaction'`, `ref_id` → the line transaction
   - `UPDATE frontdesk_inventories SET number_of_serving = number_of_serving - qty`
4. **Receipt modal opens** automatically — click Print to send to your default printer (works on thermal printers via the browser print dialog too).

**If stock is short anywhere:** the entire sale is rolled back. **No partial state ever persists** — no order header, no transactions, no stock changes. You'll see an "Insufficient stock" toast.

**Verify a successful sale:**
```sql
-- The new order
SELECT * FROM pos_orders ORDER BY id DESC LIMIT 1;

-- Its line items (transactions table)
SELECT id, item_name, quantity, unit_price, payable_amount
FROM transactions
WHERE order_id = <new_pos_order_id>;

-- Stock movement audit
SELECT * FROM stock_movements
WHERE ref_type='transaction' AND ref_id IN (
  SELECT id FROM transactions WHERE order_id = <new_pos_order_id>
);

-- Inventory decremented
SELECT m.name, i.number_of_serving
FROM frontdesk_inventories i
JOIN frontdesk_menus m ON m.id = i.frontdesk_menu_id
WHERE i.frontdesk_menu_id IN (
  SELECT menu_id FROM transactions WHERE order_id = <new_pos_order_id>
);
```

### 4.3 Room-charge sale (charge to a guest's room)

1. Add items to cart.
2. Toggle **"Charge to a room?"** ON. A search input appears.
3. Type the room number OR guest name. Pick the guest from the dropdown.
4. The selected guest preview shows: **"RM <number> · <Guest Name> — Open POS balance: ₱<X>"** (open balance is what they already owe in POS this stay).
5. (Optional) Discount.
6. Click **"Charge to Room"**.
7. Confirm modal shows blue "Room Charge" banner with the guest's room and name.
8. Click **"Confirm Room Charge"**.

**Backend effect:** identical to cash sale EXCEPT:
- `pos_orders.payment_method = NULL` (no cash collected)
- `pos_orders.paid_amount = 0`, `change_amount = 0`
- `pos_orders.guest_id` and `room_id` populated
- Each line `transaction` also has `guest_id`, `room_id`, `floor_id` populated → the line will be visible on the guest's folio

The guest pays at room checkout — the cash drawer is untouched.

**Verify:**
```sql
SELECT id, payment_method, guest_id, room_id, paid_amount, total
FROM pos_orders ORDER BY id DESC LIMIT 1;
-- payment_method should be NULL, guest_id/room_id set, paid_amount=0
```

### 4.4 Discount

Enter any non-negative integer in the **Discount (₱)** field. If > 0, the **Discount reason** input appears (e.g. "regular customer", "comp").

**Validation:** the system blocks the sale if `discount > subtotal` (returns "Sale blocked: Discount cannot exceed subtotal").

**Backend effect:** `pos_orders.discount_amount` and `discount_reason` set; `pos_orders.total = subtotal - discount`. Receipt shows the discount line.

### 4.5 Void a same-shift sale

Open the **Purchase History** modal (button in the POS header). Find the order. Click **Void**.

**Allowed only if:**
- Order is from the **same shift** you're currently in
- Order was rung by **you** (the same user)
- Order is **not already voided**

If any of those fail, you get a clear toast like "Cannot void: voids are only allowed in the same shift."

**Backend effect (atomic, via `CheckoutService::void()`):**
1. `UPDATE pos_orders SET voided_at=NOW(), voided_by_user_id=<you>, void_reason=<optional>`
2. `UPDATE transactions SET voided_at=NOW(), voided_by_user_id=<you>` for every line
3. For each line, `StockService::void()` writes:
   - `INSERT INTO stock_movements` row with `type='VOID'`, `quantity=<original qty>`, `balance_after = current + qty`, `ref_type='transaction_void'`
   - `UPDATE frontdesk_inventories SET number_of_serving = number_of_serving + qty`

**Idempotent:** voiding an already-voided order is a no-op (no double-restore of stock).

The voided row appears in Purchase History as **grey + struck-through** with a red "Voided" pill. The cash total at the bottom drops it.

**Verify:**
```sql
-- Voided order
SELECT id, voided_at, voided_by_user_id, void_reason FROM pos_orders WHERE id = <order_id>;

-- All line transactions also voided
SELECT id, voided_at, voided_by_user_id FROM transactions WHERE order_id = <order_id>;

-- VOID stock movement reversed each line
SELECT type, quantity, balance_after, ref_type, ref_id FROM stock_movements
WHERE ref_type='transaction_void' AND ref_id IN (
  SELECT id FROM transactions WHERE order_id = <order_id>
);

-- Inventory restored
SELECT i.number_of_serving FROM frontdesk_inventories i
WHERE i.frontdesk_menu_id IN (
  SELECT menu_id FROM transactions WHERE order_id = <order_id>
);
```

### 4.6 Receipt printing

After every successful checkout, the **Receipt modal** opens automatically.

- **Print** button → calls `window.print()`. The browser's standard print dialog opens; pick any printer (regular A4 or thermal). The receipt is designed to look correct on both.
- **Close** button → dismisses without printing.

**No driver integration. No PDF. Just HTML + browser print.** If you have a thermal printer installed, it appears in the print dialog like any other printer.

The receipt shows: branch name, date/time, cashier, order #, line items (snapshot name + qty × unit_price), subtotal, discount (if any), TOTAL, then either:
- **CASH ₱X** + change line, OR
- **ROOM CHARGE / RM <#> / <Guest Name>** + "Will be settled at guest checkout."

If the order was later voided and you re-print, a **"** VOIDED **"** stamp appears on the bottom.

### 4.7 Shift cash total

The right side of the POS header shows **"Shift Total"** — the sum of cash sales (non-voided) for your current shift.

**Backend effect:** computed live in `PointOfSale::render()`:
```php
PosOrder::where('shift_log_id', current shift)
    ->where('user_id', current user)
    ->whereNull('voided_at')
    ->where('payment_method', 'cash')
    ->sum('total')
```

Room-charge sales are NOT in this total (no cash collected). Voided orders are NOT in this total.

This same total feeds into the shift-end cash reconciliation page (`CashOnHand`).

---

## 5. End of shift — Cash on Hand reconciliation

Navigate: the existing Cash on Hand page (frontdesk shift end).

**Backend effect (CashOnHand mount):** the `total_pos` field is the SUM of:
1. **POS v2 cash sales:** `pos_orders` where `shift_log_id` matches AND `payment_method='cash'` AND `voided_at IS NULL`, summed by `total`
2. **Legacy POS:** `pos_transactions` where `shift_log_id` matches, summed by `total` (only relevant for shifts that straddle the cutover; will be 0 for new shifts)

The combined total is what gets saved to `shift_logs.total_pos` when you end the shift.

**Why both?** Safety. If a shift was active during the cutover from old POS to new, the sum still works correctly. New shifts only produce v2 data, so the legacy term is just 0.

---

## 6. Owner / Big Boss view

Navigate: **Back Office → Reports → "Big Boss POS Report"** (`/back-office/report-hub`, pick "Big Boss POS Report" from the dropdown).

Pick a closed shift from the selector. The report shows:

### 6.1 POS Sales section

Every order in the selected shift session — time, order #, cashier, type (CASH/ROOM), guest+room (for room charges), items, subtotal, discount + reason, total. Voided orders are listed but greyed/struck-through with a "Voided" pill. Three subtotal rows at the bottom:
- CASH SALES (non-voided)
- ROOM-CHARGE SALES (non-voided)
- GROSS POS

If any orders were voided in the shift, a red note shows the count (and confirms they're excluded from the subtotals).

### 6.2 Inventory Movement section

Every menu item that had stock movement during the shift. Per item: source (frontdesk/kitchen/pub), name, **OPENING** (balance before shift), **IN** (sum of received during shift, including any VOID restorations), **OUT** (sum of sold during shift), **CLOSING** (balance at end of shift). Items with no shift activity are omitted to keep the report focused.

### 6.3 Print / Export

Two buttons:
- **Print Report** → browser print of the on-screen view (works on any printer)
- **Export HTML** → downloads a standalone HTML file you can open later or email

This report is **separate** from the existing Big Boss Report. Neither can break the other.

---

## 7. Things that did NOT change (so you don't have to retest them)

- **Kitchen / Pub** food charges to guest rooms — still use their existing pages, write to the same `transactions` table with `type=9`, `guest_id`, `room_id`, `floor_id`. Their UI is unchanged.
- **Guest folio at checkout** — sums transactions for the guest. Room-charge POS lines have `guest_id`/`room_id` set, so they appear there.
- **Sales Report V2 / Frontdesk Report V2 / Big Boss Report** — all filter transactions by `checkin_detail_id` or `guest_id`, which POS cash sales don't have. So those reports show what they always did. POS data is visible through the **new** Big Boss POS Report.
- **`pos_transactions` table** (legacy) — frozen, read-only for historical data. Nothing writes to it anymore.

---

## 8. Quick spot-check SQL after each test

Run these against your local DB to confirm everything is consistent:

```sql
-- After any POS write: most recent order + its lines
SELECT po.id, po.payment_method, po.guest_id, po.total, po.paid_amount, po.voided_at,
       COUNT(t.id) AS line_count, SUM(t.payable_amount) AS lines_sum
FROM pos_orders po
LEFT JOIN transactions t ON t.order_id = po.id
GROUP BY po.id
ORDER BY po.id DESC
LIMIT 5;

-- Most recent stock movements
SELECT id, source_type, menu_id, type, quantity, balance_after, ref_type, ref_id, created_at
FROM stock_movements
ORDER BY id DESC LIMIT 10;

-- Drift check: latest stock_movements.balance_after vs current inventory
SELECT
  sm.source_type, sm.menu_id, sm.balance_after AS movement_says,
  CASE sm.source_type
    WHEN 'frontdesk' THEN (SELECT number_of_serving FROM frontdesk_inventories WHERE id = sm.inventory_id)
    WHEN 'kitchen' THEN (SELECT number_of_serving FROM inventories WHERE id = sm.inventory_id)
    WHEN 'pub' THEN (SELECT number_of_serving FROM pub_inventories WHERE id = sm.inventory_id)
  END AS inventory_says
FROM stock_movements sm
WHERE sm.id IN (
  SELECT MAX(id) FROM stock_movements GROUP BY source_type, inventory_id
)
HAVING movement_says <> inventory_says;
-- 0 rows = all in sync
```

---

## 9. Test sequence (recommended)

Run through this in order to cover every flow:

1. **Setup:** create 1 category, 2 menu items (e.g. Coke ₱60, Chips ₱50), set their initial inventory to 10 each.
2. **Stock-In:** receive 5 more Coke. Verify `frontdesk_inventories` shows 15, `stock_movements` shows IN row with `balance_after=15`.
3. **Cash sale:** ring 2 Coke + 1 Chips → confirm receipt shows ₱170 total, prints OK. Verify `pos_orders` row, 2 transactions (one per cart line), 2 stock_movements OUT, inventory now 13 Coke / 9 Chips.
4. **Discount:** ring 1 Chips, enter ₱10 discount with reason "test" → total should be ₱40. Verify `pos_orders.discount_amount=10`, `discount_reason='test'`, `total=40`.
5. **Stock guard:** try to ring 100 Coke (more than stock) → "Insufficient stock" toast, no DB write.
6. **Room-charge:** check in a test guest, then ring 1 Coke → toggle Charge to Room → search for the room → confirm. Verify `pos_orders.payment_method=NULL`, `guest_id` and `room_id` set, `paid_amount=0`. Verify the line transaction has `guest_id`/`room_id` so it'd show on the guest's folio.
7. **Void:** open Purchase History → void the discount sale from step 4 → verify `pos_orders.voided_at` set, transactions also voided, stock restored (Chips back up), and the Shift Total at the top drops by ₱40.
8. **Same-user/shift block:** log in as a different frontdesk user with their own shift → try to void someone else's order → blocked with "Only the cashier who rang the sale can void it."
9. **Shift end:** close your shift via Cash on Hand. Verify `shift_logs.total_pos` matches your computed cash total.
10. **Owner view:** log in as back office, open Big Boss POS Report, pick the closed shift. Verify all your sales appear with the right totals; the inventory section shows opening / IN / OUT / closing for everything you touched.

If all 10 pass, you're production-ready.

---

## 10. Where to look if something is wrong

| Symptom | Likely cause | Where to look |
|---|---|---|
| Item shows "Out of stock" but you know there's stock | Inventory row missing or zero | `frontdesk_inventories` table; use Stock-In to add |
| Sale fails with "Insufficient stock" but stock looks right | Inventory was decremented by a recent sale; race | `stock_movements` recent rows for that menu |
| Room-charge sale doesn't appear on guest folio | Pre-existing gap in BigBossReport's per-guest column (see safety notes) | Use Big Boss POS Report instead — it shows all POS regardless |
| Receipt looks wrong on thermal printer | CSS issue with that specific printer | Print to PDF as a workaround; tell us the printer model |
| Shift total at POS top doesn't match Big Boss POS Report | Different filters (POS top is current user only; report is whole shift session) | Expected — POS header shows YOUR sales, report shows the whole shift |
| `php artisan migrate` fails locally | Migration tracker out of sync | See `documentation/2026-04-25-pos-rebuild-plan-1-foundation.md` for backfill steps |

---

*Generated against branch `pos-rebuild-plan-1` at commit `782bb32`. Update this doc when the POS module changes significantly.*
