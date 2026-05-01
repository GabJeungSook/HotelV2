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

**19 of 30 audit findings fixed** across three commits on branch
`feature/temp-disable-supervisor`:

Commit `444841c` (5 finance bugs):
- ✅ **A1** — Admin Check-In C/O long-stay multiplier
- ✅ **A2** — RoomMonitoring storeGuest long-stay multiplier
- ✅ **A6** — `payAllUnpaid` sets `paid_amount` per row
- ✅ **A7** — `addOverride` sets `paid_amount` + `is_override`
- ✅ **A11** *(found during planning)* — Admin Reservation long-stay multiplier

Commit `84e2cf2` (8 batch-sync + audit fixes):
- ✅ **A8** — `claimAllDeposit` idempotency guard
- ✅ **A10** — Null-safe `?->amount ?? 0` on 6 unguarded rate lookups
- ✅ **B2** — RoomMonitoring `saveReserveCheckInDetails` notifies kiosk batch
- ✅ **B3** — Both `checkoutGuest` paths heal kiosk batch on checkout
- ✅ **B4** — `TerminationInKiosk` job calls `returnToBatch`
- ✅ **B6** *(defensive)* — Roomboy `startCleaning` calls `refreshIfStale`
- ✅ **B7** — `PriorityRoom::removePriority` notifies kiosk batch
- ✅ **B8** — RoomMonitoring `saveCheckIn` (kiosk-walk-in path) notifies batch

Commit *(this commit)* (concurrency + null-safety + ghost-room hooks + 1 finance):
- ✅ **A4** — ManageGuestTransaction `updatedTypeId` long-stay rate lookup (mirrors TransferRoom fix)
- ✅ **B5** — Ghost-Room fixers (Admin, Frontdesk, console command) all notify kiosk batch
- ✅ **C1** — CheckInFromKiosk `saveCheckIn` lockForUpdate on TemporaryCheckInKiosk + duplicate guard wrapped in transaction
- ✅ **C4** — TransferRoom destination Room `lockForUpdate` + status guard inside lock window
- ✅ **C5** — ExtendGuest `saveExtend` lockForUpdate + 3-second StayExtension recency idempotency check (prevents double-click double-extension)
- ✅ **C9** — Null-safe accessors on chained model accessors in TransferRoom and ExtendGuest mounts

Remaining backlog: **11 findings** (the rest of this document).

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

### A3. SalesReport.php double-multiplies `hours_stayed` for long-stays ✅ FIXED
- **Status:** ✅ FIXED on branch `feature/temp-disable-supervisor` (2026-05-01)
- **File:** `app/Http/Livewire/BackOffice/SalesReport.php`
- **Fix:** `initial_hrs` now displays `$detail->hours_stayed . ' hrs'` directly (already 24×days for long-stays). Removed the redundant `* number_of_days` that was doubling the displayed value, matching the same fix applied earlier in commit `1f2025d` to other SalesReport sites.

### A4. ManageGuestTransaction `updatedTypeId` — same TransferRoom NULL-rate pattern ✅ FIXED
- **Status:** ✅ FIXED on branch `feature/concurrency-and-null-safety-may-1` (2026-05-01)
- **File:** `app/Http/Livewire/Frontdesk/Monitoring/ManageGuestTransaction.php:786-820`
- **Fix:** Branched on `is_long_stay` and uses 24h staying-hour lookup × number_of_days, mirroring the same fix applied earlier in TransferRoom.php and TransferService.php. Also uses `?->amount ?? 0` to handle missing rate config gracefully.

### A5. GuestTransaction `updatedTypeId` — same NULL-rate + comparison flaw ✅ FIXED
- **Status:** ✅ FIXED on branch `feature/temp-disable-supervisor` (2026-05-01)
- **File:** `app/Http/Livewire/Frontdesk/Monitoring/GuestTransaction.php`
- **Fix:** Branched on `is_long_stay` and uses 24h staying-hour lookup × number_of_days for long-stay guests; `?->amount ?? 0` for short-stay; comparison switched from `static_room_amount` to `static_amount` for consistency. Mirrors A4's fix in ManageGuestTransaction.

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

