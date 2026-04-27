# Code Review: `Admin/UnresolvedCheckIns.php` and `Admin/GhostRooms.php`

**Filed:** 2026-04-28
**Severity:** Mix — one **High** (caused 2026-04-28 incident) and several **Medium** issues that compound risk.
**Files reviewed:**
- `app/Http/Livewire/Admin/UnresolvedCheckIns.php`
- `app/Http/Livewire/Admin/GhostRooms.php`

This is a focused list of every defect in those two files that I found while diagnosing the 2026-04-28 incident. The High-severity one is what caused the incident; the rest are latent risks waiting to bite.

---

## Issue 1 — **HIGH** — Wrong column used to detect ghost records

**File:** `app/Http/Livewire/Admin/UnresolvedCheckIns.php`
**Lines:** 45-47, 96-98 (same query duplicated in two places)

```php
->whereRaw('DATE_ADD(check_in_at, INTERVAL number_of_hours HOUR) < ?', [$cutoff1day])
```

`number_of_hours` is the **current extension's** duration. For long-stay guests with one or more extensions, this column is `0`, so the predicate becomes `check_in_at < (now − 1 day)`. Every long-stay guest older than 24 h is flagged as a ghost.

The `check_out_at` column already holds the authoritative "when this guest is supposed to leave" — updated atomically by every extension. The query should use it directly.

**Caused the 2026-04-28 incident** — see `docs/bugs/2026-04-28-fixall-unresolved-flips-active-extension-checkins.md` for the full timeline and recovery.

**Fix:**
```php
->whereNotNull('check_out_at')
->where('check_out_at', '<', now()->subHours(2))   // 2-hour grace
```

---

## Issue 2 — **HIGH** — Action method has no per-row safety net

**File:** `app/Http/Livewire/Admin/UnresolvedCheckIns.php`
**Lines:** 92-119 (`fixAllGhostRecords()`)

```php
foreach ($ghosts as $record) {
    $expectedOut = Carbon::parse($record->check_in_at)->addHours($record->number_of_hours);
    $checkoutTime = $expectedOut->copy()->addMinutes(30);

    $record->update([
        'is_check_out' => true,
        'check_out_at' => $checkoutTime,
    ]);
}
```

The loop trusts the query and updates blindly. There is no last-line-of-defense check like "is this row's `check_out_at` actually in the past?" or "does this row's room actually have a different active guest?" If the query returns a false-positive (which it did), the action has nothing left to catch it.

**Fix:** add a guard inside the loop:
```php
if ($record->check_out_at && Carbon::parse($record->check_out_at)->isFuture()) {
    \Log::warning("UnresolvedCheckIns: skipping cid={$record->id} — check_out_at is in the future");
    continue;
}
```

---

## Issue 3 — **HIGH** — Overwrites `check_out_at`, destroying audit trail

**File:** `app/Http/Livewire/Admin/UnresolvedCheckIns.php`
**Lines:** 113

```php
'check_out_at' => $checkoutTime,   // fabricated value: check_in_at + number_of_hours + 30 min
```

After this runs, the **real** `check_out_at` (the date the guest was supposed to leave, e.g. `2026-04-28 17:42`) is unrecoverable from the DB alone. The 2026-04-28 recovery only worked because we had a `mysqldump` backup taken 2 minutes before the bad click. Without that backup, the original timestamps were gone.

**Fix:** keep `check_out_at` and add separate audit columns:
- migration: add `force_closed_at TIMESTAMP NULL`, `force_closed_by BIGINT UNSIGNED NULL`
- update: set those, do not touch `check_out_at`

---

## Issue 4 — **MEDIUM** — No `ActivityLog` row for force-close

**File:** `app/Http/Livewire/Admin/UnresolvedCheckIns.php`
**Lines:** 102-119

