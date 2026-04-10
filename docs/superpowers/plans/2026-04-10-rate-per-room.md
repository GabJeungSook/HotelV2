# Rate Per Room Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Change the rate system from "per bed type" to "per room per staying hour" so each room can have its own pricing.

**Architecture:** Add `room_id` column to `rates` table alongside existing `type_id` (safe, additive — never drop `type_id`). Backfill by copying each type's rates to every room of that type. Then update all queries to filter by `room_id` instead of `type_id`. The system remains functional throughout because `type_id` is never removed.

**Tech Stack:** Laravel 9, Livewire 2, MySQL, PHPUnit

**Safety:** The system is running in production. All changes are additive — `type_id` stays on the `rates` table as a denormalized field. If anything breaks, reverting the code changes restores the old behavior instantly since `type_id` data is untouched.

---

## File Map

| Action | File | Responsibility |
|--------|------|----------------|
| Modify | `database/migrations/NEW_add_room_id_to_rates_table.php` | Add room_id, backfill from type→rooms |
| Modify | `app/Models/Rate.php` | Add room() relationship, keep type() |
| Modify | `app/Models/Type.php` | Keep rates() for backward compat |
| Modify | `app/Http/Livewire/Admin/Manage/Rate.php` | CRUD grouped by room instead of type |
| Modify | `resources/views/livewire/admin/manage/rate.blade.php` | UI: room selector instead of type |
| Modify | `app/Http/Livewire/Kiosk/CheckIn.php` | Rate lookup by room_id |
| Modify | `app/Http/Livewire/Admin/CheckInCo.php` | Rate lookup by room_id |
| Modify | `resources/views/livewire/admin/check-in-co.blade.php` | Remove "Select Type First" gate |
| Modify | `app/Http/Livewire/Frontdesk/Monitoring/RoomMonitoring.php` | Rate lookup by room_id |
| Modify | `app/Http/Livewire/Frontdesk/Monitoring/CheckInFromKiosk.php` | Rate via room_id |
| Modify | `app/Http/Livewire/Frontdesk/Monitoring/TransferRoom.php` | New room rate by room_id |
| Modify | `app/Http/Livewire/Frontdesk/Monitoring/ExtendGuest.php` | Extension rate by room_id |
| Modify | `app/Http/Livewire/Frontdesk/Monitoring/GuestTransaction.php` | Transfer rate by room_id |
| Modify | `app/Http/Controllers/API/RateController.php` | Accept room_id param |
| Modify | `app/Http/Controllers/API/CheckInController.php` | Keep type_id on guest |
| Modify | `app/Http/Controllers/API/FloorController.php` | Eager load room.rates |
| Modify | `tests/Feature/BackOffice/SalesReportV2Test.php` | Update rate creation |

---

### Task 1: Database Migration (Safe, Additive)

**Files:**
- Create: `database/migrations/2026_04_10_000000_add_room_id_to_rates_table.php`

