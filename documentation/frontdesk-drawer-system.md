# Frontdesk & Drawer System

## Overview

This document explains the frontdesk, cash drawer, and shift management system in HotelV2.

---

## Data Model

### Entities

| Entity | Purpose |
|--------|---------|
| **User** | The actual person (employee) |
| **Frontdesk** | A counter/station (Frontdesk 1, Frontdesk 2) |
| **CashDrawer** | Physical cash drawer at a counter |
| **ShiftLog** | Record of who worked when |
| **AssignedFrontdesk** | Links users to frontdesk stations |

### Relationships

```
User ──────────────┐
                   ▼
           AssignedFrontdesk
                   │
                   ▼
Frontdesk ◄────────┘
    │
    ▼
CashDrawer (1:1 with Frontdesk)
    │
    ▼
ShiftLog (tracks shift sessions)
```

---

## Shift Structure

### One Shift Can Have 2 Frontdesk Users

```
┌────────────────────────────────────────────┐
│              SHIFT LOG                      │
│         (AM Apr 27, 8am-8pm)               │
│                                             │
│   Hannah (Primary)    +    John (Partner)  │
│         ↓                                   │
│   Frontdesk 1         (helps at same desk) │
│         ↓                                   │
│   CashDrawer 1                              │
└────────────────────────────────────────────┘
```

### How It's Stored

| Field | Type | Purpose |
|-------|------|---------|
| `frontdesk_id` | integer | Primary operator (User ID) |
| `json_frontdesk_ids` | JSON | Both operators: `[fd_id, "partner_name"]` |
| `cash_drawer_id` | integer | Which drawer they're using |

### Why Partner is Stored as String

- **Flexibility**: Can add anyone, even temporary help
- **Deactivation safe**: If user is deactivated, old records still show name
- **Simple**: No complex foreign key issues

---

## Business Rules

### Shift Capacity

- **Maximum 2 active users per shift** (AM or PM)
- Primary operator selects the frontdesk/drawer
- Partner joins the same shift

### Shared Responsibility

This is **intentional by design**:

| Scenario | Rule |
|----------|------|
| Cash shortage | **Both FDs are responsible** |
| Discrepancy | Both must reconcile together |
| Accountability | Forces teamwork & double-checking |

### Why This Works for Homi

- Partners are usually consistent (same people)
- Trust between team members exists
- Supervisor oversight is in place
- Reports capture what management needs

---

## Shift Types

| Type | Hours | Determination |
|------|-------|---------------|
| **AM** | 6:00 AM - 7:59 PM | Hour 6-19 |
| **PM** | 8:00 PM - 5:59 AM | Hour 20-23, 0-5 |

Code reference (`SalesReportV2.php`):
```php
private function getShiftType(Carbon $timeIn): string
{
    $hour = $timeIn->hour;
    return ($hour >= 6 && $hour < 20) ? 'AM' : 'PM';
}
```

---

## Related Tables

### shift_logs

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `branch_id` | bigint | Which branch |
| `frontdesk_id` | bigint | Primary operator (User) |
| `json_frontdesk_ids` | json | Both operators |
| `cash_drawer_id` | bigint | Which drawer |
| `shift` | string | "AM" or "PM" |
| `time_in` | datetime | Shift start |
| `time_out` | datetime | Shift end (null if ongoing) |
| `beginning_cash` | decimal | Starting cash |
| `end_cash` | decimal | Ending cash |

### cash_drawers

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `branch_id` | bigint | Which branch |
| `name` | string | Drawer name |
| `is_active` | boolean | Currently in use |

### assigned_frontdesks

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `branch_id` | bigint | Which branch |
| `user_id` | bigint | The employee |
| `frontdesk_id` | bigint | Assigned station |
| `shift` | string | AM or PM |
| `cash_drawer_id` | bigint | Assigned drawer |

---

## Flow: Starting a Shift

1. **User logs in** as frontdesk role
2. **Selects frontdesk station** (Frontdesk 1, 2, etc.)
3. **Selects cash drawer** (if not auto-assigned)
4. **Optionally adds partner** (second FD user)
5. **ShiftLog created** with `time_in = now()`
6. **Both users work** - all transactions linked to this ShiftLog

## Flow: Ending a Shift

1. **Primary FD clicks "End Shift"**
2. **System calculates** totals (sales, expenses, remittances)
3. **ShiftLog updated** with `time_out = now()`
4. **Cash drawer** marked inactive
5. **Reports available** in Back Office

---

## Reports Using This Data

| Report | What It Shows |
|--------|---------------|
| **Sales Report** | Transactions per shift, forwarded guests |
| **Frontdesk Report** | Activity per frontdesk user |
| **Frontdesk Logs** | Detailed shift history |

---

## Potential Future Enhancement

### Manual Check-in for System Downtime

**Problem**: When system is down, hotel still operates. FD records on paper. When system comes back, check-in time is wrong (uses `now()` instead of actual time).

**Proposed Solution**:

| Feature | Description |
|---------|-------------|
| Manual time entry | Allow FD to enter actual check-in time |
| `is_manual_entry` flag | Mark transaction as manually entered |
| Reason field | "System downtime recovery" |
| Audit trail | Track who entered it & when |

**Status**: Idea only - not yet implemented.

---

## Room Status Protection Rules

### Maintenance Status Restrictions

The system prevents setting a room to **Maintenance** status in certain situations to protect data integrity and avoid report discrepancies.

| Condition | Action | Error Message |
|-----------|--------|---------------|
| Room has ongoing cleaning | **BLOCKED** | "This room has ongoing cleaning. Please finish cleaning first before setting to Maintenance." |
| Room has active guest | **BLOCKED** | "This room has an active guest checked in. Please transfer or check out the guest first before setting to Maintenance." |

### Why This Matters

1. **Ongoing Cleaning**: If a room boy has started cleaning (`status = 'Cleaning'` or `cleaning_by_user_id` is set), changing to Maintenance would:
   - Leave the cleaning record incomplete
   - Break Room Boy Reports

2. **Active Guest**: If a guest is checked in (`CheckinDetail.is_check_out = false`), changing to Maintenance would:
   - Create orphaned transactions
   - Break Sales Report calculations
   - Make guest checkout impossible

### Proper Workflow

**For rooms with ongoing cleaning:**
```
Room Boy → Click "Finish Cleaning" → Then Admin can set to Maintenance
```

**For rooms with active guests:**
```
Frontdesk → Transfer guest OR Check out guest → Then Admin can set to Maintenance
```

### Code Reference

`app/Http/Livewire/Admin/Manage/Room.php` - EditAction validation (lines 205-232)

---

## Key Files

| File | Purpose |
|------|---------|
| `app/Models/ShiftLog.php` | Shift model |
| `app/Models/CashDrawer.php` | Drawer model |
| `app/Models/Frontdesk.php` | Frontdesk station model |
| `app/Models/AssignedFrontdesk.php` | Assignment model |
| `app/Http/Livewire/Frontdesk/AssignedFrontdesk.php` | Shift assignment UI |
| `app/Http/Livewire/BackOffice/SalesReportV2.php` | Sales reporting |
| `app/Http/Livewire/Admin/Manage/Room.php` | Room management with status protection |
