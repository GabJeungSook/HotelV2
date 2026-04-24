<?php

namespace App\Services;

use App\Models\Floor;
use App\Models\KioskCurrentBatch;
use App\Models\Room;

/**
 * Manages the kiosk "batch rotation" system where the kiosk shows a fixed
 * set of up to 5 rooms (one per floor). When a room is picked, its floor
 * goes blank until ALL floors in the current batch have been picked, at
 * which point the next batch is "thrown" from the waiting stack.
 *
 * Rules:
 *  - Floor that NEVER had a room in the current batch → blank can be filled
 *    mid-batch (when a Floor N room gets cleaned, it joins the current batch).
 *  - Floor that HAD a room and was picked → stays blank until next batch.
 *
 * State is tracked in the `kiosk_current_batch` table.
 */
class KioskBatchService
{
    /**
     * End the current batch for a branch and promote the next batch.
     * Called when:
     *  - The kiosk sees an empty batch state (fresh deploy, or post-throw).
     *  - A user's check-in drains the last "active" slot.
     */
    public static function throwNextBatch(int $branchId): void
    {
        KioskCurrentBatch::where('branch_id', $branchId)->delete();

        $floors = Floor::where('branch_id', $branchId)->get();

        foreach ($floors as $floor) {
            $room = Room::where('branch_id', $branchId)
                ->where('floor_id', $floor->id)
                ->whereIn('status', ['Available', 'Cleaned'])
                ->where('is_priority', 1)
                ->orderBy('number', 'asc')
                ->first();

            if ($room) {
                KioskCurrentBatch::create([
                    'branch_id' => $branchId,
                    'room_id' => $room->id,
                    'floor_id' => $floor->id,
                    'slot_status' => KioskCurrentBatch::STATUS_ACTIVE,
                ]);
            }
        }
    }

    /**
     * Called when a roomboy finishes cleaning. If the room's floor has no row
     * in the current batch, add the room as an active slot immediately (fills
     * a floor that started blank). Otherwise the room waits for the next batch.
     */
    public static function maybeFillBlankFloor(Room $room): void
    {
        $exists = KioskCurrentBatch::where('branch_id', $room->branch_id)
            ->where('floor_id', $room->floor_id)
            ->exists();

        if ($exists) {
            return;
        }

        KioskCurrentBatch::create([
            'branch_id' => $room->branch_id,
            'room_id' => $room->id,
            'floor_id' => $room->floor_id,
            'slot_status' => KioskCurrentBatch::STATUS_ACTIVE,
        ]);
    }

    /**
     * Mark a room as 'picked' after the user confirms check-in.
     * If this was the last active slot in the batch, throw next batch.
     */
    public static function markPicked(int $branchId, int $roomId): void
    {
        KioskCurrentBatch::where('branch_id', $branchId)
            ->where('room_id', $roomId)
            ->where('slot_status', KioskCurrentBatch::STATUS_ACTIVE)
            ->update(['slot_status' => KioskCurrentBatch::STATUS_PICKED]);

        $hasActive = KioskCurrentBatch::where('branch_id', $branchId)
            ->where('slot_status', KioskCurrentBatch::STATUS_ACTIVE)
            ->exists();

        if (!$hasActive) {
            self::throwNextBatch($branchId);
        }
    }

    /**
     * Returns the list of room ids currently 'active' for a branch.
     * Used by the kiosk render to filter rooms.
     */
    public static function activeRoomIds(int $branchId): array
    {
        return KioskCurrentBatch::where('branch_id', $branchId)
            ->where('slot_status', KioskCurrentBatch::STATUS_ACTIVE)
            ->pluck('room_id')
            ->toArray();
    }

    /**
     * Whether the batch table is fully empty for a branch (no rows at all).
     * Used on first render to trigger an initial throw.
     */
    public static function isEmpty(int $branchId): bool
    {
        return !KioskCurrentBatch::where('branch_id', $branchId)->exists();
    }
}
