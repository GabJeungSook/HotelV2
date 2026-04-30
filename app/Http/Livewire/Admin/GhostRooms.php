<?php

namespace App\Http\Livewire\Admin;

use App\Models\Room;
use App\Models\CheckinDetail;
use App\Models\ActivityLog;
use App\Services\KioskBatchService;
use Livewire\Component;
use WireUi\Traits\Actions;

class GhostRooms extends Component
{
    use Actions;

    public function getGhostRoomsProperty()
    {
        $occupiedRoomIds = CheckinDetail::where('is_check_out', false)->pluck('room_id');

        return Room::where('status', 'Occupied')
            ->whereNotIn('id', $occupiedRoomIds)
            ->when(!auth()->user()->hasRole('superadmin'), fn($q) => $q->where('branch_id', auth()->user()->branch_id))
            ->with(['branch', 'floor', 'type'])
            ->get();
    }

    public function fixRoom($roomId)
    {
        $room = Room::find($roomId);

        if (!$room) {
            $this->dialog()->error('Error', 'Room not found.');
            return;
        }

        // Verify it's actually a ghost room
        $hasActiveGuest = CheckinDetail::where('room_id', $room->id)
            ->where('is_check_out', false)
            ->exists();

        if ($hasActiveGuest) {
            $this->dialog()->error('Error', 'This room has an active guest. Cannot fix.');
            return;
        }

        $room->update(['status' => 'Available']);

        // Notify the kiosk batch — the just-freed room is now eligible.
        // maybeFillBlankFloor adds a slot if the floor currently has none.
        $room->refresh();
        if ($room->is_priority) {
            KioskBatchService::maybeFillBlankFloor($room);
        }

        ActivityLog::create([
            'branch_id' => $room->branch_id,
            'user_id' => auth()->user()->id,
            'activity' => 'Fix Ghost Room',
            'description' => 'Fixed ghost room #' . $room->number . ' - changed from Occupied to Available',
        ]);

        $this->dialog()->success('Fixed', 'Room #' . $room->number . ' is now Available.');
    }

    public function fixAllRooms()
    {
        $ghostRooms = $this->ghostRooms;

        if ($ghostRooms->isEmpty()) {
            $this->dialog()->info('Nothing to fix', 'No ghost rooms found.');
            return;
        }

        $count = 0;
        foreach ($ghostRooms as $room) {
            $room->update(['status' => 'Available']);

            // Notify the kiosk batch (same hook as fixRoom above).
            $room->refresh();
            if ($room->is_priority) {
                KioskBatchService::maybeFillBlankFloor($room);
            }

            ActivityLog::create([
                'branch_id' => $room->branch_id,
                'user_id' => auth()->user()->id,
                'activity' => 'Fix Ghost Room',
                'description' => 'Fixed ghost room #' . $room->number . ' - changed from Occupied to Available',
            ]);

            $count++;
        }

        $this->dialog()->success('All Fixed', "Fixed {$count} ghost room(s).");
    }

    public function render()
    {
        return view('livewire.admin.ghost-rooms')
            ->layout('components.admin-layout');
    }
}
