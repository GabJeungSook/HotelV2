<?php

namespace App\Services;

use App\Models\Guest;
use App\Models\KioskCurrentBatch;
use App\Models\Room;
use App\Models\TemporaryCheckInKiosk;
use App\Models\TemporaryReserved;

/**
 * Manages the kiosk "batch rotation" system. The kiosk shows ONE room at a
 * time for a specific room TYPE. When that room is picked, the next best
 * available room (across all floors) is promoted.
 *
 * Scope: per (branch, type). Each room type rotates independently, so a guest
 * who picks "Double" sees a Double slot, and "Single" guests see a Single
 * slot — they don't interfere with each other.
 *
 * Selection priority:
 *  1. Never-used rooms (last_checkin_at IS NULL) — natsort tiebreak
 *  2. Earliest last_cleaned_at (FIFO from roomboy) — natsort tiebreak
 */
class KioskBatchService
{
    /**
     * End the current batch for (branch, type) and promote the next batch.
     * Called automatically when:
     *  - Kiosk first renders for this type (batch empty).
     *  - A user's check-in drains the last active slot.
     */
    public static function throwNextBatch(int $branchId, int $typeId): void
    {
        KioskCurrentBatch::where('branch_id', $branchId)
            ->where('type_id', $typeId)
            ->delete();

        // Same exclusion list the kiosk's render() applies — without it the
        // throw can re-pick rooms that already have an active kiosk hold,
        // which leaves the kiosk stuck (slot says 'active' but render filters
        // it out, showing SORRY).
        $blockedRoomIds = self::roomIdsBlockedFromBatch($branchId);

        $candidates = Room::where('branch_id', $branchId)
            ->where('type_id', $typeId)
            ->whereIn('status', ['Available', 'Cleaned'])
            ->where('is_priority', 1)
            ->whereNotIn('id', $blockedRoomIds ?: [0])
            ->get();

        if ($candidates->isEmpty()) {
            return;
        }

        $room = self::pickPreferredRoom($candidates);

        KioskCurrentBatch::create([
            'branch_id' => $branchId,
            'type_id' => $typeId,
            'room_id' => $room->id,
            'floor_id' => $room->floor_id,
            'slot_status' => KioskCurrentBatch::STATUS_ACTIVE,
        ]);
    }

    /**
     * Rooms that should be excluded from any new batch pick because the
     * kiosk's own render() filters them out — picking them would put the
     * kiosk in a "slot active but invisible" stuck state.
     *
     * Mirrors `Kiosk\CheckIn::render()`'s filters:
     *  - Has a TemporaryCheckInKiosk hold (kiosk reservation in flight)
     *  - Has a TemporaryReserved hold (frontdesk reservation)
     *  - Has an orphan Guest in the last 2 hours (guest record without
     *    checkin_detail — same scope as the master kiosk hotfix)
     */
    private static function roomIdsBlockedFromBatch(int $branchId): array
    {
        $temp = TemporaryCheckInKiosk::where('branch_id', $branchId)
            ->pluck('room_id')
            ->toArray();

        $reserved = TemporaryReserved::where('branch_id', $branchId)
            ->pluck('room_id')
            ->toArray();

        $pending = Guest::where('branch_id', $branchId)
            ->whereDoesntHave('checkInDetail')
            ->where('created_at', '>=', now()->subHours(2))
            ->pluck('room_id')
            ->toArray();

        return array_values(array_unique(array_merge($temp, $reserved, $pending)));
    }

