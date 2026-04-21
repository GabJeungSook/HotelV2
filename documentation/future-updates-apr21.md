# Future Updates Documentation - April 21, 2026

This document contains all changes that were developed but rolled back for future deployment. These changes were in commits from `d68450a` (add total rooms) to `c501026` (kisos rooom periotyh udpate).

**Rolled back from:** `c501026`
**Rolled back to:** `52b06d9` (Fix memory exhaustion in all BackOffice reports)

---

## Table of Contents

1. [Kiosk Room Priority Update](#1-kiosk-room-priority-update)
2. [Roomboy Module Enhancements](#2-roomboy-module-enhancements)
3. [BigBoss Report Updates](#3-bigboss-report-updates)
4. [Room Monitoring Total Rooms Display](#4-room-monitoring-total-rooms-display)
5. [Guest Transaction Updates](#5-guest-transaction-updates)
6. [Migration: cleaning_by_user_id](#6-migration-cleaning_by_user_id)

---

## 1. Kiosk Room Priority Update

**Commit:** `c501026` (kisos rooom periotyh udpate)
**File:** `app/Http/Livewire/Kiosk/CheckIn.php`

### Problem Solved
- Rooms were being shown without considering usage history
- Multiple rooms per floor were displayed, causing confusion

### Changes Made

#### A. Room Selection Logic - Show 1 Room Per Floor (Least Used First)

```php
// Get all available rooms, prioritize unused rooms (last_checkin_at null or oldest)
$allRooms = Room::where('branch_id', auth()->user()->branch_id)
    ->whereTypeId($this->type_id)
    ->whereIn('status', ['Available', 'Cleaned'])
    ->whereNotIn('id', $temporaryCheckInKiosk)
    ->whereNotIn('id', $temporaryReserved)
    ->where('is_priority', true)
    ->when($this->floor_id, function ($query) {
        return $query->where('floor_id', $this->floor_id);
    })
    ->with(['type.rates', 'floor'])
    ->orderByRaw('last_checkin_at IS NOT NULL, last_checkin_at ASC')
    ->orderBy('number', 'asc')
    ->get();

// Pick only 1 room per floor (prioritizing unused/least used)
$rooms = $allRooms->groupBy('floor_id')->map(function ($floorRooms) {
    return $floorRooms->first();
})->sortBy(function ($room) {
    return $room->floor->number ?? 0;
})->values();
```

#### B. selectType() Method - Added TemporaryReserved Check

```php
$temporaryReserved = TemporaryReserved::where(
    'branch_id',
    auth()->user()->branch_id
)
    ->pluck('room_id')
    ->toArray();

// Added to whereNotIn check:
->whereNotIn('id', $temporaryReserved)
```

### Key Features
- Rooms ordered by `last_checkin_at` (NULL first, then oldest)
- Only 1 room shown per floor
- Excludes rooms in temporary reserved list
- Loads `floor` relationship for sorting

---

## 2. Roomboy Module Enhancements

**Commit:** `7e16011` (update roomboy text red and rom bultple)
**Files:**
- `app/Http/Livewire/Roomboy/Index.php`
- `app/Http/Livewire/Roomboy/Main.php`
- `app/Http/Livewire/Roomboy/CleaningHistory.php`
- `resources/views/livewire/roomboy/index.blade.php`
- `resources/views/livewire/roomboy/main.blade.php`
- `resources/views/livewire/roomboy/cleaning-history.blade.php`

### New Features

#### A. Room Model - cleaningBy Relationship

```php
// app/Models/Room.php

public function cleaningBy()
{
    return $this->belongsTo(User::class, 'cleaning_by_user_id');
}

public function scopeBeingCleanedBy($query, $userId)
{
    return $query->where('cleaning_by_user_id', $userId)->where('status', 'Cleaning');
}
```

#### B. Track Which Roomboy is Cleaning Which Room

The `cleaning_by_user_id` column tracks the user currently cleaning a room. This enables:
- Preventing multiple roomboys from claiming the same room
- Showing which roomboy is assigned to which room
- Better accountability and tracking

### Migration Required

See [Migration: cleaning_by_user_id](#6-migration-cleaning_by_user_id)

---

## 3. BigBoss Report Updates

**Commits:**
- `8b114ec` (update biggoos)
- `48798d0` (remove red text in big boss reprot)
- `7e16011` (update roomboy text red and rom bultple)

**Files:**
- `app/Http/Livewire/BackOffice/Reports/BigBossReport.php`
- `resources/views/livewire/back-office/reports/big-boss-report.blade.php`
- `resources/views/livewire/back-office/reports/big-boss-report-export.blade.php`

### Changes Made

#### A. Payment On Short - Show Description Field

**Before:**
```blade
<td>&raquo; PAYMENT ON SHORT: {{ $payment->shiftLog?->shift ?? '' }}: {{ $payment->name }}</td>
```

**After:**
```blade
<td>&raquo; PAYMENT ON SHORT: {{ $payment->shiftLog?->shift ?? '' }}: {{ $payment->name }}@if($payment->description) - {{ $payment->description }}@endif</td>
```

#### B. Removed Red Text Styling

Various text that was styled in red was changed to normal styling for better readability.

---

## 4. Room Monitoring Total Rooms Display

**Commit:** `d68450a` (add total rooms)
**File:** `resources/views/livewire/frontdesk/monitoring/room-monitoring.blade.php`

### Changes Made

Added display of total room counts in the room monitoring view.

---

## 5. Guest Transaction Updates

**Commit:** `9cbfd63` (update the stagine)
**Files:**
- `resources/views/livewire/frontdesk/monitoring/guest-transaction.blade.php`
- `app/Http/Livewire/Frontdesk/Monitoring/GuestTransaction.php`
- `app/Http/Livewire/Frontdesk/Monitoring/ManageGuestTransaction.php`
- `app/Http/Livewire/Frontdesk/Monitoring/TransferRoom.php`

### Changes Made

Minor updates to guest transaction views and logic.

---

## 6. Migration: cleaning_by_user_id

**File:** `database/migrations/2026_04_21_115528_add_cleaning_by_user_id_to_rooms_table.php`

### Full Migration Code

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->foreignId('cleaning_by_user_id')
                ->nullable()
                ->after('started_cleaning_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropForeign(['cleaning_by_user_id']);
            $table->dropColumn('cleaning_by_user_id');
        });
    }
};
```

### Column Details

| Property | Value |
|----------|-------|
| Column Name | `cleaning_by_user_id` |
| Type | `BIGINT UNSIGNED` |
| Nullable | Yes |
| Foreign Key | `users.id` |
| On Delete | SET NULL |
| Position | After `started_cleaning_at` |

### Purpose

Tracks which roomboy (user) is currently cleaning a room. Used by:
- `Room::cleaningBy()` relationship
- `Room::scopeBeingCleanedBy()` query scope
- Roomboy module to prevent multiple assignments

---

## Files Changed Summary

| File | Changes |
|------|---------|
| `app/Http/Livewire/Kiosk/CheckIn.php` | Room priority, 1 per floor |
| `app/Http/Livewire/Roomboy/Index.php` | Roomboy tracking |
| `app/Http/Livewire/Roomboy/Main.php` | Roomboy tracking |
| `app/Http/Livewire/Roomboy/CleaningHistory.php` | History updates |
| `app/Models/Room.php` | cleaningBy relationship |
| `app/Http/Livewire/BackOffice/Reports/BigBossReport.php` | Report fixes |
| `app/Http/Livewire/Frontdesk/Monitoring/GuestTransaction.php` | Minor updates |
| `app/Http/Livewire/Frontdesk/Monitoring/ManageGuestTransaction.php` | Minor updates |
| `app/Http/Livewire/Frontdesk/Monitoring/TransferRoom.php` | Minor updates |
| `database/migrations/2026_04_21_115528_...` | New migration |
| `resources/views/livewire/back-office/reports/big-boss-report.blade.php` | Description display |
| `resources/views/livewire/back-office/reports/big-boss-report-export.blade.php` | Description display |
| `resources/views/livewire/frontdesk/monitoring/guest-transaction.blade.php` | UI updates |
| `resources/views/livewire/frontdesk/monitoring/room-monitoring.blade.php` | Total rooms |
| `resources/views/livewire/roomboy/index.blade.php` | UI updates |
| `resources/views/livewire/roomboy/main.blade.php` | UI updates |
| `resources/views/livewire/roomboy/cleaning-history.blade.php` | UI updates |

---

## Deployment Checklist (For Future)

When ready to deploy these features:

- [ ] Check if `cleaning_by_user_id` column exists on staging (may already be there)
- [ ] Run migration or skip if column exists
- [ ] Deploy all related PHP files
- [ ] Deploy all related Blade files
- [ ] Clear all caches
- [ ] Test Kiosk room selection
- [ ] Test Roomboy module
- [ ] Test BigBoss report
- [ ] Test Room monitoring

---

## Git Commands to Restore These Changes

If you need to see the exact changes:

```bash
# View all changes
git diff 52b06d9..c501026

# View specific file changes
git diff 52b06d9..c501026 -- app/Http/Livewire/Kiosk/CheckIn.php

# Cherry-pick specific commits later
git cherry-pick d68450a  # add total rooms
git cherry-pick 9cbfd63  # update the stagine
git cherry-pick 7e16011  # roomboy updates
git cherry-pick 8b114ec  # bigboss updates
git cherry-pick 48798d0  # remove red text
git cherry-pick c501026  # kiosk priority
```

---

**Document Created:** April 21, 2026
**Author:** Development Team
**Purpose:** Preserve rolled-back features for future deployment
