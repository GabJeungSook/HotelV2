<?php

namespace App\Http\Livewire\Roomboy;

use App\Models\ActivityLog;
use DB;
use App\Models\Room;
use App\Models\Guest;
use Livewire\Component;
use WireUi\Traits\Actions;
use App\Models\CheckinDetail;
use App\Models\RoomBoyReport;
use App\Models\CleaningHistory;
use App\Services\KioskBatchService;
use App\Support\ShiftResolver;

class Index extends Component
{
    use Actions;
    public function render()
    {
        return view('livewire.roomboy.index', [
            // Sorted by check_out_time ascending (earliest checkout first)
            'assignedRooms' => Room::whereBranchId(auth()->user()->branch_id)
                ->where('status', 'Uncleaned')
                ->whereFloorId(auth()->user()->roomboy_assigned_floor_id)
                ->orderBy('check_out_time', 'asc')
                ->get(),

            'unassignedRooms' => Room::whereBranchId(auth()->user()->branch_id)
                ->where('status', 'Uncleaned')
                ->where(
                    'floor_id',
                    '!=',
                    auth()->user()->roomboy_assigned_floor_id
                )
                ->orderBy('check_out_time', 'asc')
                ->get(),
        ]);
    }

    public function startCleaning($room_id)
    {
        DB::beginTransaction();

        // Multi-tenant safety: a roomboy can only start cleaning a room in
        // their own branch. Without the branch filter, knowing a foreign
        // room_id would let them touch another branch's room.
        $room = Room::where('id', $room_id)
            ->where('branch_id', auth()->user()->branch_id)
            ->lockForUpdate()
            ->first();

        if (! $room) {
            DB::rollBack();
            $this->dialog()->error('Room Not Found', 'This room is not in your branch.');
            return;
        }

        // Race condition protection: room may have been taken by another room boy
        if ($room->status !== 'Uncleaned') {
            DB::rollBack();
            $this->dialog()->error(
                'Error',
                'This room is already being cleaned by another room boy.'
            );
            return;
        }

        // Enforce FIFO: room must be within the first N uncleaned rooms,
        // where N = number of roomboys assigned to this floor.
        $assignedFloorId = auth()->user()->roomboy_assigned_floor_id;
        $roomboyCount = DB::table('floor_user')
            ->where('floor_id', $assignedFloorId)
            ->distinct()
            ->count('user_id');
        $roomboyCount = max($roomboyCount, 1);

        $allowedRoomIds = Room::where('branch_id', auth()->user()->branch_id)
            ->where('status', 'Uncleaned')
            ->where('floor_id', $assignedFloorId)
            ->orderBy('check_out_time', 'asc')
            ->limit($roomboyCount)
            ->pluck('id')
            ->toArray();

        if (! in_array($room->id, $allowedRoomIds)) {
            DB::rollBack();
            $this->dialog()->error(
                'Cannot Start Cleaning',
                'Please wait — rooms ahead in the queue must be started first.'
            );
            return;
        }

        $record_count = RoomBoyReport::where('roomboy_id', auth()->user()->id)
            ->whereDate('created_at', now())
            ->count();

        $getlastRecord = RoomBoyReport::where('roomboy_id', auth()->user()->id)
            ->orderBy('id', 'desc')
            ->first();

        $guest = Guest::where('previous_room_id', $room->id)->first();
        if ($guest === null) {
            $guest = Guest::where('room_id', $room->id)->first();
        }

        $checkinDetail = CheckinDetail::where('room_id', $room->id)
        ->orderBy('id', 'desc')
        ->first();

        if($checkinDetail === null)
        {
            $checkinDetail_id = CheckinDetail::where('guest_id', $guest->id)
            ->orderBy('id', 'desc')
            ->first()->id;
        }else{
            $checkinDetail_id = CheckinDetail::where('room_id', $room->id)
            ->orderBy('id', 'desc')
            ->first()->id;

        }



        $room->update([
            'status' => 'Cleaning',
            'started_cleaning_at' => \Carbon\Carbon::now(),
            'cleaning_by_user_id' => auth()->id(),
        ]);

        // Defensive: heal any stale kiosk slots for this type. Symmetric
        // with finishCleaning's maybeFillEmptySlot hook.
        KioskBatchService::refreshIfStale($room->branch_id, $room->type_id);

        $shift_schedule = ShiftResolver::current();
            $shift_date = ShiftResolver::deriveShiftDate(now(), $shift_schedule)->format('F j, Y');

            if ($record_count > 0) {
                $last_cleaned = $getlastRecord->cleaning_end;

                RoomBoyReport::create([
                    'branch_id' => auth()->user()->branch_id,
                    'room_id' => $room->id,
                    'checkin_details_id' => $checkinDetail_id,
                    'roomboy_id' => auth()->user()->id,
                    'cleaning_start' => \Carbon\Carbon::now(),
                    'cleaning_end' => \Carbon\Carbon::now()->addMinutes(15),
                    'total_hours_spent' => 0,
                    'interval' => \Carbon\Carbon::now()->diffInMinutes(
                        $last_cleaned
                    ),
                    'shift' => $shift_schedule,
                    'is_cleaned' => false,
                ]);
            } else {
                RoomBoyReport::create([
                    'branch_id' => auth()->user()->branch_id,
                    'room_id' => $room->id,
                    'checkin_details_id' => $checkinDetail_id,
                    'roomboy_id' => auth()->user()->id,
                    'cleaning_start' => \Carbon\Carbon::now(),
                    'cleaning_end' => \Carbon\Carbon::now()->addMinutes(15),
                    'total_hours_spent' => 0,
                    'interval' => 0,
                    'shift' => $shift_schedule,
                    'is_cleaned' => false,
                ]);
            }

        ActivityLog::create([
            'branch_id' => auth()->user()->branch_id,
            'user_id' => auth()->user()->id,
            'activity' => 'Start Cleaning',
            'description' => 'Started cleaning Room #' . $room->number,
        ]);

        DB::commit();
    }