    /**
     * Select the next room to release from a candidate set.
     *
     * Spec balances two goals:
     *   1. "Use unused rooms" — distribute usage so the same low-numbered
     *      room is not picked over and over while other rooms sit idle.
     *   2. Within used rooms, "1st cleaned, 1st released" (FIFO from the
     *      roomboy's cleaning entry) so floor staff see the order they
     *      actually finished cleaning rooms in.
     *
     * Tiered priority:
     *   1. Never-used rooms (last_checkin_at IS NULL) — natsort tiebreak
     *   2. Then earliest last_cleaned_at first — natsort tiebreak among
     *      same-timestamp rooms
     *
     * @param  \Illuminate\Support\Collection<int, Room>  $candidates
     */
    private static function pickPreferredRoom($candidates): ?Room
    {
        if ($candidates->isEmpty()) {
            return null;
        }

        $unused = $candidates->whereNull('last_checkin_at');

        if ($unused->isNotEmpty()) {
            $pool = $unused;
        } else {
            // All have been used at some point — pick the one cleaned earliest
            // (true FIFO from the roomboy entry). Natsort is the deterministic
            // tiebreak when timestamps match exactly.
            $oldestCleaningTimestamp = $candidates->min('last_cleaned_at');
            $pool = $candidates->filter(fn ($r) => $r->last_cleaned_at == $oldestCleaningTimestamp);
        }

        if ($pool->isEmpty()) {
            return $candidates->first(); // Fallback to any room
        }

        $numbers = $pool->pluck('number')->toArray();
        natsort($numbers);
        $lowest = reset($numbers);

        return $pool->firstWhere('number', $lowest) ?? $pool->first();
    }

    /**
     * Called when a roomboy finishes cleaning. If there is no active slot
     * in the current batch for this room's type, pick the highest-priority
     * available room (unused first, then FIFO by cleaning time) and add it
     * as the active slot. The passed $room is the trigger event, but the
     * selected room may differ if a higher-priority candidate exists.
     */
    public static function maybeFillEmptySlot(Room $room): void
    {
        $exists = KioskCurrentBatch::where('branch_id', $room->branch_id)
            ->where('type_id', $room->type_id)
            ->where('slot_status', KioskCurrentBatch::STATUS_ACTIVE)
            ->exists();

        if ($exists) {
            return;
        }

        $excludedRoomIds = array_values(array_unique(array_merge(
            KioskCurrentBatch::where('branch_id', $room->branch_id)
                ->where('type_id', $room->type_id)
                ->pluck('room_id')
                ->all(),
            self::roomIdsBlockedFromBatch($room->branch_id),
        )));

        $candidates = Room::where('branch_id', $room->branch_id)
            ->where('type_id', $room->type_id)
            ->whereIn('status', ['Available', 'Cleaned'])
            ->where('is_priority', 1)
            ->whereNotIn('id', $excludedRoomIds ?: [0])
            ->get();

        if ($candidates->isEmpty()) {
            return;
        }

        $best = self::pickPreferredRoom($candidates);

        KioskCurrentBatch::create([
            'branch_id' => $best->branch_id,
            'type_id' => $best->type_id,
            'room_id' => $best->id,
            'floor_id' => $best->floor_id,
            'slot_status' => KioskCurrentBatch::STATUS_ACTIVE,
        ]);
    }

    /**
     * Mark a room as 'picked' after the guest confirms on the kiosk.
     *
     * NOTE: this does NOT auto-throw the next batch. We use a "wait for
     * frontdesk confirmation" model — the batch only refreshes when every
     * pick has been processed by the frontdesk (saveCheckIn) AND no
     * temporary reservations remain. That throw is triggered from
     * `maybeThrowNextBatch()`, called by the frontdesk's saveCheckIn.
     *
     * Why: a cancelled / timed-out kiosk pick must be able to come back to
     * the SAME batch (return slot from picked → active). If we auto-threw
     * on the last pick, the cancelled room's slot would already be deleted
     * by the time the cancel arrived, dropping it into the waiting stack
     * instead of restoring it as visible.
     */
    public static function markPicked(int $branchId, int $roomId): void
    {
        KioskCurrentBatch::where('branch_id', $branchId)
            ->where('room_id', $roomId)
            ->where('slot_status', KioskCurrentBatch::STATUS_ACTIVE)
            ->update(['slot_status' => KioskCurrentBatch::STATUS_PICKED]);
    }

