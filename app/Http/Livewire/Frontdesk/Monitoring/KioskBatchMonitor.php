<?php

namespace App\Http\Livewire\Frontdesk\Monitoring;

use App\Models\KioskCurrentBatch;
use App\Models\Room;
use App\Models\Type;
use Livewire\Component;

class KioskBatchMonitor extends Component
{
    public $kioskBatchData = [];
    public $kioskBatchTotals = [];

    public function mount()
    {
        $this->loadBatchData();
    }

    public function loadBatchData()
    {
        $branchId = auth()->user()->branch_id;
        $types = Type::where('branch_id', $branchId)->orderBy('id')->get();

        $data = [];
        foreach ($types as $type) {
            $batchSlots = KioskCurrentBatch::where('branch_id', $branchId)
                ->where('type_id', $type->id)
                ->with(['room:id,number,status,floor_id', 'floor:id,number'])
                ->get();

            // NOW: active slots
            $now = $batchSlots->where('slot_status', 'active')
                ->sortBy(fn ($b) => $b->floor->number ?? 0)
                ->values()
                ->map(fn ($b) => [
                    'room_number' => $b->room->number ?? '?',
                    'floor_number' => $b->floor->number ?? '?',
                ])->toArray();

            // PICKED: slots picked by guests, waiting frontdesk
            $picked = $batchSlots->where('slot_status', 'picked')
                ->sortBy(fn ($b) => $b->floor->number ?? 0)
                ->values()
                ->map(fn ($b) => [
                    'room_number' => $b->room->number ?? '?',
                    'floor_number' => $b->floor->number ?? '?',
                ])->toArray();

            // Collect IDs already in the batch (now + picked)
            $batchRoomIds = $batchSlots->pluck('room_id')->toArray();

            // Build the full priority-sorted queue of all eligible rooms
            // using the same logic as KioskBatchService::pickPreferredRoom:
            //   1. Unused rooms (last_checkin_at IS NULL), natsort tiebreak
            //   2. Used rooms sorted by last_cleaned_at ASC, natsort tiebreak
            $allEligible = Room::where('branch_id', $branchId)
                ->where('type_id', $type->id)
                ->whereIn('status', ['Available', 'Cleaned'])
                ->where('is_priority', 1)
                ->whereNotIn('id', $batchRoomIds ?: [0])
                ->with('floor:id,number')
                ->get(['id', 'number', 'floor_id', 'status', 'last_checkin_at', 'last_cleaned_at']);

            $sortedQueue = $this->sortByBatchPriority($allEligible);

            // Split into NEXT (Available only) and CLEANED (Cleaned status)
            // NEXT: first 5 Available rooms in priority order
            $nextRooms = collect();
            $afterRooms = collect();
            $availableCount = 0;

            foreach ($sortedQueue as $room) {
                if ($room->status === 'Cleaned') {
                    continue; // Cleaned rooms skip NEXT/AFTER — go to CLEANED tier
                }
                // status === 'Available'
                if ($availableCount < 5) {
                    $nextRooms->push($room);
                } else {
                    $afterRooms->push($room);
                }
                $availableCount++;
            }

            $next = $nextRooms->map(fn ($r) => [
                'room_number' => $r->number,
                'floor_number' => $r->floor->number ?? '?',
            ])->values()->toArray();

            // AFTER: remaining Available rooms, grouped by floor, maintaining priority order within each floor
            $after = $afterRooms->groupBy(fn ($r) => $r->floor->number ?? '?')
                ->sortKeys()
                ->map(fn ($rooms, $floor) => [
                    'floor' => $floor,
                    'rooms' => $rooms->map(fn ($r) => ['number' => $r->number])->values()->toArray(),
                ])->values()->toArray();

            // CLEANED: all rooms with status='Cleaned' from the queue, in priority order
            $cleanedRooms = $sortedQueue->filter(fn ($r) => $r->status === 'Cleaned');
            $cleaned = $cleanedRooms->map(fn ($r) => [
                'room_number' => $r->number,
                'floor_number' => $r->floor->number ?? '?',
            ])->values()->toArray();

            // Stats
            $totalAvailable = $allEligible->count() + count($batchRoomIds);
            $activeCount = count($now);
            $waitingCount = max(0, $totalAvailable - $activeCount);

            $data[] = [
                'type_name' => $type->name,
                'now' => $now,
                'picked' => $picked,
                'next' => $next,
                'after' => $after,
                'cleaned' => $cleaned,
                'total_available' => $totalAvailable,
                'waiting_count' => $waitingCount,
                'active_count' => $activeCount,
                'picked_count' => count($picked),
            ];
        }

        // Branch-wide totals
        $this->kioskBatchTotals = [
            'available' => Room::where('branch_id', $branchId)
                ->whereIn('status', ['Available', 'Cleaned'])
                ->where('is_priority', 1)
                ->count(),
            'occupied' => Room::where('branch_id', $branchId)
                ->where('status', 'Occupied')
                ->count(),
            'total' => Room::where('branch_id', $branchId)->count(),
        ];

        $this->kioskBatchData = $data;
    }

    /**
     * Sort rooms using the same priority logic as KioskBatchService::pickPreferredRoom:
     *   1. Never-used rooms (last_checkin_at IS NULL) — natsort by number
     *   2. Used rooms — sorted by last_cleaned_at ASC (FIFO), natsort tiebreak
     */
    private function sortByBatchPriority($rooms)
    {
        $unused = $rooms->whereNull('last_checkin_at');
        $used = $rooms->whereNotNull('last_checkin_at');

        // Natsort unused rooms by number
        $unusedSorted = $unused->sort(function ($a, $b) {
            return strnatcmp($a->number, $b->number);
        })->values();

        // Sort used rooms by last_cleaned_at ASC, then natsort by number as tiebreak
        $usedSorted = $used->sort(function ($a, $b) {
            $cleanCmp = ($a->last_cleaned_at ?? '') <=> ($b->last_cleaned_at ?? '');
            if ($cleanCmp !== 0) {
                return $cleanCmp;
            }
            return strnatcmp($a->number, $b->number);
        })->values();

        // Unused first, then used
        return $unusedSorted->merge($usedSorted);
    }

    public function render()
    {
        return view('livewire.frontdesk.monitoring.kiosk-batch-monitor');
    }
}
