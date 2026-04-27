# POS System - Complete Guide

## Overview

This document explains the **new POS module** - how it works, where data goes, how it affects reports, and how to verify everything is correct.

---

## Part 1: How POS Works

### Two Payment Types

| Type | Description | Guest Connected? |
|------|-------------|------------------|
| **ROOM CHARGE** | Charge to guest's room bill | YES |
| **WALK-IN CASH** | Direct cash payment | NO |

### What Happens When You Checkout

```
┌─────────────────────────────────────────────────────────────┐
│                    POS CHECKOUT                              │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  Cashier adds items → Click Checkout                        │
│                           │                                  │
│           ┌───────────────┴───────────────┐                 │
│           │                               │                  │
│      ROOM CHARGE                     WALK-IN CASH           │
│      (Select guest)                  (Enter cash)           │
│           │                               │                  │
│           ▼                               ▼                  │
│  ┌─────────────────┐             ┌─────────────────┐        │
│  │   PosOrder      │             │   PosOrder      │        │
│  │ guest_id = 123  │             │ guest_id = NULL │        │
│  │ room_id = 45    │             │ room_id = NULL  │        │
│  │ payment = NULL  │             │ payment = cash  │        │
│  └────────┬────────┘             └────────┬────────┘        │
│           │                               │                  │
│           ▼                               ▼                  │
│  ┌─────────────────┐             ┌─────────────────┐        │
│  │  Transaction    │             │  Transaction    │        │
│  │  type_id = 9    │             │  type_id = 9    │        │
│  │  guest_id = 123 │             │  guest_id = NULL│        │
│  └────────┬────────┘             └────────┬────────┘        │
│           │                               │                  │
│           ▼                               ▼                  │
│  ┌─────────────────┐             ┌─────────────────┐        │
│  │ StockMovement   │             │ StockMovement   │        │
│  │ (decrement)     │             │ (decrement)     │        │
│  └─────────────────┘             └─────────────────┘        │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

### Database Records Created

#### 1. PosOrder (Header)
```
Table: pos_orders
├── id (order number)
├── branch_id
├── user_id (cashier who made sale)
├── shift_log_id (which shift)
├── guest_id (NULL if walk-in)
├── room_id (NULL if walk-in)
├── payment_method ('cash' or NULL for room charge)
├── subtotal
├── discount_amount
├── total
├── paid_amount (0 if room charge)
├── change_amount (0 if room charge)
├── voided_at (NULL unless voided)
└── voided_by_user_id
```

#### 2. Transaction (Line Items)
```
Table: transactions
├── id
├── order_id → links to pos_orders.id
├── transaction_type_id = 9 (POS/Food)
├── branch_id
├── guest_id (NULL if walk-in)
├── room_id (NULL if walk-in)
├── floor_id (NULL if walk-in)
├── shift_log_id
├── menu_id (which menu item)
├── item_name (snapshot)
├── unit_price (snapshot)
├── quantity
├── payable_amount (line total)
├── voided_at
└── voided_by_user_id
```

#### 3. StockMovement (Inventory)
```
Table: stock_movements
├── id
├── source_type ('frontdesk')
├── inventory_id
├── menu_id
├── type ('out' for sale, 'void' for void)
├── quantity
├── balance_after
└── created_at
```

---

## Part 2: Where Does POS Data Go?

### Summary Table

| POS Type | Sales Report | Big Boss Report | Big Boss POS | Guest Folio |
|----------|--------------|-----------------|--------------|-------------|
| **ROOM CHARGE** | ✅ FOOD | ✅ FOODS | ✅ ROOM | ✅ Shows |
| **WALK-IN CASH** | ❌ NO | ❌ NO | ✅ CASH | ❌ N/A |

### Explanation

#### ROOM CHARGE (guest connected)
- **Sales Report**: Included in **FOOD** category (type 9)
- **Big Boss Report**: Included in **FOODS** row
- **Big Boss POS Report**: Shows as **ROOM** type
- **Guest Folio**: Shows as unpaid charge until checkout

#### WALK-IN CASH (no guest)
- **Sales Report**: NOT included (no guest = no occupancy)
- **Big Boss Report**: NOT included (no floor assignment)
- **Big Boss POS Report**: Shows as **CASH** type
- **Guest Folio**: N/A (no guest)

---

## Part 3: How It Affects Each Report

### Sales Report (SalesReportV2)

**Location**: Back Office → Report Hub → Sales Report

**How POS is included**:
```php
// Type 9 = Food and Beverages (POS)
case 9:
    $summary['food'] = $sum;
    break;
```

**Grand Total formula**:
```
Grand Total = Room + Extensions + Amenities + FOOD + Damages + Transfers
                                               ↑
                                          POS is here
