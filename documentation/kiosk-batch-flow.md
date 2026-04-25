# Kiosk Batch-Rotation Flow

End-to-end reference for the kiosk batch-rotation room sequence
(`feature/kiosk-room-sequence`, now merged into `future-updates`).

This doc covers:
1. How the batch + waiting stack works
2. The full kiosk flow (Type → Room → Rate → Confirm)
3. What happens on frontdesk **confirm** / **cancel** / **auto-timeout**
4. Roomboy interactions
5. Self-healing for stale batches (`refreshIfStale`)
6. Frontdesk "Kiosk Batch" viewer modal (operations tooling)
7. Code references for every step
8. End-to-end flow diagram

Use this as the spec when testing on staging. Confirm each scenario and
flag anything that doesn't match.

---

## 0. Mental model

The kiosk is **type-scoped**: each room type (Single, Double, Twin) has its
own independent batch and waiting stack. Picking a Double doesn't affect
the Single or Twin batches.

For each `(branch, type)` there are two implicit collections:

- **Active batch** — the small set (≤ one room per floor) currently visible
  on the kiosk. Stored in `kiosk_current_batch` table.
- **Waiting stack** — every other Available/Cleaned + priority room that
  is NOT in the batch. Implicit (just rooms not in `kiosk_current_batch`).

Slot states in `kiosk_current_batch.slot_status`:

- `active` — visible on kiosk
- `picked` — guest already chose this room; floor goes blank until next batch

---

## 1. Setup state — what the kiosk shows

```
kiosk_current_batch  (per branch + per type)

  Type: DOUBLE
  ┌────────────────────────────────────────────────────┐
  │  Floor 1: Room 3   [active]  ← kiosk shows         │
  │  Floor 2: Room 65  [active]  ← kiosk shows         │
  │  Floor 3: Room 127 [active]  ← kiosk shows         │
  │  Floor 4: Room 208 [active]  ← kiosk shows         │
  │  Floor 5: Room 259 [active]  ← kiosk shows         │
  └────────────────────────────────────────────────────┘

  WAITING STACK (NOT in batch, not visible)
  Floor 1: 7, 9, 21, 24, 34, 36, 52
  Floor 2: 69, 78, 85, 90, 96, 98
  Floor 3: 128, 131, 132, ...
  ...
```

### Within a floor, which room is picked first?

The picker uses a **tiered priority** so the same low-numbered room is not
hammered while higher-numbered rooms sit idle (the "use unused rooms"
goal from the original meeting):

1. **Never-used rooms** first (rooms with `last_checkin_at IS NULL`).
2. Then **least-recently-used** rooms (oldest `last_checkin_at`).
3. Within each tier, **natural numeric order** (`natsort`) breaks ties —
   so "3" < "5A" < "21" < "256" as the client spec requires.

Example: Floor 1 has rooms `[3, 5, 10]` — Room 3 was used yesterday,
Room 5 was never used, Room 10 was used last week. The next throw picks
**Room 5** (Tier 1, never used) even though Room 3 is the lowest number.
Once Room 5 has been used, the next throw with no never-used rooms left
picks **Room 10** (Tier 2, oldest). This balances the client's literal
"ascending numerical queue" rule with the underlying goal of even room
distribution.

Code: `KioskBatchService::pickPreferredRoom()` is shared by
`throwNextBatch`, `refreshIfStale` per-slot repair, and the
`previewBatches` upcoming-batches preview, so they all use the same
selection logic.

### Rooms blocked from being picked

A room is filtered out of any new batch pick when ANY of these are true:

- It has an active row in `temporary_check_in_kiosks` (kiosk reservation in flight)
- It has an active row in `temporary_reserveds` (frontdesk reservation in flight)
- It has an orphan `Guest` (no `checkin_detail`, created within the last 2 hours)

This mirrors the kiosk's `render()` filter exactly. Without these
exclusions, `throwNextBatch` would re-pick rooms whose status is still
`Available` but which are already held — leaving the batch slot `active`
while the kiosk's render filter hides it (showing SORRY).

