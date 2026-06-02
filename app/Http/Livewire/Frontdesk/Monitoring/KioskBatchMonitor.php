<?php

namespace App\Http\Livewire\Frontdesk\Monitoring;

use App\Models\KioskCurrentBatch;
use App\Models\Room;
use App\Models\Type;
use App\Services\KioskBatchService;
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

            // Collect IDs already accounted for (now + picked)
            $batchRoomIds = $batchSlots->pluck('room_id')->toArray();

            // NEXT: preview upcoming rooms in priority order
            $nextPreview = KioskBatchService::previewBatches($branchId, $type->id, 5);
            $next = collect($nextPreview)
                ->filter(fn ($r) => $r['room_number'] !== null)
                ->map(fn ($r) => [
                    'room_number' => $r['room_number'],
                    'floor_number' => $r['floor_number'],
                ])->values()->toArray();

            $nextRoomNumbers = collect($next)->pluck('room_number')->toArray();

            // AFTER: remaining available priority rooms, grouped by floor
            $excludeNumbers = collect($now)->pluck('room_number')
                ->merge(collect($picked)->pluck('room_number'))
                ->merge($nextRoomNumbers)
                ->unique()->toArray();

            $afterRooms = Room::where('branch_id', $branchId)
                ->where('type_id', $type->id)
                ->whereIn('status', ['Available', 'Cleaned'])
                ->where('is_priority', 1)
                ->whereNotIn('id', $batchRoomIds ?: [0])
                ->whereNotIn('number', $excludeNumbers ?: [''])
                ->with('floor:id,number')
                ->get(['id', 'number', 'floor_id', 'status'])
                ->sortBy(fn ($r) => [(int) ($r->floor->number ?? 0), $r->number]);

            // Filter out rooms that appear in next preview (by room number match)
            $nextPreviewRoomNumbers = collect($nextPreview)
                ->filter(fn ($r) => $r['room_number'] !== null)
                ->pluck('room_number')
                ->toArray();
            $afterRooms = $afterRooms->reject(fn ($r) => in_array($r->number, $nextPreviewRoomNumbers));

            $after = $afterRooms->groupBy(fn ($r) => $r->floor->number ?? '?')
                ->sortKeys()
                ->map(fn ($rooms, $floor) => [
                    'floor' => $floor,
                    'rooms' => $rooms->map(fn ($r) => ['number' => $r->number])->values()->toArray(),
                ])->values()->toArray();

            // Exclude after room IDs for cleaned query
            $afterRoomIds = $afterRooms->pluck('id')->toArray();
            $allExcludeIds = array_merge($batchRoomIds, $afterRoomIds);

            // CLEANED: status='Cleaned' rooms not in any other tier
            $cleaned = Room::where('branch_id', $branchId)
                ->where('type_id', $type->id)
                ->where('status', 'Cleaned')
                ->where(function ($q) {
                    $q->where('is_priority', 0)->orWhere('is_priority', null);
                })
                ->whereNotIn('id', $allExcludeIds ?: [0])
                ->whereNotIn('number', $excludeNumbers ?: [''])
                ->with('floor:id,number')
                ->get(['id', 'number', 'floor_id'])
                ->sortBy(fn ($r) => [(int) ($r->floor->number ?? 0), $r->number])
                ->map(fn ($r) => [
                    'room_number' => $r->number,
                    'floor_number' => $r->floor->number ?? '?',
                ])->values()->toArray();

            // Stats
            $totalAvailable = Room::where('branch_id', $branchId)
                ->where('type_id', $type->id)
                ->whereIn('status', ['Available', 'Cleaned'])
                ->where('is_priority', 1)
                ->count();

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

    public function render()
    {
        return view('livewire.frontdesk.monitoring.kiosk-batch-monitor');
    }
}