```

**Important**: Only POS with `guest_id NOT NULL` is included!

**Where to check**:
- Look at "FOOD" card in Sales Report
- Click the card to see breakdown
- Each POS item shows: guest name, room, amount

### Big Boss Report

**Location**: Back Office → Report Hub → Big Boss Report

**How POS is included**:
```php
'FOODS' => [9],  // Transaction type 9 = POS
```

**Shows per floor**:
- FOODS row shows POS amount per floor
- Only room-charge POS (has floor_id)
- Walk-in POS has NULL floor_id = not shown

**Where to check**:
- Look at "FOODS" row in summary table
- Amount per floor column
- Included in GROSS TOTAL

### Big Boss POS Report

**Location**: Back Office → Report Hub → Big Boss POS Report

**Shows ALL POS**:
- Both ROOM and CASH orders
- Per-shift breakdown
- Voided orders listed separately

**Columns**:
- Time, Cashier, Type (CASH/ROOM), Guest, Room, Items, Total, Voided

**Totals**:
```
CASH Total: Walk-in cash sales
ROOM Total: Room charge sales
GROSS: CASH + ROOM
```

**Where to check**:
- Select shift from dropdown
- See every POS order for that shift
- Voided orders shown with strikethrough

---

## Part 4: How to Trace & Verify

### Scenario 1: "Sales Report Food doesn't match Big Boss POS"

**This is NORMAL!**

```
Big Boss POS = ROOM charges + CASH walk-in
Sales Report FOOD = ROOM charges ONLY

Example:
- Big Boss POS shows: ₱10,000
- Sales Food shows: ₱6,000
- Difference: ₱4,000 = walk-in cash (not in Sales)
```

**How to verify**:
```sql
-- Walk-in POS (not in Sales Report)
SELECT SUM(total) FROM pos_orders
WHERE shift_log_id IN ([shift_ids])
  AND guest_id IS NULL
  AND voided_at IS NULL;
```

### Scenario 2: "I made a POS sale but it's not showing"

**Check these**:

1. **Was it voided?**
```sql
SELECT voided_at FROM pos_orders WHERE id = [order_id];
```
If `voided_at` has a value → order was voided

2. **Is it in the right shift?**
```sql
SELECT shift_log_id, created_at FROM pos_orders WHERE id = [order_id];
```
Compare with selected shift in report

3. **Was it walk-in?**
```sql
SELECT guest_id FROM pos_orders WHERE id = [order_id];
```
If NULL → won't appear in Sales Report

### Scenario 3: "Guest folio shows POS but report doesn't"

**Check if guest is occupying during the shift**:
- Sales Report only shows transactions for guests who were **occupying rooms** during the shift
- If guest checked out before the shift, their POS won't show

### Scenario 4: "Verify POS total is correct"

**Step 1**: Get all POS for the shift
```sql
SELECT po.id, po.total, po.payment_method,
       CASE WHEN po.guest_id IS NULL THEN 'WALK-IN' ELSE 'ROOM' END as type,
       g.name as guest_name
FROM pos_orders po
LEFT JOIN guests g ON po.guest_id = g.id
WHERE po.shift_log_id IN ([shift_ids])
  AND po.voided_at IS NULL
ORDER BY po.created_at;
```

**Step 2**: Compare totals
```sql
-- Room charge total (should match Sales FOOD)
SELECT SUM(total) FROM pos_orders
WHERE shift_log_id IN ([shift_ids])
  AND guest_id IS NOT NULL
  AND voided_at IS NULL;

-- Walk-in total
SELECT SUM(total) FROM pos_orders
WHERE shift_log_id IN ([shift_ids])
  AND guest_id IS NULL
  AND voided_at IS NULL;
```

---

## Part 5: Checklist for Verifiers

### Daily Verification

| Check | How | Expected |
|-------|-----|----------|
| POS orders recorded? | Big Boss POS Report | All orders for shift shown |
| Voided orders correct? | Big Boss POS → voided section | Voided orders not in totals |
| Sales Food correct? | Sales Report → FOOD card | = Room charge POS only |
| Inventory deducted? | Big Boss POS → Inventory section | OUT column matches sold qty |
| Guest folio correct? | Guest checkout screen | Shows their POS charges |

### Reconciliation Formula

```
┌─────────────────────────────────────────────────────┐
│               RECONCILIATION                         │
├─────────────────────────────────────────────────────┤
│                                                      │
│  Big Boss POS GROSS = CASH + ROOM                   │
│                                                      │
│  Sales Report FOOD = ROOM (from Big Boss POS)       │
│                                                      │
│  Difference = CASH (walk-in sales)                  │
│                                                      │
│  ✓ If these match, POS is correct                   │
│                                                      │
└─────────────────────────────────────────────────────┘
```

### Quick SQL Verification

```sql
-- All-in-one verification for a shift
SELECT
    'Big Boss POS GROSS' as metric,
    SUM(total) as amount
FROM pos_orders
WHERE shift_log_id IN ([SHIFT_IDS])
  AND voided_at IS NULL

UNION ALL

SELECT
    'Big Boss POS ROOM' as metric,
    SUM(total) as amount
