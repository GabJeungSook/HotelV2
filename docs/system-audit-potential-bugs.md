# System Audit: Potential Bugs & Hidden Risks

> **Read-only audit produced 2026-04-30 by spawning 3 parallel investigation
> agents** (financial integrity, state synchronization, concurrency &
> data integrity).

## How to use this doc

Each finding has:
- **Severity**: HIGH / MEDIUM / LOW
- **Confidence**: HIGH / MEDIUM / LOW
- **File:line** for direct navigation
- **Trigger**: how to reproduce
- **Evidence**: the suspect code pattern
- **Status**: `✅ FIXED (commit X)` once shipped, otherwise backlog

Treat HIGH/HIGH as priority backlog. MEDIUM/HIGH or HIGH/MEDIUM are likely
real bugs that haven't surfaced yet because the trigger is rare.

## Status as of 2026-05-01

**13 of 30 audit findings fixed** across two commits on branch
`feature/temp-disable-supervisor`:

Commit `444841c` (5 finance bugs):
- ✅ **A1** — Admin Check-In C/O long-stay multiplier
- ✅ **A2** — RoomMonitoring storeGuest long-stay multiplier
- ✅ **A6** — `payAllUnpaid` sets `paid_amount` per row
- ✅ **A7** — `addOverride` sets `paid_amount` + `is_override`
- ✅ **A11** *(found during planning)* — Admin Reservation long-stay multiplier

Commit *(this commit)* (8 batch-sync + audit fixes):
- ✅ **A8** — `claimAllDeposit` idempotency guard
- ✅ **A10** — Null-safe `?->amount ?? 0` on 6 unguarded rate lookups
- ✅ **B2** — RoomMonitoring `saveReserveCheckInDetails` notifies kiosk batch
- ✅ **B3** — Both `checkoutGuest` paths heal kiosk batch on checkout
- ✅ **B4** — `TerminationInKiosk` job calls `returnToBatch`
- ✅ **B6** *(defensive)* — Roomboy `startCleaning` calls `refreshIfStale`
- ✅ **B7** — `PriorityRoom::removePriority` notifies kiosk batch
- ✅ **B8** — RoomMonitoring `saveCheckIn` (kiosk-walk-in path) notifies batch

Remaining backlog: **17 findings** (the rest of this document).

Skipped intentionally:
- **B9** (TemporaryReserved no cancellation hook) — no UI cancellation path
  exists yet; would be premature. Will be addressed when cancellation feature
  is added.

Recovery scripts for historical data:
- `docs/recovery-paid-amount-zero.md` — A6 + A7 (idempotent)
- `docs/recovery-longstay-walkin-undercharge.md` — A1, A2, A11 (per-guest)

---

## CATEGORY A — FINANCIAL / MONEY-CALCULATION BUGS

### A1. Admin "Check-In C/O" drops long-stay multiplier — silent under-charge ✅ FIXED
- **Status:** ✅ FIXED in commit `444841c` (2026-05-01)
- **Severity:** HIGH | **Confidence:** HIGH
- **File:** `app/Http/Livewire/Admin/CheckInCo.php:66, 110`
- **Pattern:** Long-stay guests got `static_amount = $rate->amount` (single-day rate), never multiplied by `number_of_days`. `hours_stayed` IS multiplied so the row was internally inconsistent: charged for 1 day but check-out scheduled for N days.
- **Fix:** Branched on `is_longStay`, multiplies max 24h rate by number of days, mirroring `Kiosk/CheckIn::proceedFillUp`.

### A2. RoomMonitoring `storeGuest` skips long-stay multiplier ✅ FIXED
- **Status:** ✅ FIXED in commit `444841c` (2026-05-01)
- **Severity:** HIGH | **Confidence:** HIGH
- **File:** `app/Http/Livewire/Frontdesk/Monitoring/RoomMonitoring.php:917-919`
- **Pattern:** `updatedRateId()` set `total = Rate::amount + 200` (single-day) regardless of `is_longStay`.
- **Fix:** `updatedRateId` now multiplies max 24h rate by `(int) $this->is_longStay` for long-stay path; total = roomCharge + 200.