    public function finishCleaning($room_id)
    {
        DB::beginTransaction();

        // Lock the room INSIDE the transaction so a concurrent frontdesk
        // saveCheckIn cannot flip status to Occupied between this read and
        // our final 'Available' write. Without the lock, the roomboy update
        // wins the race and overwrites Occupied — producing a "ghost room"
        // (status Available but with an active CheckinDetail).
        $room = Room::where('id', $room_id)
            ->where('branch_id', auth()->user()->branch_id)
            ->lockForUpdate()
            ->first();

        if (! $room) {
            DB::rollBack();
            $this->dialog()->error('Room Not Found', 'This room is no longer accessible.');
            return;
        }

        // If the room is now Occupied, frontdesk took it during cleaning.
        // Bail without flipping to Available — that would erase the live
        // booking from the room status field.
        if ($room->status === 'Occupied') {
            DB::rollBack();
            $this->dialog()->error(
                'Cannot Finish Cleaning',
                'Front desk has already checked a guest into this room. Cleaning state cannot be flipped to Available.'
            );
            return;
        }

        // Guard: Block finish cleaning if room has unresolved previous guest
        $openCheckin = CheckinDetail::where('room_id', $room->id)
            ->where('is_check_out', false)
            ->with('guest:id,name')
            ->first();

        if ($openCheckin) {
            DB::rollBack();
            $ghostName = $openCheckin->guest->name ?? 'Unknown';
            $ghostDate = $openCheckin->check_in_at
                ? \Carbon\Carbon::parse($openCheckin->check_in_at)->format('M d, Y g:i A')
                : 'unknown date';
            $this->dialog()->error(
                'Cannot Finish Cleaning',
                "Room has unresolved previous guest: {$ghostName} (checked in {$ghostDate}). Front desk must check out first."
            );
            return;
        }

        $getlastRecord = RoomBoyReport::where('room_id', $room->id)
            ->where('roomboy_id', auth()->user()->id)
            ->orderBy('id', 'desc')
            ->first();

        if ($room->started_cleaning_at == null) {
            $room->update([
                'started_cleaning_at' => now(),
            ]);
        }

        if ($room->time_to_clean === null) {
            $room->update([
                'time_to_clean' => \Carbon\Carbon::parse($room->started_cleaning_at)->addMinutes(15),
            ]);
        }

        CleaningHistory::create([
            'user_id' => auth()->user()->id,
            'room_id' => $room->id,
            'floor_id' => $room->floor_id,
            'branch_id' => $room->branch_id,
            'current_assigned_floor_id' =>
                auth()->user()->roomboy_assigned_floor_id == $room->floor_id
                    ? true
                    : false,
            'start_time' => $room->started_cleaning_at,
            'end_time' => \Carbon\Carbon::now(),
            'expected_end_time' => $room->time_to_clean,
            'cleaning_duration' => now()->diffInMinutes(
                $room->started_cleaning_at
            ),
            'delayed_cleaning' => \Carbon\Carbon::parse(
                $room->time_to_clean
            )->isPast()
                ? true
                : false,
        ]);

        $room->update([
            'status' => 'Available',
            'is_priority' => 1,
            'started_cleaning_at' => null,
            'time_to_clean' => null,
            'cleaning_by_user_id' => null,
            'last_cleaned_at' => \Carbon\Carbon::now(),
        ]);

        // If the room's floor has no row in the current kiosk batch, this
        // room fills the blank slot immediately. Otherwise it waits for next
        // batch (strict batch rotation per client spec).
        $room->refresh();
        KioskBatchService::maybeFillEmptySlot($room);

        if ($getlastRecord) {
            $totalMinutes = ceil(
                \Carbon\Carbon::parse($getlastRecord->cleaning_start)
                    ->diffInSeconds(\Carbon\Carbon::now()) / 60
            );

            $getlastRecord->update([
                'cleaning_end' => \Carbon\Carbon::now(),
                'total_hours_spent' => $totalMinutes,
                'is_cleaned' => true,
            ]);
        }

        ActivityLog::create([
            'branch_id' => auth()->user()->branch_id,
            'user_id' => auth()->user()->id,
            'activity' => 'Finish Cleaning',
            'description' => 'Finished cleaning Room #' . $room->number,
        ]);

        DB::commit();

        $this->dialog()->success(
            $title = 'Success',
            $message = 'Room cleaned successfully'
        );
    }
}
