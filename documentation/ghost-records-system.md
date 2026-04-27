# Ghost Records System Documentation

This document explains the ghost records problem, how it occurs, and the solution implemented to fix and prevent it.

---

## Table of Contents

1. [What are Ghost Records?](#what-are-ghost-records)
2. [How Ghost Records Happen](#how-ghost-records-happen)
3. [Impact on Operations](#impact-on-operations)
4. [Solution Overview](#solution-overview)
5. [Admin Page: Unresolved Check-Ins](#admin-page-unresolved-check-ins)
6. [Guard System](#guard-system)
7. [What the Fix Does](#what-the-fix-does)
8. [Technical Details](#technical-details)

---

## What are Ghost Records?

A **ghost record** is a check-in record where:
- The guest has physically left the hotel
- But the system still shows them as "checked in" (`is_check_out = FALSE`)
- The room was already cleaned and reused by other guests

### Example Scenario

| Time | Event | Room Status | Record Status |
|------|-------|-------------|---------------|
| Mar 11, 5PM | PARBA checks in via kiosk for 6 hours | Occupied | is_check_out = FALSE |
| Mar 11, 11PM | PARBA leaves without going to frontdesk | Still Occupied | is_check_out = FALSE |
| Mar 12, 8AM | Roomboy cleans room, marks as Available | Available | is_check_out = FALSE (GHOST!) |
| Mar 12, 10AM | JUAN checks in via kiosk to same room | Occupied | New record created |

**Result:** PARBA's record is now a "ghost" - the system thinks PARBA is still checked in even though JUAN is now using the room.

---

## How Ghost Records Happen

Ghost records occur when **room status** and **check-in record** get out of sync. There are three main causes:

### 1. Guest Walks Out
- Guest leaves the hotel without going to the frontdesk
- No checkout is processed
- Room status eventually changes (roomboy cleans it)
- But the check-in record stays open

### 2. Roomboy Finishes Cleaning
- Roomboy marks room as "Available" after cleaning
- System doesn't check if previous guest was properly checked out
- Room becomes available for new guests while old record is still open

### 3. Admin Status Change
- Admin manually changes room status (e.g., to Maintenance then back to Available)
- Guest record is not updated during manual status changes
- Room becomes available while old check-in record remains open

---

## Impact on Operations

### Without Guards Enabled

| Problem | Impact |
|---------|--------|
| Wrong guest counts | Reports show inflated numbers (ghosts counted as current guests) |
| Stuck deposits | Deposits from ghost records are unaccounted |
| Corrupted room history | Room shows multiple overlapping check-ins |
| Inaccurate revenue reports | Stay duration calculations are wrong |

### With Guards Enabled (But Ghost Records Not Fixed)

| Problem | Impact |
|---------|--------|
| Blocked rooms | Rooms with ghost records cannot be selected in kiosk |
| Blocked cleaning | Roomboy cannot mark rooms as cleaned |
| Operational disruption | Staff cannot use affected rooms until fixed |

---

## Solution Overview

The solution has two parts:

### Part 1: Admin Page
- View all ghost records in one place
- See which rooms are affected
- One-click fix to resolve all ghost records
- Clear explanation of what the fix does

### Part 2: Guard System
- Prevents new ghost records from forming
- Blocks kiosk check-in if room has unresolved previous guest
- Blocks roomboy from marking room as cleaned if unresolved guest exists
- Forces proper checkout before room can be reused

---

## Admin Page: Unresolved Check-Ins

### Location
- **URL:** `/admin/unresolved-check-ins`
- **Sidebar:** Red "Unresolved" button (only appears when ghost records exist)

### Features

#### Summary Bar
Shows at-a-glance statistics:
- Total ghost records count
- Total stuck deposits amount
- Number of rooms affected
- Guard status (Enabled/Disabled)

#### Ghost Records Explanation
Brief description of what ghost records are and how they happen.

#### Affected Rooms List
Shows which rooms have ghost records with:
- Room number
- Floor number
- Warning that these will be blocked if guards are enabled

#### What Happens When Fixed
Clear explanation of:
- Database changes that will occur
- Effect on reports
- That new check-ins are NOT affected

#### Ghost Records Table
Detailed list showing:
- Room number and floor
- Current room status
- Guest name
- Original check-in date/time
- Expected checkout date/time
- Days overdue
- Deposit amount

#### Fix Button
- Requires confirmation before executing
- Shows summary of what will be fixed
- Processes all ghost records in a single transaction

### Sidebar Button Behavior
- **Visible:** Only when ghost records exist (count > 0)
- **Design:** Red animated gradient to draw attention
- **Badge:** Shows count of ghost records
- **Auto-hide:** Disappears after all records are fixed

---

## Guard System

Guards are code blocks that prevent operations on rooms with unresolved check-in records.

### Guard Locations

| File | Method | Purpose |
|------|--------|---------|
| `app/Http/Livewire/Kiosk/CheckIn.php` | Check-in process | Blocks kiosk check-in |
| `app/Http/Livewire/Roomboy/Main.php` | `finishCleaning()` | Blocks finish cleaning |
| `app/Http/Livewire/Roomboy/Index.php` | `finishCleaning()` | Blocks finish cleaning |

### How Guards Work

```php
// Check for unresolved previous guest
$openCheckin = CheckinDetail::where('room_id', $room->id)
    ->where('is_check_out', false)
    ->first();

if ($openCheckin) {
    // Block the operation and show error
    $this->dialog()->error(
        'Cannot proceed',
        "Room has unresolved previous guest: {$guestName}. Please contact front desk."
    );
    return;
}
```

### Guard Messages

**Kiosk Check-in:**
> "Room has unresolved previous guest: {name} (checked in {date}). Please contact the front desk."

**Roomboy Finish Cleaning:**
> "Room has unresolved previous guest: {name} (checked in {date}). Front desk must check out first."

---

## What the Fix Does

### Database Changes

When "Fix All" is clicked, for each ghost record:

```sql
UPDATE checkin_details
SET is_check_out = 1,
    check_out_at = '{expected_checkout + 30 minutes}'
WHERE id = {record_id}
```

### Important Notes

| Aspect | Behavior |
|--------|----------|
| Checkout time | **Backdated** to expected checkout + 30 mins (NOT today's date) |
| Stay duration | Preserved accurately based on original check-in |
| New check-ins | NOT affected |
| Current guests | NOT affected (only records 1+ day overdue are fixed) |
| Deposits | Marked as resolved/forfeited |

### Example

| Field | Before Fix | After Fix |
|-------|------------|-----------|
| is_check_out | FALSE | TRUE |
| check_out_at | NULL | Mar 11, 2026 11:30 PM |

*Guest checked in Mar 11 at 5PM for 6 hours = expected out at 11PM + 30 min buffer = 11:30 PM*

---

## Technical Details

### Ghost Record Detection Query

Records are considered "ghost" if:
- `is_check_out = FALSE` (not checked out)
- Expected checkout was more than 1 day ago

```php
CheckInDetail::where('is_check_out', 0)
    ->whereRaw('DATE_ADD(check_in_at, INTERVAL number_of_hours HOUR) < ?', [now()->subDays(1)])
    ->get();
```

### Affected Room Detection

A room "will be blocked" if:
- It has a ghost record
- Current room status is: `Available`, `Uncleaned`, or `Cleaning`

Rooms with status `Occupied` are not flagged as "will block" because they already have a current guest.

### Files Modified

| File | Changes |
|------|---------|
| `app/Http/Livewire/Admin/UnresolvedCheckIns.php` | New Livewire component |
| `resources/views/livewire/admin/unresolved-check-ins.blade.php` | Admin page view |
| `routes/admin.php` | Added route |
| `resources/views/components/admin-layout.blade.php` | Added sidebar link |
| `app/Http/Livewire/Kiosk/CheckIn.php` | Enabled guard |
| `app/Http/Livewire/Roomboy/Main.php` | Enabled guard |
| `app/Http/Livewire/Roomboy/Index.php` | Enabled guard |

---

## Recommended Workflow

### Initial Cleanup

1. Go to `/admin/unresolved-check-ins`
2. Review the list of ghost records
3. Click "Fix All" and confirm
4. Verify the sidebar button disappears (no more ghosts)

### Ongoing Prevention

With guards enabled, the system will:
1. Block kiosk check-in if room has unresolved guest
2. Block roomboy from finishing cleaning if room has unresolved guest
3. Force frontdesk to properly check out guests before room reuse

### If Ghost Records Reappear

If the sidebar button appears again:
1. Investigate how the ghost was created
2. Check if guards are still enabled in all 3 files
3. Fix the records via the admin page
4. Address the root cause

---

## Summary

| Component | Purpose |
|-----------|---------|
| Admin Page | View and fix existing ghost records |
| Guards | Prevent new ghost records from forming |
| Sidebar Button | Alert when ghost records exist |
| Fix Function | Backdate checkout to resolve records |

**After implementation:**
- Existing ghost records are resolved
- New ghosts cannot form (guards block them)
- Reports show accurate guest counts
- Room history is clean

---

*Last updated: April 27, 2026*