Code: `KioskBatchService::roomIdsBlockedFromBatch()` is the central
helper, used by all three pickers.

---

## 2. Stage A — Guest interacts with kiosk

### Step 1: SELECT ROOM TYPE

```
┌──────────┐  ┌──────────┐  ┌──────────┐
│  SINGLE  │  │  DOUBLE  │  │   TWIN   │   ← guest taps DOUBLE
└──────────┘  └──────────┘  └──────────┘
```

**Code:** `Kiosk\CheckIn::selectType($type_id)`

1. If `KioskBatchService::isEmpty(branch, type)` → `throwNextBatch()`
2. `KioskBatchService::refreshIfStale(branch, type)` (self-heal — see §5)
3. Count usable rooms in batch (Available/Cleaned + not in temp tables
   + not orphaned in last 2 hours)
4. If 0 → show "SORRY, no available room in this type"
5. Otherwise → set `$this->type_id`, advance to step 2

### Step 2: SELECT ROOM (one per floor from active batch)

```
┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐
│ Floor 1 │  │ Floor 2 │  │ Floor 3 │  │ Floor 4 │  │ Floor 5 │
│ Room 3  │  │ Room 65 │  │Room 127 │  │Room 208 │  │Room 259 │
└─────────┘  └─────────┘  └─────────┘  └─────────┘  └─────────┘
```

**Code:** `Kiosk\CheckIn::render()` — queries `kiosk_current_batch` where
`slot_status='active'` (joined with current room status filters).

Guest taps Room 3 → `selectRoom($room_id)` sets `$this->room_id`, loads
rates for the type → advances to step 3.

### Step 3: SELECT RATE (hourly stays or long stay days)

```
┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────────┐
│ 6 hours  │  │ 12 hours │  │ 24 hours │  │ Long Stay (N │
│   ₱400   │  │   ₱600   │  │   ₱900   │  │ days input)  │
└──────────┘  └──────────┘  └──────────┘  └──────────────┘
```

**Code:** `selectRate($rate_id)` then `proceedFillUp()` validates and
moves to step 4. Long-stay branches into a separate path (must have a
24h rate set up; multiplier is `branch.discount` × days).

### Step 4: SUMMARY + GUEST INFO

```
Room #: 3      Floor: 1st     Type: Double
Stay:  12h     Pay:  ₱600

Name:    [_______________]    (required, min 3 chars)
Contact: 09 [_________]        (optional, 9 digits)

[ APPLY DISCOUNT ]   ← only if rate.has_discount + branch enabled

[ CONFIRM TRANSACTION ]
```

**Code:** `confirmTransaction()` validates name/contact → confirm dialog
→ `confirmCheckIn()`.

### Step 5: QR CODE shown to guest

```
┌──────────────┐
│  ▓▓▓▓▓ ▓▓ ▓  │   Code: "12604001"
│  ▓▓ ▓▓▓ ▓ ▓▓ │   (branch_id + year + 4-digit sequence)
│  ▓▓ ▓ ▓▓▓ ▓  │
└──────────────┘
"Please proceed to FRONT DESK with this QR"
```

**`confirmCheckIn()` did the following inside a DB transaction:**

| Action | Effect |
|---|---|
| Locks the room | Prevents race with another kiosk |
| Verifies room not already Occupied | Returns SORRY if it is |
| Verifies room not in temp_check_in_kiosks / temp_reserveds | Returns SORRY if it is |
| Creates `Guest` record | guest's basic info, room/rate/type IDs, discount info |
| Creates `TemporaryCheckInKiosk` row | `terminated_at = now() + 20 min` |
| `KioskBatchService::markPicked(branch, room)` | slot: `active → picked` |
| Auto-throw next batch? | **No — wait for frontdesk confirmation** (see §5) |

After Step 5:

