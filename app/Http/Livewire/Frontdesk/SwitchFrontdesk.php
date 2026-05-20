<?php

namespace App\Http\Livewire\Frontdesk;

use Livewire\Component;
use App\Models\Floor;
use App\Models\Guest;
use App\Models\Room;
use App\Models\UnoccupiedRoomReport;
use App\Support\ShiftResolver;
use DB;
use Carbon\Carbon;

class SwitchFrontdesk extends Component
{
    public $switch_modal = false;
    public $frontdesks = [];
    public function render()
    {
        return view('livewire.frontdesk.switch-frontdesk', [
            'floors' => Floor::where(
                'branch_id',
                auth()->user()->branch_id
            )->get(),
            'new_guests' =>
                Guest::where('branch_id', auth()->user()->branch_id)
                    ->whereHas('checkInDetail', function ($query) {
                        $query->whereNotNull('check_in_at');
                    })
                    ->count() ?? 0,
            'total_extended_guest_count' =>
                Guest::whereHas('stayExtensions')
                    ->where('branch_id', auth()->user()->branch_id)
                    ->count() ?? 0,
            'unoccupied_rooms' =>
                Room::whereHas('floor', function ($q) {
                    $q->where('branch_id', auth()->user()->branch_id);
                })
                    ->whereDoesntHave('checkInDetails', function ($q) {
                        $q->whereNotNull('check_in_at');
                    })
                    ->get('number') ?? 00,
        ]);
    }

    public function updatedSwitchModal()
    {
        $this->frontdesks = json_decode(auth()->user()->assigned_frontdesks);
        $this->emit('switchModalUpdated');
    }

    public function endShift()
    {
        $unoccupied_rooms = Room::whereHas('floor', function ($q) {
            $q->where('branch_id', auth()->user()->branch_id);
        })
            ->whereDoesntHave('checkInDetails', function ($q) {
                $q->whereNotNull('check_in_at');
            })
            ->get('number')
            ->pluck('number')
            ->toArray();

        $shift_schedule = ShiftResolver::current();
        $shift_date = ShiftResolver::deriveShiftDate(now(), $shift_schedule)->format('F j, Y');

        DB::beginTransaction();

        UnoccupiedRoomReport::create([
            'branch_id' => auth()->user()->branch_id,
            'frontdesk_id' => json_decode(
                auth()->user()->assigned_frontdesks
            )[0],
            'partner_name' => json_decode(
                auth()->user()->assigned_frontdesks
            )[1],
            'rooms' => implode(', ', $unoccupied_rooms),
            'shift' => $shift_schedule,
        ]);
        DB::commit();

        auth()
            ->user()
            ->update([
                'assigned_frontdesks' => null,
            ]);

        return redirect()->route('frontdesk.room-monitoring');
    }
}