    /**
     * Throw the next batch for (branch, type) IF every batch slot is now
     * fully resolved by the frontdesk:
     *
     *  - All slots are 'picked' (no active rooms remain on display)
     *  - None of those rooms still have a temporary_check_in_kiosks hold
     *    (every pick has been confirmed by saveCheckIn — Room.status now
     *    Occupied — or the hold was cleaned up)
     *
     * Returns true if a throw was performed.
     */
    public static function maybeThrowNextBatch(int $branchId, int $typeId): bool
    {
        $slots = KioskCurrentBatch::where('branch_id', $branchId)
            ->where('type_id', $typeId)
            ->get();

        if ($slots->isEmpty()) {
            return false;
        }

        $hasActive = $slots->contains(fn ($s) => $s->slot_status === KioskCurrentBatch::STATUS_ACTIVE);
        if ($hasActive) {
            return false;
        }

        // All slots are 'picked'. If any of these rooms still has an active
        // kiosk hold, frontdesk hasn't finished processing yet — wait.
        $hasOutstandingHold = TemporaryCheckInKiosk::where('branch_id', $branchId)
            ->whereIn('room_id', $slots->pluck('room_id'))
            ->exists();

        if ($hasOutstandingHold) {
            return false;
        }

        self::throwNextBatch($branchId, $typeId);
        return true;
    }

    /**
     * Refresh the single slot for (branch, type) immediately after the
     * frontdesk confirms a kiosk pick.
     *
     * Behavior:
     *  - Delete any 'picked' slot row (the room is now Occupied).
     *  - Pick the next-priority available room across all floors
     *    (`pickPreferredRoom` honors "unused first, then FIFO by cleaning").
     *  - Insert it as a new 'active' slot so it shows in the kiosk right away.
     *  - If no eligible room exists, leave the slot empty —
     *    `maybeFillEmptySlot` will promote the next room when a roomboy
     *    finishes cleaning.
     */
    public static function refreshSlot(int $branchId, int $typeId): void
    {
        KioskCurrentBatch::where('branch_id', $branchId)
            ->where('type_id', $typeId)
            ->where('slot_status', KioskCurrentBatch::STATUS_PICKED)
            ->delete();

        // Skip refill if the slot was already replaced by another path
        // (e.g. roomboy `maybeFillEmptySlot` ran first).
        $alreadyHasSlot = KioskCurrentBatch::where('branch_id', $branchId)
            ->where('type_id', $typeId)
            ->where('slot_status', KioskCurrentBatch::STATUS_ACTIVE)
            ->exists();

        if ($alreadyHasSlot) {
            return;
        }

        $excludedRoomIds = array_values(array_unique(array_merge(
            KioskCurrentBatch::where('branch_id', $branchId)
                ->where('type_id', $typeId)
                ->pluck('room_id')
                ->all(),
            self::roomIdsBlockedFromBatch($branchId),
        )));

        $candidates = Room::where('branch_id', $branchId)
            ->where('type_id', $typeId)
            ->whereIn('status', ['Available', 'Cleaned'])
            ->where('is_priority', 1)
            ->whereNotIn('id', $excludedRoomIds ?: [0])
            ->get();

        if ($candidates->isEmpty()) {
            return;
        }

        $room = self::pickPreferredRoom($candidates);

        KioskCurrentBatch::create([
            'branch_id' => $branchId,
            'type_id' => $typeId,
            'room_id' => $room->id,
            'floor_id' => $room->floor_id,
            'slot_status' => KioskCurrentBatch::STATUS_ACTIVE,
        ]);
    }

    /**
     * Flip a picked slot back to active. Called when a kiosk check-in is
     * cancelled or times out before the frontdesk confirms — the slot
     * should reappear on the kiosk. No-op if the slot is not currently
     * picked for this room (e.g. cleanup runs twice, or the batch has
     * already rotated).
     */
    public static function returnToBatch(int $branchId, int $roomId): void
    {
        KioskCurrentBatch::where('branch_id', $branchId)
            ->where('room_id', $roomId)
            ->where('slot_status', KioskCurrentBatch::STATUS_PICKED)
            ->update(['slot_status' => KioskCurrentBatch::STATUS_ACTIVE]);
    }