```
Type: DOUBLE
┌────────────────────────────────────────────────────┐
│  Floor 1: Room 3   [PICKED]  ← floor blanks on UI  │  ← guest just picked
│  Floor 2: Room 65  [active]                        │
│  Floor 3: Room 127 [active]                        │
│  Floor 4: Room 208 [active]                        │
│  Floor 5: Room 259 [active]                        │
└────────────────────────────────────────────────────┘
```

The next guest sees only Floors 2/3/4/5 for Doubles. Floor 1 stays blank
until the rest are picked.

---

## 3. Stage B — Guest walks to frontdesk

The pending check-in appears in **Frontdesk Room Monitoring**. Three things
can happen.

### 3.1 Path 1: FRONTDESK CONFIRMS (guest pays, gets keys)

**Code:** `Frontdesk\Monitoring\CheckInFromKiosk::saveCheckIn()`

Inputs from frontdesk: amount paid, has_discount, save excess option.

| Database change | Why |
|---|---|
| `CheckinDetail` created | the real check-in record |
| `Transaction` (cash payment) | accounting |
| `Transaction` (deposit) | key/remote deposit |
| `CashOnDrawer` entries | drawer reconciliation |
| `Room.status = 'Occupied'` | room is now actually used |
| `TemporaryCheckInKiosk` deleted | hold consumed |
| `NewGuestReport` entry | shift reporting |
| `ActivityLog` entry | audit trail |

**Batch effect: NONE.** The slot stays `picked` (correct — it was already
drained when guest used kiosk; the room going Occupied just confirms it).

### 3.2 Path 2: FRONTDESK CANCELS (guest changed mind, walked away)

**Code:** `Frontdesk\Monitoring\CheckInFromKiosk::cancelCheckIn()`

| Database change | Why |
|---|---|
| `Guest` row deleted | only if it has no transactions and no checkin_detail |
| `TemporaryCheckInKiosk` deleted | hold released |
| `Room.status` NOT touched | stays Available/Cleaned |
| `KioskBatchService::returnToBatch(branch, room)` | slot: `picked → active` |

After cancel:

```
Type: DOUBLE
┌────────────────────────────────────────────────────┐
│  Floor 1: Room 3   [active]  ← BACK on kiosk!      │
│  Floor 2: Room 65  [active]                        │
│  ... (unchanged)                                   │
└────────────────────────────────────────────────────┘
```

### 3.3 Path 3: AUTO-TIMEOUT (guest never showed up)

**Code:** `Console\Commands\CleanupTemporaryKiosk::handle` (`kiosk:cleanup`)

Runs **every minute** via the scheduler.

Trigger: `TemporaryCheckInKiosk.created_at` older than
`branch.kiosk_time_limit` minutes (default = 10).

For each expired hold:
- `Guest` deleted (only if no transactions/checkin)
- `TemporaryCheckInKiosk` deleted
- `KioskBatchService::returnToBatch(branch, room)` → slot: `picked → active`

End-state matches Path 2 — slot becomes `active` again, kiosk shows it.

> ⚠️ The `terminated_at` field on `TemporaryCheckInKiosk` is set to +20 min
> when the row is created (in kiosk `confirmCheckIn`). The actual cleanup
> uses `kiosk_time_limit` minutes from `created_at` (default 10). The
> shorter window wins — guest has 10 min to reach frontdesk.

---

## 4. Stage C — Roomboy interactions (parallel to all of above)

When a roomboy finishes cleaning a room:

```
Room.status      = 'Available'
Room.is_priority = 1
Room.last_checkin_at = now()
        │
        ▼
maybeFillBlankFloor(room)
        ├─ floor has any batch row for this type?
        │    YES → room joins WAITING STACK (waits for next batch)
        │    NO  → room added as ACTIVE slot (mid-batch fill!)
        │         ◄── kiosk shows it immediately
```

**Code:** `Roomboy\Index::finishCleaning` and `Roomboy\Main::finishCleaning`
both call `KioskBatchService::maybeFillBlankFloor`.