`fixAllGhostRecords()` creates **zero** `ActivityLog` rows. After the 2026-04-28 incident there was no in-DB record of:
- WHO clicked the button
- WHEN they clicked it
- WHICH records were closed
- WHAT the values were before/after

The only forensic source was the `mysqldump` files. This is the difference between "we got lucky we had backups" and "we can always recover."

**Compare:** `GhostRooms.php:47-52` already creates `ActivityLog` per row inside its `fixRoom()` and `fixAllRooms()`. UnresolvedCheckIns should do the same.

**Fix:** inside the loop, after `$record->update(...)`:
```php
ActivityLog::create([
    'branch_id'   => $record->room->branch_id ?? auth()->user()->branch_id,
    'user_id'     => auth()->id(),
    'activity'    => 'Force-Close Unresolved Check-In',
    'description' => "Force-closed cid={$record->id} room=#{$record->room->number} guest_id={$record->guest_id} (was open since {$record->check_in_at})",
]);
```

---

## Issue 5 — **MEDIUM** — Guard-status detection parses source files

**File:** `app/Http/Livewire/Admin/UnresolvedCheckIns.php`
**Lines:** 24-39 (`checkGuardsStatus()`)

```php
$content = file_get_contents($kioskFile);
$this->guardsEnabled = preg_match('/^\s*\$openCheckin\s*=\s*CheckinDetail::where/m', $content) === 1;
```

This reads `app/Http/Livewire/Kiosk/CheckIn.php` from disk and regex-matches its source code to decide whether to display "Guards Enabled" or "Guards Disabled" in the UI. Reasons this is wrong:

- **Couples runtime UI to source code text.** A harmless rename or whitespace change silently flips the badge.
- **Doesn't survive opcache / view caching** — when OPcache compiles the kiosk file, `file_get_contents` still works, but in some FPM tuning the file mtime + opcache could drift.
- **No actual *runtime* guarantee.** A frontdesk can see a green "Enabled" badge while the guard is, in fact, bypassed by some other code path.

**Fix:** use a config flag (`config('hotelv2.kiosk_guards_enabled')`) or a feature-flag DB row. Tie the displayed badge to the same value the guard code reads.

---

## Issue 6 — **MEDIUM** — Misleading help text on the page

**File:** `resources/views/livewire/admin/unresolved-check-ins.blade.php` (referenced from `UnresolvedCheckIns.php`)

The page tells the operator:

> Effects on reports: are accurate · Deposits treated as forfeited/received · Room Status will be reset · New check-ins safe.

This copy is only true if every record returned by the query is actually a ghost. With Issue 1 unfixed, the page is showing this reassurance over a list that includes active guests. The operator on 2026-04-28 read this text and clicked the button.

**Fix:** rewrite the copy to be conditional, and refuse to render the "Fix All" button at all if any returned record's room is currently `Occupied` AND `check_out_at` is in the future. Force the operator to manually deselect each ambiguous row first.

---

## Issue 7 — **MEDIUM** — `GhostRooms` action lacks branch isolation

**File:** `app/Http/Livewire/Admin/GhostRooms.php`
**Lines:** 26-55 (`fixRoom`), 57-81 (`fixAllRooms`)

The query in `getGhostRoomsProperty()` correctly scopes to the current admin's branch (line 21). But the action methods don't:

```php
public function fixRoom($roomId)
{
    $room = Room::find($roomId);  // ← unscoped lookup
    ...
}
```

A non-superadmin admin could (in theory) pass any `$roomId` from another branch to `fixRoom` via crafted Livewire request and flip its status. Same risk in `fixAllRooms` since it iterates `$this->ghostRooms` (which is scoped) but doesn't re-verify branch on each `Room::update`.

**Fix:** scope the lookup:
```php
$room = Room::where('id', $roomId)
    ->when(!auth()->user()->hasRole('superadmin'),
           fn($q) => $q->where('branch_id', auth()->user()->branch_id))
    ->first();
```

---

