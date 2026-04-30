# Recovery: Long-Stay Walk-In Guests Charged 1 Day Instead of N Days

> Run after deploying fix `feature/urgent-finance-bugs-1-may-2026` (bugs A1, A2, A11).

## What this fixes

Three walk-in / reservation flows charged long-stay guests for **one day's
rate** instead of `24h_rate × number_of_days`:

- **A1** `Admin\CheckInCo::saveCheckInCO` (`CheckInCo.php:66`)
- **A2** `Frontdesk\Monitoring\RoomMonitoring::updatedRateId` + `storeGuest`
  (`RoomMonitoring.php:917-920, 949`)
- **A11** `Admin\Manage\Reservation::saveReservation` (`Reservation.php:99`)

Each affected guest's `guests.static_amount` (and the corresponding
`checkin_details.static_amount`, plus the Check-In transaction's amount fields)
hold the single-day rate instead of the multi-day total. This is **revenue
loss** — the guest was scheduled for N days but billed for 1.

The code defect is fixed for new guests. This document handles the
**historical data** for guests already affected.

## ⚠ Before running anything

This recovery is more delicate than the long-stay-transfer recovery, because:

1. **Money may already have been collected at the wrong amount.** If the cash
   drawer matches the (wrong) system amount, simply rewriting the system
   number creates a real cash discrepancy.
2. **Guests may have already checked out.** Once they're gone, you can't re-bill.
3. **Guests still in-house can be billed for the difference** if you decide to.

Therefore: **STEP 1 produces a list. STEP 2-onwards should be run only after
a per-guest business decision** (bill, write off, or only fix the historical
record).

## How to run in TablePlus

Run order: **STEP 1 (always) → STEP 2 (per guest after decision)**.

---

## STEP 1 — Identify potentially affected guests (READ-ONLY)

```sql
SELECT
    g.id AS guest_id,
    g.name,
    r.number AS room,
    t.name AS type,
    g.is_long_stay,
    g.number_of_days,
    rt24.amount AS rate_24h,
    b.initial_deposit AS deposit,
    g.static_amount AS current_static_amount,
    (rt24.amount * g.number_of_days) + b.initial_deposit AS expected_static_amount,
    cd.static_room_amount AS current_cd_static_room_amount,
    cd.static_amount AS current_cd_static_amount,
    cd.is_check_out,
    g.created_at,
    -- the guest is suspect if static_amount looks like single-day +/- deposit
    CASE
        WHEN g.static_amount = rt24.amount THEN 'single-day rate only (no deposit)'
        WHEN g.static_amount = rt24.amount + b.initial_deposit THEN 'single-day rate + deposit'
        WHEN g.static_amount = (rt24.amount * g.number_of_days) + b.initial_deposit THEN 'CORRECT (no fix needed)'
        ELSE 'manual review — unusual amount'
    END AS pattern
FROM guests g
JOIN checkin_details cd ON cd.guest_id = g.id
JOIN rooms r ON r.id = g.room_id
JOIN types t ON t.id = g.type_id
JOIN branches b ON b.id = g.branch_id
JOIN staying_hours sh24 ON sh24.branch_id = g.branch_id AND sh24.number = 24
JOIN rates rt24 ON rt24.branch_id = g.branch_id
                AND rt24.type_id = g.type_id
                AND rt24.staying_hour_id = sh24.id
                AND rt24.is_available = 1
WHERE g.is_long_stay = 1
  AND g.number_of_days >= 2
  AND g.previous_room_id IS NULL  -- exclude transferred-from-kiosk (covered by other recovery doc)
  AND g.static_amount < (rt24.amount * g.number_of_days) + b.initial_deposit
ORDER BY cd.is_check_out, g.created_at;
```

### Expected

A list of guests whose `current_static_amount` is **less than** what the
correct charge should have been. Each row's `pattern` column tells you
which sub-pattern it matches:

- `single-day rate only (no deposit)` — definitely the bug
- `single-day rate + deposit` — definitely the bug
- `manual review — unusual amount` — investigate per-guest before deciding

If the query returns 0 rows → no historical fix needed.

---

## STEP 2 — Per-guest decision and fix (run only after deciding)