### Three scenarios for cleaning

| Floor was... | Just cleaned... | Result |
|---|---|---|
| **Blank** (never had batch row) | Room 73 on Floor 2 | Floor 2 lights up with Room 73 immediately |
| **Active** (Room 65 still showing) | Room 73 on Floor 2 | Room 73 sits in waiting stack |
| **Picked** (Floor 2 already drained) | Room 73 on Floor 2 | Room 73 sits in waiting stack |

---

## 5. Stage D — Batch refresh ("the throw") — wait-for-confirm rule

The throw is **NOT** instantaneous when a guest taps confirm on the
kiosk. The system waits for the frontdesk to fully process every pick
in the batch (via `saveCheckIn`). Only when all batch slots are `picked`
AND no `temporary_check_in_kiosks` holds remain does the throw fire.

This ensures: if frontdesk **cancels (trash 🗑️)** or the **10-min
timeout** clears a kiosk pick, the cancelled room reappears in the
SAME batch as `active` — instead of being lost to the next batch's
waiting stack.

Code: `KioskBatchService::maybeThrowNextBatch()` is the gatekeeper,
called from `Frontdesk\Monitoring\CheckInFromKiosk::saveCheckIn()`
after every successful frontdesk confirm.

Example timeline:

1. Initial batch: `[3A active, 203 active, 257 active]`
2. Guest picks 3A on kiosk → slot 'picked'. Batch `[3A picked, 203 active, 257 active]`. No throw.
3. Guest picks 203 → `[3A picked, 203 picked, 257 active]`. No throw.
4. Guest picks 257 → `[3A picked, 203 picked, 257 picked]`. **Still no throw — temp holds exist.**
5. Frontdesk cancels 257 (trash) → `[3A picked, 203 picked, 257 active]`. Cancelled room is back on kiosk.
6. Another guest picks 257 → `[3A picked, 203 picked, 257 picked]` (all picked again).
7. Frontdesk confirms 3A → temp 3A cleared. Still 2 holds → no throw.
8. Frontdesk confirms 203 → temp 203 cleared. 1 hold → no throw.
9. Frontdesk confirms 257 → temp 257 cleared. 0 holds left → **throw fires**.
10. New batch: `[3C active, 4A active, 293 active]` (next preferred per floor).

After picks accumulate:

```
Type: DOUBLE
┌────────────────────────────────────────────────────┐
│  Floor 1: Room 3   [PICKED]                        │
│  Floor 2: Room 65  [PICKED]                        │
│  Floor 3: Room 127 [PICKED]                        │
│  Floor 4: Room 208 [PICKED]                        │
│  Floor 5: Room 259 [PICKED]                        │
└────────────────────────────────────────────────────┘
                    │
                    ▼  markPicked detects 0 active left
                    │
        throwNextBatch(branch, type)
                    │
                    ▼
Type: DOUBLE
┌────────────────────────────────────────────────────┐
│  Floor 1: Room 7   [active]  ← next from stack     │
│  Floor 2: Room 69  [active]                        │
│  Floor 3: Room 128 [active]                        │
│  Floor 4: Room 212 [active]                        │
│  Floor 5: Room 262 [active]                        │
└────────────────────────────────────────────────────┘

WAITING STACK (now smaller):
Floor 1: 9, 21, 24, 34, 36, 52   ← 7 was promoted (natsort order)
Floor 2: 78, 85, 90, 96, 98       ← 69 was promoted
...
```

`throwNextBatch()` deletes ALL rows for `(branch, type)` and re-inserts
one row per floor — the preferred Available/Cleaned room with
`is_priority = 1` per floor, selected by the tiered priority described
in §0 (never-used > least-recently-used > natsort).

If a floor has no available rooms → no row inserted → that floor stays
blank for the new batch (until a roomboy cleans something).

---

## 6. Self-healing — `refreshIfStale`

