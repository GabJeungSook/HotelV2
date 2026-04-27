# Bugs Found - April 27, 2026

## Summary

| Issue | Status | Priority |
|-------|--------|----------|
| Force Auto-Override Toggle | FIXED | Was High |
| Missing Branch ID Filters | ALL FIXED | Was Critical |
| Room Status Validation | FIXED | Was Medium |
| Duplicate Inventory Records | FIXED | Was High |
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
- [ ] Run `php artisan migrate` on production
- [ ] Test Force Auto-Override end-to-end

---

*Last Updated: April 27, 2026*
