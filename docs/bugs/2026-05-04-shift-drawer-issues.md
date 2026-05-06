# Shift & Drawer Issues

**Date Reported:** 2026-05-04
**Status:** Pending Confirmation

---

## Summary

| # | Issue | Severity | Status |
|---|-------|----------|--------|
| 1 | Beginning cash copies from wrong drawer | High | Needs Confirmation |
| 2 | Drawer 2 always ends with 1.00 | Medium | User Behavior? |
| 3 | FD cannot edit/add note in remittance | Medium | **Missing Feature** |
| 4 | Auto-close fallback value is 1.00 | Low | By Design |

---

## Issue #1: Beginning Cash Copies from Wrong Drawer

### Problem

When second frontdesk opens their shift, the system copies **another drawer's** beginning cash instead of using their own drawer's previous end cash.

### Location

`app/Http/Livewire/Frontdesk/BeginningCash.php`

### Code Flow

```
User opens Drawer 2
    ↓
System finds Drawer 1 is already open
    ↓
Copies Drawer 1's beginning_cash → Drawer 2  ❌
    ↓
Should use Drawer 2's previous end_cash ✓
```

### Evidence

| ID | Drawer | Previous End | Beginning | Problem |
|----|--------|--------------|-----------|---------|
| 230 | 2 | 1.00 | 95,291.00 | Copied from Drawer 1 |
| 232 | 2 | 1.00 | 145,727.00 | Copied from Drawer 1 |

### Code Reference

```php
// Line 28-34: Finds ANOTHER open shift
$this->current_shift = ShiftLog::where('cash_drawer_id', '!=', $user->cash_drawer_id)
    ->whereNull('time_out')
    ->where('beginning_cash', '>', 0)
    ...

// Line 67: Copies that drawer's beginning_cash
$shift->beginning_cash = $this->current_shift->beginning_cash;
```

### Question

**Is this intentional?**

| Option | Description |
|--------|-------------|
| A - Shared Pool | Both drawers share same cash pool (current behavior) |
| B - Independent | Each drawer tracks own cash (needs fix) |

---

## Issue #2: Drawer 2 Always Ends with 1.00

### Problem

All Drawer 2 shifts end with `end_cash = 1.00`.

### Evidence

| ID | Drawer | Duration | End Cash |
|----|--------|----------|----------|
| 230 | 2 | 12h | 1.00 |
| 228 | 2 | 12h | 1.00 |
| 226 | 2 | 11h | 1.00 |
| 224 | 2 | 11h | 1.00 |
| 222 | 2 | 12h | 1.00 |

### Analysis

- Duration is 11-12 hours → **NOT auto-closed**
- Users are **manually entering "1"**
- This is user behavior, not code bug

### Possible Reasons

| Reason | Description |
|--------|-------------|
| SOP | Drawer 2 transfers cash to Drawer 1, enters "1" |
| Training | Users don't know to count actual cash |
| Placeholder | Users entering "1" as quick exit |

### Question

**Is entering "1" the correct SOP for Drawer 2?**

---

## Issue #3: FD Cannot Edit/Add Note in Remittance Tab

### Report

> "maam Kristine Mae good p dli daw makanote ang FD sa remittance tab"
> — QUINE PINO, 2026-05-04 3:29 PM

### Translation

Frontdesk cannot note/record in the remittance tab.

### Investigation Result

**Finding: No Edit Feature Exists**

| Feature | Status |
|---------|--------|
| Add new remittance with description | ✓ Exists |
| View description in table | ✓ Exists |
| **Edit existing remittance** | ✗ **Missing** |
| **Update description/note** | ✗ **Missing** |
| **Delete remittance** | ✗ **Missing** |

### Code Evidence

```php
// Remittance.php only has:
public function saveRemittance()  // CREATE only

// Missing methods:
// - editRemittance()
// - updateRemittance()
// - deleteRemittance()
```

### Likely Problem

User created a remittance and now wants to:
- Edit/update the description/note, OR
- Add a note to existing remittance

But **no edit functionality exists** in the current implementation.

### Files

| File | Purpose |
|------|---------|
| `app/Http/Livewire/Frontdesk/Remittance.php` | Remittance component |
| `resources/views/livewire/frontdesk/remittance.blade.php` | Remittance view |

### Status

**Pending Confirmation** — Need to confirm with user:
- Do they need edit/update feature for remittance notes?
- Or is there a different issue?

---

## Issue #4: Auto-Close Fallback Value

### Problem

When shifts are auto-closed, fallback `end_cash` is `1.00`.

### Location

`app/Http/Livewire/Frontdesk/AssignedFrontdesk.php`

### Code

```php
// Line 150: Stale shifts (>14h)
'end_cash' => DB::raw('COALESCE(NULLIF(end_cash, 0), 1.00)'),

// Line 161: Existing open shift
'end_cash' => $openShift->end_cash ?: 1.00,
```

### Impact

- Cannot distinguish "user entered 1" vs "auto-closed"
- Corrupts cash audit trail

### Recommendation

Use `null` or add `is_auto_closed` flag.

---

## Related Files

| File | Purpose |
|------|---------|
| `app/Http/Livewire/Frontdesk/AssignedFrontdesk.php` | Shift creation |
| `app/Http/Livewire/Frontdesk/BeginningCash.php` | Beginning cash entry |
| `app/Http/Livewire/Frontdesk/CashOnHand.php` | End shift |
| `app/Models/ShiftLog.php` | Shift model |
| `app/Models/CashDrawer.php` | Drawer model |

---

## Action Items

| # | Action | Owner | Status |
|---|--------|-------|--------|
| 1 | Confirm: Independent or shared drawers? | — | Pending |
| 2 | Confirm: Drawer 2 SOP for end cash | — | Pending |
| 3 | Confirm: Need edit feature for remittance notes? | — | Pending |
| 4 | Decide: Auto-close fallback value | — | Pending |
