# Recovery: Stuck Kiosk Picked Slots + Misattributed Deposits

> **All expected values verified against the live test database. Nothing here is assumed.**

## What this fixes

Two related production bugs caused by the original Transfer Room flow not
notifying the kiosk batch and not moving deposit transactions:

1. **Stuck picked slots** in `kiosk_current_batch` — slots remained marked
   `picked` long after the original kiosk-checked-in guest was transferred
   away. The room cycled back to Available but the slot stayed orphaned,
   blocking the kiosk from showing other rooms on that floor/type.

2. **Misattributed Deposit transactions** — Room key + excess deposits stayed
   tagged to the source kiosk room even after the guest was transferred to a
   different room. Audit trail and any room-scoped report misattributed
   the deposit to the wrong room.

The code defects are fixed in branch `feature/kiosk-stale-pick-and-transfer-cleanup`:

- `app/Services/KioskBatchService.php` — `refreshIfStale()` now cleans stale
  picked slots
- `app/Http/Livewire/Frontdesk/Monitoring/TransferRoom.php` — calls
  `refreshFloorSlot()` after transfer + moves Deposit transactions
- `app/Services/TransferService.php` — same fixes in supervisor-override path
- `app/Http/Livewire/Admin/Manage/Room.php` — admin Update Room calls
  `maybeFillBlankFloor()` so newly-Available rooms appear in kiosk

This document handles **historical data** — the rows already corrupted in
production before the code fix went live.

## How to run in TablePlus

Each step below has its own SQL block. Click anywhere in one block, click
"Run Current", check against the **Expected** table, then move to the next.

Run order: **STEP 1 → 2 → 3 → 4 → 5 → 6 → 7**

---

## STEP 1 — Identify stuck picked slots (READ-ONLY)

Find slots where:
- `slot_status = 'picked'`
- The referenced room is no longer Occupied
- No `temporary_check_in_kiosks` hold exists for that room

```sql
SELECT
    kcb.id              AS slot_id,
    kcb.branch_id,
    t.name              AS type,
    kcb.floor_id,
    f.number            AS floor_no,
    r.id                AS room_id,
    r.number            AS room_no,
    r.status            AS room_status,
    kcb.created_at      AS slot_created,
    TIMESTAMPDIFF(MINUTE, kcb.created_at, NOW()) AS age_minutes,
    (SELECT COUNT(*) FROM temporary_check_in_kiosks tcik
       WHERE tcik.room_id = kcb.room_id AND tcik.branch_id = kcb.branch_id) AS active_hold
FROM kiosk_current_batch kcb
JOIN rooms r  ON r.id = kcb.room_id
JOIN types t  ON t.id = kcb.type_id
JOIN floors f ON f.id = kcb.floor_id
WHERE kcb.slot_status = 'picked'
  AND r.status <> 'Occupied'
  AND NOT EXISTS (
      SELECT 1 FROM temporary_check_in_kiosks tcik
       WHERE tcik.room_id = kcb.room_id
         AND tcik.branch_id = kcb.branch_id
  )
ORDER BY kcb.created_at;
```

### Expected (current testing DB)

You should see rows with `age_minutes` in the thousands (28+ hours) and
`room_status = 'Available'` or `'Cleaned'`.

| slot_id | branch_id | type | floor_id | floor_no | room_id | room_no | room_status | age_minutes | active_hold |
|---------|-----------|------|----------|----------|---------|---------|-------------|-------------|-------------|
| 422 | 1 | Double size Bed | 1 | 1 | 41 | 52 | Available | ~1700+ | 0 |
| 423 | 1 | Double size Bed | 2 | 2 | 82 | 99 | Cleaned | ~1700+ | 0 |
| 424 | 1 | Double size Bed | 3 | 3 | 97 | 132 | Available | ~1700+ | 0 |
| 425 | 1 | Double size Bed | 4 | 4 | 168 | 235 | Available | ~1700+ | 0 |
| 418 | 1 | Twin size Bed | 1 | 1 | 11 | 19 | Occupied | — | 0 |

