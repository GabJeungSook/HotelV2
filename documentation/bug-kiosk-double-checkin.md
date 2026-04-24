# Bug: Kiosk Double Check-In / Occupied Room Reappearing

**Reported:** 2026-04-23 (Brian / frontdesk concern)
**Status:** Fix applied on `future-updates` — needs to be ported / merged into `master` for production
**Severity:** HIGH — production data corruption (same room assigned to 2 guests)
**Branches affected:** `master` (production) and `future-updates` (code was identical before fix)

---

## Fix Applied (2026-04-23)

Applied the recommended combination of Option A + Option B below:

1. **`app/Http/Livewire/Kiosk/CheckIn.php`** — `render()` and `selectType()` now also exclude rooms that have an orphaned Guest record (Guest without a linked `CheckInDetail`):
   ```php
   $pendingGuestRooms = Guest::where('branch_id', auth()->user()->branch_id)
       ->whereDoesntHave('checkInDetail')
       ->pluck('room_id')
       ->toArray();
   // ... ->whereNotIn('id', $pendingGuestRooms)
   ```

2. **`app/Console/Commands/CleanupTemporaryKiosk.php`** — when an expired `TemporaryCheckInKiosk` row is deleted, the orphaned Guest linked to that hold is also deleted (inside a DB transaction, and only if that Guest has no `CheckInDetail`).

**Still to do:**
- Merge the fix into `master` so production is covered.
- Run the cleanup SQL queries (at the bottom of this doc) on production to audit existing orphans / duplicates before deploying.
- Reproduce the failure scenario on staging to confirm the fix closes it.

---

## Symptom

Frontdesk reported:
- Rooms that are already checked-in keep appearing in the Kiosk as "available"
- Some guests end up checked into the **same room** (`kaduwa na check inan`)
- Some Guest records exist but were **never actually entered** to a room (`wala gid nasudlan`)
- Happens across shifts (gaina na shift → karon na adlaw)

Example from 2026-04-23:
- D-type room → guest "Acmad cosain" checked in 11:52 AM (6 hrs)
- Same D-type room → guest "Merriam" checked in 7:18 PM (24 hrs)
- Only one of them should have been able to occupy that room at a time.

---

## How the Kiosk Currently Picks Rooms

`app/Http/Livewire/Kiosk/CheckIn.php` — `render()` method, lines 44-77.

Filters used:
```php
->whereIn('status', ['Available', 'Cleaned'])
->whereNotIn('id', $temporaryCheckInKiosk)   // rooms currently "held" by kiosk
->whereNotIn('id', $temporaryReserved)
->where('is_priority', true)
->orderByRaw('last_checkin_at IS NOT NULL, last_checkin_at ASC')  // UNUSED FIRST, then oldest
->orderBy('number', 'asc')
```

Then `groupBy('floor_id')->first()` — **only 1 room per floor** is displayed.

Ordering logic is based on **usage + date**:
1. Rooms with `last_checkin_at = NULL` (never used) appear first
2. Rooms with oldest `last_checkin_at` next
3. Then by room number

---

## Root Cause

The kiosk flow has **two stages**:

| Stage | Room.status | Guest row | TemporaryCheckInKiosk |
|-------|-------------|-----------|-----------------------|
| 1. Customer finishes kiosk (`CheckIn::confirmCheckIn`, line 251) | still `Available`/`Cleaned` | **created** | **created** (20-min hold) |
| 2. Frontdesk confirms (`CheckInFromKiosk::confirmCheckIn`, line 411-419) | → `Occupied` | linked to `CheckInDetail` | deleted |

Between Stage 1 and Stage 2, the **room status does NOT change**. The only thing preventing a second guest from picking the same room is the `TemporaryCheckInKiosk` row.

`app/Console/Commands/CleanupTemporaryKiosk.php` (signature `kiosk:cleanup`) deletes that hold after `kiosk_time_limit` minutes (default **10 min**):

```php
$deleted = TemporaryCheckInKiosk::where('branch_id', $branch->id)
    ->where('created_at', '<=', now()->subMinutes($minutes))
    ->delete();
```

### Failure Scenario
1. Customer A completes kiosk → Guest A created, `TemporaryCheckInKiosk` created
2. Frontdesk is busy → doesn't click "Check-In from Kiosk" within 10 minutes
3. `kiosk:cleanup` runs → deletes `TemporaryCheckInKiosk` row
4. **Guest A row still exists** and **room status is still `Available`/`Cleaned`**
5. Kiosk re-displays the same room (now "free" from its perspective)
6. Customer B completes kiosk on the SAME room → Guest B created
7. Frontdesk eventually processes → two guests linked to the same room (**double check-in**)
8. Or Guest A is forgotten entirely (**wala nasudlan** — never entered)