- [ ] **Step 1: Create migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Step 1: Add room_id as nullable (safe — doesn't break existing queries)
        Schema::table('rates', function (Blueprint $table) {
            $table->unsignedBigInteger('room_id')->nullable()->after('type_id');
            $table->foreign('room_id')->references('id')->on('rooms')->nullOnDelete();
        });

        // Step 2: Backfill — for each existing rate (type_id + staying_hour),
        // create a copy for every room of that type.
        // Keep the original rate rows intact (type_id stays populated).
        $existingRates = DB::table('rates')->whereNull('room_id')->get();

        foreach ($existingRates as $rate) {
            $rooms = DB::table('rooms')
                ->where('type_id', $rate->type_id)
                ->where('branch_id', $rate->branch_id)
                ->get();

            if ($rooms->isEmpty()) {
                continue;
            }

            // Assign the first room to the existing rate row
            $firstRoom = $rooms->shift();
            DB::table('rates')->where('id', $rate->id)->update(['room_id' => $firstRoom->id]);

            // Create new rate rows for the remaining rooms
            foreach ($rooms as $room) {
                DB::table('rates')->insert([
                    'branch_id' => $rate->branch_id,
                    'staying_hour_id' => $rate->staying_hour_id,
                    'type_id' => $rate->type_id,
                    'room_id' => $room->id,
                    'amount' => $rate->amount,
                    'is_available' => $rate->is_available,
                    'has_discount' => $rate->has_discount,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down()
    {
        // Remove duplicated rates (keep only one per type_id + staying_hour_id + branch_id)
        $groups = DB::table('rates')
            ->select('type_id', 'staying_hour_id', 'branch_id', DB::raw('MIN(id) as keep_id'))
            ->groupBy('type_id', 'staying_hour_id', 'branch_id')
            ->get();

        $keepIds = $groups->pluck('keep_id')->toArray();
        if (!empty($keepIds)) {
            DB::table('rates')->whereNotIn('id', $keepIds)->delete();
        }

        Schema::table('rates', function (Blueprint $table) {
            $table->dropForeign(['room_id']);
            $table->dropColumn('room_id');
        });
    }
};
```

- [ ] **Step 2: Run migration**

Run: `php artisan migrate`
Expected: Migration completes. Each room now has its own rate rows. Original `type_id` data is preserved.

- [ ] **Step 3: Verify backfill**

Run: `php artisan tinker --execute="echo 'Rates with room_id: '.DB::table('rates')->whereNotNull('room_id')->count().PHP_EOL.'Rates without room_id: '.DB::table('rates')->whereNull('room_id')->count().PHP_EOL.'Total rates: '.DB::table('rates')->count();"`

Expected: All rates have room_id set. Zero rates without room_id. Total = rooms × staying_hours.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_04_10_000000_add_room_id_to_rates_table.php
git commit -m "feat: add room_id to rates table with backfill from type"
```

---

### Task 2: Update Rate Model

**Files:**
- Modify: `app/Models/Rate.php`

- [ ] **Step 1: Add room relationship to Rate model**

Current code (lines 13-16):
```php
public function type()
{
    return $this->belongsTo(Type::class);
}
```

Add `room()` relationship AFTER the existing `type()` (keep `type()` for backward compatibility):
```php
public function type()
{
    return $this->belongsTo(Type::class);
}

public function room()
{
    return $this->belongsTo(Room::class);
}
```

Also add `use App\Models\Room;` at the top if not present.

- [ ] **Step 2: Run existing tests to verify no breakage**

Run: `php artisan test --filter=SalesReportV2Test`
Expected: All 20 tests pass (model change is additive).

- [ ] **Step 3: Commit**

```bash
git add app/Models/Rate.php
git commit -m "feat: add room() relationship to Rate model"
```

---

### Task 3: Update Admin Rate Management

**Files:**
- Modify: `app/Http/Livewire/Admin/Manage/Rate.php`
- Modify: `resources/views/livewire/admin/manage/rate.blade.php`

- [ ] **Step 1: Update Rate.php component — change property and queries**

In `app/Http/Livewire/Admin/Manage/Rate.php`:

**Line 28:** Change property declaration:
```php
// OLD:
public $amount, $hours_id, $type_id, $rate_id;
// NEW:
public $amount, $hours_id, $type_id, $room_id, $rate_id;
```

**Lines 59-66:** Change `getTableQuery()` to group by Room instead of Type:
```php
// OLD:
return Type::query()
    ->where('branch_id', $this->branch_id)
    ->with(['rates.stayingHour', 'rates.type']);
// NEW:
return Room::query()
    ->where('branch_id', $this->branch_id)
    ->with(['rates.stayingHour', 'type']);
```

Add `use App\Models\Room;` at the top.

**Lines 122-135:** Change `saveRate()` — use `room_id` instead of `type_id`:
```php
// OLD:
rateModel::create([
    'branch_id' => auth()->user()->hasRole('superadmin') ? $this->branch_id : auth()->user()->branch_id,
    'amount' => $this->amount,
    'staying_hour_id' => $this->hours_id,
    'type_id' => $this->type_id,
]);
// NEW:
$room = Room::find($this->room_id);
rateModel::create([
    'branch_id' => auth()->user()->hasRole('superadmin') ? $this->branch_id : auth()->user()->branch_id,
    'amount' => $this->amount,
    'staying_hour_id' => $this->hours_id,
    'type_id' => $room?->type_id,
    'room_id' => $this->room_id,
]);
```

**Line 141:** Update description:
```php
// OLD:
'description' => 'Created rate ' . $this->amount . ' for type ID ' . $this->type_id,
// NEW:
'description' => 'Created rate ' . $this->amount . ' for room ID ' . $this->room_id,
```

**Lines 242-250:** Change `editRate()`:
```php
// OLD:
$this->type_id = $rate->type_id;
// NEW:
$this->room_id = $rate->room_id;
$this->type_id = $rate->type_id;
```

**Lines 288-338:** Change `updateRates()` duplicate check — use `room_id`:
Replace all occurrences of `->where('type_id', $this->type_id)` in the duplicate checking queries with `->where('room_id', $this->room_id)`.

In the update query, also set `room_id`:
```php
// Add alongside existing type_id update:
'room_id' => $this->room_id,
'type_id' => Room::find($this->room_id)?->type_id,
```

- [ ] **Step 2: Update rate blade view — room selector instead of type**

In `resources/views/livewire/admin/manage/rate.blade.php`:

**Line 45-49:** Change table grouping header from type to room:
```blade
{{-- OLD: Groups by $type->name --}}
{{-- NEW: Groups by room number + type --}}
@foreach ($types as $room)
<tr class="bg-gray-50">
    <td colspan="6" class="px-3 py-2 text-sm font-bold text-gray-900">
        Room {{ $room->number }} ({{ $room->type->name }})
    </td>
</tr>
```

**Line 63:** Change rate display:
```blade
{{-- OLD: --}}
<td>{{ $rate->type->name }}</td>
{{-- NEW: --}}
<td>Room {{ $rate->room->number ?? '—' }} ({{ $rate->room->type->name ?? '—' }})</td>
```

**Lines 115-120 and 217-222:** Change Type dropdown to Room dropdown in create/edit modals:
```blade
{{-- OLD: --}}
<x-native-select label="Select Type" wire:model="type_id">
    <option value="">Select</option>
    @foreach ($types as $type)
        <option value="{{ $type->id }}">{{ $type->name }}</option>
    @endforeach
</x-native-select>
{{-- NEW: --}}
<x-native-select label="Select Room" wire:model="room_id">
    <option value="">Select</option>
    @foreach ($types as $room)
        <option value="{{ $room->id }}">Room {{ $room->number }} ({{ $room->type->name }})</option>
    @endforeach
</x-native-select>
```

Apply this change to BOTH the create modal AND the edit modal sections.

- [ ] **Step 3: Test manually**

Run: `php artisan serve`
Navigate to Admin → Manage Rates. Verify:
- Rates are grouped by room number
- Create rate shows room dropdown
- Edit rate loads room correctly

- [ ] **Step 4: Commit**

```bash
git add app/Http/Livewire/Admin/Manage/Rate.php resources/views/livewire/admin/manage/rate.blade.php
git commit -m "feat: admin rate management uses room_id instead of type_id"
```

---

### Task 4: Update Kiosk Check-In Flow

**Files:**
- Modify: `app/Http/Livewire/Kiosk/CheckIn.php`

- [ ] **Step 1: Change rate lookup from type_id to room_id**

**Lines 128-131** — `selectRoom()` or rate loading after room selection:
```php
// OLD:
$this->rates = Rate::whereBranchId(auth()->user()->branch_id)
    ->whereTypeId($this->type_id)
    ->with(['stayingHour'])
    ->get();
// NEW:
$this->rates = Rate::where('branch_id', auth()->user()->branch_id)
    ->where('room_id', $this->room_id)
    ->with(['stayingHour'])
    ->get();
```

**Lines 178-195** — Long stay rate lookup:
```php
// OLD (all occurrences):
->where('type_id', $this->type_id)
// NEW:
->where('room_id', $this->room_id)
```

There are TWO occurrences in the long stay section — replace both.

- [ ] **Step 2: Commit**

```bash
git add app/Http/Livewire/Kiosk/CheckIn.php
git commit -m "feat: kiosk check-in uses room_id for rate lookup"
```

---

### Task 5: Update Admin Check-In C/O

**Files:**
- Modify: `app/Http/Livewire/Admin/CheckInCo.php`
- Modify: `resources/views/livewire/admin/check-in-co.blade.php`

- [ ] **Step 1: Update CheckInCo.php — rate filter by room_id**

**Line 24:** Add property:
```php
// OLD:
public $type_id;
// NEW:
public $type_id, $room_id;
```

**Lines 38-45** — `render()` rate query:
```php
// OLD:
'rates' => Rate::where('branch_id', auth()->user()->branch_id)
    ->when($this->type_id, function ($query) {
        $query->where('type_id', $this->type_id);
    })
    ->get(),
// NEW:
'rates' => Rate::where('branch_id', auth()->user()->branch_id)
    ->when($this->room_id, function ($query) {
        $query->where('room_id', $this->room_id);
    })
    ->get(),
```

Add a `updatedRoomId()` method or wire the room selection to also set `$this->room_id`.

- [ ] **Step 2: Update check-in-co blade — rate shows after room selection**

In `resources/views/livewire/admin/check-in-co.blade.php`:

**Lines 79-90:** Change the rate dropdown condition:
```blade
{{-- OLD: --}}
@if($type_id)
    @foreach ($rates as $rate)
    ...
    @endforeach
@else
    <option value="" disabled>Select Room Type First</option>
@endif
{{-- NEW: --}}
@if($room_id)
    @foreach ($rates as $rate)
    ...
    @endforeach
@else
    <option value="" disabled>Select Room First</option>
@endif
```

- [ ] **Step 3: Commit**

```bash
git add app/Http/Livewire/Admin/CheckInCo.php resources/views/livewire/admin/check-in-co.blade.php
git commit -m "feat: admin check-in uses room_id for rate lookup"
```

---

### Task 6: Update Frontdesk Components (RoomMonitoring, CheckInFromKiosk)

**Files:**
- Modify: `app/Http/Livewire/Frontdesk/Monitoring/RoomMonitoring.php`
- Modify: `app/Http/Livewire/Frontdesk/Monitoring/CheckInFromKiosk.php`

- [ ] **Step 1: Update RoomMonitoring.php**

This file has MANY rate references. All `Rate::where('type_id', ...)` lookups must change to `Rate::where('room_id', ...)`.

Specific changes (search and replace within this file):
- Every `->where('type_id', $this->type_id)` on Rate queries → `->where('room_id', $this->room_id)` (or the appropriate room variable)
- Guest/CheckinDetail creation still stores `type_id` (get it from room: `$room->type_id`)

Key locations:
- **Line 580-588** `checkIn()`: Rate lookup — already uses `$this->guest->rate_id` (no change needed)
- **Line 623-633** `checkInReserve()`: Same pattern (no change needed)
- **Line 652-654** `updatedRateId()`: Uses `Rate::where('id', $this->rate_id)` (no change needed — already by ID)
- **Lines 697-699** `storeGuest()`: Keep `type_id` on Guest (get from room)
- **Lines 862-864** `saveCheckInDetails()`: Keep `type_id` on CheckinDetail (get from guest)

The main change is in the rate SELECTION flow where rooms are picked and rates are filtered.

- [ ] **Step 2: Update CheckInFromKiosk.php**

**Line 67-68:** Already uses `$this->guest->rate_id` to find rate (no change needed).

**Line 192:** Keep `type_id` on CheckinDetail (it's denormalized from guest):
```php
'type_id' => $this->guest->type_id, // Keep — denormalized
```

- [ ] **Step 3: Commit**

```bash
git add app/Http/Livewire/Frontdesk/Monitoring/RoomMonitoring.php app/Http/Livewire/Frontdesk/Monitoring/CheckInFromKiosk.php
git commit -m "feat: frontdesk components use room_id for rate lookup"
```

---

### Task 7: Update Transfer Room

**Files:**
- Modify: `app/Http/Livewire/Frontdesk/Monitoring/TransferRoom.php`

- [ ] **Step 1: Change rate lookup to use new room's ID**

**Lines 98-109** — `updatedSelectedTypeId()` or room selection:
```php
// OLD:
$this->new_room = Rate::where('branch_id', auth()->user()->branch_id)
    ->where('type_id', $this->selected_type_id)
    ->where('is_available', true)
    ->whereHas('stayingHour', function ($query) use ($hours) {
        $query->where('number', '=', $hours);
    })
    ->first();
// NEW:
$this->new_room = Rate::where('branch_id', auth()->user()->branch_id)
    ->where('room_id', $this->selected_room_id)
    ->where('is_available', true)
    ->whereHas('stayingHour', function ($query) use ($hours) {
        $query->where('number', '=', $hours);
    })
    ->first();
```

Apply the same pattern to **line 126** (second rate lookup).

**Lines 356-357 and 386-388:** CheckinDetail and Guest creation — keep `type_id` (derive from room):
```php
'type_id' => Room::find($this->selected_room_id)?->type_id ?? $this->selected_type_id,
```

- [ ] **Step 2: Commit**

```bash
git add app/Http/Livewire/Frontdesk/Monitoring/TransferRoom.php
git commit -m "feat: transfer room uses room_id for rate lookup"
```

---

### Task 8: Update ExtendGuest

**Files:**
- Modify: `app/Http/Livewire/Frontdesk/Monitoring/ExtendGuest.php`

- [ ] **Step 1: Change all type_id rate lookups to room_id**

**Lines 73-76** — Staying hour lookup:
```php
// OLD:
$stayingHourIds = Rate::where('branch_id', auth()->user()->branch_id)
    ->where('type_id', $this->rate->type_id)
    ->distinct()
    ->pluck('staying_hour_id');
// NEW:
$stayingHourIds = Rate::where('branch_id', auth()->user()->branch_id)
    ->where('room_id', $this->rate->room_id)
    ->distinct()
    ->pluck('staying_hour_id');
```

**Lines 115-116 and 132-133** — Rate lookups for extension cycle:
```php
// OLD (both locations):
->where('type_id', operator: $this->rate->type_id)
// NEW:
->where('room_id', $this->rate->room_id)
```

There are 3 occurrences of `$this->rate->type_id` in rate queries — change ALL to `$this->rate->room_id`.

- [ ] **Step 2: Commit**

```bash
git add app/Http/Livewire/Frontdesk/Monitoring/ExtendGuest.php
git commit -m "feat: extension rate lookup uses room_id"
```

---

### Task 9: Update GuestTransaction and ManageGuestTransaction

**Files:**
- Modify: `app/Http/Livewire/Frontdesk/Monitoring/GuestTransaction.php`
- Modify: `app/Http/Livewire/Frontdesk/Monitoring/ManageGuestTransaction.php`

- [ ] **Step 1: Update GuestTransaction.php**

This is a large file. Search for ALL `Rate::where` or `Rate::whereHas` calls that filter by `type_id` and change to `room_id`.

Key locations:
- **Lines 377, 403, 441** — Rate lookups by staying hour for resets/transfers. These use `whereHas('stayingHour', ...)` but may also filter by type. Add `->where('room_id', $room_id)` where the room is known.
- **Lines 1468-1485** — Transfer operations with `->where('type_id', $this->type_id)`:
```php
// OLD:
$new_room = Rate::where('branch_id', auth()->user()->branch_id)
    ->where('type_id', $this->type_id)
    ...
// NEW:
$new_room = Rate::where('branch_id', auth()->user()->branch_id)
    ->where('room_id', $this->selected_room_id)
    ...
```

- [ ] **Step 2: Commit**

```bash
git add app/Http/Livewire/Frontdesk/Monitoring/GuestTransaction.php app/Http/Livewire/Frontdesk/Monitoring/ManageGuestTransaction.php
git commit -m "feat: guest transaction rate lookups use room_id"
```

---

### Task 10: Update API Controllers

**Files:**
- Modify: `app/Http/Controllers/API/RateController.php`
- Modify: `app/Http/Controllers/API/FloorController.php`

- [ ] **Step 1: Update RateController — accept room_id parameter**

```php
// OLD:
public function index(Request $request)
{
    $request->validate([
        'branch_id' => 'required|integer',
        'type_id' => 'required|integer',
    ]);

    $rates = Rate::where('branch_id', $request->branch_id)
        ->where('type_id', $request->type_id)
        ->with('stayingHour')
        ->get();
// NEW:
public function index(Request $request)
{
    $request->validate([
        'branch_id' => 'required|integer',
        'room_id' => 'required|integer',
    ]);

    $rates = Rate::where('branch_id', $request->branch_id)
        ->where('room_id', $request->room_id)
        ->with('stayingHour')
        ->get();
```

- [ ] **Step 2: Update FloorController — eager load room.rates**

**Line 53:**
```php
// OLD:
->with(['type.rates'])
// NEW:
->with(['rates.stayingHour', 'type'])
```

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/API/RateController.php app/Http/Controllers/API/FloorController.php
git commit -m "feat: API endpoints use room_id for rate lookup"
```

---

### Task 11: Update Tests

**Files:**
- Modify: `tests/Feature/BackOffice/SalesReportV2Test.php`

- [ ] **Step 1: Update Rate creation in setUp to include room_id**

**Lines 80-82** — setUp rate creation:
```php
// OLD:
$this->rate = Rate::create([
    'branch_id' => $this->branch->id,
    'type_id' => $this->roomType->id,
    'staying_hour_id' => $this->stayingHour->id,
    'amount' => 500,
    'is_available' => true,
    'has_discount' => false,
]);
// NEW:
$this->rate = Rate::create([
    'branch_id' => $this->branch->id,
    'type_id' => $this->roomType->id,
    'room_id' => $this->room->id,
    'staying_hour_id' => $this->stayingHour->id,
    'amount' => 500,
    'is_available' => true,
    'has_discount' => false,
]);
```

Search for ALL `Rate::create` calls in the test file and add `'room_id' => $this->room->id` (or the appropriate room) to each one.

- [ ] **Step 2: Run full test suite**

Run: `php artisan test --filter=SalesReportV2Test`
Expected: All 20 tests pass.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/BackOffice/SalesReportV2Test.php
git commit -m "test: update rate creation in tests to include room_id"
```

---

### Task 12: Final Verification

- [ ] **Step 1: Run full test suite**

Run: `php artisan test --filter=SalesReportV2Test`
Expected: All 20 tests pass.

- [ ] **Step 2: Verify database integrity**

Run: `php artisan tinker --execute="echo 'Rates without room_id: '.DB::table('rates')->whereNull('room_id')->count().PHP_EOL.'Total rates: '.DB::table('rates')->count();"`
Expected: 0 rates without room_id.

- [ ] **Step 3: Verify routes compile**

Run: `php artisan route:list --compact | head -20`
Expected: No errors.

- [ ] **Step 4: Push to remote**

```bash
git push origin feature/rate-per-room-publish
```

---

## Rollback Plan

If anything breaks in production:
1. **Code rollback:** `git revert` the commits — all old `type_id` queries still work because `type_id` was never removed from the rates table
2. **Migration rollback:** `php artisan migrate:rollback --step=1` removes `room_id` and deduplicates rates back to per-type
3. **No data loss:** Guest, CheckinDetail, and Transaction records are untouched
