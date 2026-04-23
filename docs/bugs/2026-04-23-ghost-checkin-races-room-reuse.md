# Bug Report: Ghost check-in records due to missing integrity check on room reuse

**Filed:** 2026-04-23
**Severity:** **High** — causes data corruption, cash reconciliation failures, and report inaccuracy
**Category:** System bug — **not human error** (see "Why this is a system bug" below)
**Incident reference:** See `docs/data-repairs/2026-04-23-vee-meelita-ghost-checkout.md` for the manual cleanup record

---

## Executive summary

The system allows a new guest to be checked into a room **while a previous guest's `checkin_details` row is still open** (`is_check_out = 0`). This leaves "ghost" records that:

- Show as currently-checked-in on dashboards forever
- Hold deposits in limbo (cash drawer reconciliation fails)
- Poison occupancy reports and shift handover
- Require manual `php artisan tinker` cleanup to resolve

A band-aid for the symptom was deployed in commit `2bed2e1` on **2026-04-23 08:52** (subject: `"test"`), but it:

1. **Does not fix the kiosk entry point** — the primary attack surface remains open
2. **Silently closes previous records** without handling deposits, activity logs, or checkout reports — it just hides the symptom in the UI
3. **Adds no preventive guards** at room-state transitions

This document describes the root cause, the gaps, and the complete fix.

---

## Incident — Vee Meelita (confirmed case)

**Guest id 13218**, checked in via kiosk to Room #68 (`rooms.id = 51`) on **2026-04-22 17:35:55**.

### Timeline of room 68 after Vee

| Time | Event | Vee's `is_check_out` |
|---|---|---|
| 2026-04-22 17:35:55 | Vee kiosk check-in, `rooms.status = 'Occupied'` | 0 |
| 2026-04-22 20:58:03 | **Han (13330) checks into room 68** — 3h 22m after Vee | 0 (still) |
| 2026-04-23 08:44:36 | Han checks out normally | 0 (still) |
| 2026-04-23 14:32:51 | Haron ante (13525) checks in | 0 (still) |
| 2026-04-23 16:48:52 | Haron checks out normally | 0 (still) |
| 2026-04-23 19:36:03 | Larry matalandang (13632) checks in — **current occupant** | 0 (still) |
| 2026-04-23 20:23:18 | Manual cleanup via tinker | **1** |

**How the system let Han check in:** the guard at `app/Http/Livewire/Kiosk/CheckIn.php:258-272` only verifies `rooms.status = 'Occupied'` — it does not inspect `checkin_details`. Between 17:35 and 20:58 the room status flipped off `'Occupied'` (likely via roomboy "finish cleaning" or manual admin change), and that was sufficient for the kiosk to accept Han.

### Collateral damage

- **₱555 deposit** (transactions 30554 + 30555) still open in the DB
- `check_out_guest_reports` has no row for Vee — her stay doesn't exist in remittance
- Activity log shows only "Check In from Kiosk", no checkout
- Dashboard showed her as checked in for 26+ hours while the room cycled through 3 other guests

---

## Root cause analysis

The bug is **not one line** — it's a class of missing invariant checks across the room-state lifecycle. The system uses `rooms.status` as the sole indicator of room availability, but does not enforce the invariant:

> **Invariant:** `rooms.status = 'Available' OR 'Cleaned'` ⇒ **no row** in `checkin_details` for that room has `is_check_out = 0`.

This invariant is violated in 3 places, any one of which is sufficient to create a ghost.

### Gap 1 — Kiosk self-checkin insufficient guard

**File:** `app/Http/Livewire/Kiosk/CheckIn.php`
**Lines:** 258-272

```php
$room = Room::where('branch_id', auth()->user()->branch_id)
    ->where('id', $this->room_id)
    ->where('status', 'Occupied')   // ← ONLY checks rooms.status
    ->with('latestCheckInDetail')
    ->lockForUpdate()
    ->first();

if ($room) {
    DB::rollBack();
    $this->dialog()->error(
        'SORRY',
        'Room is already occupied. Please select another room.'
    );
    return;
}
// ... proceeds to create Guest + TemporaryCheckInKiosk ...
```

**Problem:** If `rooms.status ≠ 'Occupied'` for any reason — `'Uncleaned'`, `'Available'`, `'Cleaned'`, or `NULL` — `$room` is `null` and the code proceeds. It never asks "does this room have an open `checkin_details`?"

**Fix:** Add an additional guard:

```php
$openCheckin = CheckinDetail::where('room_id', $this->room_id)
    ->where('is_check_out', false)
    ->lockForUpdate()
    ->exists();

if ($openCheckin) {
    DB::rollBack();
    $this->dialog()->error(
        'SORRY',
        'Room has an unresolved previous guest. Please contact the front desk.'
    );
    return;
}
```