### Why "last_checkin_at" ordering makes it worse
Because the priority ordering puts unused rooms first, the same "never-used" room keeps surfacing to the top of the kiosk for every new customer until either:
- Frontdesk confirms (status → `Occupied`), OR
- Guest checks out (sets `last_checkin_at`)

If neither happens (because the kiosk check-in was abandoned), the room is a permanent collision target.

### Configuration inconsistency
- `CheckIn.php:330` sets `terminated_at = now()->addMinutes(20)` (20 min)
- `CleanupTemporaryKiosk.php:39` uses `kiosk_time_limit ?? 10` (default 10 min)
- The cleanup command ignores `terminated_at` entirely and uses `created_at` instead.
  These two timers are not aligned.

---

## Related Files

| File | Role |
|------|------|
| `app/Http/Livewire/Kiosk/CheckIn.php` | Kiosk check-in flow (creates pending Guest + hold) |
| `app/Http/Livewire/Frontdesk/Monitoring/CheckInFromKiosk.php` | Frontdesk confirms → room becomes Occupied |
| `app/Console/Commands/CleanupTemporaryKiosk.php` | Cron-driven cleanup of expired holds (THE GAP) |
| `app/Jobs/TerminationInKiosk.php` | Unused / not dispatched anywhere |
| `app/Models/TemporaryCheckInKiosk.php` | The "hold" model |
| `app/Models/Guest.php` | Guest model (no cleanup when hold expires) |

---

## Proposed Fixes (choose one or both before implementing)

### Option A — Exclude rooms with pending Guest records from kiosk render (minimal)

In `CheckIn.php::render()`, also exclude rooms where a Guest exists without a completed `CheckInDetail`:

```php
$pendingGuestRooms = Guest::whereDoesntHave('checkInDetail')
    ->where('branch_id', auth()->user()->branch_id)
    ->pluck('room_id')
    ->toArray();

// add to the room query:
->whereNotIn('id', $pendingGuestRooms)
```

Also apply the same filter in `selectType()` (lines 117-124) so the "no available room" dialog is accurate.

**Pros:** Surgical, no schema change. Fixes the display bug.
**Cons:** Orphan Guest records still pile up — they need a separate cleanup path.

### Option B — Make `kiosk:cleanup` also delete orphaned Guests (cleaner)

When `CleanupTemporaryKiosk` deletes an expired hold, also delete the associated Guest record (the one that never got a `CheckInDetail`):

```php
$expired = TemporaryCheckInKiosk::where('branch_id', $branch->id)
    ->where('created_at', '<=', now()->subMinutes($minutes))
    ->get();

foreach ($expired as $hold) {
    Guest::where('id', $hold->guest_id)
        ->whereDoesntHave('checkInDetail')
        ->delete();
    $hold->delete();
}
```

**Pros:** Keeps the DB clean. No need to filter in render.
**Cons:** Be sure the schema doesn't have other FK dependencies on that Guest row (transactions, etc.).

### Option C — Reserve the room at kiosk stage (safest, biggest change)

In `CheckIn::confirmCheckIn()`, set the room status to `'Reserved'` (or a new `'PendingKiosk'` status). When frontdesk confirms, status → `Occupied`. When the hold expires via cleanup, status → back to `'Available'`/`'Cleaned'`.

**Pros:** Single source of truth (status column). Existing `whereIn('status', ['Available','Cleaned'])` filter naturally excludes pending rooms without extra logic.
**Cons:** Touches more files, need to make sure no other places assume Reserved rooms are never kiosk-originated.

### Recommended combination
**Option A + Option B.**
- A guarantees display is correct right now.
- B prevents orphan Guest rows from piling up in the `guests` table.

---

## Fix Verification Checklist

Before marking this fixed, reproduce this scenario on staging:

- [ ] Go through kiosk check-in for Room X (Guest A), do **not** let frontdesk confirm
- [ ] Wait for `kiosk:cleanup` to run (or invoke manually: `php artisan kiosk:cleanup`)
- [ ] Open kiosk again → Room X should **NOT** appear in the available list
- [ ] Check `guests` table → Guest A should either still be there with a flag, or be deleted (depending on option chosen)
- [ ] Frontdesk's "Check-In from Kiosk" queue should not show stale entries
- [ ] Re-check production data for rooms with more than one active Guest on the same day

---

## Data Cleanup Query (for current production state)

Before fixing, identify existing orphans:

```sql
SELECT g.id, g.branch_id, g.room_id, g.name, g.created_at
FROM guests g
LEFT JOIN check_in_details c ON c.guest_id = g.id
WHERE c.id IS NULL
ORDER BY g.created_at DESC;
```

And same-room duplicates:

```sql
SELECT room_id, DATE(created_at) AS day, COUNT(*) AS guest_count
FROM guests
GROUP BY room_id, DATE(created_at)
HAVING guest_count > 1
ORDER BY day DESC, guest_count DESC;
```