### A3. SalesReport.php double-multiplies `hours_stayed` for long-stays
- **Severity:** MEDIUM (display only) | **Confidence:** HIGH
- **File:** `app/Http/Livewire/BackOffice/SalesReport.php:509-512`
- **Pattern:** `hours_stayed` is stored as `24 × number_of_days` for long-stays, but this view multiplies again: `$detail->hours_stayed * $guest?->number_of_days`. Recent commit `1f2025d` fixed this elsewhere; this site was missed.

### A4. ManageGuestTransaction `updatedTypeId` — same TransferRoom NULL-rate pattern
- **Severity:** HIGH | **Confidence:** HIGH
- **File:** `app/Http/Livewire/Frontdesk/Monitoring/ManageGuestTransaction.php:786-800`
- **Pattern:** Identical to the bug just fixed in TransferRoom: `Rate::where(...)->whereHas('stayingHour', ... 'number' = $hours)->first()` where `$hours` for long-stay = `24×days`, never present in `staying_hours`. Result: `$new_room` NULL → calling `$new_room->amount` throws fatal error OR silently sets total to 0.
- **Trigger:** Long-stay guest transferred via the legacy "Transfer" modal inside ManageGuestTransaction.
- **Same pattern as:** Bug ③.

### A5. GuestTransaction `updatedTypeId` — same NULL-rate + comparison flaw
- **Severity:** HIGH | **Confidence:** HIGH
- **File:** `app/Http/Livewire/Frontdesk/Monitoring/GuestTransaction.php:1495-1520`
- **Pattern:** Same lookup pattern using `hours_stayed` for the destination rate. Long-stay guests cause `$new_room === null` branch. Even when not null, comparing `$new_room->amount > $check_in_details->static_room_amount` is wrong because `static_room_amount` is per-day for some flows and total for others (see A1, A2). Wrong delta computed.