### A8. `claimAllDeposit` overstates Damage Charges, no idempotency ✅ FIXED
- **Status:** ✅ FIXED in earlier commit on branch `feature/concurrency-and-null-safety-may-1` (2026-05-01)
- **File:** `app/Http/Livewire/Frontdesk/Monitoring/ManageGuestTransaction.php`
- **Reality check on the audit text:**
  - "Damage charge becomes unpaid bill" — was inaccurate. Damage Charges row is created with `paid_amount = deposit_remote_and_key`, so it's paid, not a debt.
  - "No idempotency — second click double-charges" — was real. Now fixed: function checks for an existing Damage Charges row by remarks before creating a new one and shows an "Already Claimed" dialog if found.

### A9. ExtendGuest "Priority 1" rate lookup not branch-scoped ✅ FIXED
- **Status:** ✅ FIXED on branch `feature/temp-disable-supervisor` (2026-05-01)
- **File:** `app/Http/Livewire/Frontdesk/Monitoring/ExtendGuest.php`
- **Fix:** Both `whereHas('stayingHour', ...)` subqueries (Priority 1 reset path + Priority 2 boundary path) now also constrain the staying-hour by `branch_id = auth()->user()->branch_id`. Defense-in-depth against any cross-branch staying-hour reference.

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

### B1. Admin Reservation creation marks room `Reserved` but never notifies kiosk batch ✅ FIXED
- **Status:** ✅ FIXED on branch `feature/temp-disable-supervisor` (2026-05-01)
- **File:** `app/Http/Livewire/Admin/Manage/Reservation.php`
- **Fix:** Both branches of `saveReservation()` (long-stay and short-stay) now call `KioskBatchService::refreshIfStale($branch_id, $type_id)` after `Db::commit()`. If the kiosk had an active slot pointing at the now-Reserved room, it gets repaired in-place so the kiosk no longer shows a reserved room as pickable.

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

### B5. Ghost Rooms / `rooms:fix-ghost` flip Occupied → Available without notifying batch ✅ FIXED
- **Status:** ✅ FIXED on branch `feature/concurrency-and-null-safety-may-1` (2026-05-01)
- **Files (all 3 sites):**
  - `app/Http/Livewire/Admin/GhostRooms.php` — `fixRoom` and `fixAllRooms`
  - `app/Http/Livewire/Frontdesk/Monitoring/RoomMonitoring.php` — `fixGhostRoom`
  - `app/Console/Commands/FixGhostRooms.php` — `handle`
- **Fix:** All 3 sites now call `KioskBatchService::maybeFillBlankFloor($room)` after the status update (guarded by `is_priority` check), matching the existing hook pattern in roomboy `finishCleaning` and admin `Update Room`.

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

### C1. Frontdesk confirm-from-kiosk: open-checkin guard inside transaction but WITHOUT `lockForUpdate` ✅ FIXED
- **Status:** ✅ FIXED on branch `feature/concurrency-and-null-safety-may-1` (2026-05-01)
- **File:** `app/Http/Livewire/Frontdesk/Monitoring/CheckInFromKiosk.php:178-260`
- **Fix:** `saveCheckIn` now opens DB transaction first, locks TemporaryCheckInKiosk + duplicate-CheckinDetail check + ghost-checkin guard with `lockForUpdate`. Two simultaneous Confirm clicks now serialize, with the second seeing the hold gone and rolling back cleanly.