## Issue 8 — **LOW** — `GhostRooms::fixAllRooms` reads `$this->ghostRooms` twice

**File:** `app/Http/Livewire/Admin/GhostRooms.php`
**Lines:** 59 (`$ghostRooms = $this->ghostRooms;`)

`ghostRooms` is a computed property — every read re-runs the query. The `getGhostRoomsProperty` query is also called by `render()` whenever Livewire repaints. Not a correctness bug, just wasted DB calls.

**Fix:** cache once per request, e.g. `@computed` style or a private memoized field.

---

## Issue 9 — **LOW** — No transactional bracket around `GhostRooms::fixAllRooms`

**File:** `app/Http/Livewire/Admin/GhostRooms.php`
**Lines:** 57-81

`UnresolvedCheckIns::fixAllGhostRecords()` correctly wraps its loop in `DB::beginTransaction() / commit()`. `GhostRooms::fixAllRooms()` does not. If one of the `ActivityLog::create()` calls fails mid-loop, you end up with N rooms updated, M activity logs, where M < N — partial state.

**Fix:** wrap in `DB::transaction(function () { ... });`.

---

## Issue 10 — **LOW** — Dialog message inflation

**File:** `app/Http/Livewire/Admin/UnresolvedCheckIns.php`
**Lines:** 124-126

```php
$this->dialog()->success(
    'Ghost Records Fixed',
    "Successfully resolved {$fixedCount} ghost check-in records. ..."
);
```

If the action force-closed 30 rows but 10 of them shouldn't have been closed, the operator sees a green success dialog with no indication of risk. They got that dialog on 2026-04-28 and walked away. The visible damage was discovered only later.

**Fix:** after Issues 1-3 are addressed, this becomes a non-issue. Until then, change the copy to: "Fixed {$fixedCount} record(s). **Please verify Room Monitoring before continuing.**"

---

## Suggested fix sequence (by priority)

| # | Issue | Why first |
|---|---|---|
| 1 | Fix the query (Issue 1) | Stops new false-positives immediately. |
| 2 | Add per-row guard (Issue 2) | Defence in depth in case of future query regression. |
| 3 | Add ActivityLog (Issue 4) | Future incidents become recoverable from in-DB log. |
| 4 | Migrate `force_closed_at`/`force_closed_by` (Issue 3) | Adds audit columns, allows safer force-close that keeps `check_out_at`. |
| 5 | Branch-scope GhostRooms actions (Issue 7) | Prevents multi-branch leakage. |
| 6 | Wrap GhostRooms in transaction (Issue 9) | Prevents partial state. |
| 7 | Rewrite help-text + dialog (Issues 6, 10) | UX clarity once correctness is fixed. |
| 8 | Replace file-content guard check (Issue 5) | Stops subtle UI lying. |
| 9 | Memoize `ghostRooms` (Issue 8) | Performance polish. |

Issues 1, 2, 3, 4 should ship together. The rest can ship incrementally.

---

## What's NOT wrong (deliberately called out)

For honest balance — these were checked and are fine:

- **Cutoff window (1 day)** is reasonable. The bug is the query, not the threshold.
- **`fixAllGhostRecords` `DB::beginTransaction` / `commit`** is correctly bracketed.
- **`GhostRooms::getGhostRoomsProperty` branch scoping** is correct (it's the actions that miss it).
- **No SQL injection vector** — the `whereRaw` uses parameter binding correctly.
- **No N+1 eager-load issue** in either component (eager loads `room`, `room.type`, `room.floor`, `guest`).
- **No race condition** with the kiosk path, because `fixAllGhostRecords` only writes `is_check_out` and `check_out_at`; the kiosk's invariant guard (Issue 7 from `docs/bugs/2026-04-23-ghost-checkin-races-room-reuse.md`) is the right place to fix kiosk races, not here.

The two files together are not architecturally broken — they just have one critical query bug, one missing safety net, and a few hardening gaps. A single focused PR can clean all of it up.