### Gap 2 — Roomboy "Finish Cleaning" flips status without invariant check

**File:** `app/Http/Livewire/Roomboy/Index.php`
**Lines:** 192-198 (also duplicated in `Roomboy/Main.php:282-283`)

```php
$room->update([
    'status' => 'Available',  // ← no check that no checkin_details are still open
    'is_priority' => 1,
    'started_cleaning_at' => null,
    'time_to_clean' => null,
    'cleaning_by_user_id' => null,
]);
```

**Problem:** A roomboy can mark a room "Available" while `checkin_details` for that room still has `is_check_out = 0`. This is the most likely path that opened room 68 for Han's check-in.

**Fix:** Refuse the state transition if an open checkin exists:

```php
$hasOpenCheckin = CheckinDetail::where('room_id', $room->id)
    ->where('is_check_out', false)
    ->exists();

if ($hasOpenCheckin) {
    DB::rollBack();
    $this->dialog()->error(
        'Cannot Finish Cleaning',
        'Room has unresolved previous guest. Front desk must check out first.'
    );
    return;
}

$room->update([
    'status' => 'Available',
    // ...
]);
```

### Gap 3 — `rooms.status` is authoritative but not constrained at the schema level

No DB-level constraint, index, or trigger enforces the invariant. Every place in the codebase that writes `rooms.status` is free to violate it.

There are **13 call sites** that write `rooms.status` across the app (found via `grep "'status'\s*=>\s*'(Available|Uncleaned|Cleaned|Occupied)'"`). Any new code that sets status is a potential new gap.

**Proper fix:** either a DB trigger that rejects status = 'Available'/'Cleaned' when an open checkin exists, or a `Room::markAvailable()` / `Room::markOccupied()` helper with the invariant built in — used everywhere, grep-enforced.

---

## The existing band-aid — what it does and doesn't do

### Commit `2bed2e1` ("test", 2026-04-23 08:52:46, by Gabriel Jon Icawalo)

Added silent cleanup in 4 locations:

- `app/Http/Livewire/Frontdesk/Monitoring/CheckInFromKiosk.php:198-201`
- `app/Http/Livewire/Frontdesk/Monitoring/RoomMonitoring.php` — `storeGuest()`, `saveCheckInDetails()`, `saveReserveCheckInDetails()` (3 call sites)

```php
// Mark any existing active checkin_details for this room as checked out
CheckinDetail::where('room_id', $this->guest->room_id)
    ->where('is_check_out', false)
    ->update(['is_check_out' => true, 'check_out_at' => now()]);
```

And changed the `RoomMonitoring` search query to only join the latest open `checkin_details` per room — so duplicates stop showing in the UI.

### What the band-aid accomplishes ✅

- New check-ins processed through these 4 paths auto-close the ghost
- Dashboard stops showing ghosts in Room Monitoring

### What the band-aid does NOT accomplish ❌

1. **Does not cover the kiosk entry point (`Kiosk/CheckIn.php`)** — the primary self-service path. Vee's scenario is still reproducible today if `rooms.status` gets flipped between kiosk-tap and frontdesk-processing.
2. **Does not cover the roomboy state transition** — still allows "Finish Cleaning" on a room with an open checkin, which is the trigger for Gap 1.
3. **Silently closes records with no integrity** — no deposit resolution, no activity log, no checkout report, `total_deduction` stays 0. The ₱555 ghost deposit is preserved exactly as before; only `is_check_out` changes. Cash reconciliation is still broken.
4. **Commit message `"test"` on a production patch** — no description of what it fixes, no test added, no PR review. Smells like a hotfix jammed in.
5. **No preventive guard** — only reactive cleanup after damage is done.

---

## Proposed complete fix

### Phase 1 — Stop the bleeding (blocks new ghosts)

**Priority: this week.** Add invariant guards at the two entry points:

1. `Kiosk/CheckIn.php::confirmCheckIn()` — add the open-checkin guard from Gap 1
2. `Roomboy/Index.php::finishCleaning()` AND `Roomboy/Main.php` equivalent — add the guard from Gap 2

Both should refuse with a user-friendly error pointing to the front desk.

### Phase 2 — Fix the existing band-aid

The silent-close band-aid in commit `2bed2e1` should either:

