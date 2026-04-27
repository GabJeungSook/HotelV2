# Bug Report: "Fix All" on Unresolved Check-Ins force-closes ACTIVE guests with future check-out dates

**Filed:** 2026-04-28
**Severity:** **High** — silently force-closes paying guests, flips their rooms to Available, and erases their real `check_out_at`. Frontdesk loses sight of real guests.
**Category:** System bug — query uses the wrong column to detect ghost records.
**Incident reference:** See `docs/data-repairs/2026-04-28-restore-10-flipped-rooms-after-fixall.md` for the recovery record.

---

## Executive summary

Clicking the **"Fix All Records"** button on `/admin/unresolved-check-ins` on **2026-04-27 23:19:54** force-closed **30 check-in records**. Of those:

- **9** were genuine ghost records (March / early-April abandonments where the room had been reused). Action was correct.
- **20** were **active guests still inside their rooms** with `check_out_at` set to a future timestamp. Action was wrong — these were force-closed in error.
- **1** was ambiguous (`cid=12086`, room #71): the guest's planned `check_out_at` was 4 h *before* the bug fired, so they could be either a legitimate overstay or a real ghost. Needs frontdesk confirmation before recovery.

**9 seconds later** (23:20:03), clicking **"Fix All"** on `/admin/ghost-rooms` then flipped **22 rooms** from `Occupied` to `Available` (or `Cleaned`):

- 20 of those flips were the false-positive guests above.
- 1 (room #71) is the ambiguous case.
- 1 (room #161) was a separate pre-existing ghost that the GhostRooms tool correctly cleaned up — leave that one alone.

The UI told the operator the action was safe ("Effects on reports: deposits stay marked as forfeited/received, room status restored, new check-ins safe"). The action was **not** safe. Frontdesk had to reconcile from physical room cards.

A complete recovery is straightforward because no transactions were touched and the deposit/deduction columns were not modified.

---

## Confirmed timeline

| Time (Asia/Manila) | Event |
|---|---|
| `23:17:30` | DB backup taken (`homi_app_producoot_lastest_now.sql`) — 20 active extended check-ins exist with `is_check_out=0` and real `check_out_at` in the future. |
| `23:19:54` | `UnresolvedCheckIns::fixAllGhostRecords()` runs. 30 rows updated: `is_check_out 0 → 1`, `check_out_at` overwritten to fake timestamp. |
| `23:20:03` | `GhostRooms::fixAllRooms()` runs. 22 rooms flipped from `status=Occupied` to `status=Available`/`Cleaned` (rooms 4, 5, 6, 11, 40, 41, 43, 45, 46, 48, 54, 57, 75, 83, 106, 116, 121, 126, 139, 145, 148, 216). |
| `2026-04-28 00:21` | Frontdesk discovers the issue: physical key cards show real guests, system shows rooms as Available. |
| `2026-04-28 00:49` | DB backup taken (`homi_prod_28_2026.sql`) for forensic comparison. |

---

## Root cause

**File:** `app/Http/Livewire/Admin/UnresolvedCheckIns.php`
**Lines:** 45-47, 96-98 (same query in two places — the page query and the action query)

```php
CheckinDetail::where('is_check_out', 0)
    ->whereRaw('DATE_ADD(check_in_at, INTERVAL number_of_hours HOUR) < ?', [$cutoff1day])
```

The query computes "expected checkout" as `check_in_at + number_of_hours`. **It never reads `check_out_at`.**

For long-stay / multi-extension guests, AND for any check-in where the system stored `number_of_hours = 0` (which turns out to be most of them in this database):

- `hours_stayed` (col 9) holds the original duration (e.g., 24).
- `number_of_hours` (col 16) holds the *current extension* duration. **For long-stay guests this is `0`. For many other check-ins it is also `0` due to a separate data-population issue.**
- `check_out_at` (col 13) holds the **real** checkout target, updated by every extension.

So the query becomes `WHERE check_in_at + 0 HOUR < (now - 1 day)` — i.e., flag every check-in older than 24 h. Twenty active extended-stay guests qualified, none of them ghosts.

### Verified in the data

All 20 false-positive records have `number_of_hours = 0` in the BEFORE backup, while their real `check_out_at` was a future timestamp (2026-04-28). Examples:

| `cid` | room | `check_in_at` | real `check_out_at` | `number_of_hours` | `total_deposit` |
|------:|-----:|---|---|---:|---:|
| 10854 | #51 | 2026-04-21 16:49 | 2026-04-28 16:49 | 0 | ₱3,425 |
| 11018 | #286 | 2026-04-22 00:52 | 2026-04-28 00:52 | 0 | ₱246 |
| 11263 | #151 | 2026-04-22 22:30 | 2026-04-28 10:30 | 0 | ₱1,323 |
| 11297 | #215 | 2026-04-23 02:51 | 2026-04-28 14:51 | 0 | ₱4,254 |
| 11923 | #63 | 2026-04-25 09:09 | 2026-04-28 09:09 | 0 | ₱200 |
| 11924 | #74 | 2026-04-25 09:09 | 2026-04-28 09:09 | 0 | ₱200 |
| 12051 | #4 | 2026-04-25 17:42 | 2026-04-28 17:42 | 0 | ₱200 |
| 12308 | #205 | 2026-04-26 14:30 | 2026-04-28 14:30 | 0 | ₱200 |
| 12318 | #52 | 2026-04-26 14:57 | 2026-04-28 14:57 | 0 | ₱200 |
| 12319 | #6 | 2026-04-26 14:57 | 2026-04-28 14:57 | 0 | ₱200 |
| 12333 | #5 | 2026-04-26 15:57 | 2026-04-28 03:57 | 0 | ₱900 |
| 12341 | #65 | 2026-04-26 16:56 | 2026-04-28 16:56 | 0 | ₱200 |
| 12347 | #62 | 2026-04-26 17:36 | 2026-04-28 05:36 | 0 | ₱200 |
| 12356 | #92 | 2026-04-26 17:56 | 2026-04-28 17:56 | 0 | ₱200 |
| 12387 | #100 | 2026-04-26 19:21 | 2026-04-28 07:21 | 0 | ₱600 |
| 12388 | #166 | 2026-04-26 19:21 | 2026-04-28 07:21 | 0 | ₱200 |
| 12393 | #60 | 2026-04-26 19:34 | 2026-04-28 07:34 | 0 | ₱200 |
| 12395 | #171 | 2026-04-26 19:35 | 2026-04-28 19:35 | 0 | ₱1,400 |
| 12454 | #11 | 2026-04-26 22:10 | 2026-04-28 10:10 | 0 | ₱650 |
| 12461 | #211 | 2026-04-26 22:32 | 2026-04-28 10:32 | 0 | ₱600 |

Total deposits at risk if these guests had been billed-by-default: **₱15,295**. None lost — the values are intact in their rows.

---

## Why the second click compounded the damage

`app/Http/Livewire/Admin/GhostRooms.php` `fixAllRooms()` (line 57) computes its target list as:

```php
$occupiedRoomIds = CheckinDetail::where('is_check_out', false)->pluck('room_id');
return Room::where('status', 'Occupied')
    ->whereNotIn('id', $occupiedRoomIds);
```

Once the upstream `fixAllGhostRecords()` had flipped the 30 records to `is_check_out=1`, the rooms that previously had only one of those checkins (no other active guest) were now genuinely "Occupied with no active check-in" — exactly the GhostRooms heuristic. The second click *correctly* flagged them given its inputs, but the inputs were already corrupted.

---

## Damage assessment

| Aspect | Status |
|---|---|
| Transactions | **Untouched** — no inserts, no updates at 23:19:54 / 23:20:03. |
| `total_deposit` / `total_deduction` | **Preserved** in every row. Verified byte-for-byte across all 20 records. |
| `check_in_at` / `last_checkin_at` | **Preserved**. |
| `is_check_out` | Flipped 0→1 (recoverable). |
| `check_out_at` | Overwritten to `check_in_at + number_of_hours + 30 min` — **the original is lost from the DB but recoverable from the BEFORE backup**. |
| `rooms.status` | Flipped Occupied→Available (recoverable). |

No financial state was harmed. The deposit balance and pending deductions in each row remain exactly as the guest had them at backup time.

---

## Why the UI told the operator this was safe

The page (`resources/views/livewire/admin/unresolved-check-ins.blade.php`) displays:

> **What happens when fixed?**
> Changes to records:
> - `is_check_out` set to TRUE
> - `check_out_at` set to expected checkout + 30 minutes
> - Checkout time is **backdated** (not today's date)
>
> Effects on reports: are accurate · Deposits treated as forfeited/received · Room Status will be reset · New check-ins safe.

This copy is correct **only if** every record returned by the query is actually a ghost. The query is wrong, so the copy is misleading. The page also showed the false-positive count (₱17,808 deposits at risk) without flagging that those deposits belonged to **active** guests — the operator had no signal that the list contained real guests.

---

## Fix proposals (priority order)

### Fix 1 — Use `check_out_at` as source of truth (REQUIRED)

The `check_out_at` column is the system's authoritative "this guest is supposed to leave at X" value, updated atomically by every extension. Use it instead of recomputing.

**`app/Http/Livewire/Admin/UnresolvedCheckIns.php:45-47` and `:96-98`** — replace:

```php
->whereRaw('DATE_ADD(check_in_at, INTERVAL number_of_hours HOUR) < ?', [$cutoff1day])
```

with:

```php
->whereNotNull('check_out_at')
->where('check_out_at', '<', now()->subHours(2))   // 2 h grace past real checkout
```

A 2-hour grace is enough to cover late checkouts and clock skew without including legitimately-staying guests.

### Fix 2 — Hard guard in the action loop (DEFENSE IN DEPTH)

Even with Fix 1, the action method should refuse to close a record whose `check_out_at` is in the future:

**`app/Http/Livewire/Admin/UnresolvedCheckIns.php:104` (inside the `foreach ($ghosts as $record)` loop):**

```php
if ($record->check_out_at && Carbon::parse($record->check_out_at)->isFuture()) {
    \Log::warning("Skipping cid={$record->id} — check_out_at is in the future, not a ghost");
    continue;
}
```

This is a belt-and-suspenders check that also protects against future query regressions.

### Fix 3 — Stop overwriting `check_out_at` (PRESERVE AUDIT TRAIL)

The current action overwrites the real `check_out_at` with a fabricated value, which destroys the historical record of when the guest was *supposed* to leave. Better:

```php
$record->update([
    'is_check_out'     => true,
    // KEEP existing check_out_at — that's the real expected-out time.
    // Add a new column to record the force-close audit:
    'force_closed_at'  => now(),
    'force_closed_by'  => auth()->id(),
]);
```

This requires a migration to add `force_closed_at` and `force_closed_by` columns. Without them, the original `check_out_at` is unrecoverable from the DB alone.

### Fix 4 — Require explicit per-row confirmation (UX)

The current "Fix All 30 Records" button is a single irreversible click. A safer UX:

- Show each record as a checkbox row with the operator's required acknowledgment of the room status (`Occupied?`).
- Disable the row's checkbox if `room.status = 'Occupied'` until a frontdesk supervisor manually confirms.
- The bulk action then only operates on the explicitly-checked subset.

### Fix 5 — Audit log every force-close (OBSERVABILITY)

Currently the action logs nothing (no `ActivityLog::create()`). Add a per-record activity log so the next incident is recoverable from the in-DB log without needing a backup diff.

---

## Reproduction (do NOT run on prod)

In a test DB with a long-stay extended check-in (`is_check_out=0`, `number_of_hours=0`, `check_out_at='2026-04-30 12:00:00'`, `check_in_at='2026-04-25 12:00:00'`):

1. Browse `/admin/unresolved-check-ins` as superadmin.
2. The record will appear in the "Ghost Records" table even though the guest is mid-stay.
3. Clicking "Fix All Records" → `is_check_out` flips to 1 and `check_out_at` becomes `2026-04-25 12:30:00`.

Expected behavior after Fix 1 + Fix 2: the record should not appear, and even if forced into the action, it should be skipped with a warning log entry.

---

## Status

- **Bug confirmed** — verified by diffing `homi_app_producoot_lastest_now.sql` (BEFORE) against `homi_prod_28_2026.sql` (AFTER).
- **Recovery prepared** — see `docs/data-repairs/2026-04-28-restore-10-flipped-rooms-after-fixall.md` and the runnable `2026-04-28-recover.sql`.
- **Forward fix** — pending. Recommend a separate branch (NOT `feature/temp-disable-supervisor`) for Fixes 1 + 2 + 5; Fix 3 is a follow-up migration; Fix 4 is a UX iteration.