    /**
     * Returns the list of room ids currently 'active' for (branch, type).
     * Used by the kiosk render to filter rooms.
     */
    public static function activeRoomIds(int $branchId, int $typeId): array
    {
        return KioskCurrentBatch::where('branch_id', $branchId)
            ->where('type_id', $typeId)
            ->where('slot_status', KioskCurrentBatch::STATUS_ACTIVE)
            ->pluck('room_id')
            ->toArray();
    }

    /**
     * Whether the batch table is fully empty for (branch, type).
     * Used on first render to trigger an initial throw.
     */
    public static function isEmpty(int $branchId, int $typeId): bool
    {
        return !KioskCurrentBatch::where('branch_id', $branchId)
            ->where('type_id', $typeId)
            ->exists();
    }

    /**
     * Read-only preview of upcoming batches. Used by the frontdesk
     * "View Kiosk Batch" modal so staff can see what is coming next
     * without walking to the kiosk.
     *
     * Returns an array of $batchCount entries, each with one room:
     *   [
     *     ['room_number'=>'7', 'floor_number'=>1, 'floor_id'=>X],
     *     ['room_number'=>'69', 'floor_number'=>2, 'floor_id'=>Y],
     *     ...
     *   ]
     *
     * Entries beyond available candidates get room_number=null.
     */
    public static function previewBatches(int $branchId, int $typeId, int $batchCount = 1): array
    {
        // Same combined exclusion that throwNextBatch uses, so the preview
        // matches what the system will actually pick (not what could be
        // picked in the absence of holds).
        $excludedRoomIds = array_values(array_unique(array_merge(
            KioskCurrentBatch::where('branch_id', $branchId)
                ->where('type_id', $typeId)
                ->pluck('room_id')
                ->toArray(),
            self::roomIdsBlockedFromBatch($branchId),
        )));

        $rooms = Room::where('branch_id', $branchId)
            ->where('type_id', $typeId)
            ->whereIn('status', ['Available', 'Cleaned'])
            ->where('is_priority', 1)
            ->whereNotIn('id', $excludedRoomIds ?: [0])
            ->with('floor:id,number')
            ->get(['id', 'number', 'floor_id', 'last_checkin_at', 'last_cleaned_at']);

        // Build a queue using the same tiered priority as throwNextBatch.
        $queue = [];
        $remaining = $rooms;
        $maxIterations = $rooms->count() + 1;
        $iterations = 0;
        while ($remaining->isNotEmpty() && $iterations < $maxIterations && count($queue) < $batchCount) {
            $iterations++;
            $next = self::pickPreferredRoom($remaining);
            if (! $next) {
                break;
            }
            $queue[] = [
                'room_number' => $next->number,
                'floor_number' => $next->floor->number ?? null,
                'floor_id' => $next->floor_id,
            ];
            $remaining = $remaining->reject(fn ($r) => $r->id === $next->id);
        }

        // Pad to requested count with nulls.
        while (count($queue) < $batchCount) {
            $queue[] = [
                'room_number' => null,
                'floor_number' => null,
                'floor_id' => null,
            ];
        }

        return $queue;
    }

    /**
     * Preview the next single room that would be thrown.
     */
    public static function previewNextBatch(int $branchId, int $typeId): array
    {
        $batches = self::previewBatches($branchId, $typeId, 1);
        return $batches[0] ?? [];
    }

