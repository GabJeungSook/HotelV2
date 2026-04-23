# Data Repair: Vee Meelita ghost checkout

**Date:** 2026-04-23 20:23:18
**Executor:** superadmin (`users.id = 1`) via `php artisan tinker`
**Database:** `hotelv2`
**Reason:** Client request — close stuck kiosk check-in before shift handover so the next shift inherits a clean dashboard.

## Summary

Guest **Vee Meelita** (id 13218) checked in to **Room #68** via kiosk on **2026-04-22 17:35:55** and never used the room. She abandoned the stay. Her `checkin_details` row (id 11139) remained with `is_check_out = 0` while 3 later guests (Han, Haron, Larry) came and went in the same room. This patch closes her record via direct DB write in tinker, bypassing the app's UI which can't reach her record.

### Why the UI couldn't do it

1. `guests.has_kiosk_check_out = 0` blocks the "Check Out" button
2. Room Monitoring shows Larry as current occupant of room 68, not her
3. The normal `checkoutGuest()` method would overwrite Larry's `rooms` row — incorrect

## Changes made (minimal version — deposit untouched)

### 1. `checkin_details` (id = 11139)
| Column | Before | After |
|---|---|---|
| `is_check_out` | `0` | `1` |
| `check_out_at` | `2026-04-23 05:35:55` (original scheduled time) | `2026-04-23 20:23:18` (actual patch time) |

### 2. `guests` (id = 13218)
| Column | Before | After |
|---|---|---|
| `has_kiosk_check_out` | `0` | `1` |

### 3. `activity_logs` (INSERT)
New row:
- `id` = **32390**
- `branch_id` = 1
- `user_id` = 1 (superadmin)
- `activity` = `"Check Out"`
- `description` = `"Checked out guest Vee Meelita from Room #68 (manual cleanup — ghost record)"`
- `created_at` = `2026-04-23T12:23:18.000000Z` (UTC storage)

## What this patch deliberately did NOT do

- ❌ No `rooms` table change — Larry (guest 13632, checkin 11492) is the current occupant of room 51/#68
- ❌ No `check_out_guest_reports` row — no real frontdesk performed the checkout, so no shift gets credit
- ❌ No `Deduct Deposit` / forfeit transaction — the ₱555 deposit (transactions 30554 + 30555) remains open in the DB
- ❌ No change to Vee's existing transactions (30553 check-in ₱400, 30554 deposit ₱200, 30555 deposit ₱355)

## Known leftover state

- **₱555 open deposit** — transactions 30554 (`Deposit From Check In (Room Key & TV Remote)` ₱200) and 30555 (`Deposit From Check In (Excess Amount)` ₱355) are still unreturned/undeducted. May appear on unreturned-deposit reports. Client accepted this.
- `guests.room_id = 51` — Vee's guest row still points to room 51. Not harmful because `is_check_out = 1` on her checkin and room 51 belongs to Larry by checkin_details chronology.

## Rollback procedure

If this patch turns out to be wrong, **restore the exact prior state** by running this in `php artisan tinker` on the same DB:

```php
use App\Models\Guest;
use App\Models\CheckinDetail;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

DB::transaction(function () {
    // 1. Revert the checkin_details row
    $affected = CheckinDetail::where('id', 11139)
        ->where('guest_id', 13218)
        ->where('is_check_out', 1)  // safety: only revert if still in our post-state
        ->update([
            'is_check_out' => 0,
            'check_out_at' => Carbon::parse('2026-04-23 05:35:55'),
        ]);
    if ($affected !== 1) {
        throw new \RuntimeException("Expected 1 checkin_details row to revert, got {$affected} — ABORTED.");
    }

    // 2. Revert the guest row
    $affected = Guest::where('id', 13218)
        ->where('has_kiosk_check_out', 1)
        ->update(['has_kiosk_check_out' => 0]);
    if ($affected !== 1) {
        throw new \RuntimeException("Expected 1 guests row to revert, got {$affected} — ABORTED.");
    }

    // 3. Delete the audit log row we created
    $affected = ActivityLog::where('id', 32390)
        ->where('description', 'like', '%Vee Meelita%manual cleanup%')
        ->delete();
    if ($affected !== 1) {
        throw new \RuntimeException("Expected 1 activity_logs row to delete, got {$affected} — ABORTED.");
    }
});
```

### Verify rollback

```php
[
    'checkin' => \App\Models\CheckinDetail::find(11139)->only(['id','is_check_out','check_out_at']),
    'guest'   => \App\Models\Guest::find(13218)->only(['id','name','has_kiosk_check_out']),
    'activity'=> \App\Models\ActivityLog::find(32390),
];
```

Expected after rollback:
- `checkin.is_check_out = 0`
- `checkin.check_out_at = "2026-04-23 05:35:55"`
- `guest.has_kiosk_check_out = 0`
- `activity = null` (row deleted)

## Rollback risks

1. **Reverting re-creates the ghost.** The dashboard will show Vee as active again, and the shift-handover problem returns. Only roll back if the patch was the wrong call — not to "try again cleanly."
2. **If `activity_logs.id = 32390` was deleted or its description changed by something else**, the DELETE asserts will fail and the whole transaction aborts. Investigate before re-running.
3. **If another fix has been applied on top** (e.g., someone later added a forfeit transaction for the ₱555), rolling back only undoes this patch, not the follow-up. The ₱555 forfeit row, if any, would remain and point to a re-opened checkin. Check for additional transactions on `guest_id = 13218` before rolling back:
   ```php
   \App\Models\Transaction::where('guest_id', 13218)->orderBy('id')->get(['id','transaction_type_id','description','remarks','created_at'])->toArray();
   ```
   If there are more than 3 transactions, a follow-up patch was applied — review before rolling back.

## Underlying bug (separate work item)

At **2026-04-22 20:58:03**, the kiosk allowed Han (guest 13330) to check into room 68 while `checkin_details#11139` was still open. The check-in precondition only inspects `rooms.status`, not open `checkin_details`.

**Suggested fix (not included in this patch):**
1. In kiosk/frontdesk check-in logic, refuse check-in if `CheckinDetail::where('room_id', $roomId)->where('is_check_out', 0)->exists()`.
2. In `app/Http/Livewire/Roomboy/Index.php::finishCleaning()` (line ~147), refuse flipping `rooms.status` to `Available` if any open checkin_details exist for that room.

This patch does not prevent recurrence. File a separate ticket.