FROM pos_orders
WHERE shift_log_id IN ([SHIFT_IDS])
  AND guest_id IS NOT NULL
  AND voided_at IS NULL

UNION ALL

SELECT
    'Big Boss POS CASH' as metric,
    SUM(total) as amount
FROM pos_orders
WHERE shift_log_id IN ([SHIFT_IDS])
  AND guest_id IS NULL
  AND voided_at IS NULL

UNION ALL

SELECT
    'Sales Report FOOD (should = ROOM)' as metric,
    SUM(t.payable_amount) as amount
FROM transactions t
JOIN pos_orders po ON t.order_id = po.id
WHERE t.transaction_type_id = 9
  AND t.shift_log_id IN ([SHIFT_IDS])
  AND t.guest_id IS NOT NULL
  AND t.voided_at IS NULL
  AND po.voided_at IS NULL;
```

---

## Part 6: Known Behaviors

### Behavior 1: Walk-in POS not in Sales Report
**Status**: By Design
**Reason**: Sales Report is occupancy-based; walk-in has no guest/room
**Where to see**: Big Boss POS Report only

### Behavior 2: Big Boss Report FOODS = 0 for walk-in
**Status**: By Design
**Reason**: Walk-in has NULL floor_id; Big Boss groups by floor
**Where to see**: Big Boss POS Report

### Behavior 3: Voided orders still visible
**Status**: Correct
**Reason**: Audit trail - voided orders shown but not in totals
**Where to see**: Big Boss POS Report (strikethrough text)

### Behavior 4: Inventory restored on void
**Status**: Correct
**Reason**: StockMovement TYPE_VOID adds quantity back
**Where to see**: Big Boss POS → Inventory → "IN" column

---

## Part 7: Code Verification

### Files Checked ✓

| File | Purpose | Status |
|------|---------|--------|
| `CheckoutService.php` | Creates POS orders | ✅ Fixed - now sets checkin_detail_id |
| `PointOfSale.php` | POS UI | ✅ Fixed - captures checkin_detail_id |
| `SalesReportV2.php` | Sales report | ✅ Type 9 in FOOD |
| `BigBossReport.php` | Big Boss report | ✅ Type 9 in FOODS |
| `BigBossPosReport.php` | POS-specific report | ✅ Shows all POS |

### Bug Fixed (2026-04-27)

**Issue**: POS transactions were missing `checkin_detail_id`, so they didn't appear in Sales Report.

**Fix Applied**:
1. `PointOfSale.php` - Added `checkin_detail_id` to `selectedGuestData`
2. `PointOfSale.php` - Passing `checkin_detail_id` in checkout context
3. `CheckoutService.php` - Now sets `checkin_detail_id` on Transaction

**Result**: Room-charge POS now correctly appears in Sales Report FOOD category.

### Transaction Type Mapping

| Type ID | Name | Used For |
|---------|------|----------|
| 1 | Check In | Room charges |
| 2 | Deposit | Guest deposits |
| 4 | Damages | Damage charges |
| 5 | Cashout | Deposit withdrawal |
| 6 | Extend | Extensions |
| 7 | Transfer | Room transfers |
| 8 | Amenities | Extra amenities |
| **9** | **Food** | **POS sales** |

### Code Snippets

**CheckoutService creates type 9 transaction**:
```php
// Line 118 in CheckoutService.php
'transaction_type_id' => 9,
```

**Sales Report includes type 9 in FOOD**:
```php
// Line 1083-1084 in SalesReportV2.php
case 9:
    $summary['food'] = $sum;
```

**Big Boss Report includes type 9 in FOODS**:
```php
// Line 228 in BigBossReport.php
'FOODS' => [9],
```

---

## Part 8: Troubleshooting

### Problem: POS not deducting inventory

**Check**:
1. Is the menu item linked to inventory?
2. Is there stock available?
3. Check stock_movements table for the transaction

### Problem: Voided order still showing in report totals

**Check**:
1. Verify `voided_at` is set on pos_orders
2. Verify `voided_at` is set on all related transactions
3. Reports filter: `WHERE voided_at IS NULL`

### Problem: Guest folio missing POS charge

**Check**:
1. Was the order made with guest selected?
2. Check `pos_orders.guest_id` is not NULL
3. Check `transactions.guest_id` is not NULL

### Problem: Inventory not restoring after void

**Check**:
1. Look for TYPE_VOID movement in stock_movements
2. Check `balance_after` is incremented
3. Verify menu_id and quantity match original sale

---

## Summary

| Question | Answer |
|----------|--------|
| Where does ROOM CHARGE go? | Sales (FOOD), Big Boss (FOODS), Big Boss POS (ROOM) |
| Where does WALK-IN go? | Big Boss POS (CASH) ONLY |
| Why Big Boss POS > Sales FOOD? | Walk-in cash not in Sales |
| How to verify totals? | Big Boss POS ROOM = Sales FOOD |
| Where to see voided? | Big Boss POS Report |
| How inventory works? | OUT on sale, IN on void |