A batch slot can become stale if its room becomes Occupied through a
**non-kiosk** path (frontdesk direct check-in, manual status edit, etc.).
The slot stays `active` but points to an unusable room. Without help, the
kiosk shows "SORRY" forever even when other rooms are available.

**Code:** `KioskBatchService::refreshIfStale(branch, type)`

Called from:
- `Kiosk\CheckIn::render()`
- `Kiosk\CheckIn::selectType()`

Logic:
1. Get all `active` room IDs for `(branch, type)`
2. Check: any of them still Available/Cleaned + priority?
   - YES → no-op, return false
   - NO  → `throwNextBatch()` to recover, return true

This makes the batch self-healing on every kiosk interaction.

---

## 6.5 Frontdesk "Kiosk Batch" viewer modal

A button on Room Monitoring (next to **Check-In C/O**) labeled
**Kiosk Batch** opens a modal showing live batch state without staff
having to walk over to the kiosk. **This is operations tooling — not
part of the original client spec.**

For each room type, the modal shows three rows of rooms (floors as
columns, batches as rows):

| Row | Meaning |
|---|---|
| **NOW** (green highlight) | What guests see on the kiosk right now (active = green chip; picked = amber struck-through chip) |
| **NEXT** | Rooms the system will pick the next time `throwNextBatch` fires (after current batch fully drains) |
| **AFTER** | The batch after that (`Batch +2`) |

Empty cells (`—`) mean: no room available on that floor for that batch
(either no rooms of that type exist on the floor, or none are ready).

**Code:**
- Service: `KioskBatchService::previewBatches($branchId, $typeId, $count)`
  — read-only preview, never modifies the DB
- Component: `Frontdesk\Monitoring\RoomMonitoring::showKioskBatch()` /
  `closeKioskBatchModal()`
- View: modal block at the bottom of
  `resources/views/livewire/frontdesk/monitoring/room-monitoring.blade.php`

The "Refresh" button in the modal footer re-fetches state via the same
`showKioskBatch()` call.

---

## 7. Master batch-effects cheat sheet

| Event | Slot before | Slot after | Notes |
|---|---|---|---|
| Guest picks on kiosk | active | picked | floor blanks on kiosk; NO auto-throw |
| Frontdesk **confirms** payment | picked | picked (no change) | calls `maybeThrowNextBatch` — fires if all picked AND no holds left |
| Frontdesk **cancels** | picked | active | room re-enters SAME batch (kiosk shows it again) |
| Auto-timeout (10 min, no frontdesk action) | picked | active | guest never showed; same effect as cancel |
| All slots picked AND all holds confirmed | (all picked, 0 holds) | new batch thrown | tiered priority per floor |
| Roomboy cleans on **blank** floor | (no row) | new active row | mid-batch floor fill |
| Roomboy cleans on **active/picked** floor | unchanged | unchanged | room waits for next batch |
| Frontdesk **direct** check-in (bypasses kiosk) | active (stale) | active (still stale) | caught by `refreshIfStale` next render |

---

## 8. Code map (file references)

| File | Purpose |
|---|---|
| `app/Services/KioskBatchService.php` | All batch logic (throw, mark, return, fill, refresh) |
| `app/Models/KioskCurrentBatch.php` | Eloquent model for `kiosk_current_batch` |
| `database/migrations/2026_04_24_191441_create_kiosk_current_batch_table.php` | Schema |
| `app/Http/Livewire/Kiosk/CheckIn.php` | Kiosk UI flow (steps 1-5) + `refreshIfStale` calls |
| `app/Http/Livewire/Frontdesk/Monitoring/CheckInFromKiosk.php` | Frontdesk confirm/cancel + `returnToBatch` |
| `app/Console/Commands/CleanupTemporaryKiosk.php` | Auto-timeout (`kiosk:cleanup`) |
| `app/Http/Livewire/Roomboy/Index.php` | Roomboy view A → `maybeFillBlankFloor` |
| `app/Http/Livewire/Roomboy/Main.php` | Roomboy view B → `maybeFillBlankFloor` |
| `app/Http/Livewire/Frontdesk/Monitoring/RoomMonitoring.php` | Kiosk Batch viewer modal — `showKioskBatch()` / `closeKioskBatchModal()` |
| `resources/views/livewire/frontdesk/monitoring/room-monitoring.blade.php` | "Kiosk Batch" button + modal HTML |
| `tests/Feature/KioskBatch/KioskBatchTest.php` | 21 feature tests covering all paths |