### C2. Cron `kiosk:cleanup` races with `confirmCheckIn` and `saveCheckIn` ✅ FIXED
- **Status:** ✅ FIXED on branch `feature/temp-disable-supervisor` (2026-05-01)
- **File:** `app/Console/Commands/CleanupTemporaryKiosk.php`
- **Fix:** Each expired hold is now processed inside `DB::transaction` with `lockForUpdate` on the TCK row AND on the `Guest` row before deletion. Existence of `CheckinDetail` / `Transaction` is re-checked INSIDE the lock window so a frontdesk save in flight cannot have its Guest deleted out from under it. The frontdesk's existing `lockForUpdate` on TCK (C1 fix) serializes against this — whichever side wins, the other sees consistent state.

### C3. API `CheckInController::store` has NO transaction, NO locks, NO occupancy guard ✅ FIXED
- **Status:** ✅ FIXED on branch `feature/temp-disable-supervisor` (2026-05-01)
- **File:** `app/Http/Controllers/API/CheckInController.php`
- **Fix:** Rewrote the controller to mirror `Kiosk\CheckIn::confirmCheckIn`'s safety envelope: explicit `validate()`, `DB::beginTransaction`, `lockForUpdate` on Room (Occupied check) + TCK + TemporaryReserved + open CheckinDetail, `lockForUpdate` on the qr_code sequence count to prevent duplicates, `branch->kiosk_time_limit` instead of hardcoded 20 minutes, atomic Guest+TCK creation, `KioskBatchService::markPicked` after commit so the kiosk batch advances. Two simultaneous API calls now fail-fast on the second one.

### C4. TransferRoom — no row lock on destination room ✅ FIXED
- **Status:** ✅ FIXED on branch `feature/concurrency-and-null-safety-may-1` (2026-05-01)
- **File:** `app/Http/Livewire/Frontdesk/Monitoring/TransferRoom.php:422-668`
- **Fix:** `confirmTransfer` now `lockForUpdate`s the destination Room *and* re-checks `status === 'Available'` inside the lock window. Concurrent transfers to the same destination now serialize, with the second receiving a clear "Destination Unavailable" error.

### C5. `ExtendGuest::saveExtend` no row-lock, no idempotency guard ✅ FIXED
- **Status:** ✅ FIXED on branch `feature/concurrency-and-null-safety-may-1` (2026-05-01)
- **File:** `app/Http/Livewire/Frontdesk/Monitoring/ExtendGuest.php:156-260`
- **Fix:** `saveExtend` now `lockForUpdate`s the CheckinDetail row inside the transaction AND checks for any StayExtension created within the last 3 seconds for the guest. Double-clicks, network lag, two-tab races all blocked.

### C6. Roomboy `finishCleaning` unprotected status read-then-write ✅ FIXED
- **Status:** ✅ FIXED on branch `feature/temp-disable-supervisor` (2026-05-01)
- **Files:** `app/Http/Livewire/Roomboy/Index.php`, `app/Http/Livewire/Roomboy/Main.php`
- **Fix:** `finishCleaning` now opens the transaction first, reads the room with `lockForUpdate` AND a branch filter, then aborts cleanly (with a clear "Front desk has already checked a guest into this room" dialog) if the room flipped to `Occupied` mid-cleaning. The `Available` write can no longer overwrite a live `Occupied`, eliminating the ghost-room (Available + active CheckinDetail) state.

### C7. API `OccupiedRoomController::occupiedRooms` and `QrRoomController::getRoomByQr` — no branch authz ✅ FIXED
- **Status:** ✅ FIXED on branch `feature/temp-disable-supervisor` (2026-05-01)
- **Files:** `app/Http/Controllers/API/OccupiedRoomController.php`, `app/Http/Controllers/API/QrRoomController.php`
- **Fix:** Both controllers now reject the request with a 403 "Forbidden — branch mismatch" when the URL/body `branch_id` does not match `auth()->user()->branch_id`. Authenticated users can no longer read another branch's data even when they know the foreign branch_id.