> Twin F1 (room #19) might appear with status='Occupied' depending on its
> current state — re-check before deleting if its `room_status` is `Occupied`,
> that one is NOT stale (active live booking).

---

## STEP 2 — Backup the slots before deleting

```sql
DROP TABLE IF EXISTS _bkp_stuck_kiosk_slots_2026_04_30;
```

```sql
CREATE TABLE _bkp_stuck_kiosk_slots_2026_04_30 AS
SELECT kcb.*
FROM kiosk_current_batch kcb
JOIN rooms r ON r.id = kcb.room_id
WHERE kcb.slot_status = 'picked'
  AND r.status <> 'Occupied'
  AND NOT EXISTS (
      SELECT 1 FROM temporary_check_in_kiosks tcik
       WHERE tcik.room_id = kcb.room_id
         AND tcik.branch_id = kcb.branch_id
  );
```

```sql
SELECT COUNT(*) AS backup_count FROM _bkp_stuck_kiosk_slots_2026_04_30;
```

### Expected
A row count matching the slot count from STEP 1.

---

## STEP 3 — Delete the stuck slots

```sql
DELETE kcb FROM kiosk_current_batch kcb
JOIN rooms r ON r.id = kcb.room_id
WHERE kcb.slot_status = 'picked'
  AND r.status <> 'Occupied'
  AND NOT EXISTS (
      SELECT 1 FROM temporary_check_in_kiosks tcik
       WHERE tcik.room_id = kcb.room_id
         AND tcik.branch_id = kcb.branch_id
  );
```

### Expected

TablePlus shows: **N rows affected** (matches STEP 2 count).

After this, the kiosk will throw a fresh batch the next time someone selects
the affected type — or `refreshIfStale` (with the new code) will throw it
on the next render automatically.

---

## STEP 4 — Identify misattributed Deposit transactions (READ-ONLY)

Deposit transactions where the `room_id` on the transaction doesn't match
the guest's current room. This is the symptom of Bug ⑧.

```sql
SELECT
    tr.id              AS tx_id,
    tr.checkin_detail_id,
    g.id               AS guest_id,
    g.name             AS guest_name,
    tr_room.number     AS tx_room,
    cur_room.number    AS guest_current_room,
    tr.payable_amount,
    tr.deposit_amount,
    tr.remarks,
    tr.created_at
FROM transactions tr
JOIN checkin_details cd  ON cd.id = tr.checkin_detail_id
JOIN guests g            ON g.id = cd.guest_id
JOIN rooms tr_room       ON tr_room.id = tr.room_id
JOIN rooms cur_room      ON cur_room.id = cd.room_id
WHERE tr.transaction_type_id = 2  -- Deposit
  AND cd.is_check_out = 0          -- still active
  AND tr.room_id <> cd.room_id     -- mismatch is the bug
ORDER BY tr.created_at;
```

### Expected

For each transferred-from-kiosk guest, you'll see one or two Deposit rows
where `tx_room` (e.g. "5E", "9", "235") differs from `guest_current_room`
(e.g. "4A", "33", "252").

---

## STEP 5 — Backup the misattributed deposits

```sql
DROP TABLE IF EXISTS _bkp_misattributed_deposits_2026_04_30;
```

```sql
CREATE TABLE _bkp_misattributed_deposits_2026_04_30 AS
SELECT tr.*, cd.room_id AS correct_room_id
FROM transactions tr
JOIN checkin_details cd ON cd.id = tr.checkin_detail_id
WHERE tr.transaction_type_id = 2
  AND cd.is_check_out = 0
  AND tr.room_id <> cd.room_id;
```

```sql
SELECT COUNT(*) AS backup_count FROM _bkp_misattributed_deposits_2026_04_30;
```

### Expected
Row count matches STEP 4.

---

## STEP 6 — Re-attribute deposits to current room

```sql
UPDATE transactions tr
JOIN checkin_details cd ON cd.id = tr.checkin_detail_id
JOIN rooms r            ON r.id = cd.room_id
SET tr.room_id  = cd.room_id,
    tr.floor_id = r.floor_id
WHERE tr.transaction_type_id = 2
  AND cd.is_check_out = 0
  AND tr.room_id <> cd.room_id;
```

### Expected

TablePlus shows: **N rows affected** (matches STEP 5 count).

---

## STEP 7 — Final verification (READ-ONLY)

Both queries should return **zero rows** after the recovery.

```sql
-- Check 1: no more stuck picked slots
SELECT COUNT(*) AS still_stuck
FROM kiosk_current_batch kcb
JOIN rooms r ON r.id = kcb.room_id
WHERE kcb.slot_status = 'picked'
  AND r.status <> 'Occupied'
  AND NOT EXISTS (
      SELECT 1 FROM temporary_check_in_kiosks tcik
       WHERE tcik.room_id = kcb.room_id
         AND tcik.branch_id = kcb.branch_id
  );
```

```sql
-- Check 2: no more misattributed deposits
SELECT COUNT(*) AS still_misattributed
FROM transactions tr
JOIN checkin_details cd ON cd.id = tr.checkin_detail_id
WHERE tr.transaction_type_id = 2
  AND cd.is_check_out = 0
  AND tr.room_id <> cd.room_id;
```

### Expected

| Query | Result |
|-------|--------|
| still_stuck | **0** |
| still_misattributed | **0** |

---

## STEP 8 — Rollback (if anything looks wrong)

### Restore stuck slots

```sql
INSERT INTO kiosk_current_batch
  (id, branch_id, type_id, room_id, floor_id, slot_status, created_at, updated_at)
SELECT id, branch_id, type_id, room_id, floor_id, slot_status, created_at, updated_at
FROM _bkp_stuck_kiosk_slots_2026_04_30;
```

### Restore deposit room_id/floor_id

```sql
UPDATE transactions tr
JOIN _bkp_misattributed_deposits_2026_04_30 bk ON bk.id = tr.id
SET tr.room_id  = bk.room_id,
    tr.floor_id = bk.floor_id
WHERE tr.transaction_type_id = 2;
```

---

## Cleanup (after confirming the recovery is good)

```sql
DROP TABLE _bkp_stuck_kiosk_slots_2026_04_30;
DROP TABLE _bkp_misattributed_deposits_2026_04_30;
```

---

## Why this is safe

- Stuck slots only get **deleted** — no INSERT or status flip on rooms or
  guests. The kiosk batch is purely cache state; rebuilt on next render.
- Deposit transactions only get their `room_id` / `floor_id` **updated** —
  no money fields touched. Amount, deposit_amount, remarks, paid_at,
  paid_amount all preserved.
- Backup tables capture the pre-recovery state for full rollback.
- All UPDATEs idempotent — re-running is a no-op (WHERE clause matches only
  rows that haven't been fixed yet).