    /**
     * Self-heal stale batches at the per-slot level.
     *
     * The batch is meant to advance via markPicked() on kiosk check-in. But if
     * a batch-slot room becomes Occupied/Uncleaned/Maintenance through any
     * non-kiosk path (frontdesk direct check-in, manual status edit, etc.),
     * the slot stays 'active' but points to an unusable room. Without help the
     * kiosk shows blank for that floor (or SORRY if every slot is stale) even
     * though other rooms are available.
     *
     * Strategy:
     *  1. If the active slot is stale → throwNextBatch() (full refresh).
     *  2. Otherwise replace the bad slot's room_id with the next-available
     *     room across all floors (excluding rooms already in this batch).
     *     If no replacement exists, delete the slot so `maybeFillEmptySlot`
     *     can fill it later when a roomboy cleans.
     *
     * Returns true if any change was made.
     */
    /**
     * Delete picked slots whose underlying TCK is gone. The TCK is the
     * canonical "kiosk pick in flight" marker — once it is gone no flow can
     * ever resolve the slot, so leaving it as 'picked' just blocks the slot.
     *
     * Earlier this also spared slots whose room was Occupied (in case the
     * frontdesk confirm was mid-flight). That carve-out turned out to mask
     * permanent leftovers: when CheckInFromKiosk::saveCheckIn committed but
     * its post-commit refreshSlot did not run (PHP timeout / dropped
     * connection), the row stayed 'picked' on an Occupied room forever (4F
     * incident, 2026-04-30). With Layer A moving refreshSlot inside
     * the transaction, the only race left is identical to the cancel race
     * (TCK deleted, slot still picked), which this cleanup is meant to fix.
     */
    private static function cleanupStalePickedSlots(int $branchId, int $typeId): bool
    {
        $pickedSlots = KioskCurrentBatch::where('branch_id', $branchId)
            ->where('type_id', $typeId)
            ->where('slot_status', KioskCurrentBatch::STATUS_PICKED)
            ->get();

        if ($pickedSlots->isEmpty()) {
            return false;
        }

        $heldRoomIds = TemporaryCheckInKiosk::where('branch_id', $branchId)
            ->whereIn('room_id', $pickedSlots->pluck('room_id'))
            ->pluck('room_id')
            ->all();

        $stale = $pickedSlots->reject(fn ($slot) => in_array($slot->room_id, $heldRoomIds, true));

        if ($stale->isEmpty()) {
            return false;
        }

        foreach ($stale as $slot) {
            $slot->delete();
        }

        return true;
    }

    public static function refreshIfStale(int $branchId, int $typeId): bool
    {
        // First pass: clean up stale PICKED slots. These can occur when the
        // original picker abandoned without going through cancelCheckIn (e.g.
        // a Transfer Room moved them, or the cleanup job didn't run, or the
        // saveCheckIn flow committed but maybeThrowNextBatch returned false
        // and never fired again). The picked slot is "stale" if its room is
        // no longer Occupied AND there is no temp_check_in_kiosks hold for it.
        // Once cleaned, the slot is free for a fresh active room via the
        // existing throw / maybeFillEmptySlot paths.
        $pickedChanged = self::cleanupStalePickedSlots($branchId, $typeId);

        $activeSlots = KioskCurrentBatch::where('branch_id', $branchId)
            ->where('type_id', $typeId)
            ->where('slot_status', KioskCurrentBatch::STATUS_ACTIVE)
            ->get();

        // If after picked-slot cleanup the batch is now fully empty, throw a
        // fresh batch so an available room gets visible immediately.
        if ($activeSlots->isEmpty()) {
            $hasAnySlot = KioskCurrentBatch::where('branch_id', $branchId)
                ->where('type_id', $typeId)
                ->exists();

            if (! $hasAnySlot && $pickedChanged) {
                self::throwNextBatch($branchId, $typeId);
                return true;
            }

            return $pickedChanged;
        }

        // Identify which active slots are stale (room no longer usable).
        $usableRoomIds = Room::where('branch_id', $branchId)
            ->whereIn('id', $activeSlots->pluck('room_id'))
            ->whereIn('status', ['Available', 'Cleaned'])
            ->where('is_priority', 1)
            ->pluck('id')
            ->all();

        $staleSlots = $activeSlots->reject(fn ($s) => in_array($s->room_id, $usableRoomIds, true));

        if ($staleSlots->isEmpty()) {
            // No stale active slots, but the picked-slot cleanup may have
            // made changes — propagate that so callers see the right answer.
            return $pickedChanged;
        }

        // The single active slot is stale — replace it with the next best
        // available room across all floors.
        self::throwNextBatch($branchId, $typeId);
        return true;
    }
}