### C8. BackOffice reports leak across tenants ✅ FIXED
- **Status:** ✅ FIXED on branch `feature/temp-disable-supervisor` (2026-05-01)
- **Files:** `app/Http/Livewire/BackOffice/Reports/OccupiedRoom.php`, `app/Http/Livewire/BackOffice/Reports/Guest.php`
- **Fix:** `Guest::loadQuery` case 3 now filters by `branch_id = auth()->user()->branch_id` on the outer Guest query. `OccupiedRoom::render` now also adds an explicit `branch_id` filter on the outer Transaction query (defense-in-depth alongside the existing `whereHas('guest.room', ...)` filter).

### C9. Null-pointer hazards on chained model accessors ✅ FIXED
- **Status:** ✅ FIXED on branch `feature/temp-disable-supervisor` (2026-05-01)
- **Original partial fix** (`feature/concurrency-and-null-safety-may-1`):
  - TransferRoom `mount()` — `$this->guest?->checkInDetail?->rate?->amount ?? 0`
  - ExtendGuest `mount()` — `$this->guest?->checkInDetail?->room_id` etc.
  - CheckInFromKiosk `saveCheckIn` — `Rate::...->first()?->stayingHour?->number ?? 0`
- **Final pass:** all 10 `auth()->user()->frontdesk->id` references across CheckInFromKiosk (3), GuestTransaction (6), and TransferRoom (1) now use `?->id`. `frontdesk_id` is a nullable column on `checkin_details` and `cash_on_drawers`, so a null relation no longer fatals — it stores NULL and the calling user can be diagnosed from the activity log.

### C10. Honorable mentions (lower priority but worth noting)
- `TransferRoom.php:140-149` — destination Room listing has no row lock; saveTransfer's `lockForUpdate` (C4 fix) covers the actual race window. **Not fixed: low priority — listing is purely advisory.**
- `Roomboy/Index::startCleaning:44` ✅ FIXED — `Room::where` now scoped to `auth()->user()->branch_id` with a clear "Room Not Found" error if the foreign branch is targeted.
- `OverrideRequests::cancelRequest:105` ✅ FIXED — lookup now scoped to `branch_id` AND `requester_id`; cannot even load a foreign branch's request id.
- `Kiosk\CheckIn.php:365` — `Guest::whereYear('created_at', now()->year)->lockForUpdate()->count()` locks all year's rows; under load serializes every kiosk check-in. **Not fixed: needs separate redesign (sequence table or year-scoped counter).**

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
   ✅ FIXED 2026-05-01 across three commits:
   ──────────────────────────────────────────
   Commit 444841c (finance):     A1, A2, A6, A7, A11
   Commit 84e2cf2 (batch sync):  A8, A10, B2, B3, B4, B6, B7, B8
   Commit (this commit):         A4, B5, C1, C4, C5, C9 (partial)

   ⏸ DEFERRED (no trigger path exists yet):
   ─────────────────────────────────────────
   B9 — TemporaryReserved no cancellation hook

   IMMEDIATE (revenue impact / data corruption — still open):
   ─────────────────────────────────────────────────────────
   C7 — API tenant leak (OccupiedRoomController, QrRoomController)
   C8 — BackOffice reports tenant leak (Guest::loadQuery case 3)

   HIGH (silent state drift — still open):
   ────────────────────────────────────────
   A5 — GuestTransaction updatedTypeId long-stay rate lookup (sibling of A4)
   B1 — Admin Reservation create missing batch hook (sibling of B2)

   HIGH (race conditions under concurrent use — still open):
   ──────────────────────────────────────────────────────────
   C3 — API CheckInController no transaction (commented-out code)

   MEDIUM (specific edge cases — still open):
   ───────────────────────────────────────────
   A3 — SalesReport display double-multiplication
   A9 — ExtendGuest rate not branch-scoped (low impact in single-branch)
   C2 — Cron + saveCheckIn race
   C6 — Roomboy + frontdesk race
   C9 (remaining) — `auth()->user()->frontdesk->id` chained accessor
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