### A6. `payAllUnpaid` sets `paid_at` without `paid_amount` ✅ FIXED
- **Status:** ✅ FIXED in commit `444841c` (2026-05-01)
- **Severity:** HIGH | **Confidence:** HIGH
- **File:** `app/Http/Livewire/Frontdesk/Monitoring/ManageGuestTransaction.php:1534-1549`
- **Pattern:** Updated unpaid transactions to `paid_at = now()` but left `paid_amount = 0`. Cash-drawer reconciliation broken; audit trail says "paid ₱0" when guest paid the full amount.
- **Fix:** Now also sets `paid_amount = DB::raw('payable_amount')` per row.
- **Note:** `addAllPaymentWithDeposit` (line 1582) intentionally keeps `paid_amount = 0` (deposits don't add new cash). Left untouched.

### A7. `addOverride` doesn't set `paid_amount` or `is_override` ✅ FIXED
- **Status:** ✅ FIXED in commit `444841c` (2026-05-01)
- **Severity:** HIGH (audit-trail) | **Confidence:** HIGH
- **File:** `app/Http/Livewire/Frontdesk/Monitoring/ManageGuestTransaction.php:1923-1934`
- **Pattern:** Override action set `payable_amount = override_amount` but neither `paid_amount` (stayed 0) nor `is_override = true` (stayed false). SalesReportV2:718 conditional read on `is_override` couldn't detect override rows.
- **Fix:** Now also sets `paid_amount = $this->override_amount` and `is_override = true`.

### A8. `claimAllDeposit` overstates Damage Charges, no idempotency
- **Severity:** HIGH | **Confidence:** MEDIUM
- **File:** `app/Http/Livewire/Frontdesk/Monitoring/ManageGuestTransaction.php:1664-1715`
- **Pattern:** When `is_checkout = false`, creates a "Damage Charges" tx of `deposit_remote_and_key` and a Cashout of `$balance`. `total_deduction` incremented by `$balance` only — the damage charge becomes an unpaid bill. On second invocation it again creates a Damage row for the same key/remote deposit (no idempotency guard), double-charging.
- **Trigger:** Frontdesk clicks "Claim All Deposit" twice, or mid-stay.

### A9. ExtendGuest "Priority 1" rate lookup not branch-scoped
- **Severity:** MEDIUM | **Confidence:** MEDIUM
- **File:** `app/Http/Livewire/Frontdesk/Monitoring/ExtendGuest.php:115-122, 132-136`
- **Pattern:** `whereHas('stayingHour', fn ... where('number', $extended_rate->hour))` doesn't scope by branch. Multi-branch deployments could pick wrong-branch rate if a different branch has same `number`.

### A10. `updatedExtendRate` — multiple unguarded `->first()->amount` ✅ FIXED
- **Status:** ✅ FIXED in commit on branch `feature/batch-sync-and-finance-cleanup-may-1` (2026-05-01)
- **Severity:** MEDIUM | **Confidence:** HIGH
- **File:** `app/Http/Livewire/Frontdesk/Monitoring/ManageGuestTransaction.php:1133, 1140, 1186, 1194, 1216, 1222`
- **Pattern:** Six chained `->first()->amount` calls. Note: `?? 0` on lines 1186/1194 wasn't actually safe — null-coalescing happens *after* the `->amount` access, so `null->amount` errored before reaching `?? 0`.
- **Fix:** All 6 sites now use `->first()?->amount ?? 0` (PHP 7+ null-safe operator), so missing rate config no longer fatals the extension flow.

### A11. Admin Reservation long-stay multiplier ✅ FIXED
- **Status:** ✅ FIXED in commit `444841c` (2026-05-01)
- **Severity:** HIGH | **Confidence:** HIGH
- **File:** `app/Http/Livewire/Admin/Manage/Reservation.php:99` (long-stay branch of `saveReservation`)
- **Pattern:** Long-stay reservation branch used `Rate::where('id',$rate_id)->first()->amount` (single-day) and stored as `static_amount`, the same shape as A1 + A2. Found during the planning phase by Phase 1 explore agent — added to fix scope.
- **Fix:** Now uses `max(amount) for type * (int) $this->number_of_days`, matching the validated `number_of_days` form field.

---

## CATEGORY B — STATE SYNCHRONIZATION BUGS

### B1. Admin Reservation creation marks room `Reserved` but never notifies kiosk batch
- **Severity:** HIGH | **Confidence:** HIGH
- **File:** `app/Http/Livewire/Admin/Manage/Reservation.php:140, 189`
- **Pattern:** `saveReservation()` creates `TemporaryReserved` + sets `Room.status = 'Reserved'`. No `KioskBatchService` call. If active kiosk slot is currently pointing at this room, it becomes stale.

### B2. Frontdesk reservation check-in (`saveReserveCheckInDetails`) doesn't notify batch ✅ FIXED
- **Status:** ✅ FIXED on branch `feature/batch-sync-and-finance-cleanup-may-1` (2026-05-01)
- **File:** `app/Http/Livewire/Frontdesk/Monitoring/RoomMonitoring.php:1566-1573`
- **Fix:** Added `KioskBatchService::refreshIfStale($branch_id, $room->type_id)` after DB::commit so the just-occupied reservation room's stale slot gets healed.

### B3. Checkout from `GuestTransaction` and `ManageGuestTransaction` — no batch refill ✅ FIXED
- **Status:** ✅ FIXED on branch `feature/batch-sync-and-finance-cleanup-may-1` (2026-05-01)
- **File:** `app/Http/Livewire/Frontdesk/Monitoring/GuestTransaction.php:2418-2477`, `ManageGuestTransaction.php:1857-1903`
- **Fix:** Both `checkoutGuest` paths now call `KioskBatchService::refreshIfStale($branch_id, $guest->type_id)` after DB::commit so any stale slot left by the just-vacated (now Uncleaned) room is repaired immediately.

### B4. `TerminationInKiosk` job deletes hold without notifying batch ✅ FIXED
- **Status:** ✅ FIXED on branch `feature/batch-sync-and-finance-cleanup-may-1` (2026-05-01)
- **File:** `app/Jobs/TerminationInKiosk.php`
- **Fix:** Job now calls `KioskBatchService::returnToBatch($room->branch_id, $room_id)` after deleting the temp hold, mirroring the cleanup cron's behavior. If ever activated, no more orphan picked slots.

### B5. Ghost Rooms / `rooms:fix-ghost` flip Occupied → Available without notifying batch
- **Severity:** HIGH | **Confidence:** HIGH
- **Files:**
  - `app/Http/Livewire/Admin/GhostRooms.php:45, 68`
  - `app/Http/Livewire/Frontdesk/Monitoring/RoomMonitoring.php:393`
  - `app/Console/Commands/FixGhostRooms.php:86`
- **Pattern:** All three flip `status='Available'` without calling `maybeFillBlankFloor`. Same symptom as the Admin Update Room fix we just shipped.

### B6. Roomboy `startCleaning` flips Uncleaned → Cleaning without batch notification ✅ FIXED (defensive)
- **Status:** ✅ FIXED on branch `feature/batch-sync-and-finance-cleanup-may-1` (2026-05-01)
- **Files:** `app/Http/Livewire/Roomboy/Main.php:170`, `app/Http/Livewire/Roomboy/Index.php:77`
- **Note:** Verified that the bug described by the audit doesn't actually trigger — Uncleaned rooms aren't in the kiosk batch (filter is Available/Cleaned), so going Uncleaned → Cleaning can't create a stale slot. **However** added a defensive `refreshIfStale` call for symmetry with `finishCleaning`. No-op when no stale slots exist; harmless and maintains invariants.

### B7. `PriorityRoom::removePriority` clears `is_priority` without notifying batch ✅ FIXED
- **Status:** ✅ FIXED on branch `feature/batch-sync-and-finance-cleanup-may-1` (2026-05-01)
- **File:** `app/Http/Livewire/Frontdesk/PriorityRoom.php:67-77`
- **Fix:** `removePriority` now calls `KioskBatchService::refreshIfStale($room->branch_id, $room->type_id)` after the update so the kiosk batch stops showing the non-priority room immediately.

### B8. `RoomMonitoring::saveCheckIn` (kiosk-walked-in path) — no batch refresh ✅ FIXED
- **Status:** ✅ FIXED on branch `feature/batch-sync-and-finance-cleanup-may-1` (2026-05-01)
- **File:** `app/Http/Livewire/Frontdesk/Monitoring/RoomMonitoring.php:1345-1359`
- **Fix:** Mirror of the existing fix in `CheckInFromKiosk::saveCheckIn` — now calls `KioskBatchService::refreshFloorSlot` for the room's floor after committing.

### B9. No cancellation hook for `TemporaryReserved` (deferred)
- **Status:** ⏸ DEFERRED — no UI path exists to cancel a reservation, so the
  bug can't be triggered today. Re-evaluate when a cancellation feature is added.
- **File:** Nowhere — that's the gap
- **Pattern:** Asymmetric lifecycle vs. kiosk holds. Kiosk holds have `cancelCheckIn` and a cron cleanup; frontdesk reservations have no expiration/cancellation hook.

---

## CATEGORY C — CONCURRENCY & DATA-INTEGRITY BUGS

### C1. Frontdesk confirm-from-kiosk: open-checkin guard inside transaction but WITHOUT `lockForUpdate`
- **Severity:** HIGH | **Confidence:** HIGH
- **File:** `app/Http/Livewire/Frontdesk/Monitoring/CheckInFromKiosk.php:221-260`
- **Pattern:** `saveCheckIn()` reads `CheckinDetail::where('room_id',...)->where('is_check_out', false)->first()` then `CheckinDetail::create()`. No row lock between read and write. Two staff/tabs clicking Confirm simultaneously can both pass and create two check-in details + duplicate transactions.

### C2. Cron `kiosk:cleanup` races with `confirmCheckIn` and `saveCheckIn`
- **Severity:** HIGH | **Confidence:** MEDIUM
- **Files:** `app/Console/Commands/CleanupTemporaryKiosk.php:45-66` vs. `CheckInFromKiosk.php:178-454`
- **Pattern:** Cleanup deletes orphaned `Guest` (no checkInDetail, no transactions) without locking. Frontdesk's `saveCheckIn` doesn't lock the Guest either. Cron firing mid-confirm could delete the guest the frontdesk transaction is using → FK orphans.

### C3. API `CheckInController::store` has NO transaction, NO locks, NO occupancy guard
- **Severity:** HIGH | **Confidence:** HIGH
- **File:** `app/Http/Controllers/API/CheckInController.php:15-95`
- **Pattern:** All concurrency guards commented out (lines 27-58). `Guest::create` and `TemporaryCheckInKiosk::create` outside any DB transaction. No room occupancy check. No `lockForUpdate`. The transaction-code generator (`Guest::whereYear()->count()+1`) is a classic increment race → duplicate `qr_code` values. Hardcoded `addMinutes(20)` ignores `branch->kiosk_time_limit`.

### C4. TransferRoom — no row lock on destination room
- **Severity:** HIGH | **Confidence:** HIGH
- **File:** `app/Http/Livewire/Frontdesk/Monitoring/TransferRoom.php:422-668`
- **Pattern:** Opens `DB::beginTransaction` but never reads destination room with `lockForUpdate`. Two transfers to the same destination simultaneously (or transfer racing with kiosk pick) both update status to `Occupied`, overwriting each other's bookkeeping.

### C5. `ExtendGuest::saveExtend` no row-lock, no idempotency guard
- **Severity:** HIGH | **Confidence:** HIGH
- **File:** `app/Http/Livewire/Frontdesk/Monitoring/ExtendGuest.php:156-308`
- **Pattern:** No `lockForUpdate` on `CheckinDetail`. Double-clicking "Save Extend" or two tabs creates two extension transactions, two `StayExtension` rows, doubles `addHours()` to `check_out_at`. No unique constraint preventing this.

### C6. Roomboy `finishCleaning` unprotected status read-then-write
- **Severity:** MEDIUM | **Confidence:** MEDIUM
- **File:** `app/Http/Livewire/Roomboy/Index.php:148-252`
- **Pattern:** Reads room without lock, updates `status => 'Available'` at end. If frontdesk transitions room to Occupied between read and write (in `CheckInFromKiosk::saveCheckIn` which also doesn't lock), roomboy update overwrites — ending with Available room with active checkin_detail (a ghost). `maybeFillBlankFloor` then puts the just-occupied room in kiosk batch.

### C7. API `OccupiedRoomController::occupiedRooms` and `QrRoomController::getRoomByQr` — no branch authz
- **Severity:** HIGH | **Confidence:** HIGH
- **Files:**
  - `app/Http/Controllers/API/OccupiedRoomController.php:16-33`
  - `app/Http/Controllers/API/QrRoomController.php:18`
- **Pattern:** Accepts `$branchId` from URL/body and returns guest details for ANY branch. No check that `auth()->user()->branch_id == $branchId`. Multi-tenant data leak: any authenticated user from branch A can read another branch's data.

### C8. BackOffice reports leak across tenants
- **Severity:** MEDIUM-HIGH | **Confidence:** HIGH
- **Files:**
  - `app/Http/Livewire/BackOffice/Reports/OccupiedRoom.php:14-23`
  - `app/Http/Livewire/BackOffice/Reports/Guest.php:33-41`
- **Pattern:** `Guest::loadQuery()` case 3 (`whereHas('transactions',...)`) has NO `where('branch_id', auth()->user()->branch_id)` on the outer Guest query — returns guests from every branch.

### C9. Null-pointer hazards on chained model accessors
- **Severity:** MEDIUM | **Confidence:** HIGH
- **Files:**
  - `app/Http/Livewire/Frontdesk/Monitoring/TransferRoom.php:74-81` — `$this->guest->checkInDetail->rate->amount`
  - `app/Http/Livewire/Frontdesk/Monitoring/ExtendGuest.php:50-60` — `Rate::...->first()->staying_hour_id`
  - `app/Http/Livewire/Frontdesk/Monitoring/CheckInFromKiosk.php:193, 302, 347, 392` — `Rate::where('id', $this->guest->rate_id)->first()->stayingHour->number`, `auth()->user()->frontdesk->id`
- **Pattern:** Direct chained accessors with no `?->` or null-check. Stale data, deleted rate, user without `frontdesk` relation → fatal error.

### C10. Honorable mentions (lower priority but worth noting)
- `TransferRoom.php:140-149` — destination Room listing has no row lock; by saveTransfer it may be Occupied
- `Roomboy/Index::startCleaning:44` — `Room::where('id',$room_id)->first()` without branch filter
- `OverrideRequests::cancelRequest:105` — `OverrideRequest::find($requestId)` no branch_id check before delete
- `Kiosk\CheckIn.php:365` — `Guest::whereYear('created_at', now()->year)->lockForUpdate()->count()` locks all year's rows; under load serializes every kiosk check-in

---

## CROSS-CUTTING THEMES

### Theme 1 — "Long-stay multiplier missed" pattern recurs across the codebase

The bug we fixed in TransferRoom has TWIN siblings in:
- A1: Admin/CheckInCo
- A2: RoomMonitoring's storeGuest
- A3: SalesReport (display)
- A4: ManageGuestTransaction's updatedTypeId
- A5: GuestTransaction's updatedTypeId

**Recommendation:** A single helper `RateService::longStayAwareRate($guest, $typeId)` would eliminate this whole class. Until that exists, every new transfer/check-in/extend code path is a fresh chance to repeat the bug.

### Theme 2 — "Room status changes that bypass KioskBatchService"

Originally the bug we fixed:
- ⑥ TransferRoom — fixed
- ⑦ Admin Update Room — fixed

Same defect remains in:
- B1 Admin Reservation
- B2 Reservation check-in
- B3 Checkout flows
- B4 TerminationInKiosk job
- B5 Ghost room fixers (3 places)
- B6 Roomboy startCleaning (asymmetric)
- B7 PriorityRoom removePriority
- B8 RoomMonitoring::saveCheckIn

**Recommendation:** Convert every direct `Room::...->update(['status' => ...])` into a `RoomStatusService::transitionTo($room, $newStatus)` that auto-fires the right batch hook. Forces every code path to go through one place.

### Theme 3 — "Read-then-write without lockForUpdate"

Most write paths skip row-level locks:
- C1 (CheckInFromKiosk)
- C4 (TransferRoom destination)
- C5 (ExtendGuest)
- C6 (Roomboy finishCleaning)

Plus the well-implemented `Kiosk\CheckIn::confirmCheckIn` shows the team knows how to do it right (`lockForUpdate()` on every relevant table). It just wasn't applied consistently.

**Recommendation:** Audit every `DB::beginTransaction` in the codebase, ensure each touches `lockForUpdate` on the rows it depends on. Add a guideline in CLAUDE.md.

### Theme 4 — Multi-tenant leak surface

- C7 (API endpoints)
- C8 (BackOffice reports)
- C10 honorable mentions
- A9 (rate lookups)

Several places trust `branch_id` from input or omit it entirely. Multi-tenant isolation is enforced by convention, not by tooling.

**Recommendation:** Add a global query scope or middleware that auto-injects `branch_id = auth()->user()->branch_id` for branch-bound models. Or write tests that enumerate every query and assert branch_id presence.

---

## Recommended priority order for follow-up

```
   ✅ FIXED 2026-05-01 (commit 444841c — finance):
   ────────────────────────────────────────────────
   A1, A2, A6, A7, A11

   ✅ FIXED 2026-05-01 (this commit — batch sync + audit):
   ────────────────────────────────────────────────────────
   A8, A10, B2, B3, B4, B6, B7, B8

   ⏸ DEFERRED (no trigger path exists yet):
   ─────────────────────────────────────────
   B9 — TemporaryReserved no cancellation hook

   IMMEDIATE (revenue impact / data corruption — still open):
   ─────────────────────────────────────────────────────────
   C7 — API tenant leak (OccupiedRoomController, QrRoomController)
   C8 — BackOffice reports tenant leak (Guest::loadQuery case 3)

   HIGH (silent state drift — still open):
   ────────────────────────────────────────
   A4, A5 — ManageGuestTransaction / GuestTransaction long-stay rate lookup
   B1 — Admin Reservation create missing batch hook (sibling of B2)
   B5 — Ghost room fixers missing batch hooks (3 files)

   HIGH (race conditions under concurrent use — still open):
   ──────────────────────────────────────────────────────────
   C1 — CheckInFromKiosk no lockForUpdate
   C4 — TransferRoom no destination lock
   C5 — ExtendGuest double-extension
   C3 — API CheckInController no transaction

   MEDIUM (specific edge cases — still open):
   ───────────────────────────────────────────
   A3 — SalesReport display double-multiplication
   A9 — ExtendGuest rate not branch-scoped (low impact in single-branch)
   C2 — Cron + saveCheckIn race
   C6 — Roomboy + frontdesk race
   C9 — Null-pointer hazards (chained model accessors)
   C10 — Honorable mentions
```

---

## Source

This audit was produced by spawning 3 parallel agents on 2026-04-30:

| Agent | Domain | Findings |
|-------|--------|----------|
| Financial integrity audit | Money calculations, rate lookups, deposits, overrides | 10 |
| State synchronization audit | Room status changes, kiosk batch invariants, cache hooks | 10 |
| Concurrency & data integrity audit | Locks, transactions, multi-tenant, null hazards | 10 |

Total: **30 distinct potential bugs** identified.

This audit is intentionally read-only — no code was changed. Items above are the backlog. Pick what to address based on business priority.
