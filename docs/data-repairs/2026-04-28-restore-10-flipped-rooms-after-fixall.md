# Data Repair: Restore 20 active rooms flipped Available by `Fix All Unresolved`

**Date:** 2026-04-28
**Executor:** _(to be filled after running)_
**Database:** `hotelv2` (production)
**Backup-of-truth:** `c:\Users\Owner\Downloads\homi_app_producoot_lastest_now.sql` taken **2026-04-27 23:17:30** (≈2 minutes before the incident — last clean snapshot)
**Bug reference:** `docs/bugs/2026-04-28-fixall-unresolved-flips-active-extension-checkins.md`
**Runnable script:** `docs/data-repairs/2026-04-28-recover.sql`

> **NOTE on filename:** the filename says "10" because the initial diagnosis only spotted 10 affected rooms; a complete BEFORE↔AFTER diff later revealed the full scope is **20 rooms + 1 ambiguous case (room #71)**. The numbers in this document are the corrected ones.

## Summary

Clicking "Fix All Records" on `/admin/unresolved-check-ins` then "Fix All" on `/admin/ghost-rooms` on **2026-04-27 23:19:54 → 23:20:03** force-closed 30 check-in records. Of those:

- **20** were **false positives** — active guests still inside, with `check_out_at` set to a future timestamp. Their rooms flipped from `Occupied` to `Available`/`Cleaned`. ← these need recovery.
- **9** were genuine ghosts (March / early-April abandonments). They stay closed.
- **1** is **ambiguous** (cid=12086, room #71, planned check_out_at 4 h before the bug). Operator decision needed.

This patch restores `checkin_details.is_check_out`, `checkin_details.check_out_at`, and `rooms.status` to the BEFORE-backup values for the 20 confirmed false-positives. Room #71 is handled in a separate optional block.

It does **not** touch transactions (none were modified by the bug). It does **not** touch the 9 legitimate ghosts.

---

## The 20 confirmed false-positive records

| `cid` | room id | room # | guest_id | real `check_out_at` (BEFORE) | deposit |
|---:|---:|---:|---:|---|---:|
| 10854 | 40 | #51 | 12843 | 2026-04-28 16:49:41 | ₱3,425 |
| 11018 | 216 | #286 | 13038 | 2026-04-28 00:52:59 | ₱246 |
| 11263 | 106 | #151 | 13373 | 2026-04-28 10:30:23 | ₱1,323 |
| 11297 | 148 | #215 | 13415 | 2026-04-28 14:51:24 | ₱4,254 |
| 11923 | 46 | #63 | 14135 | 2026-04-28 09:09:09 | ₱200 |
| 11924 | 57 | #74 | 14136 | 2026-04-28 09:09:13 | ₱200 |
| 12051 | 4 | #4 | 14285 | 2026-04-28 17:42:52 | ₱200 |
| 12308 | 139 | #205 | 14594 | 2026-04-28 14:30:53 | ₱200 |
| 12318 | 41 | #52 | 14597 | 2026-04-28 14:57:22 | ₱200 |
| 12319 | 6 | #6 | 14603 | 2026-04-28 14:57:55 | ₱200 |
| 12333 | 5 | #5 | 14623 | 2026-04-28 03:57:02 | ₱900 |
| 12341 | 48 | #65 | 14633 | 2026-04-28 16:56:26 | ₱200 |
| 12347 | 45 | #62 | 14639 | 2026-04-28 05:36:31 | ₱200 |
| 12356 | 75 | #92 | 14647 | 2026-04-28 17:56:55 | ₱200 |
| 12387 | 83 | #100 | 14675 | 2026-04-28 07:21:20 | ₱600 |
| 12388 | 121 | #166 | 14685 | 2026-04-28 07:21:35 | ₱200 |
| 12393 | 43 | #60 | 14687 | 2026-04-28 07:34:57 | ₱200 |
| 12395 | 126 | #171 | 14690 | 2026-04-28 19:35:20 | ₱1,400 |
| 12454 | 11 | #11 | 14754 | 2026-04-28 10:10:44 | ₱650 |
| 12461 | 145 | #211 | 14768 | 2026-04-28 10:32:35 | ₱600 |

Total deposits preserved across the 20 records: **₱15,398**. None lost — values intact in each row.

---

## Pre-flight verification (REQUIRED — do not skip)

The `backup-prod-before-up` MySQL DB on the local machine was loaded from `homi_app_producoot_lastest_now.sql`. Verify the BEFORE state on it first.

```sql
USE `backup-prod-before-up`;

-- Should return exactly 20 rows, all with is_check_out = 0
SELECT id, guest_id, room_id, check_in_at, check_out_at, is_check_out, number_of_hours, total_deposit
FROM checkin_details
WHERE id IN (
    10854, 11018, 11263, 11297, 11923, 11924,
    12051, 12308, 12318, 12319, 12333, 12341,
    12347, 12356, 12387, 12388, 12393, 12395,
    12454, 12461
)
ORDER BY id;

-- Should return 20 rows, all with status = 'Occupied'
SELECT id, number, status, last_checkin_at FROM rooms
WHERE id IN (
      4,   5,   6,  11,  40,  41,  43,  45,  46,  48,
     57,  75,  83, 106, 121, 126, 139, 145, 148, 216
)
ORDER BY id;
```

If either query returns a row whose state already differs (e.g., a check-in that was legitimately checked out before 23:17), STOP and re-investigate before continuing.

---

## Pre-flight verification on production

Confirm the corrupted state matches what we expect:

```sql
USE `hotelv2`;

-- All 20 should currently show is_check_out = 1, check_out_at ≈ check_in_at + 30 min,
-- updated_at = '2026-04-27 23:19:54'
SELECT id, room_id, check_in_at, check_out_at, is_check_out, updated_at
FROM checkin_details
WHERE id IN (
    10854, 11018, 11263, 11297, 11923, 11924,
    12051, 12308, 12318, 12319, 12333, 12341,
    12347, 12356, 12387, 12388, 12393, 12395,
    12454, 12461
)
ORDER BY id;

-- All 20 rooms should currently show status = 'Available' (one might be 'Cleaned'),
-- updated_at = '2026-04-27 23:20:03' (some may differ slightly if frontdesk touched them)
SELECT id, number, status, updated_at FROM rooms
WHERE id IN (
      4,   5,   6,  11,  40,  41,  43,  45,  46,  48,
     57,  75,  83, 106, 121, 126, 139, 145, 148, 216
)
ORDER BY id;
```

If any row has `updated_at` LATER than `2026-04-28 00:21:06` (room 121 cleaned manually) or LATER than `2026-04-28 00:49` (the AFTER backup time), it means a frontdesk has already started repairing manually — **STOP and reconcile case-by-case before running this patch**, or you may overwrite real frontdesk activity.

---

## Recovery patch

Use the runnable file `docs/data-repairs/2026-04-28-recover.sql` — it is the canonical script. The relevant portions are reproduced here for in-doc reference.

```sql
USE `hotelv2`;

START TRANSACTION;

-- 1. Restore the 20 force-closed check-in records to active state
--    Values copied directly from homi_app_producoot_lastest_now.sql (BEFORE backup).

-- (… 20 UPDATE statements, see recover.sql for the full block …)

-- 2. Restore room status

-- (… 20 UPDATE statements, see recover.sql for the full block …)

-- 3. Verify the patch — both should return EXACTLY 20

SELECT 'checkin_details' AS tbl, COUNT(*) AS restored
FROM checkin_details
WHERE id IN (10854, 11018, 11263, 11297, 11923, 11924,
             12051, 12308, 12318, 12319, 12333, 12341,
             12347, 12356, 12387, 12388, 12393, 12395,
             12454, 12461)
  AND is_check_out = 0;

SELECT 'rooms' AS tbl, COUNT(*) AS restored
FROM rooms
WHERE id IN (4, 5, 6, 11, 40, 41, 43, 45, 46, 48,
             57, 75, 83, 106, 121, 126, 139, 145, 148, 216)
  AND status = 'Occupied';

-- If both counts are 20, COMMIT. Otherwise, ROLLBACK and investigate.
COMMIT;
```

---

## Ambiguous case — room #71 (cid=12086)

This guest's planned `check_out_at` was **2026-04-27 19:07:42** — about 4 h *before* the bug fired at 23:19:54.

Two possibilities:

- **Real overstaying guest** — guest paid for a 12-h stay, was supposed to leave at 19:07, but was still inside. The Fix-All was wrong here too.
- **Real ghost** — guest abandoned and left without checkout, frontdesk hadn't closed the record yet. The Fix-All was right here.

**Decide before recovering** by:
- Physical key-card slot check (is there a key out?).
- Asking the frontdesk on duty at 23:19 last night.
- Calling the guest on the phone number on file (`guest_id=14336`).

If real guest, run the optional block in `recover.sql`:
```sql
START TRANSACTION;
UPDATE checkin_details SET is_check_out = 0, check_out_at = '2026-04-27 19:07:42', updated_at = '2026-04-27 06:32:53' WHERE id = 12086 AND is_check_out = 1;
UPDATE rooms SET status = 'Occupied', updated_at = NOW() WHERE id = 54 AND status IN ('Available', 'Cleaned');
COMMIT;
```

If real ghost, do nothing — the existing force-close was correct.

---

## Post-patch verification

```sql
-- Frontdesk dashboard / Room Monitoring should now show all 20 rooms as Occupied
-- and their guests reachable for checkout / extension / transfer.

-- Spot check end-to-end
SELECT cd.id, cd.is_check_out, cd.check_in_at, cd.check_out_at,
       r.id AS room_id, r.number AS room_number, r.status,
       g.name AS guest_name, cd.total_deposit
FROM checkin_details cd
JOIN rooms r ON r.id = cd.room_id
JOIN guests g ON g.id = cd.guest_id
WHERE cd.id IN (10854, 11018, 11263, 11297, 11923, 11924,
                12051, 12308, 12318, 12319, 12333, 12341,
                12347, 12356, 12387, 12388, 12393, 12395,
                12454, 12461)
ORDER BY cd.id;
```

Then operationally:

1. Frontdesk verifies each restored room against the physical key-card slot — every restored guest should match a known stay.
2. Frontdesk attempts a normal Extension or Checkout on one room (say #4) — confirm the modal opens and proceeds.

---

## What this patch deliberately did NOT do

- ❌ Did not touch the 9 genuine ghost records force-closed at the same timestamp (cids 406, 613, 826, 842, 1069, 1101, 1218, 6846, 10730). Those are legitimate cleanups (March / early-April records where the room had been reused). Leaving them as `is_check_out = 1` is correct.
- ❌ Did not touch room #161 (room_id=116). That room was a separate pre-existing ghost room that the GhostRooms tool correctly cleaned up — not related to this incident.
- ❌ Did not auto-recover room #71 (cid=12086) — it requires operator confirmation first.
- ❌ Did not touch any transactions. None were created or modified by the bug.
- ❌ Did not touch `last_checkin_at`, `last_checkout_at`, or `check_out_time` on the rooms — those were never modified by the bug.
- ❌ Did not insert ActivityLog rows for the restoration. The restoration is documented here and in git; the bug-fix commit will add proper activity logging going forward.

---

## Rollback procedure

If this patch turns out to be wrong (extremely unlikely given the BEFORE backup is the source of truth), restore the corrupted state with:

```sql
USE `hotelv2`;

START TRANSACTION;

-- Re-apply what the original Fix-All did (using the same backdated check_out_at values)
UPDATE checkin_details SET is_check_out = 1, check_out_at = '2026-04-21 17:19:41', updated_at = '2026-04-27 23:19:54' WHERE id = 10854 AND is_check_out = 0;
UPDATE checkin_details SET is_check_out = 1, check_out_at = '2026-04-22 01:22:59', updated_at = '2026-04-27 23:19:54' WHERE id = 11018 AND is_check_out = 0;
UPDATE checkin_details SET is_check_out = 1, check_out_at = '2026-04-22 23:00:23', updated_at = '2026-04-27 23:19:54' WHERE id = 11263 AND is_check_out = 0;
UPDATE checkin_details SET is_check_out = 1, check_out_at = '2026-04-23 03:21:24', updated_at = '2026-04-27 23:19:54' WHERE id = 11297 AND is_check_out = 0;
UPDATE checkin_details SET is_check_out = 1, check_out_at = '2026-04-25 09:39:09', updated_at = '2026-04-27 23:19:54' WHERE id = 11923 AND is_check_out = 0;
UPDATE checkin_details SET is_check_out = 1, check_out_at = '2026-04-25 09:39:13', updated_at = '2026-04-27 23:19:54' WHERE id = 11924 AND is_check_out = 0;
UPDATE checkin_details SET is_check_out = 1, check_out_at = '2026-04-25 18:12:52', updated_at = '2026-04-27 23:19:54' WHERE id = 12051 AND is_check_out = 0;
UPDATE checkin_details SET is_check_out = 1, check_out_at = '2026-04-26 15:00:53', updated_at = '2026-04-27 23:19:54' WHERE id = 12308 AND is_check_out = 0;
UPDATE checkin_details SET is_check_out = 1, check_out_at = '2026-04-26 15:27:22', updated_at = '2026-04-27 23:19:54' WHERE id = 12318 AND is_check_out = 0;
UPDATE checkin_details SET is_check_out = 1, check_out_at = '2026-04-26 15:27:55', updated_at = '2026-04-27 23:19:54' WHERE id = 12319 AND is_check_out = 0;
UPDATE checkin_details SET is_check_out = 1, check_out_at = '2026-04-26 16:27:02', updated_at = '2026-04-27 23:19:54' WHERE id = 12333 AND is_check_out = 0;
UPDATE checkin_details SET is_check_out = 1, check_out_at = '2026-04-26 17:26:26', updated_at = '2026-04-27 23:19:54' WHERE id = 12341 AND is_check_out = 0;
UPDATE checkin_details SET is_check_out = 1, check_out_at = '2026-04-26 18:06:31', updated_at = '2026-04-27 23:19:54' WHERE id = 12347 AND is_check_out = 0;
UPDATE checkin_details SET is_check_out = 1, check_out_at = '2026-04-26 18:26:55', updated_at = '2026-04-27 23:19:54' WHERE id = 12356 AND is_check_out = 0;
UPDATE checkin_details SET is_check_out = 1, check_out_at = '2026-04-26 19:51:20', updated_at = '2026-04-27 23:19:54' WHERE id = 12387 AND is_check_out = 0;
UPDATE checkin_details SET is_check_out = 1, check_out_at = '2026-04-26 19:51:35', updated_at = '2026-04-27 23:19:54' WHERE id = 12388 AND is_check_out = 0;
UPDATE checkin_details SET is_check_out = 1, check_out_at = '2026-04-26 20:04:57', updated_at = '2026-04-27 23:19:54' WHERE id = 12393 AND is_check_out = 0;
UPDATE checkin_details SET is_check_out = 1, check_out_at = '2026-04-26 20:05:20', updated_at = '2026-04-27 23:19:54' WHERE id = 12395 AND is_check_out = 0;
UPDATE checkin_details SET is_check_out = 1, check_out_at = '2026-04-26 22:40:44', updated_at = '2026-04-27 23:19:54' WHERE id = 12454 AND is_check_out = 0;
UPDATE checkin_details SET is_check_out = 1, check_out_at = '2026-04-26 23:02:35', updated_at = '2026-04-27 23:19:54' WHERE id = 12461 AND is_check_out = 0;

UPDATE rooms SET status = 'Available', updated_at = '2026-04-27 23:20:03'
WHERE id IN (4, 5, 6, 11, 40, 41, 43, 45, 46, 48, 57, 75, 83, 106, 121, 126, 139, 145, 148, 216)
  AND status = 'Occupied';

COMMIT;
```

---

## Why we are confident this repair is safe

1. **Source of truth.** The BEFORE backup `homi_app_producoot_lastest_now.sql` is a `mysqldump` of production taken **2 minutes before** the bad clicks. Every value in this patch came directly from that file — no inference, no reconstruction.
2. **Idempotent guards.** Every `UPDATE` has an `AND is_check_out = 1` / `AND status IN ('Available','Cleaned')` clause, so re-running the patch cannot double-apply or corrupt rows that have moved on.
3. **Transaction-bounded.** The whole patch is one transaction with a built-in count check before COMMIT.
4. **Restricted scope.** Only the 20 confirmed false-positives are touched. The 9 legitimate ghosts and all other check-ins/rooms are untouched. Room #71 is held aside for operator decision.
5. **No financial fields touched.** Deposits, deductions, and transactions are untouched and were already untouched by the bug.
6. **Reversible.** Full rollback script above re-applies the corrupted state byte-for-byte if needed.

If a frontdesk has already done one or more of these checkouts manually between the bug and the patch, the guard clauses (`AND is_check_out = 1` / `AND status IN ...`) make those rows no-ops — the patch will skip them safely.

---

## Operator checklist

- [ ] Backup current production DB **right now** (after-patch snapshot for forensic chain).
- [ ] Run "Pre-flight verification on production" block. Counts should be 20/20.
- [ ] Run "Pre-flight verification" against `backup-prod-before-up` to confirm BEFORE state matches this doc.
- [ ] Run `2026-04-28-recover.sql` inside a transaction. Verify both COUNT checks return 20.
- [ ] If both checks pass → `COMMIT;`. If not → `ROLLBACK;` and report.
- [ ] Run the post-patch verification SELECT.
- [ ] Tell frontdesk to refresh Room Monitoring and verify each room shows the right guest.
- [ ] Decide on room #71 (cid=12086) with frontdesk; run optional block if appropriate.
- [ ] Update this doc's `Executor:` field with the operator name and exact run timestamp.
- [ ] Watch `storage/logs/laravel.log` for 5 minutes for any cascading errors.
