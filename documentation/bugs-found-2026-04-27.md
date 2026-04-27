# Bugs Found - April 27, 2026

## Summary

| Issue | Status | Priority |
|-------|--------|----------|
| Force Auto-Override Toggle | FIXED | Was High |
| Missing Branch ID Filters | ALL FIXED | Was Critical |
| Room Status Validation | FIXED | Was Medium |
| Duplicate Inventory Records | FIXED | Was High |
| POS Charge to Room Shows Available Rooms | FIXED | Was Medium |
| Manual Occupied Status Change | FIXED (Blocked) | Was High |
| Ghost Rooms (Occupied with No Guest) | FIXED + Feature Added | Was High |
| Null Pointer Exceptions | Potential only | Low |
| Race Conditions | Theoretical | Low |

**All critical bugs have been fixed.** Remaining items are potential edge cases for future review.

---

## Fixed Issues

### 1. Force Auto-Override Toggle (FIXED)

**Location:** `app/Http/Livewire/Supervisor/ForceAutoOverrideToggle.php`

**Problem:** Toggle had Livewire conflicts from duplicate mobile/desktop instances.

**Fix Applied:**
- Added `wire:key="mobile-toggle"` and `wire:key="desktop-toggle"` to layout
- Added event listener to sync state between toggle instances

**Verification:** Run `php artisan migrate`, then test toggle ON → frontdesk transfer/cancel should auto-approve.

---

### 2. Missing Branch ID Filters (ALL FIXED)

**Problem:** Data could leak between branches.

| File | Fixed |
|------|-------|
| `Admin/Manage/KitchenInventory.php` | Yes |
| `Frontdesk/Food/Inventory.php` | Yes |
| `Frontdesk/PointOfSale.php` | Yes |

---

### 3. Room Status Validation (FIXED)

**Location:** `app/Http/Livewire/Admin/Manage/Room.php`

**Problem:** Could set room to Available while guest is checked in.

**Fix:** Added active guest check before allowing status change to Available or Maintenance.

---

### 4. Duplicate Inventory Records (FIXED)

**Problem:** Multiple FrontdeskInventory records for same menu_id caused wrong stock display.

**Fix:** Deleted duplicate records via tinker (IDs 25, 27, 29, 31).

---

### 5. POS Charge to Room Shows Available Rooms (FIXED)

**Location:** `app/Http/Livewire/Frontdesk/PointOfSale.php`

**Problem:** When using "Charge to Room" in POS, guests in Available rooms were appearing in search results.

**Fix Applied:**
- Added filter `->whereHas('checkInDetail.room', fn ($q) => $q->where('status', 'Occupied'))` to guest search
- Added validation in `selectGuest()` to block non-Occupied rooms with error message

---

### 6. Manual Occupied Status Change (FIXED - Blocked)

**Location:** `app/Http/Livewire/Admin/Manage/Room.php`

**Problem:** Frontdesk/Admin could manually set room status to "Occupied" without a guest, causing data inconsistency (ghost rooms).

**Fix Applied:**
- Added blocker that prevents setting room to Occupied manually
- Rooms can ONLY become Occupied through the check-in process
- Error message: "Room status can only be set to Occupied through the check-in process."

---

### 7. Ghost Rooms Detection & Fix Feature (NEW)

**Problem:** Room 100 was discovered showing "Occupied" status but had no active guest in `checkin_details` table. This was caused by past manual status changes.

**Solution - Multi-layered approach:**

1. **Prevention:** Added blocker for manual Occupied status (Issue #6 above)

2. **Detection:** Created Ghost Rooms admin feature
   - `app/Http/Livewire/Admin/GhostRooms.php` - Livewire component
   - `resources/views/livewire/admin/ghost-rooms.blade.php` - View
   - `app/Console/Commands/FixGhostRooms.php` - CLI command

3. **Admin Sidebar:** Purple "Ghost Rooms" badge appears only when ghost rooms exist
   - Location: `resources/views/components/admin-layout.blade.php`

4. **Fix Actions:**
   - "Fix Room" button - fixes individual room
   - "Fix All" button - fixes all ghost rooms at once
   - Changes room status from Occupied → Available
   - Logs action to ActivityLog

**CLI Usage:**
```bash
php artisan rooms:fix-ghost          # Report only
php artisan rooms:fix-ghost --fix    # Actually fix
```

---

## Potential Issues (Low Priority - Not Active Bugs)

### Null Pointer Exceptions
- `$guest->checkInDetail->property` without null checks
- `Room::find($id)->floor->id` without null checks
- **Risk:** Only fails if data is corrupted/deleted manually

### Race Conditions
- Concurrent check-ins to same room (theoretical)
- Concurrent POS orders overselling (theoretical)
- **Risk:** Very rare, requires exact timing

### Items to Monitor
- Transfer report amounts when room types differ
- Deposit refund exceeding drawer cash
- Shift log gaps if frontdesk forgets to end shift
- Old pending override requests (should they expire?)

---

## Action Items

- [x] Fix toggle Livewire conflicts
- [x] Fix branch_id filters
- [x] Fix room status validation
- [x] Clean up duplicate inventory records
- [x] Fix POS charge to room showing Available rooms
- [x] Add blocker for manual Occupied status change
- [x] Create Ghost Rooms detection feature
- [x] Add Ghost Rooms sidebar item (purple badge)
- [x] Create Ghost Rooms CLI command
- [ ] Run `php artisan migrate` on production
- [ ] Test Force Auto-Override end-to-end
- [ ] Test Ghost Rooms feature on production

---

*Last Updated: April 27, 2026*
