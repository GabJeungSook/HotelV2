# Production Incident Report

**System:** HotelV2 — Hotel Management System (Laravel 9 + Livewire 2)
**Branch affected:** ALMA Residences GenSan (branch_id = 1)
**Incidents occurred:** April 24, 2026
**Reported by:** Frontdesk / Back-Office team
**Fixes deployed:** April 24–25, 2026
**Status:** ✅ RESOLVED

---

## Executive Summary

Two separate production issues occurred on the same day at ALMA Residences GenSan branch. Both were **display/reporting bugs** — no data was lost, corrupted, or altered in the database. Both have been patched on `master` and pushed to the production server.

| # | Issue | Severity | Status |
|---|---|---|---|
| 1 | Kiosk not displaying available rooms (empty / almost empty grid) | 🔴 CRITICAL | ✅ FIXED — commit `fcaa8ff` |
| 2 | Roomboy penalty report showing duplicate rows and inflated totals | 🟠 HIGH | ✅ FIXED — commit `11ceb51` |
| 2b | Z-Read cleaning chart showing false 20–50h red elapses (same root cause as #2) | 🟠 HIGH | ✅ FIXED — commit `0b26eb4` |

---

## Incident #1 — Kiosk Not Displaying Available Rooms

### Symptoms Reported

- Frontdesk saw **11 rooms available** in their system at ALMA.
- The **kiosk** (guest self-service terminal) displayed **no rooms** for selection.
- **Impact:** Guests could not self-check-in; staff had to manually process every arrival.

### Investigation

Querying the production database (locally copied) against the filters used by the kiosk render logic revealed:

- At ALMA, **16 rooms** had status `Available` or `Cleaned`.
- The kiosk's query was excluding **15 of them** due to a filter called `$pendingGuestRooms`.
- That filter excluded any room that had a `Guest` record without a matching `CheckinDetail` (an "orphaned" kiosk check-in).
- **Crucially:** the filter had **no time limit**. Orphan Guest records from days, weeks, or months earlier were still blocking the rooms they referenced.

### Root Cause

> The kiosk's anti-double-check-in guard filter was too aggressive.
>
> It was designed to prevent a race condition where a guest could be selected in the kiosk before the frontdesk confirmed the check-in. But because the filter looked at *all historical* orphan Guest records (not just recent ones), accumulated stale orphans — especially "money-attached" orphans that the cleanup job cannot safely delete — permanently blocked real available rooms.

### Why Orphan Guest Records Accumulate

Orphan guests are created when:

- A guest starts a kiosk check-in → a `Guest` record is created with a hold.
- The frontdesk never confirms the check-in (doesn't create `CheckinDetail`).
- The hold eventually expires.

The scheduled cleanup job (`kiosk:cleanup`, runs every minute) deletes these orphans — **except** orphans that have already generated real `transactions` (real money). Those must be preserved for audit. Over time, these money-attached orphans permanently block the rooms they referenced.

### Fix Applied

**File:** `app/Http/Livewire/Kiosk/CheckIn.php`
**Commit:** `fcaa8ff`

Scoped the `$pendingGuestRooms` filter to the last 2 hours only. This preserves the race-condition guard (the kiosk:cleanup job runs every minute, so 2 hours is far wider than any legitimate race) while preventing stale historical orphans from blocking available rooms.

```php
// BEFORE (unbounded — caused the bug):
$pendingGuestRooms = Guest::where('branch_id', $branchId)
    ->whereDoesntHave('checkInDetail')
    ->pluck('room_id')
    ->toArray();

// AFTER (2-hour scope — the fix):
$pendingGuestRooms = Guest::where('branch_id', $branchId)
    ->whereDoesntHave('checkInDetail')
    ->where('created_at', '>=', now()->subHours(2))
    ->pluck('room_id')
    ->toArray();
```

### Verification

Re-ran the kiosk query against the same production data snapshot. ALMA goes from **1 room visible** to **12 rooms visible**, matching frontdesk's count. All other safety filters (occupied status, temporary holds, priority flag) remain enforced.

---

## Incident #2 — Penalty Report Showing Duplicate Rows

### Symptoms Reported

- Roomboy Penalty Report displayed the **same guest + checkout time + room** appearing multiple times with different "cleaning end" timestamps.
- Penalty totals were inflated (e.g., 5 rows reported, 2 were duplicates).
- Same pattern across multiple roomboys (Chris Baran, George Mendoza, Moises Tuazon, etc.).
- **Example:** Room 20 — Rex Siodina, check-out 8:52 PM, appeared 3 times with cleanings at 11:23, 12:07, and 1:36 PM.

### Investigation

The penalty calculation runs in two steps:

1. **Step 1** — match cleanings to checkouts that happened *during this shift*.
2. **Step 2** — for leftover "orphan" cleanings, reach back to checkouts from *before this shift* and attribute the cleaning to the most recent one.

Querying the database revealed that the duplicates always fit the same pattern:

- Room had a guest check out **the day before** (e.g., Rex — previous day).
- Another guest occupied the room next (e.g., Honney).
- That second guest checked out and their cleaning happened *within* the reporting shift.
- Extra cleanings later in the shift (re-cleans) existed — these had no in-shift checkout to match.
- Step 2 incorrectly attributed these extra cleanings to the **pre-shift guest** (Rex), producing multiple penalty rows for one guest.

### Root Cause

> Step 2 only considered checkouts from *before the shift* when attributing orphan cleanings.
>
> It did not check whether another guest had *occupied the room in between* the pre-shift checkout and the cleaning. Because the in-shift checkout (e.g., Honney) was not in Step 2's candidate list, the algorithm reached back to the older pre-shift checkout (Rex) even though Rex had long since left and another stay had occurred.

### Important: the database was never wrong

Every check-in, check-out, and cleaning record in the database is correct and represents real hotel activity. The bug was purely in the report's attribution algorithm — it was telling the wrong story about correct data.

### Fix Applied

**File:** `app/Http/Livewire/BackOffice/Reports/RoomBoyReport.php`
**Commit:** `11ceb51`

Added an **intervening-checkin guard**. Before attributing an orphan cleaning to a pre-shift checkout, verify that no other guest checked in between that checkout and the cleaning's end-time. If a later guest occupied the room in between, the cleaning is a re-clean for that later stay — not a late cleanup for the earlier guest — and is skipped.

```php
// Added guard (simplified):
$hasInterveningCheckin = $roomCheckins->contains(function ($c) use ($checkOutAt, $cleaningEnd) {
    if (!$c->check_in_at) return false;
    $checkInAt = Carbon::parse($c->check_in_at);
    return $checkInAt->gt($checkOutAt) && $checkInAt->lt($cleaningEnd);
});

if ($hasInterveningCheckin) {
    continue; // skip — this cleaning was for a later stay, not this one
}
```

### Verification

Re-ran the penalty calculation against the same April 24 shift data. **34 false-positive rows suppressed**. All reported duplicates disappeared (Christine Batallar x2, Rex Siodina x3, Roger Malmis x2, ESTAURA x2, and others). No legitimate penalties were lost — genuine >4-hour cleaning delays are still reported correctly via Step 1.

---

## Incident #2b — Z-Read Cleaning Chart Showing False Red Elapses

### Relationship to Incident #2

The Bigboss Z-Read's *Room Cleaning Chart* uses the same orphan-attribution logic as the Roomboy Penalty Report, so it inherited the same bug. On the April 24 shift, the chart showed **34 rooms with red "elapse" times of 20–50 hours**, suggesting massive cleaning delays. This misled management into believing roomboys had chronic performance issues.

### Root Cause

Same as Incident #2 — orphan cleanings were being attributed to pre-shift checkouts without checking for intervening occupancy. When the misattribution involved a guest from a day earlier, the computed elapse was multi-day (hence the 30–50 hour red numbers).

### Fix Applied

**File:** `app/Http/Livewire/BackOffice/Reports/BigBossReport.php`
**Commit:** `0b26eb4`

Same intervening-checkin guard applied to two places in `buildRoomCleaningChart()`: the forwarded-guest delayed-cleaning block and the orphan-cleanings loop. When an intervening check-in exists, the Elapse column is left blank instead of showing a false multi-day red value. The cleaning row still appears (its Time and Status columns are unchanged).

### Verification

Re-ran Z-Read chart logic against the April 24 data. **34 false red rows cleared**, matching exactly the 34 rows suppressed by the Roomboy Penalty Report fix.

Only **2 genuine red elapses remain** on the April 24 AM shift:

| Room | Guest | Duration |
|---|---|---|
| Room 4 | John Paul Soriano | 4h 48m |
| Room 212 | Marlon Ubal Ubal | 11h 46m |

These match the 2 penalties shown in the Roomboy Penalty Report. **The two reports are now aligned.**

---

## Deployment Summary

| Commit | Purpose | File Changed |
|---|---|---|
| `fcaa8ff` | Kiosk empty-display hotfix (2-hour orphan scope) | `app/Http/Livewire/Kiosk/CheckIn.php` |
| `11ceb51` | Roomboy Penalty Report intervening-checkin guard | `app/Http/Livewire/BackOffice/Reports/RoomBoyReport.php` |
| `0b26eb4` | Z-Read cleaning chart intervening-checkin guard | `app/Http/Livewire/BackOffice/Reports/BigBossReport.php` |

All three fixes are pushed to `origin/master` and propagated to `feature/kiosk-room-sequence` and `future-updates` to prevent future merge conflicts.

### Production Deployment Steps

```bash
cd /var/www/HotelV2
git pull origin master
php artisan config:clear
php artisan view:clear
```

No database migrations required. Changes take effect immediately after cache clearing.

---

## Key Takeaways

- **Zero data loss.** Every one of these incidents was a display/report bug. The database records are accurate and complete.
- **Nothing was tampered with.** No check-ins, checkouts, deposits, or cleaning records were changed, deleted, or modified.
- **Both bugs shared a common pattern:** algorithms reaching back to older records without verifying no newer activity had occurred in between. The fixes add that verification.
- **Roomboy penalty totals and Z-Read cleaning chart now agree.** For the April 24 AM shift, both correctly show 2 genuine penalties totaling ₱550.
- **The Kiosk fix is fully backwards-compatible.** The cleanup cron continues to run every minute, and the 2-hour scope is far wider than any legitimate race window — so the double-check-in protection remains intact.

---

## Prevention / Recommendations

- **Monitoring.** Track the count of orphan Guest records (those with no matching CheckinDetail) per branch. A sudden increase signals either broken kiosk flow or cleanup job failure.
- **Scheduled cleanup health-check.** Ensure the `kiosk:cleanup` artisan command continues to run every minute in production. A silent failure would slowly accumulate orphans again.
- **Report parity check.** Roomboy Penalty Report totals should match the count of red elapses in the Z-Read cleaning chart for any given shift. If they ever diverge, one of the two is wrong — now a useful correctness signal.
- **Money-attached orphan investigation.** Consider a periodic reconciliation job that flags orphan Guests with real transactions for back-office review. These represent either incomplete check-ins or aborted kiosk flows that still took a deposit. They should be resolved at the operations level rather than sitting indefinitely in the database.

---

*Report generated April 25, 2026 · HotelV2 production incident documentation · Fixes verified against a local copy of the production database.*