For each guest in STEP 1, decide:

### Option A — bill the difference (guest still in-house)

Add a new "balance owed" transaction for the missing amount. Have frontdesk
collect from the guest before checkout. Use the regular UI, do NOT manually
edit transactions.

After collection, fix the historical record (Option C below) so reports tally.

### Option B — write off (guest already checked out / cannot be re-billed)

Decide internally to absorb the loss. Fix the historical record so reports
are accurate going forward, but don't try to re-bill.

### Option C — fix the historical record only (per guest)

For a single guest where you've decided Option A or B, run these in order:

#### Fix `guests.static_amount`

```sql
-- Replace 12345 with the actual guest_id from STEP 1
UPDATE guests g
JOIN branches b ON b.id = g.branch_id
JOIN staying_hours sh ON sh.branch_id = g.branch_id AND sh.number = 24
JOIN rates rt ON rt.branch_id = g.branch_id
              AND rt.type_id = g.type_id
              AND rt.staying_hour_id = sh.id
              AND rt.is_available = 1
SET g.static_amount = (rt.amount * g.number_of_days) + b.initial_deposit
WHERE g.id = 12345;
```

#### Fix `checkin_details` (only if guest still active)

```sql
-- Replace 12345 with the actual guest_id
UPDATE checkin_details cd
JOIN guests g ON g.id = cd.guest_id
JOIN branches b ON b.id = g.branch_id
JOIN staying_hours sh ON sh.branch_id = g.branch_id AND sh.number = 24
JOIN rates rt ON rt.branch_id = g.branch_id
              AND rt.type_id = g.type_id
              AND rt.staying_hour_id = sh.id
              AND rt.is_available = 1
SET cd.static_room_amount = rt.amount * g.number_of_days,
    cd.static_amount      = (rt.amount * g.number_of_days) + b.initial_deposit
WHERE g.id = 12345
  AND cd.is_check_out = 0;
```

#### Fix the Check-In transaction (only if guest still active)

```sql
-- Replace 12345 with the actual guest_id
UPDATE transactions tr
JOIN checkin_details cd ON cd.id = tr.checkin_detail_id
JOIN guests g ON g.id = cd.guest_id
JOIN staying_hours sh ON sh.branch_id = g.branch_id AND sh.number = 24
JOIN rates rt ON rt.branch_id = g.branch_id
              AND rt.type_id = g.type_id
              AND rt.staying_hour_id = sh.id
              AND rt.is_available = 1
SET tr.payable_amount = rt.amount * g.number_of_days,
    tr.paid_amount    = rt.amount * g.number_of_days
WHERE g.id = 12345
  AND tr.description = 'Guest Check In'
  AND cd.is_check_out = 0;
```

---

## Rollback (per guest)

If you need to undo a fix, you'll need to know the original values. Take a
backup row before STEP 2 if you haven't already:

```sql
-- Capture the row before any changes
SELECT 'guest', g.* FROM guests g WHERE g.id = 12345
UNION ALL
SELECT 'checkin_detail', cd.* FROM checkin_details cd WHERE cd.guest_id = 12345
UNION ALL
SELECT 'check_in_tx', tr.* FROM transactions tr
JOIN checkin_details cd ON cd.id = tr.checkin_detail_id
WHERE cd.guest_id = 12345 AND tr.description = 'Guest Check In';
```

Save the output before running STEP 2. To roll back, write `UPDATE` statements
manually using those values.

---

## Why per-guest manual review is required

Unlike the long-stay-transfer recovery (where the bug always overwrote known
correct values to predictable wrong ones), this bug:

- Could have been caught by attentive frontdesk staff who collected the
  correct amount in cash but the system stored less
- Could have been the actual amount collected (under-collection)
- Affects different guest scenarios differently (some checked out, some still
  in-house, some had follow-up transactions that compensated)

Each affected guest is a **business decision**, not a mechanical fix.

## Going forward

After deploying the code fix in `feature/urgent-finance-bugs-1-may-2026`,
new long-stay walk-ins will be charged correctly. STEP 1's query should
return zero new rows on subsequent runs (any rows that appear are
pre-deploy guests, decreasing as you process them).
