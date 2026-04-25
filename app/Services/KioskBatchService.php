<?php

namespace App\Services;

use App\Models\Floor;
use App\Models\KioskCurrentBatch;
use App\Models\Room;

/**
 * Manages the kiosk "batch rotation" system. The kiosk shows a fixed set of
 * rooms (one per floor) for a specific room TYPE. When a room is picked, its
 * floor goes blank until ALL floors in the current batch have been picked, at
 * which point the next batch is "thrown" from the waiting stack.
 *
 * Scope: per (branch, type). Each room type rotates independently, so a guest
 * who picks "Double" sees a Double batch, and "Single" guests see a Single
 * batch — they don't interfere with each other.
 *
 * Rules:
 *  - Floor that NEVER had a room in the current batch → blank can be filled
 *    mid-batch (when a Floor N room of this type gets cleaned, it joins).
 *  - Floor that HAD a room and was picked → stays blank until next batch.
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

        $floors = Floor::where('branch_id', $branchId)->get();

        foreach ($floors as $floor) {
            $candidates = Room::where('branch_id', $branchId)
                ->where('type_id', $typeId)
                ->where('floor_id', $floor->id)
                ->whereIn('status', ['Available', 'Cleaned'])
                ->where('is_priority', 1)
                ->get();

            if ($candidates->isEmpty()) {
                continue;
            }

            // Client spec requires "ascending numerical" order. rooms.number
            // is varchar (must hold alphanumeric like "5A", "5C"), so a SQL
            // orderBy gives string sort: ["3","21"] becomes ["21","3"]. Use
            // PHP natsort so "3" < "5A" < "21" naturally.
            $numbers = $candidates->pluck('number')->toArray();
            natsort($numbers);
            $lowest = reset($numbers);
            $room = $candidates->firstWhere('number', $lowest);

            KioskCurrentBatch::create([
                'branch_id' => $branchId,
                'type_id' => $typeId,
                'room_id' => $room->id,
                'floor_id' => $floor->id,
                'slot_status' => KioskCurrentBatch::STATUS_ACTIVE,
            ]);
        }
    }

    /**
     * Called when a roomboy finishes cleaning. If the room's floor has no row
     * in the current batch for that room's type, add the room as an active
     * slot immediately (fills a floor that started blank). Otherwise the room
     * waits for the next batch.
     */
    public static function maybeFillBlankFloor(Room $room): void
    {
        $exists = KioskCurrentBatch::where('branch_id', $room->branch_id)
            ->where('type_id', $room->type_id)
            ->where('floor_id', $room->floor_id)
            ->exists();

        if ($exists) {
            return;
        }

        KioskCurrentBatch::create([
            'branch_id' => $room->branch_id,
            'type_id' => $room->type_id,
            'room_id' => $room->id,
            'floor_id' => $room->floor_id,
            'slot_status' => KioskCurrentBatch::STATUS_ACTIVE,
        ]);
    }

    /**
     * Mark a room as 'picked' after the user confirms check-in.
     * If this drains the last active slot for the (branch, type), auto-throws
     * the next batch for that type.
     */
    public static function markPicked(int $branchId, int $roomId): void
    {
        $row = KioskCurrentBatch::where('branch_id', $branchId)
            ->where('room_id', $roomId)
            ->where('slot_status', KioskCurrentBatch::STATUS_ACTIVE)
            ->first();

        if (!$row) {
            return;
        }

        $typeId = $row->type_id;

        $row->update(['slot_status' => KioskCurrentBatch::STATUS_PICKED]);

        $hasActive = KioskCurrentBatch::where('branch_id', $branchId)
            ->where('type_id', $typeId)
            ->where('slot_status', KioskCurrentBatch::STATUS_ACTIVE)
            ->exists();

        if (!$hasActive) {
            self::throwNextBatch($branchId, $typeId);
        }
    }

    /**
     * Flip a picked slot back to active. Called when a kiosk check-in is
     * cancelled or times out before the frontdesk confirms — the floor's
     * slot should reappear on the kiosk instead of staying blank until the
     * next batch. No-op if the slot is not currently picked for this room
     * (e.g. cleanup runs twice, or the batch has already rotated).
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
     * Self-heal stale batches.
     *
     * The batch is meant to advance via markPicked() on kiosk check-in. But if
     * a batch-slot room becomes Occupied/Uncleaned/Maintenance through any
     * non-kiosk path (frontdesk direct check-in, manual status edit, etc.),
     * the slot stays 'active' forever and the kiosk shows SORRY even though
     * other rooms of that type are actually available.
     *
     * This guard runs at the top of render()/selectType(): if there are active
     * slots but NONE of them refer to a still-usable room (Available|Cleaned +
     * is_priority), throw the next batch so the waiting stack gets promoted.
     *
     * Returns true if a refresh was performed.
     */
    public static function refreshIfStale(int $branchId, int $typeId): bool
    {
        $activeRoomIds = self::activeRoomIds($branchId, $typeId);

        if (empty($activeRoomIds)) {
            return false;
        }

        $usableActive = Room::where('branch_id', $branchId)
            ->whereIn('id', $activeRoomIds)
            ->whereIn('status', ['Available', 'Cleaned'])
            ->where('is_priority', 1)
            ->exists();

        if ($usableActive) {
            return false;
        }

        self::throwNextBatch($branchId, $typeId);
        return true;
    }
}
