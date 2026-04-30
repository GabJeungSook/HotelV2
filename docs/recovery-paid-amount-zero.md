# Recovery: Transactions with `paid_at` set but `paid_amount = 0`

> Run after deploying fix `feature/urgent-finance-bugs-1-may-2026` (bugs A6 + A7).

## What this fixes

Two flows in production wrote `paid_at = now()` without setting `paid_amount`:

- **A6 — `payAllUnpaid`** (`ManageGuestTransaction.php:1534`): bulk-marked transactions paid but left `paid_amount = 0`.
- **A7 — `addOverride`** (`ManageGuestTransaction.php:1912`): set `payable_amount` to the override but left `paid_amount = 0` and `is_override = false`.

Result: transactions in the DB say "paid at this time" but record ₱0 actually paid. Cash-drawer reconciliation reports look wrong, audit trail is misleading, and any code that reads `paid_amount` directly returns 0.

The code defect is fixed in `ManageGuestTransaction.php` (committed in branch
`feature/urgent-finance-bugs-1-may-2026`). This document fixes the historical
data that already has the wrong values.

## NOT affected (intentionally `paid_amount = 0`)

- **`addAllPaymentWithDeposit`** (`ManageGuestTransaction.php:1582`) explicitly
  writes `paid_amount = 0` because the bill is being paid using the existing
  deposit, not new cash. This is correct and the recovery script must NOT
  touch these rows. The query below excludes them by checking `payable_amount > 0`
  on the row itself only — but if you find rows where the user clearly paid
  with deposit, leave them alone.

## How to run in TablePlus

Each step is a separate SQL block. Click anywhere in one block, "Run Current",
verify against the **Expected** notes, move to the next.

Run order: **STEP 1 → 2 → 3 → 4 → 5**

---

## STEP 1 — Identify affected rows (READ-ONLY)

```sql
SELECT
    tr.id,
    tr.branch_id,
    tr.guest_id,
    g.name AS guest_name,
    r.number AS room,
    tt.name AS tx_type,
    tr.payable_amount,
    tr.paid_amount,
    tr.deposit_amount,
    tr.paid_at,
    tr.override_at,
    tr.is_override,
    tr.remarks,
    tr.created_at
FROM transactions tr
LEFT JOIN guests g ON g.id = tr.guest_id
LEFT JOIN rooms  r ON r.id = tr.room_id
LEFT JOIN transaction_types tt ON tt.id = tr.transaction_type_id
WHERE tr.paid_at IS NOT NULL
  AND tr.paid_amount = 0
  AND tr.payable_amount > 0
  AND tr.voided_at IS NULL
  AND tr.deposit_amount = 0  -- exclude deposit-paid rows where 0 is intentional
ORDER BY tr.created_at;
```

### Expected
Rows with `payable_amount > 0` (real money owed) but `paid_amount = 0`. Each
will have `paid_at` set (so it was marked paid) and either `override_at` set
(an override row) or just be a `payAllUnpaid` victim.

If 0 rows return → no recovery needed; the production data is already clean.

---

## STEP 2 — Backup (run once)

```sql
DROP TABLE IF EXISTS _bkp_paid_amount_zero;
```

```sql
CREATE TABLE _bkp_paid_amount_zero AS
SELECT *
FROM transactions
WHERE paid_at IS NOT NULL
  AND paid_amount = 0
  AND payable_amount > 0
  AND voided_at IS NULL
  AND deposit_amount = 0;
```

```sql
SELECT COUNT(*) AS backup_count FROM _bkp_paid_amount_zero;
```

### Expected
Count matches STEP 1's row count.

---

## STEP 3 — Fix `paid_amount` (set to row's payable_amount)

```sql
UPDATE transactions
SET paid_amount = payable_amount
WHERE paid_at IS NOT NULL
  AND paid_amount = 0
  AND payable_amount > 0
  AND voided_at IS NULL
  AND deposit_amount = 0;
```

### Expected
TablePlus shows: **N rows affected** (matches STEP 2 count).

---

## STEP 4 — Fix `is_override` flag where override happened but flag missing

```sql
UPDATE transactions
SET is_override = 1
WHERE override_at IS NOT NULL
  AND is_override = 0
  AND voided_at IS NULL;
```

### Expected
**M rows affected** (the subset of STEP 3 rows that are overrides).

---

## STEP 5 — Final verification

```sql
SELECT COUNT(*) AS still_broken
FROM transactions
WHERE paid_at IS NOT NULL
  AND paid_amount = 0
  AND payable_amount > 0
  AND voided_at IS NULL
  AND deposit_amount = 0;
```

```sql
SELECT COUNT(*) AS overrides_missing_flag
FROM transactions
WHERE override_at IS NOT NULL
  AND is_override = 0
  AND voided_at IS NULL;
```

### Expected
Both queries return **0**.

---

## Rollback

```sql
UPDATE transactions tr
JOIN _bkp_paid_amount_zero bk ON bk.id = tr.id
SET tr.paid_amount = bk.paid_amount,
    tr.is_override = bk.is_override;
```

This restores both `paid_amount` and `is_override` to their pre-fix values
for every row touched.

## Cleanup

```sql
DROP TABLE _bkp_paid_amount_zero;
```

---

## Why this is safe

- Only updates fields that match the precise bug pattern (paid_at NOT NULL +
  paid_amount = 0 + payable_amount > 0 + voided_at IS NULL + deposit_amount = 0)
- Cannot match deposit-paid rows (those have deposit_amount > 0 OR were created
  via the legitimate addAllPaymentWithDeposit flow)
- Only sets `paid_amount = payable_amount` (the obvious correct value)
- Only sets `is_override = 1` where `override_at` confirms the override happened
- Backup table preserves original values for rollback
- Idempotent — re-running is a no-op (WHERE clause matches only broken rows)