---

## 9. Manual test checklist (run on staging)

Use this when verifying after deploy. Tick each item.

### Setup
- [ ] Truncate `kiosk_current_batch` once on staging (forces fresh throws)

### Basic display
- [ ] Open kiosk → SELECT ROOM TYPE shows Single, Double, Twin
- [ ] Tap DOUBLE → SELECT ROOM shows ≤ 5 rooms (one per floor)
- [ ] Each room is the **numerically lowest** Available room on its floor
  (no string-sort weirdness like "21" appearing before "3")

### Pick → blank
- [ ] Pick a Floor 1 Double → complete check-in flow → get QR
- [ ] Reopen kiosk → SELECT DOUBLE → Floor 1 is blank, other floors unchanged

### Roomboy mid-batch
- [ ] Roomboy cleans a Floor 1 room (which is now blank in batch) → kiosk
  shows that Floor 1 room mid-batch
- [ ] Roomboy cleans a Floor 2 room (Floor 2 still has active slot) → kiosk
  unchanged (room sits in waiting stack)

### Frontdesk paths
- [ ] Make a kiosk reservation, then **frontdesk cancels** → that floor's
  room reappears on the kiosk
- [ ] Make a kiosk reservation, **wait 10+ minutes** (or trigger
  `kiosk:cleanup` manually) → room reappears on the kiosk

### Batch throw
- [ ] Pick all 5 floors of Double → batch auto-throws → next set appears
  with next-lowest rooms

### Stale recovery
- [ ] Make a kiosk reservation, **frontdesk confirms** → room is Occupied
- [ ] Manually mark another batch room as Occupied (admin or DB)
- [ ] Reopen kiosk → if all batch rooms are now unusable, `refreshIfStale`
  should auto-throw a new batch (no SORRY error if rooms still exist)

### Type independence
- [ ] Pick a Double → confirm picking did NOT affect Single or Twin batches

### Frontdesk batch viewer
- [ ] Open Room Monitoring → see new **Kiosk Batch** button next to Check-In C/O
- [ ] Click → modal opens showing NOW / NEXT / AFTER rows per type
- [ ] Pick a room on the kiosk → reopen modal → that room appears struck-through (amber) in NOW row
- [ ] Frontdesk confirms the kiosk reservation → reopen modal → batch state matches reality
- [ ] Click Refresh → modal re-fetches without closing

---

## 9.5 End-to-end flow diagram

Combined view: roomboy fills the stack, kiosk shows the batch, guest picks,
frontdesk handles the result.