- **(A)** Be removed (once Phase 1 is in place, it's unreachable)
- **(B)** Be upgraded to a full cleanup path: log to `activity_logs`, force-forfeit any open deposits (or at least flag them as `needs_review`), and write a `check_out_guest_reports` row marked as an auto-close

I recommend (A) once Phase 1 is live. The band-aid is a workaround, not a solution.

### Phase 3 — Defense in depth

- Add a DB check (trigger or migration-level constraint) enforcing the invariant at the DB layer
- Refactor all `'status' => '...'` writes into `Room` model helper methods (`markAvailable()`, `markOccupied()`, `markUncleaned()`, etc.) — each enforces the invariant
- Add a scheduled command `app:find-ghost-checkins` (daily) that scans for open `checkin_details` where a newer `checkin_details` exists on the same room — alerts admin. Self-diagnosis.

### Phase 4 — Data cleanup

Run the ghost-hunt query across the live DB:

```php
\App\Models\CheckinDetail::where('is_check_out', 0)
    ->whereExists(function ($q) {
        $q->select(\DB::raw(1))
          ->from('checkin_details as cd2')
          ->whereColumn('cd2.room_id', 'checkin_details.room_id')
          ->whereColumn('cd2.id', '>', 'checkin_details.id');
    })
    ->with('guest:id,name', 'room:id,number')
    ->get()
    ->toArray();
```

Any rows returned need the same cleanup we did for Vee (see `docs/data-repairs/2026-04-23-vee-meelita-ghost-checkout.md`). Review their deposits case-by-case.

---

## Test scenarios

Before marking fixed, verify each:

### T1 — Kiosk blocks when ghost exists
1. Create guest A, check in to room X via kiosk (frontdesk-processed)
2. Manually flip `rooms.status` to `'Available'` (simulate Gap 1)
3. Attempt kiosk check-in for guest B to room X
4. **Expected:** error "Room has an unresolved previous guest"

### T2 — Roomboy blocks when ghost exists
1. Create guest A, check in to room X
2. `rooms.status` is currently `'Occupied'`
3. Manually set `rooms.status` to `'Uncleaned'` (simulate missed checkout path)
4. Roomboy opens cleaning for room X, clicks "Finish Cleaning"
5. **Expected:** error "Room has unresolved previous guest"

### T3 — Happy path still works
1. Guest A checks in, checks out normally (is_check_out = 1)
2. Roomboy "Finish Cleaning" on the room
3. **Expected:** status flips to Available, guest B can check in

### T4 — Regression: no ghost remains after fix
1. Reproduce T1 flow end-to-end over 24 hours
2. Run the ghost-hunt query
3. **Expected:** returns `[]`

---

## Why this is a **system bug**, not human error

If any of the following were true, it would be human error:

- The user bypassed a documented procedure → ❌ no such procedure exists
- The user had adequate warning/error messaging → ❌ no error shown; check-in silently succeeded
- The user had a clear way to avoid the outcome → ❌ kiosk self-service has no visible state
- A safer default was ignored → ❌ the "safer default" isn't implemented

Instead:

1. **Guest action cannot cause this** — Vee just walked away. She didn't do anything wrong. A walked-out-without-checkout guest is a completely normal hotel scenario that any hotel system must handle.
2. **Frontdesk action did not cause this** — no frontdesk approved Han's checkin knowing Vee was open; the system never showed them a conflict.
3. **Roomboy action did not cause this** — the roomboy just pressed "Finish Cleaning," which the system let them do. No warning, no block.
4. **The system provided no mechanism to prevent it** — the invariant is not enforced anywhere. You'd have to write custom SQL to catch it.

A correctly designed system would:
- Refuse the second kiosk check-in with a visible error
- Refuse the roomboy "Finish Cleaning" with a visible error
- Alert an admin that room X has a stuck guest after N hours

None of those existed. Therefore: **this is a system bug. The staff did nothing wrong.** Manual cleanup via tinker is the workaround for a missing feature.

---

## Action items

- [ ] Phase 1: add guards in `Kiosk/CheckIn.php` and `Roomboy/Index.php` / `Roomboy/Main.php`
- [ ] Phase 1: deploy + monitor for a week, compare ghost-hunt query output before/after
- [ ] Phase 2: decide on band-aid removal vs. upgrade
- [ ] Phase 3: `Room` model helpers + DB constraint/trigger + daily scan command
- [ ] Phase 4: inventory & clean up existing ghosts (run the hunt query)
- [ ] Add tests T1–T4
- [ ] Rewrite commit `2bed2e1` with a proper message (or add a follow-up commit documenting what it actually fixed and its limitations)

## Owners

- **Code fix:** Gabriel Jon Icawalo (already touched the affected files in `2bed2e1`) + Brian Orbino
- **Data cleanup / reconciliation:** hotel admin + Brian
- **Verification:** front desk ops during normal shift work + dev QA

---

*Generated as part of the Vee Meelita incident investigation. Cross-reference `docs/data-repairs/2026-04-23-vee-meelita-ghost-checkout.md` for the manual fix that resolved the specific incident.*
