# INCIDENT REPORT: Fix-All Unresolved Force-Close of 20 Active Guests

| Field | Value |
|---|---|
| **Incident ID** | 2026-04-28-001 |
| **Severity** | High (operational impact, no financial loss) |
| **Started** | 2026-04-27 23:19:54 (Asia/Manila) |
| **Detected** | 2026-04-28 00:21 (~62 min after start) |
| **Recovery committed** | 2026-04-28 05:30 (~6 h after start) |
| **Fully resolved** | 2026-04-28 06:06 (final verification) |
| **Duration of impact** | 6 h 11 min |
| **Affected scope** | 21 of ~230 rooms; ~10% of inventory (20 actively-staying + 1 overstay/Room #71 — see addendum) |
| **Financial loss** | **₱0.00** (verified) |
| **Data loss** | **None** (verified — every value recoverable from pre-deploy backup) |
| **Filed** | 2026-04-28 |

---

## TABLE OF CONTENTS

1. [Executive Summary](#1-executive-summary)
2. [Timeline](#2-timeline)
3. [The Bug](#3-the-bug)
4. [Detection](#4-detection)
5. [Investigation Phase](#5-investigation-phase)
6. [Recovery Phase](#6-recovery-phase)
7. [Verification & Damage Assessment](#7-verification--damage-assessment)
8. [Operational Impact During Outage](#8-operational-impact-during-outage)
9. [Code Changes](#9-code-changes)
10. [Lessons Learned](#10-lessons-learned)
11. [Re-enabling Procedure](#11-re-enabling-procedure)
12. [Reference Material](#12-reference-material)

---

## 1. Executive Summary

On **2026-04-27 at 11:19:54 PM Manila time**, an admin user clicked the **"Fix All Records"** button on `/admin/unresolved-check-ins` immediately after deploying the `feature/temp-disable-supervisor` branch to production. The button was advertised as a routine cleanup tool for stuck check-in records, with a help block on the page stating:

> *"Effects on reports: are accurate · Deposits marked as forfeited/resolved · Room Status will be reset · New check-ins not affected."*

The button instead force-closed **30 check-in records**. Of those:
- **9** were genuine ghost records — correctly cleaned (no action needed)
- **20** were **active paying guests** with future check-out dates — incorrectly closed (recovered)
- **1** was ambiguous (Room #71) — handled separately

**Nine seconds later**, the same admin clicked **"Fix All"** on `/admin/ghost-rooms`, which then flipped 22 rooms (the 20 affected + 2 collateral) from `Occupied` to `Available`/`Cleaned`.

**Zero financial damage occurred.** Every guest deposit (₱15,598 total), every transaction, every shift log, every activity log was preserved byte-for-byte. The bug was confined to two columns on `checkin_details` (`is_check_out`, `check_out_at`) and one column on `rooms` (`status`).

**Recovery was complete by 5:30 AM** using a `mysqldump` backup taken at 23:17:30 — exactly 2 minutes 24 seconds before the bug fired. By 6:06 AM, all 20 guests were verified restored, with 4 already legitimately checked out by frontdesk through normal flow (proof the recovery worked).

---

## 2. Timeline

| Time (Asia/Manila) | Event |
|---|---|
| **2026-04-27 23:17:30** | DB backup taken (`homi_app_producoot_lastest_now.sql`, 27.3 MB) before deploying `feature/temp-disable-supervisor`. **This backup became the source of truth for recovery.** |
| ~23:18 | Deploy completed. Branch active in production. |
| **23:19:54** | Admin clicked "Fix All Records" on `/admin/unresolved-check-ins`. `fixAllGhostRecords()` ran. 30 `checkin_details` rows updated: `is_check_out: 0→1`, `check_out_at` overwritten with `check_in_at + number_of_hours + 30 min` (a fabricated date). All 30 rows received `updated_at = '2026-04-27 23:19:54'`. |
| **23:20:03** | Admin clicked "Fix All" on `/admin/ghost-rooms`. `fixAllRooms()` ran. 22 rooms updated: `status: Occupied → Available` (20 rooms) or `Occupied → Cleaned` (2 rooms — already in cleaning queue). All received `updated_at = '2026-04-27 23:20:03'`. |
| 23:20 → 00:21 | **Frontdesk began encountering the broken state.** Walk-in guests checked into rooms that "looked Available." Each conflict was resolved by transferring the new guest to a truly-empty room. 12 such transfers logged in `activity_logs`. |
| **2026-04-28 00:21** | Frontdesk discovered the discrepancy after manually inspecting key card slots. Reported to engineering. |
| **00:49** | Forensic backup taken (`homi_prod_28_2026.sql`, 29.4 MB). |
| 00:50 → 02:00 | Investigation: diff of BEFORE vs AFTER backups. Affected scope identified (initially 10 rooms, later corrected to 20 + 1 ambiguous). Bug root cause traced to `app/Http/Livewire/Admin/UnresolvedCheckIns.php:45-47` and `:96-98`. |
| 02:00 → 05:00 | Documentation drafted (bug report, code review, repair runbook, recovery SQL). |
| **01:43** | Pre-recovery snapshot (`homi_app_production.sql`, 29.4 MB). |
| **05:20** | Most recent pre-recovery snapshot (`homi_app_6.sql`, 29.5 MB) — used as the operational baseline for the recovery transaction. |
| **05:30:00** | `START TRANSACTION` on production via TablePlus. |
| **05:30:15** | UPDATE on `checkin_details` — 20 rows affected (single CASE WHEN statement). |
| **05:30:25** | UPDATE on `rooms` — 20 rows affected. |
| **05:30:28** | Verification SELECT → both counts = 20. `COMMIT;` executed. **Recovery permanent.** |
| **05:30:28** | First post-recovery activity: frontdesk performed Extension on Room #286 (Anjeannette lao), ₱400 added — proof system was working normally on restored guests. |
| **05:30 → 06:06** | 4 of the 20 restored guests legitimately checked out via normal frontdesk flow (Rooms #5, #52, #60, #62). Status went `Occupied → Cleaning/Uncleaned`. |
| **05:35** | Post-recovery backup (`homi_app_afet_udapted_and_latest.sql`, 29.5 MB). |
| **06:06** | Final verification: 20/20 rows accounted for, 16 still active, 4 legitimately checked out. All financial fields intact. |
| **~06:30** | Hotfix commits pushed: button disabled, sidebar hidden, action gated by feature flag. |

---

## 3. The Bug

### 3.1 Location

| Component | File | Lines |
|---|---|---|
| Detection query | `app/Http/Livewire/Admin/UnresolvedCheckIns.php` | 45-47 |
| Action query | `app/Http/Livewire/Admin/UnresolvedCheckIns.php` | 96-98 |
| Sidebar count query (mirror) | `resources/views/components/admin-layout.blade.php` | 374-376 (mobile), 931-933 (desktop) |

All four locations used identical SQL:

```php
CheckinDetail::where('is_check_out', 0)
    ->whereRaw('DATE_ADD(check_in_at, INTERVAL number_of_hours HOUR) < ?', [$cutoff1day])
```

### 3.2 The wrong column

The query computes "expected check-out time" as `check_in_at + number_of_hours`. But for any guest who has extended their stay, `number_of_hours` is **0** (because each extension resets the per-period hours counter). The authoritative "when this guest is supposed to leave" lives in the `check_out_at` column — which the query never reads.

| Column on `checkin_details` | Meaning | Quirk |
|---|---|---|
| `hours_stayed` | Original duration at check-in (e.g., 24) | Never updated |
| `number_of_hours` | Hours of the **current** rate period | **Becomes 0 after extension** |
| `check_out_at` | Real planned checkout (updated atomically by every extension) | **Authoritative source of truth** |

### 3.3 Mathematical demonstration of the bug

For Equicom (cid=10854, Room #51) — long-stay guest with the largest deposit:
- `check_in_at` = `2026-04-21 16:49:41`
- `number_of_hours` = `0`
- `check_out_at` = `2026-04-28 16:49:41` (a week later, real)

Buggy query:
```
expected_out = check_in_at + number_of_hours hours
             = 2026-04-21 16:49:41 + 0 hours
             = 2026-04-21 16:49:41
cutoff       = now - 1 day = 2026-04-26 23:19:54

is expected_out < cutoff?  TRUE  → flagged as ghost
```

But she was a real guest who had paid ₱3,425 in deposits over 7 days, with checkout still 17 hours in the future.

The same false-positive pattern hit 19 other guests, all with `number_of_hours = 0`.

### 3.4 The action then made it worse

```php
$expectedOut = Carbon::parse($record->check_in_at)->addHours($record->number_of_hours);
$checkoutTime = $expectedOut->copy()->addMinutes(30);

$record->update([
    'is_check_out' => true,
    'check_out_at' => $checkoutTime,   // ← OVERWRITES the real planned checkout
]);
```

Equicom's `check_out_at` went from `2026-04-28 16:49:41` (real, future) to `2026-04-21 17:19:41` (fake, in the past, 30 min after check-in). The real value was destroyed from the database and would have been unrecoverable without the BEFORE backup.

### 3.5 The cascade — the second click

After 30 records were force-closed, 22 rooms had no remaining active `checkin_details` row but still showed `status = 'Occupied'`. This is exactly the heuristic used by `app/Http/Livewire/Admin/GhostRooms.php` `fixAllRooms()`:

```php
$occupiedRoomIds = CheckinDetail::where('is_check_out', false)->pluck('room_id');
return Room::where('status', 'Occupied')->whereNotIn('id', $occupiedRoomIds);
```

The second click correctly flagged those 22 rooms given its inputs (the upstream bug had already corrupted them) and flipped them to `Available`. From the operator's view it looked like a normal cleanup of "ghost rooms."

---

## 4. Detection

### 4.1 How frontdesk found out

Frontdesk noticed the discrepancy at ~00:21 AM after a series of confusing events:

1. Walk-in guest arrived. Frontdesk searched for empty rooms on the dashboard.
2. Room #4 (and others) showed `Available` on the system.
3. Frontdesk attempted check-in into Room #4 via the system.
4. The **physical key card slot** for Room #4 still had a key out — meaning a real guest was inside.
5. Frontdesk realized the dashboard was lying.

The only signal that caught it was the **physical key card slot vs. system status mismatch**. There was no error message, no system alert, no internal consistency check that fired.

### 4.2 Initial scope (incorrect)

When the issue was first reported, frontdesk identified rooms based on guest complaints and physical inspection. The initial report mentioned 10 rooms (#4, #11, #51, #63, #74, #92, #151, #166, #205, #215). This became the working list for the first 4 hours of investigation.

### 4.3 Corrected scope

A complete BEFORE-vs-AFTER `mysqldump` diff at ~01:30 AM revealed the full scope:

- **30 `checkin_details` records** had `updated_at = '2026-04-27 23:19:54'` (the bug's signature timestamp)
- Of those 30, **20 had `check_out_at` in the future** at the moment the bug fired (false positives)
- **9 had `check_out_at` in the past** (true ghosts — the action correctly closed these)
- **1 was ambiguous** (cid=12086, Room #71) — `check_out_at` was 4 h before the bug, could have been overstay or real ghost

The full list:

| cid | room_id | room # | guest | deposit | check_out_at (BEFORE) |
|----:|----:|----:|---|---:|---|
| 10854 | 40 | #51 | Equicom | ₱3,425 | 2026-04-28 16:49:41 |
| 11018 | 216 | #286 | Anjeannette lao | ₱246 | 2026-04-28 00:52:59 |
| 11263 | 106 | #151 | Jilyana dante | ₱1,323 | 2026-04-28 10:30:23 |
| 11297 | 148 | #215 | Neil galon | ₱4,254 | 2026-04-28 14:51:24 |
| 11923 | 46 | #63 | Ailyn cauilan | ₱200 | 2026-04-28 09:09:09 |
| 11924 | 57 | #74 | Ailyn cauilan | ₱200 | 2026-04-28 09:09:13 |
| 12051 | 4 | #4 | Merlin utbo | ₱200 | 2026-04-28 17:42:52 |
| 12308 | 139 | #205 | Francis | ₱200 | 2026-04-28 14:30:53 |
| 12318 | 41 | #52 | Lawrence quinco | ₱200 | 2026-04-28 14:57:22 |
| 12319 | 6 | #6 | Jonathan ancla | ₱200 | 2026-04-28 14:57:55 |
| 12333 | 5 | #5 | Macabuhay daisyjun | ₱900 | 2026-04-28 03:57:02 |
| 12341 | 48 | #65 | Jayve cagaanan | ₱200 | 2026-04-28 16:56:26 |
| 12347 | 45 | #62 | Jesielyn Melo | ₱200 | 2026-04-28 05:36:31 |
| 12356 | 75 | #92 | Andy santos | ₱200 | 2026-04-28 17:56:55 |
| 12387 | 83 | #100 | Ashrea Esmael | ₱600 | 2026-04-28 07:21:20 |
| 12388 | 121 | #166 | Norfia Dukan | ₱200 | 2026-04-28 07:21:35 |
| 12393 | 43 | #60 | Almuhamdis Muslimin | ₱200 | 2026-04-28 07:34:57 |
| 12395 | 126 | #171 | Juliana kylie paler | ₱1,400 | 2026-04-28 19:35:20 |
| 12454 | 11 | #11 | Chleo | ₱650 | 2026-04-28 10:10:44 |
| 12461 | 145 | #211 | Elva | ₱600 | 2026-04-28 10:32:35 |
| **TOTAL** | | | | **₱15,598** | |

---

## 5. Investigation Phase

### 5.1 Backup chain established

To safely investigate without risk to production, three `mysqldump` files were referenced:

| File | Captured | Size | Purpose |
|---|---|---:|---|
| `homi_app_producoot_lastest_now.sql` | 2026-04-27 23:17:30 | 27.3 MB | **BEFORE state** — source of truth |
| `homi_prod_28_2026.sql` | 2026-04-28 00:49 | 29.4 MB | **AFTER bug** — forensic snapshot |
| `homi_app_production.sql` | 2026-04-28 01:43 | 29.4 MB | **NOW snapshot** — chain-of-custody |
| `homi_app_6.sql` | 2026-04-28 05:20 | 29.5 MB | **PRE-RECOVERY** baseline |
| `homi_app_afet_udapted_and_latest.sql` | 2026-04-28 05:35 | 29.5 MB | **POST-RECOVERY** verification |

Total: 5 forensic snapshots covering every state transition.

### 5.2 Bug confirmation queries

Run against `homi_prod_28_2026.sql` (AFTER backup) to confirm scope:

```sql
-- All checkin_details records touched by the bulk update
SELECT id, guest_id, room_id, check_in_at, check_out_at, is_check_out, updated_at
FROM checkin_details
WHERE updated_at = '2026-04-27 23:19:54';
-- Result: 30 rows
```

```sql
-- All rooms touched by the second bulk update
SELECT id, number, status, updated_at
FROM rooms
WHERE updated_at = '2026-04-27 23:20:03'
   OR (status IN ('Available', 'Cleaned') AND id IN (SELECT room_id FROM checkin_details WHERE updated_at = '2026-04-27 23:19:54'));
-- Result: 22 rooms
```

```sql
-- Distinguish false positives from true ghosts
SELECT cd.id AS cid, cd.room_id, r.number AS room,
       g.name AS guest_name,
       cd.check_in_at, cd.check_out_at,
       CASE
         WHEN cd.check_out_at > '2026-04-27 23:19:54' THEN 'FALSE POSITIVE — REAL GUEST'
         WHEN cd.check_out_at < '2026-04-27 23:00:00' THEN 'TRUE GHOST — leave closed'
         ELSE 'AMBIGUOUS — verify with frontdesk'
       END AS classification
FROM checkin_details cd
JOIN rooms r ON r.id = cd.room_id
JOIN guests g ON g.id = cd.guest_id
WHERE cd.updated_at = '2026-04-27 23:19:54';
-- Result: 20 false positives + 9 true ghosts + 1 ambiguous
```

NOTE: The above must be run against the BEFORE backup, because in the AFTER backup `check_out_at` has already been overwritten by the bug. The real classification can only be determined from the pre-bug values.

### 5.3 Financial integrity check (no damage)

Critical to verify before recovery — to be sure no money was lost:

```sql
-- Total deposits and deductions across the 20 affected check-ins
-- Should be IDENTICAL in BEFORE and AFTER backups
SELECT SUM(total_deposit) AS sum_deposits,
       SUM(total_deduction) AS sum_deductions,
       COUNT(*) AS n_records
FROM checkin_details
WHERE id IN (10854, 11018, 11263, 11297, 11923, 11924,
             12051, 12308, 12318, 12319, 12333, 12341,
             12347, 12356, 12387, 12388, 12393, 12395,
             12454, 12461);
```

Both backups returned identical results:
- `sum_deposits` = ₱15,598
- `sum_deductions` = ₱11,194
- `n_records` = 20

```sql
-- Confirm no transactions inserted at the bug's timestamp
SELECT COUNT(*) FROM transactions
WHERE created_at >= '2026-04-27 23:19:54'
  AND created_at < '2026-04-27 23:20:30';
-- Result: 0
```

```sql
-- Confirm no transactions modified at the bug's timestamp
SELECT COUNT(*) FROM transactions
WHERE updated_at >= '2026-04-27 23:19:54'
  AND updated_at < '2026-04-27 23:20:30';
-- Result: 0
```

```sql
-- Confirm no transactions voided
SELECT COUNT(*) FROM transactions
WHERE voided_at IS NOT NULL;
-- Result: 0 (no transactions in the entire database have voided_at set)
```

```sql
-- Total transaction count comparison across snapshots
-- BEFORE: 34857 transactions
-- AFTER:  34917 transactions (+60 — normal frontdesk activity in the gap)
-- POST:   34918 transactions (+1 — Anjeannette lao extension after recovery)
```

Conclusion: **zero financial impact** from the bug or the recovery.

### 5.4 Scenario C check (most important pre-recovery verification)

Before running the recovery, we had to ensure no NEW guest was checked into any of the 20 affected rooms during the gap. If a new guest was active, restoring the original guest's record would create two active `checkin_details` rows for the same room — a real conflict.

```sql
-- Any active check-in (is_check_out=0) on any of the 20 affected rooms?
SELECT cd.id AS cid, cd.room_id, r.number AS room_num,
       cd.check_in_at, cd.is_check_out, r.status
FROM checkin_details cd
JOIN rooms r ON r.id = cd.room_id
WHERE cd.room_id IN (4, 5, 6, 11, 40, 41, 43, 45, 46, 48,
                     57, 75, 83, 106, 121, 126, 139, 145, 148, 216)
  AND cd.is_check_out = 0;
-- Result: 0 rows (= NO scenario C, safe to recover)
```

This was the green light for recovery.

### 5.5 Operational drift during the gap

Between 23:20 and 05:20, frontdesk performed 12 Room Transfers from the affected rooms (logged in `activity_logs`):

```sql
SELECT id, activity, description, created_at FROM activity_logs
WHERE description LIKE '%Room #4%' OR description LIKE '%Room #5%' OR /* etc. */
  AND created_at BETWEEN '2026-04-27 23:20:00' AND '2026-04-28 05:30:00'
  AND activity = 'Room Transfer';
```

Result: 12 transfer events. Each was a different walk-in guest temporarily checked into a "looks-empty" affected room, then transferred out to a truly-empty room. By the time we ran Scenario C, none of those temporary check-ins had `is_check_out = 0` on any of our 20 room_ids — they had all been transferred to other rooms.

This explains why our 20 affected rooms drifted from `Available` → `Cleaned` for 8 of them (the transfer-out flow correctly sets the source room to `Cleaned`).

---

## 6. Recovery Phase

### 6.1 Strategy

Direct SQL UPDATE inside a single transaction with idempotent guards. Recovery values copied byte-for-byte from the BEFORE backup. No code deployment required — the bug was data-only.

### 6.2 Pre-flight verification queries

```sql
-- A. Verify the 20 check-ins are still in the corrupted state (expected: 20 rows, all is_check_out=1)
SELECT id, room_id, check_in_at, check_out_at, is_check_out, updated_at
FROM checkin_details
WHERE id IN (10854, 11018, 11263, 11297, 11923, 11924,
             12051, 12308, 12318, 12319, 12333, 12341,
             12347, 12356, 12387, 12388, 12393, 12395,
             12454, 12461)
ORDER BY id;
```

Result confirmed: 20 rows, all `is_check_out=1`, all `updated_at='2026-04-27 23:19:54'`.

```sql
-- B. Verify the 20 rooms are still in Available/Cleaned state
SELECT id, number, status, updated_at FROM rooms
WHERE id IN (4, 5, 6, 11, 40, 41, 43, 45, 46, 48,
             57, 75, 83, 106, 121, 126, 139, 145, 148, 216)
ORDER BY id;
```

Result: 12 rows `Available`, 8 rows `Cleaned`. **Zero rows `Occupied` (no scenario C).**

### 6.3 Recovery transaction

The full recovery executed atomically inside one transaction:

#### Step 1 — Open transaction

```sql
START TRANSACTION;
```

Result: `Query OK`

#### Step 2 — Restore all 20 check-ins (single CASE WHEN UPDATE)

```sql
UPDATE checkin_details
SET is_check_out = 0,
    check_out_at = CASE id
        WHEN 10854 THEN '2026-04-28 16:49:41'
        WHEN 11018 THEN '2026-04-28 00:52:59'
        WHEN 11263 THEN '2026-04-28 10:30:23'
        WHEN 11297 THEN '2026-04-28 14:51:24'
        WHEN 11923 THEN '2026-04-28 09:09:09'
        WHEN 11924 THEN '2026-04-28 09:09:13'
        WHEN 12051 THEN '2026-04-28 17:42:52'
        WHEN 12308 THEN '2026-04-28 14:30:53'
        WHEN 12318 THEN '2026-04-28 14:57:22'
        WHEN 12319 THEN '2026-04-28 14:57:55'
        WHEN 12333 THEN '2026-04-28 03:57:02'
        WHEN 12341 THEN '2026-04-28 16:56:26'
        WHEN 12347 THEN '2026-04-28 05:36:31'
        WHEN 12356 THEN '2026-04-28 17:56:55'
        WHEN 12387 THEN '2026-04-28 07:21:20'
        WHEN 12388 THEN '2026-04-28 07:21:35'
        WHEN 12393 THEN '2026-04-28 07:34:57'
        WHEN 12395 THEN '2026-04-28 19:35:20'
        WHEN 12454 THEN '2026-04-28 10:10:44'
        WHEN 12461 THEN '2026-04-28 10:32:35'
    END,
    updated_at = CASE id
        WHEN 10854 THEN '2026-04-27 17:47:03'
        WHEN 11018 THEN '2026-04-27 12:17:24'
        WHEN 11263 THEN '2026-04-27 21:05:42'
        WHEN 11297 THEN '2026-04-27 21:16:05'
        WHEN 11923 THEN '2026-04-27 08:14:45'
        WHEN 11924 THEN '2026-04-27 08:13:44'
        WHEN 12051 THEN '2026-04-27 17:10:58'
        WHEN 12308 THEN '2026-04-27 08:47:42'
        WHEN 12318 THEN '2026-04-26 14:57:22'
        WHEN 12319 THEN '2026-04-27 11:27:35'
        WHEN 12333 THEN '2026-04-27 15:17:48'
        WHEN 12341 THEN '2026-04-27 16:29:04'
        WHEN 12347 THEN '2026-04-27 17:10:46'
        WHEN 12356 THEN '2026-04-26 17:56:55'
        WHEN 12387 THEN '2026-04-27 18:48:45'
        WHEN 12388 THEN '2026-04-27 17:58:02'
        WHEN 12393 THEN '2026-04-27 17:37:07'
        WHEN 12395 THEN '2026-04-27 18:59:01'
        WHEN 12454 THEN '2026-04-27 22:28:25'
        WHEN 12461 THEN '2026-04-27 21:58:40'
    END
WHERE id IN (10854, 11018, 11263, 11297, 11923, 11924,
             12051, 12308, 12318, 12319, 12333, 12341,
             12347, 12356, 12387, 12388, 12393, 12395,
             12454, 12461)
  AND is_check_out = 1;
```

Result: **20 rows affected**.

Every value copied byte-for-byte from the BEFORE backup. The `AND is_check_out = 1` guard makes the statement idempotent (re-running it would update 0 rows).

#### Step 3 — Restore all 20 rooms

```sql
UPDATE rooms
SET status = 'Occupied', updated_at = NOW()
WHERE id IN (4, 5, 6, 11, 40, 41, 43, 45, 46, 48,
             57, 75, 83, 106, 121, 126, 139, 145, 148, 216)
  AND status IN ('Available', 'Cleaned');
```

Result: **20 rows affected**.

The `IN ('Available', 'Cleaned')` guard handles the 8 rooms that admins/roomboys had manually flipped to `Cleaned` during the night as a workaround attempt.

#### Step 4 — Verification before commit

```sql
SELECT
    (SELECT COUNT(*) FROM checkin_details
     WHERE id IN (10854, 11018, 11263, 11297, 11923, 11924,
                  12051, 12308, 12318, 12319, 12333, 12341,
                  12347, 12356, 12387, 12388, 12393, 12395,
                  12454, 12461)
       AND is_check_out = 0) AS checkins_restored,
    (SELECT COUNT(*) FROM rooms
     WHERE id IN (4, 5, 6, 11, 40, 41, 43, 45, 46, 48,
                  57, 75, 83, 106, 121, 126, 139, 145, 148, 216)
       AND status = 'Occupied') AS rooms_restored;
```

Result: `checkins_restored = 20`, `rooms_restored = 20`.

#### Step 5 — Commit

```sql
COMMIT;
```

Result: `Query OK`. Recovery permanent.

### 6.4 The ambiguous case — Room #71 (cid=12086)

The 21st affected record was a guest with `check_out_at = '2026-04-27 19:07:42'` — exactly 4 h 12 min before the bug fired. Two interpretations:

- **Real overstay**: guest paid for a 12-hour stay, was supposed to leave at 19:07, was still inside (frontdesk would normally process an overstay charge or extension)
- **Real ghost**: guest abandoned the stay around 19:07 without checkout; the original record was a legitimate ghost

Without a physical room inspection during the bug window, this case is unresolvable from the data alone. The recovery script left this record alone (kept `is_check_out = 1`). If frontdesk later determines it was a real guest, the optional recovery block in `docs/data-repairs/2026-04-28-recover.sql` can be run.

---

## 7. Verification & Damage Assessment

### 7.1 Post-recovery verification query

```sql
SELECT r.number AS 'Room #',
       r.status AS 'Room Status',
       g.name AS 'Guest',
       cd.id AS 'Check-in ID',
       cd.is_check_out AS 'Active? (0=yes,1=no)',
       cd.check_in_at AS 'Checked In',
       cd.check_out_at AS 'Will Check Out',
       cd.total_deposit AS 'Deposit',
       cd.total_deduction AS 'Deduction'
FROM checkin_details cd
JOIN rooms r ON r.id = cd.room_id
JOIN guests g ON g.id = cd.guest_id
WHERE cd.id IN (10854, 11018, 11263, 11297, 11923, 11924,
                12051, 12308, 12318, 12319, 12333, 12341,
                12347, 12356, 12387, 12388, 12393, 12395,
                12454, 12461)
ORDER BY CAST(r.number AS UNSIGNED), r.number;
```

Result at 06:06 AM (36 minutes after commit):
- 16 of 20 rows showed `Active=0` and `status=Occupied` — guests still inside
- 4 of 20 rows showed `Active=1` and `status=Cleaning/Uncleaned` — guests had legitimately checked out via normal frontdesk flow (Rooms #5, #52, #60, #62)
- All 20 deposit and deduction values matched the BEFORE backup

The 4 normal checkouts post-recovery are **proof the recovery worked** — frontdesk could see those guests on the room board, click them, and process check-out through the normal modal.

### 7.2 BEFORE vs POST-RECOVERY field-by-field comparison

For 19 of 20 records, every field matched byte-for-byte. The single difference was for cid=11018 (Room #286):

| Field | BEFORE | POST-RECOVERY | Reason |
|---|---|---|---|
| `is_check_out` | 0 | 0 | match |
| `check_in_at` | `2026-04-22 00:52:59` | `2026-04-22 00:52:59` | match |
| `check_out_at` | `2026-04-28 00:52:59` | **`2026-04-28 12:52:59`** | +12h Extension by frontdesk at 05:30:28 (after our COMMIT) |
| `updated_at` | `2026-04-27 12:17:24` | **`2026-04-28 05:30:28`** | reflects the Extension transaction |
| `total_deposit` | 246 | 246 | match |
| `total_deduction` | 46 | 46 | match |

The Extension on Room #286 was logged in `activity_logs`:
```
(35976, 1, 9, 'Add Extension', 'Added new extension of ₱400 for guest Anjeannette lao', '2026-04-28 05:30:28', '2026-04-28 05:30:28'),
(35977, 1, 9, 'Payment', 'Payment of ₱400 for guest Anjeannette lao', '2026-04-28 05:30:56', '2026-04-28 05:30:56');
```

This drift is correct — the recovery brought the guest back, frontdesk extended her, normal flow.

### 7.3 Guest record integrity

```sql
SELECT id, name, contact_no FROM guests
WHERE id IN (12843, 13038, 13373, 13415, 14135, 14136, 14285, 14594, 14597, 14603,
             14623, 14633, 14639, 14647, 14675, 14685, 14687, 14690, 14754, 14768);
```

All 20 guest records compared byte-for-byte between BEFORE and POST-RECOVERY backups. **Zero changes** — names, phone numbers, IDs, all preserved.

### 7.4 Other tables — sanity check

| Table | BEFORE | PRE-REC | POST-REC | Verdict |
|---|---:|---:|---:|---|
| `cash_drawers` | 2 | 2 | 2 | Untouched |
| `shift_logs` | 201 | 201 | 201 | Untouched |
| `activity_logs` | 35,879 | 35,969 | 35,977 | Only grew (normal activity) |
| `guests` | 12,786 | 12,800 | 12,800 | Only grew (14 new check-ins on other rooms) |
| `transactions` | 34,857 | 34,917 | 34,918 | Only grew (60 + 1 normal activity) |

No table lost any rows. No financial table modified by the bug or recovery.

---

## 8. Operational Impact During Outage

### 8.1 Frontdesk workaround attempts

During the 6-hour gap, frontdesk encountered the broken rooms and devised three workaround patterns (visible in `activity_logs` and the room status timeline):

#### Pattern A — Walk-in temporary occupancy + transfer out (12 instances)

When a walk-in guest arrived and was checked into a "looks-empty" affected room, frontdesk immediately transferred them out upon discovering the conflict. Examples:

| Time | Action |
|---|---|
| 23:49:55 | Imelda transferred from Room #286 → Room #281 |
| 00:21:06 | Jaspher tamosa transferred from Room #166 → Room #168 |
| 01:24:14 | Corey onggad transferred from Room #92 → Room #158 |
| 01:40:14 | Cristyll elorde transferred from Room #51 → Room #227 |
| 01:41:28 | Jamil sebastian transferred from Room #4 → Room #277 |
| 01:52:31 | Renelyn transferred from Room #205 → Room #157 |
| 02:12:37 | Jenny franco transferred from Room #166 → Room #161 |
| 02:13:45 | Ehra Nacional transferred from Room #51 → Room #237 |
| 02:16:14 | Ehra transferred from Room #92 → Room #251 |
| 04:24:39 | Romer dellomes transferred from Room #63 → Room #253 |
| 04:36:01 | ALVIN transferred from Room #286 → Room #264 |
| 05:06:09 | Brian florentino transferred from Room #211 → Room #150 |

These transfers are normal hotel operations and are correctly recorded. **The transferred guests are now correctly in their destination rooms** with proper check-in records pointing there. They are separate from our 20 affected guests (different `cid`s).

#### Pattern B — Manual "Cleaned" status flip via admin Edit Room (6+ instances)

Some admins manually edited room status via `/admin/rooms` Edit dropdown, changing `Available` → `Cleaned` as a "reset" attempt. This was a cosmetic flip with no functional effect on the underlying broken state.

Affected by this workaround at the time recovery ran: rooms #4, #51, #92, #166, #205, #286 (and #211 flipped right before recovery). The recovery handles both `Available` and `Cleaned` source states.

#### Pattern C — Physical key card vigilance

Frontdesk relied on the physical key card slot to identify which rooms truly had guests inside. Without this physical signal, the incident could have escalated (e.g., walk-in checked into Room #51 might not have noticed the existing guest until much later).

### 8.2 Why no scenario C occurred

The transfer pattern is the reason scenario C (a new guest left in an affected room) never materialized. Frontdesk caught and corrected each false-empty before any conflict could persist. Credit to frontdesk's vigilance — without it, recovery would have been more complex.

### 8.3 No financial impact during outage

Verified by querying `transactions` for any activity tied to the 20 affected `guest_id`s during the outage window:

```sql
SELECT * FROM transactions
WHERE guest_id IN (12843, 13038, 13373, 13415, 14135, 14136, 14285, 14594, 14597, 14603,
                   14623, 14633, 14639, 14647, 14675, 14685, 14687, 14690, 14754, 14768)
  AND created_at BETWEEN '2026-04-27 23:20:00' AND '2026-04-28 05:30:00';
-- Result: 0 rows
```

Zero transactions were created, modified, or voided for any of the 20 affected guests during the outage window. This means:
- No payments were collected from them
- No deposits were refunded to them
- No checkouts were processed for them
- Their financial state is exactly what it was before the bug

The first transaction post-recovery was the Anjeannette lao Extension at 05:30:28 (38 seconds after `COMMIT`).

---

## 9. Code Changes

Four commits on `feature/temp-disable-supervisor`:

```
62185a4 fix: implement corrected Unresolved Check-Ins logic, gated off by flag
aab749c fix: revert mobile sidebar edit that broke parent Blade comment
382061d fix: blade parse error in user.blade.php top-of-file comment
d2df90a fix: disable buggy Unresolved Check-Ins fix-all + add incident docs
```

### 9.1 Detection query — corrected

`app/Http/Livewire/Admin/UnresolvedCheckIns.php:41-73` — `getGhostRecordsProperty()`:

**Before:**
```php
->whereRaw('DATE_ADD(check_in_at, INTERVAL number_of_hours HOUR) < ?', [$cutoff1day])
```

**After:**
```php
->whereNotNull('check_out_at')
->where('check_out_at', '<', now()->subHours(2))
```

### 9.2 Action — corrected and gated by feature flag

`app/Http/Livewire/Admin/UnresolvedCheckIns.php`:

```php
public bool $fixActionEnabled = false;   // hard guard
```

Both `confirmFix()` and `fixAllGhostRecords()` short-circuit with an "Action Disabled" dialog before reaching the corrected logic. To re-enable, the flag flips to `true`.

The corrected `fixAllGhostRecords()` body:
- Uses the corrected query (`check_out_at < cutoff`)
- **Per-record safety guard**: refuses to close any record whose `check_out_at` is in the future
- **Preserves `check_out_at`** (does not overwrite — preserves audit trail)
- **Writes ActivityLog row per force-close** (incident-recoverable without external backup)
- Wrapped in `DB::transaction` with rollback on any error

### 9.3 UI — disabled state

`resources/views/livewire/admin/unresolved-check-ins.blade.php`:
- Replaced misleading "What happens when fixed?" green panel with a yellow maintenance warning
- Replaced active red button with a disabled gray button
- Read-only ghost records table preserved (admin can see what's flagged, just can't act)

### 9.4 Sidebar — hidden

`resources/views/components/admin-layout.blade.php:924-960`:
- Desktop sidebar "Unresolved" badge commented out
- Admin will not see the red gradient "Unresolved" item until re-enabled

### 9.5 Self-inflicted Blade parse error (and its fix)

Commits `382061d` and `aab749c` resolved two follow-on parse errors:

1. `user.blade.php`: a warning comment about Blade comment markers contained the literal `{{-- --}}` text inside a Blade comment block, causing the inner `--}}` to prematurely close the outer comment. Rewrote the warning to use words instead of literal markers.

2. `admin-layout.blade.php`: the mobile sidebar edit added `{{-- --}}` markers inside a giant Blade comment that wraps the inactive legacy layout (lines 1-528). The added inner markers prematurely closed the parent comment, exposing the inactive HTML block as rendered content and orphaning the parent's closing `--}}` as visible text. Reverted the unnecessary mobile edit (the desktop edit, which is in active code, was kept).

---

## 10. Lessons Learned

### 10.1 Architecture / engineering

| # | Lesson | Action |
|---|---|---|
| 1 | A `mysqldump` backup taken minutes before a deploy is the difference between "recoverable incident" and "data loss disaster." | Always take a fresh `mysqldump` before any production deploy that touches admin-tool code, even small changes. |
| 2 | Detection queries that compute derived fields are fragile. The authoritative column should always be preferred. | Future ghost-detection: use `check_out_at` directly. Never compute `check_in_at + duration`. |
| 3 | Bulk-action UIs need per-row confirmation when the action is irreversible. | Re-enable of Unresolved feature requires per-row checkbox + "I verified this with frontdesk" affirmation. |
| 4 | Server-side action methods should never trust their inputs (the query result). Defense in depth is mandatory. | Per-record safety guard added: refuses to close any record whose `check_out_at` is in the future. |
| 5 | Destructive operations should preserve audit trail, not overwrite it. | The corrected action keeps `check_out_at` intact; only flips `is_check_out`. ActivityLog rows record every force-close. |
| 6 | UI copy that promises safety must reflect current code reality. | "What happens when fixed?" green panel was misleading. Replaced with yellow maintenance warning. |
| 7 | Nested Blade comment markers break the parser silently. | Documented in `docs/operations/blade-comment-conventions.md` (TODO). |

### 10.2 Operational

| # | Lesson | Action |
|---|---|---|
| 1 | Frontdesk's physical key card vigilance was the only signal that caught the issue. | Add a UI consistency check: if `rooms.status = 'Available'` but `latestCheckInDetail.is_check_out = 0`, surface a warning to frontdesk. |
| 2 | Admin manual-fix workarounds (status edits) had no functional effect and risked confusing the recovery. | Lock admin Edit Status dropdown to prevent direct status manipulation; require workflow-based transitions. |
| 3 | The Extension activity 38 seconds after recovery proves "frontdesk knows what to do" given a working system. | Trust the existing modal flows; avoid building shortcut tools without proper safeguards. |

### 10.3 Process

| # | Lesson | Action |
|---|---|---|
| 1 | Slow, methodical staged paste-and-verify is the right approach for production repair. Confirm each step before the next. | Codified in `docs/data-repairs/2026-04-28-recover.sql` (canonical recovery script template). |
| 2 | Idempotent guards on every UPDATE (`AND is_check_out = 1` etc.) make recovery scripts re-runnable without risk. | Future repair scripts must follow this pattern. |
| 3 | Multi-snapshot forensic chain (BEFORE / AFTER / NOW / PRE-REC / POST-REC) gives complete transition visibility. | Standard practice for any production data incident from now on. |

---

## 11. Re-enabling Procedure

The Unresolved Check-Ins feature is architecturally necessary — it is the release valve for rooms blocked by kiosk and roomboy guards. To re-enable when the per-row confirmation UI is built:

### 11.1 Prerequisites (must ship before flip)

- [ ] Per-row confirmation checkbox UI on `/admin/unresolved-check-ins`
  - Each row has a "Confirm Ghost" checkbox, default unchecked
  - Bulk-action button only operates on checked rows
  - Disabled if any checked row's `room.status = 'Occupied'` until supervisor override
- [ ] Migration to add `force_closed_at`, `force_closed_by` columns to `checkin_details` (preserves audit trail at column level, not just `activity_logs`)
- [ ] Test on staging with multiple long-stay guests (hours_stayed > 24, number_of_hours = 0, check_out_at in future) to verify they are NOT flagged
- [ ] Test on staging with at least one true ghost (check_out_at clearly in past) to verify the action correctly closes it

### 11.2 Three-line flip

```php
// 1. app/Http/Livewire/Admin/UnresolvedCheckIns.php
public bool $fixActionEnabled = true;   // was false
```

```blade
{{-- 2. resources/views/livewire/admin/unresolved-check-ins.blade.php --}}
{{-- Restore the active button and remove the maintenance warning. See commit d2df90a for the disabled version. --}}
```

```blade
{{-- 3. resources/views/components/admin-layout.blade.php (lines 924-960) --}}
{{-- Uncomment the desktop sidebar block. --}}
```

### 11.3 Post-flip smoke test

1. Open `/admin/unresolved-check-ins` as superadmin
2. Verify the "Unresolved" badge appears in sidebar (count ≥ 0)
3. Verify the page lists ghost records with correct details
4. Verify clicking a checkbox enables it
5. Verify clicking "Fix Selected" with no selections shows "no records selected" dialog
6. Verify clicking "Fix Selected" with one true ghost selected closes that record
7. Verify the closed record gets an ActivityLog row with the correct description
8. Verify the closed record's `check_out_at` is preserved (not overwritten)

### 11.4 Rollback if anything goes wrong

```php
public bool $fixActionEnabled = false;
```

Single-line flip. Action becomes inert immediately, button becomes disabled. The `activity_logs` rows from any in-flight runs remain as audit trail.

---

## 12. Reference Material

### 12.1 Files in this incident

| Doc | Purpose |
|---|---|
| `docs/incidents/2026-04-28-fix-all-unresolved-incident-report.md` | **This document** — comprehensive narrative |
| `docs/bugs/2026-04-28-fixall-unresolved-flips-active-extension-checkins.md` | Bug report — root cause, scope, fix proposals |
| `docs/bugs/2026-04-28-unresolved-checkins-code-review.md` | Code review — 10 issues ranked by severity |
| `docs/data-repairs/2026-04-28-restore-10-flipped-rooms-after-fixall.md` | Repair runbook — operator checklist |
| `docs/data-repairs/2026-04-28-recover.sql` | Runnable recovery SQL (single-statement format) |
| `docs/bugs/2026-04-23-ghost-checkin-races-room-reuse.md` | Related — original kiosk-race bug that motivated the Unresolved feature |
| `docs/data-repairs/2026-04-23-vee-meelita-ghost-checkout.md` | Related — similar manual-cleanup pattern |
| `documentation/staging-branch-switch-guide.md` | Operational — how branches are deployed to staging |
| `documentation/deploy-temp-disable-supervisor-to-staging.md` | Operational — the deploy that preceded this incident |

### 12.2 Backup files referenced

All in `c:\Users\Owner\Downloads\`:

| File | Time | MB | Purpose |
|---|---|---:|---|
| `homi_app_producoot_lastest_now.sql` | 2026-04-27 23:17:30 | 27.3 | BEFORE — source of truth |
| `homi_prod_28_2026.sql` | 2026-04-28 00:49 | 29.4 | AFTER bug |
| `homi_app_production.sql` | 2026-04-28 01:43 | 29.4 | NOW snapshot |
| `homi_app_6.sql` | 2026-04-28 05:20 | 29.5 | Pre-recovery |
| `homi_app_afet_udapted_and_latest.sql` | 2026-04-28 05:35 | 29.5 | Post-recovery |

### 12.3 Commits

```
62185a4  fix: implement corrected Unresolved Check-Ins logic, gated off by flag
aab749c  fix: revert mobile sidebar edit that broke parent Blade comment
382061d  fix: blade parse error in user.blade.php top-of-file comment
d2df90a  fix: disable buggy Unresolved Check-Ins fix-all + add incident docs
```

All on branch `feature/temp-disable-supervisor`. Pushed to `origin`.

### 12.4 Affected rooms / guests (final reference list)

```
Room  cid    guest                       guest_id  deposit  real check_out_at
----  -----  --------------------------  --------  -------  -------------------
#4    12051  Merlin utbo                    14285  ₱200    2026-04-28 17:42:52
#5    12333  Macabuhay daisyjun             14623  ₱900    2026-04-28 03:57:02
#6    12319  Jonathan ancla                 14603  ₱200    2026-04-28 14:57:55
#11   12454  Chleo                          14754  ₱650    2026-04-28 10:10:44
#51   10854  Equicom                        12843  ₱3,425  2026-04-28 16:49:41
#52   12318  Lawrence quinco                14597  ₱200    2026-04-28 14:57:22
#60   12393  Almuhamdis Muslimin            14687  ₱200    2026-04-28 07:34:57
#62   12347  Jesielyn Melo                  14639  ₱200    2026-04-28 05:36:31
#63   11923  Ailyn cauilan                  14135  ₱200    2026-04-28 09:09:09
#65   12341  Jayve cagaanan                 14633  ₱200    2026-04-28 16:56:26
#74   11924  Ailyn cauilan                  14136  ₱200    2026-04-28 09:09:13
#92   12356  Andy santos                    14647  ₱200    2026-04-28 17:56:55
#100  12387  Ashrea Esmael                  14675  ₱600    2026-04-28 07:21:20
#151  11263  Jilyana dante                  13373  ₱1,323  2026-04-28 10:30:23
#166  12388  Norfia Dukan                   14685  ₱200    2026-04-28 07:21:35
#171  12395  Juliana kylie paler            14690  ₱1,400  2026-04-28 19:35:20
#205  12308  Francis                        14594  ₱200    2026-04-28 14:30:53
#211  12461  Elva                           14768  ₱600    2026-04-28 10:32:35
#215  11297  Neil galon                     13415  ₱4,254  2026-04-28 14:51:24
#286  11018  Anjeannette lao                13038  ₱246    2026-04-28 00:52:59
                                                   -------
                                                   ₱15,598  total deposits preserved
```

### 12.5 The 9 legitimate ghosts (correctly closed by the action — leave alone)

```
cid    room  check_in_at          check_out_at         deposit
-----  ----  -------------------  -------------------  -------
406    129   2026-03-10 19:08:49  2026-03-11 07:08:49  ₱200    (Mar 10 — abandoned)
613    72    2026-03-11 17:19:18  2026-03-12 11:19:18  ₱0      (Mar 11 — abandoned)
826    144   2026-03-12 13:01:49  2026-03-13 13:01:49  ₱200    (Mar 12 — abandoned)
842    6     2026-03-12 13:51:19  2026-03-14 13:51:19  ₱200    (Mar 12 — abandoned)
1069   28    2026-03-13 10:31:32  2026-03-14 10:31:32  ₱200    (Mar 13 — abandoned)
1101   104   2026-03-13 12:32:43  2026-03-13 18:32:43  ₱200    (Mar 13 — abandoned)
1218   49    2026-03-14 07:17:32  2026-03-14 19:17:32  ₱608    (Mar 14 — abandoned)
6846   120   2026-04-06 16:42:23  2026-04-07 16:42:23  ₱200    (Apr 6 — abandoned)
10730  107   2026-04-21 00:38:28  2026-04-21 12:38:28  ₱200    (Apr 21 — abandoned)
```

These were genuine ghosts where the room had been reused by other guests. The Fix-All action correctly closed them. They remain `is_check_out = 1` post-recovery.

### 12.6 The ambiguous case

```
cid    room  check_in_at          check_out_at         deposit
-----  ----  -------------------  -------------------  -------
12086  54    2026-04-25 19:07:42  2026-04-27 19:07:42  ₱200    (4 h before bug — operator decision)
```

Pending physical room verification by frontdesk. Optional recovery block in `docs/data-repairs/2026-04-28-recover.sql`.

---

## 13. Addendum — Room #71 / Elmettose rivera (post-recovery follow-up)

After the initial 5:30 AM recovery on the 20 active-guest rooms, a frontdesk message at 09:20 AM confirmed that Room #71 (cid=12086) was also a real guest, not a ghost — making the total affected room count **21**, not 20.

By the time of confirmation, the guest (Elmettose rivera) had physically left and the room had been cleaned (08:43 AM). Her record is therefore in a different state than the other 20:

- `is_check_out = 1` is **correct** (she's gone)
- `check_out_at` is **bogus** (bug-overwritten to `2026-04-25 19:37:42`) — needs cosmetic fix
- ₱200 deposit still held — operational decision on forfeit/refund

**Lost revenue:** approximately ₱400-800 from ~13 hours of overstay during the bug window (frontdesk could not see her record to bill extensions). Partially offset by the ₱200 deposit if forfeited.

**Recovery procedure for Room #71:** see the dedicated runbook at `docs/data-repairs/2026-04-28-room-71-elmettose-followup.md`.

**Updated totals:**

| Metric | Value |
|---|---:|
| Total rooms affected | **21** (was 20) |
| Total active deposits preserved | **₱15,798** (was ₱15,598) |
| Lost revenue (Room #71 overstay) | ~₱400-800 |
| Time-pressure on remaining fix | **Low** (cosmetic only) |

---

## 14. Sign-off

| Role | Status |
|---|---|
| Recovery executed by | _(operator)_ |
| Recovery verified by | _(reviewer)_ |
| Documentation completed | 2026-04-28 |
| Forward-fix code shipped | Commits `d2df90a`, `382061d`, `aab749c`, `62185a4` |
| Re-enable target | TBD — pending per-row confirmation UI design |
| Client-facing summary delivered | _(date)_ |

---

*End of incident report.*