```
┌──────────────────────────────────────────────────────────────────────────┐
│ ROOMBOY                                                                  │
│  finishCleaning(room) → Room.status = 'Available'                        │
│         │                                                                │
│         ▼                                                                │
│  KioskBatchService::maybeFillBlankFloor(room)                            │
│         ├─ floor has any batch row?  YES → room joins WAITING STACK      │
│         └─ floor has NO batch row    NO  → room added as ACTIVE slot     │
└──────────────────────────────────────────────────────────────────────────┘
                          │
                          ▼
   ┌─────────────────────────────────────────────────────────┐
   │ kiosk_current_batch  (per branch + type)                │
   │                                                         │
   │  Floor 1: Room  3 [active] ◄── shown on kiosk           │
   │  Floor 2: Room 65 [active] ◄── shown on kiosk           │
   │  Floor 3: Room 127[active] ◄── shown on kiosk           │
   │  Floor 4: Room 208[active] ◄── shown on kiosk           │
   │  Floor 5: Room 259[active] ◄── shown on kiosk           │
   │                                                         │
   │  WAITING STACK (rooms not in batch, sorted natsort)     │
   │  Floor 1: 7, 9, 21, 24, 34, 36, 52                      │
   │  Floor 2: 69, 78, 85, ...                               │
   └─────────────────────────────────────────────────────────┘
                          │
                          ▼
┌──────────────────────────────────────────────────────────────────────────┐
│ KIOSK (Guest)                                                            │
│  render() → refreshIfStale() → show one room per floor                   │
│                                                                          │
│  Step 1: SELECT ROOM TYPE      (Single / Double / Twin)                  │
│  Step 2: SELECT ROOM           (one per floor from active batch)         │
│  Step 3: SELECT RATE           (6h / 12h / 24h / Long Stay)              │
│  Step 4: SUMMARY + GUEST INFO  (name + optional contact)                 │
│  Step 5: QR CODE shown                                                   │
│                                                                          │
│  confirmCheckIn()  ────────────────────────────────────────────────►     │
│    • creates Guest + TemporaryCheckInKiosk                               │
│    • markPicked() → slot 'active' → 'picked'                             │
│    • if 0 active left → throwNextBatch (auto refresh)                    │
└──────────────────────────────────────────────────────────────────────────┘
                          │
                          ▼
┌──────────────────────────────────────────────────────────────────────────┐
│ FRONTDESK ROOM MONITORING                                                │
│ (pending kiosk reservation appears in queue)                             │
│                                                                          │
│  ┌──── confirms ────► saveCheckIn() ─► Room.status='Occupied'            │
│  │                  (slot stays 'picked', batch unaffected)              │
│  │                                                                       │
│  ├──── cancels  ────► cancelCheckIn() ─► returnToBatch()                 │
│  │                  (slot 'picked' → 'active', kiosk shows again)        │
│  │                                                                       │
│  └─ no action 10 min ► kiosk:cleanup ─► returnToBatch()                  │
│                  (auto-rescue, slot 'picked' → 'active')                 │
│                                                                          │
│  Kiosk Batch button (modal) — read-only preview of NOW / NEXT / AFTER    │
└──────────────────────────────────────────────────────────────────────────┘
```

### Master cheat sheet

| Event | Slot before | Slot after | Why |
|---|---|---|---|
| Guest picks on kiosk | active | picked | floor blanks for guest pool |
| Frontdesk **confirms** payment | picked | picked (no change) | slot was already drained on kiosk pick |
| Frontdesk **cancels** | picked | active | room re-enters kiosk display |
| 10-min auto-timeout (no frontdesk action) | picked | active | guest never showed; room returns |
| All slots picked for a type | (n picked, 0 active) | new batch thrown | next-lowest-numerical per floor |
| Roomboy cleans on **blank** floor | (no row) | new active row | mid-batch floor fill |
| Roomboy cleans on **active/picked** floor | unchanged | unchanged | room waits for next batch |
| Frontdesk **direct** check-in (bypasses kiosk) | active (stale) | active (stale) | caught by `refreshIfStale` next render |

---

## 10. Known limitations / open questions

- **Per-type vs per-branch batching**: client spec example doesn't mention
  types, but UI requires type selection first. Current implementation is
  per-type (each type rotates independently). Confirm this matches client's
  expectation before final sign-off.
- **Branch 2 (FLOR-AL MANSION ILIGAN)** has no rooms in the local
  staging-snapshot DB. Verify staging data is complete for that branch.
- **Blank floors in UI**: current blade view groups by `floor_id` so blank
  floors are simply hidden. Client may want a placeholder ("Coming soon")
  instead. Flag during testing.
- **Frontdesk Kiosk Batch viewer is NOT in client spec** — it was added as
  internal ops tooling. Confirm with client whether they want to keep it
  or remove it before final sign-off.
