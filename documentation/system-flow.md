# HotelV2 - System Flow Documentation

## Table of Contents

1. [System Overview](#system-overview)
2. [Role-Based Access Flow](#role-based-access-flow)
3. [Room Status Flow](#room-status-flow)
4. [Check-In Flow](#check-in-flow)
5. [Check-Out Flow](#check-out-flow)
6. [Stay Extension Flow](#stay-extension-flow)
7. [Room Transfer Flow](#room-transfer-flow)
8. [POS / Transaction Flow](#pos--transaction-flow)
9. [Damage & Charges Flow](#damage--charges-flow)
10. [Roomboy Cleaning Flow](#roomboy-cleaning-flow)
11. [Shift & Cash Management Flow](#shift--cash-management-flow)
12. [Kiosk Self-Service Flow](#kiosk-self-service-flow)
13. [Data Model Relationships](#data-model-relationships)
14. [Transaction Types](#transaction-types)
15. [API Endpoints](#api-endpoints)

---

## System Overview

```
+-------------------------------------------------------------+
|                       SUPERADMIN                             |
|          Multi-Branch Management & Oversight                 |
+-------------------------------------------------------------+
        |                    |                    |
+---------------+  +------------------+  +----------------+
|   BRANCH A    |  |    BRANCH B      |  |   BRANCH C     |
+---------------+  +------------------+  +----------------+
        |
        v
+-------------------------------------------------------------+
|                         ADMIN                                |
|   Branch Config | Users | Rooms | Rates | Types | Floors    |
+-------------------------------------------------------------+
        |
        +------------------+------------------+
        |                  |                  |
+-------------+  +------------------+  +--------------+
|  FRONTDESK  |  |     KITCHEN      |  |   ROOMBOY    |
|  Check-in   |  |  Food Orders     |  |  Cleaning    |
|  Check-out  |  |  Menu Mgmt       |  |  Inspection  |
|  POS        |  |  Inventory       |  |  History     |
|  Monitor    |  +------------------+  +--------------+
|  Cash Mgmt  |  |    PUB KITCHEN   |
+-------------+  |  Bar Orders      |
        |        |  Beverage Mgmt   |
+-------------+  +------------------+
| BACK OFFICE |
|  Reports    |         +--------------+
|  Sales      |         |    KIOSK     |
|  Expenses   |         | Self Check-in|
+-------------+         | Self Checkout|
                        +--------------+
```

---

## Role-Based Access Flow

```
User Login
    |
    v
Authentication (Laravel Sanctum / Session)
    |
    v
Role Check (Spatie Permissions)
    |
    +---> superadmin ---> /superadmin/dashboard
    |                     (Multi-branch oversight, reports)
    |
    +---> admin --------> /admin/dashboard
    |                     (Branch management, room/rate/user config)
    |
    +---> frontdesk ----> /frontdesk/dashboard
    |                     (Check-in, check-out, POS, monitoring)
    |
    +---> back_office --> /back-office/dashboard
    |                     (Reports, expenses, analytics)
    |
    +---> kitchen ------> /kitchen/dashboard
    |                     (Food orders, menu, inventory)
    |
    +---> pub_kitchen --> /pub-kitchen/dashboard
    |                     (Bar orders, beverage menu, inventory)
    |
    +---> roomboy ------> /roomboy/dashboard
    |                     (Room cleaning, inspection)
    |
    +---> kiosk --------> /kiosk/dashboard
                          (Guest self-service check-in/out)
```

**Middleware:** Each role route file applies `role:{role_name}` middleware. All queries are branch-scoped using `auth()->user()->branch_id`.

---

## Room Status Flow

```
+-------------+       Check-In       +-------------+
|  AVAILABLE  | ------------------> |   OCCUPIED   |
+-------------+                      +-------------+
      ^                                    |
      |                               Check-Out
      |                                    |
      |                                    v
+-------------+    Roomboy Starts    +-------------+
|   CLEANING  | <------------------ |  UNCLEANED   |
+-------------+                      +-------------+
      |
      | Roomboy Completes
      v
+-------------+
|  AVAILABLE  |
+-------------+
```

**During Occupied state:**
- Guest can extend stay (status stays Occupied)
- Guest can transfer rooms (old room -> Uncleaned, new room -> Occupied)

---

## Check-In Flow

### Frontdesk Check-In

```
Frontdesk Staff
    |
    v
1. Select Room Type
    |
    v
2. Select Floor / Available Room
    |
    v
3. Select Rate (Staying Hours: 1hr, 2hr, 3hr, etc.)
    |
    v
4. Enter Guest Information (Name, Contact, Vehicle)
    |
    v
5. System generates QR Code (branchId + year + counter)
    |
    v
6. Create Records:
    |
    +---> Guest record (name, contact, room_id, rate_id, type_id)
    +---> CheckinDetail (check_in_at, static_amount, hours)
    +---> Transaction type=1 "Check In" (room charge)
    +---> Transaction type=2 "Deposit" (key/remote deposit, if enabled)
    +---> NewGuestReport (reporting record)
    |
    v
7. Update Room status --> "Occupied"
    |
    v
8. Broadcast CheckInEvent (real-time dashboard update)
    |
    v
9. Guest receives QR code / room key
```

**Validations:**
- Room must be "Available" (cleaned)
- Room must not have pending kiosk/reservation
- Branch deposit settings determine if deposit is required

---

## Check-Out Flow

### Frontdesk Check-Out

```
Frontdesk Staff selects Guest / Room
    |
    v
1. Load Guest + CheckinDetail + all Transactions
    |
    v
2. Verify Key/Remote Return
    |    |
    |    +---> Key returned? Yes/No
    |    +---> Remote returned? Yes/No
    |
    v
3. Calculate Totals:
    |
    +---> Room charges (static_amount + extensions)
    +---> Deposit total
    +---> Damage charges (if any)
    +---> Kitchen/POS charges (if any)
    +---> Amenity charges (if any)
    |
    v
4. Process Damages (if any):
    |
    +---> Key damage charge (transaction type=4)
    +---> Remote damage charge (transaction type=4)
    +---> Other damage charges (transaction type=4)
    |
    v
5. Calculate Final Bill:
    |
    +---> Total charges - Total deposits = Amount Due / Refund
    |
    v
6. Create Records:
    |
    +---> CheckOutGuestReport
    +---> Update CheckinDetail (is_check_out=true, check_out_at=now)
    |
    v
7. Update Room status --> "Uncleaned"
    |
    v
8. Room appears in Roomboy dashboard for cleaning
```

---

## Stay Extension Flow

```
Frontdesk views Room Monitoring Dashboard
    |
    v
1. Select Guest nearing checkout time
    |
    v
2. View current stay details:
    |
    +---> Current rate & hours
    +---> Time remaining
    +---> Extension rates available
    |
    v
3. Select Extension Option:
    |
    +---> Same rate (original staying hour)
    |     (if within extension_time_reset threshold)
    |
    +---> Custom extension rate
    |     (if exceeded reset threshold)
    |
    v
4. Extension Logic:
    |
    +---> Check next_extension_is_original flag
    |     |
    |     +---> true: use original StayingHour rate
    |     +---> false: use ExtensionRate pricing
    |
    v
5. Create Records:
    |
    +---> StayExtension record
    +---> Transaction type=6 "Extend"
    +---> Update CheckinDetail.number_of_hours
    |
    v
6. New checkout time calculated
    |
    v
7. Room stays "Occupied"
```

---

## Room Transfer Flow

```
Frontdesk selects Guest to transfer
    |
    v
1. Select new Room:
    |
    +---> Filter by Type
    +---> Filter by Floor
    +---> Show only Available rooms
    |
    v
2. Calculate Rate Difference:
    |
    +---> Current room rate vs New room rate
    +---> Handle long-stay calculations (if applicable)
    |
    v
3. Determine Payment:
    |
    +---> New room more expensive? --> Guest pays difference
    +---> New room cheaper? -------> Guest gets refund
    +---> Same price? -------------> No charge
    |
    v
4. Create Records:
    |
    +---> Transaction type=7 "Transfer Room" (deduct old)
    +---> Transaction type=7 "Transfer Room" (add new)
    +---> TransferedGuestReport
    |
    v
5. Update Records:
    |
    +---> Guest.room_id = new room
    +---> Guest.previous_room_id = old room
    +---> CheckinDetail.static_room_amount (if override needed)
    |
    v
6. Update Room Statuses:
    |
    +---> Old room --> "Uncleaned"
    +---> New room --> "Occupied"
```

---

## POS / Transaction Flow

### Frontdesk POS (Snacks & Items)

```
Frontdesk Staff
    |
    v
1. Select Guest (by room or name)
    |
    v
2. Browse FrontdeskMenu (by FrontdeskCategory)
    |
    v
3. Add items to order (quantity x price)
    |
    v
4. Create Transaction type=9 "Food & Beverages"
    |    +---> payable_amount = qty x price
    |    +---> Tied to guest's CheckinDetail
    |
    v
5. Update FrontdeskInventory (deduct stock)
    |
    v
6. Charge added to guest's bill (settled at check-out)
```

### Kitchen Orders

```
Kitchen Staff
    |
    v
1. Receive order (from frontdesk / room service)
    |
    v
2. Select Menu items (by MenuCategory)
    |
    v
3. Enter quantity & pricing
    |
    v
4. Create Transaction type=3 "Kitchen Order"
    |
    v
5. Update Inventory (deduct ingredients)
    |
    v
6. Charge added to guest's bill
```

### Pub/Bar Orders

```
Pub Staff
    |
    v
1. Receive order
    |
    v
2. Select PubMenu items (by PubCategory)
    |
    v
3. Create Transaction type=9 "Food & Beverages"
    |
    v
4. Update PubInventory (deduct stock)
    |
    v
5. Charge added to guest's bill
```

---

## Damage & Charges Flow

```
Guest damages item (key, remote, room item)
    |
    v
1. Frontdesk records damage:
    |
    +---> Select damaged item from DamageCharges catalog
    +---> Item price + optional additional amount
    |
    v
2. Create Transaction type=4 "Damage Charges"
    |
    v
3. Link to guest's CheckinDetail
    |
    v
4. At Check-Out:
    |
    +---> Damage total deducted from deposit
    +---> If damage > deposit: guest pays difference
    +---> Flag shown: "Guest Charged for Damage"
```

---

## Roomboy Cleaning Flow

```
Guest checks out --> Room status = "Uncleaned"
    |
    v
1. Roomboy views assigned floor(s) dashboard
    |    (User <-> Floor many-to-many)
    |
    v
2. See list of Uncleaned rooms
    |
    v
3. Start Cleaning:
    |
    +---> Room status --> "Cleaning"
    +---> Timer starts
    |
    v
4. Inspect Room:
    |
    +---> Check for damages
    +---> Check supplies
    +---> Note any issues
    |
    v
5. Complete Cleaning:
    |
    +---> Create CleaningHistory (user_id, room_id, timestamps)
    +---> Create RoomBoyReport
    +---> Room status --> "Available"
    |
    v
6. Room available for next check-in
    |
    v
7. Broadcast update to Frontdesk dashboard
```

---

## Shift & Cash Management Flow

### Start of Shift

```
Frontdesk Staff logs in
    |
    v
1. Select / Assign CashDrawer
    |
    v
2. Enter Beginning Cash amount
    |
    v
3. Create ShiftLog:
    |
    +---> time_in = now()
    +---> beginning_cash = entered amount
    +---> shift = A/B/C (morning/afternoon/night)
    +---> branch_id, user_id
    |
    v
4. CashDrawer activated (is_active=true)
    |
    v
5. User.cash_drawer_id set
    |
    v
6. All transactions during shift tied to ShiftLog
```

### During Shift

```
+---> Check-ins ------> Transaction recorded --> ShiftLog
+---> Check-outs -----> Transaction recorded --> ShiftLog
+---> POS Sales ------> Transaction recorded --> ShiftLog
+---> Extensions -----> Transaction recorded --> ShiftLog
+---> Expenses -------> Expense record -------> ShiftLog
+---> Deductions -----> CashOnDrawer ---------> ShiftLog
```

### Remittance (Cash Collection)

```
Cash collected during shift
    |
    v
1. Create Remittance record:
    |
    +---> amount collected
    +---> shift_log_id
    +---> user_id, branch_id
    |
    v
2. ShiftLog.total_remittance updated
    |
    v
3. Cash physically handed to back office
```

### End of Shift

```
Frontdesk Staff ends shift
    |
    v
1. Count cash in drawer
    |
    v
2. Update ShiftLog:
    |
    +---> time_out = now()
    +---> end_cash = counted amount
    +---> total_expenses calculated
    +---> total_remittance finalized
    |
    v
3. CashDrawer deactivated (is_active=false)
    |
    v
4. On Logout: ClearCashDrawerOnLogout listener
    |
    +---> Nullify user.cash_drawer_id
    +---> Close any open ShiftLog
```

---

## Kiosk Self-Service Flow

### Kiosk Check-In

```
Guest at Kiosk Terminal
    |
    v
1. POST /api/kiosk/check-in (Sanctum auth)
    |
    v
2. Enter Guest Info (name, contact)
    |
    v
3. Select:
    |
    +---> Room Type
    +---> Floor
    +---> Available Room
    +---> Rate / Staying Hours
    |
    v
4. Apply Discount (if available, has_discount flag)
    |
    v
5. System creates:
    |
    +---> Guest record
    +---> TemporaryCheckInKiosk (20-min expiry)
    +---> QR Code generated
    |
    v
6. Waiting for Frontdesk Confirmation
    |
    +---> Frontdesk sees pending kiosk check-in
    +---> Confirms within 20 minutes
    |         |
    |         v
    |     Full check-in process triggered
    |     (same as Frontdesk Check-In steps 6-9)
    |
    +---> If NOT confirmed within 20 min:
          |
          v
          kiosk:cleanup command removes expired records
          Room released back to Available
```

### Kiosk Check-Out

```
Guest at Kiosk Terminal
    |
    v
1. Enter Room Number
    |
    v
2. GET /api/occupied-rooms/{branchId}
    |
    v
3. Display final bill breakdown:
    |
    +---> Room charges
    +---> Deposits held
    +---> Additional charges (food, damages, etc.)
    +---> Amount due / refund
    |
    v
4. Guest confirms check-out
    |
    v
5. POST /api/guest-kiosk-checkout/{guest}
    |
    +---> Guest.has_kiosk_check_out = 1
    |
    v
6. Frontdesk receives notification
    |
    v
7. Frontdesk confirms and completes check-out
    (same as Frontdesk Check-Out steps 4-8)
```

---

## Data Model Relationships

```
Branch (1) -----> (N) User
       (1) -----> (N) Room
       (1) -----> (N) Type
       (1) -----> (N) Floor
       (1) -----> (N) CashDrawer
       (1) -----> (N) StayingHour
       (1) -----> (N) Expense
       (1) -----> (N) ActivityLog
       (1) -----> (N) Remittance

Type   (1) -----> (N) Room
       (1) -----> (N) Rate

Floor  (1) -----> (N) Room

Room   (1) -----> (N) Guest (history)
       (1) -----> (N) CheckinDetail
       (1) -----> (N) CleaningHistory
       (1) -----> (N) Transaction

Rate   (1) -----> (N) Guest
       (1) -----> (N) CheckinDetail
       (1) -----> (1) StayingHour

Guest  (1) -----> (N) Transaction
       (1) -----> (N) StayExtension
       (1) -----> (1) CheckinDetail (current)
       (1) -----> (N) TemporaryCheckInKiosk
       (1) -----> (N) TemporaryReserved

CheckinDetail (1) -----> (N) Transaction
              (1) -----> (N) Reports (various types)

User   (1) -----> (N) ShiftLog
       (1) -----> (N) Expense
       (1) -----> (N) CleaningHistory
       (N) <----> (N) Floor (roomboy assignment)
       (1) -----> (1) CashDrawer (active)

ShiftLog (1) -----> (N) Transaction
         (1) -----> (N) Expense
         (1) -----> (N) Remittance
         (1) -----> (N) CashOnDrawer
```

---

## Transaction Types

| ID | Type | Description | When Used |
|----|------|-------------|-----------|
| 1 | Check In | Initial room charge | Guest checks in |
| 2 | Deposit | Key/remote & security deposits | Check-in (if enabled) |
| 3 | Kitchen Order | Food service charges | Kitchen orders food |
| 4 | Damage Charges | Breakage/damage fees | Item damaged by guest |
| 5 | Cashout | Check-out settlement | Guest checks out |
| 6 | Extend | Stay extension charge | Guest extends hours |
| 7 | Transfer Room | Room change charges | Guest transfers room |
| 8 | Amenities | Amenity usage charges | Guest uses amenities |
| 9 | Food & Beverages | F&B / POS charges | POS or pub orders |

---

## API Endpoints

### Authentication
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/login` | Login & get Sanctum token |

### Room Data
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/room-types/{branchId}` | Get room types for branch |
| GET | `/api/branch/{branch}/floors-with-rooms` | Get floors with rooms |
| GET | `/api/rates` | Get available rates |

### Kiosk Operations (Sanctum Protected)
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/kiosk/check-in` | Kiosk self-service check-in |
| GET | `/api/occupied-rooms/{branchId}` | List occupied rooms |
| GET | `/api/guest-room-by-qr/{qr_code}` | Lookup guest by QR |
| POST | `/api/guest-kiosk-checkout/{guest}` | Kiosk check-out request |

---

## Event Broadcasting

| Event | Channel | Trigger |
|-------|---------|---------|
| CheckInEvent | `newcheckin.{branchId}` | New guest check-in |

---

## Scheduled Commands

| Command | Schedule | Description |
|---------|----------|-------------|
| `kiosk:cleanup` | Periodic | Remove expired kiosk check-ins (based on branch `kiosk_time_limit`) |

---

## Recent Schema Changes (2026)

### New Tables

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `cash_drawers` | Physical cash drawer tracking per branch | `branch_id`, `name`, `is_active` |
| `cash_on_drawers` | Track deductions/adjustments on drawers | `branch_id`, `frontdesk_id`, `cash_drawer_id`, `amount`, `deduction`, `transaction_type`, `shift` |
| `transfered_guest_reports` | Log room transfers with rate differences | `checkin_detail_id`, `previous_room_id`, `new_room_id`, `previous_amount`, `new_amount` |
| `remittances` | Cash handover from frontdesk to back office | `shift_log_id`, `user_id`, `branch_id`, `total_remittance`, `description` |
| `frontdesk_shifts` | Detailed shift report with 60+ fields | Cash drawer details, final sales, cash reconciliation |

### Column Additions by Table

#### `users`
| Column | Type | Purpose |
|--------|------|---------|
| `shift` | string, nullable | Current shift assignment (A/B/C) |
| `cash_drawer_id` | unsigned bigint, nullable | Active cash drawer |

#### `transactions`
| Column | Type | Purpose |
|--------|------|---------|
| `cash_drawer_id` | unsigned bigint, nullable | Drawer used for transaction |
| `shift` | string, nullable | Shift during transaction |
| `is_override` | boolean, default: false | Manual price override flag |
| `checkin_detail_id` | unsigned bigint, nullable | Link transaction to check-in record |
| `shift_log_id` | FK, nullable | Link transaction to shift log |

#### `checkin_details`
| Column | Type | Purpose |
|--------|------|---------|
| `next_extension_is_original` | boolean, default: false | Extension rate logic flag |
| `static_room_amount` | decimal(10,2), default: 0 | Override room amount (for transfers) |

#### `shift_logs`
| Column | Type | Purpose |
|--------|------|---------|
| `shift` | string, nullable | Shift type (A/B/C) |
| `cash_drawer_id` | unsigned bigint, nullable | Assigned drawer |
| `frontdesk_id` | unsigned bigint, nullable | Frontdesk station |
| `beginning_cash` | decimal(15,2), default: 0 | Cash at shift start |
| `end_cash` | decimal(15,2), default: 0 | Cash at shift end |
| `description` | text, nullable | Shift notes |
| `total_expenses` | decimal(15,2), default: 0 | Expenses during shift |
| `total_remittances` | decimal(15,2), default: 0 | Cash collected |
| `branch_id` | FK, nullable | Branch association |

#### `frontdesks`
| Column | Type | Purpose |
|--------|------|---------|
| `user_id` | unsigned bigint, nullable | Assigned user |
| `passcode` | string, nullable, default: '12345' | Frontdesk station passcode |

#### `expenses`
| Column | Type | Purpose |
|--------|------|---------|
| `branch_id` | unsigned bigint, default: 1 | Branch association |
| `shift_log_id` | FK, nullable | Link expense to shift |

#### `branches`
| Column | Type | Purpose |
|--------|------|---------|
| `kiosk_time_limit` | integer, default: 10 | Minutes before kiosk check-in expires |

#### `temporary_check_in_kiosks`
| Column | Type | Purpose |
|--------|------|---------|
| `is_opened` | boolean, default: false | Whether kiosk entry has been opened/viewed |

### Performance Indexes (2026-03-28)

```
rooms:            branch_id, floor_id, type_id, status, (branch_id + status)
checkin_details:  room_id, guest_id, is_check_out, (room_id + is_check_out + created_at)
guests:           room_id, branch_id, has_kiosk_check_out
new_guest_reports: room_id
transactions:     room_id, guest_id, branch_id
rates:            type_id, branch_id
```
