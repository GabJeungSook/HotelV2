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

class Index extends Component
{
    use Actions;
    public function render()
    {
        return view('livewire.roomboy.index', [
            'assignedRooms' => Room::whereBranchId(auth()->user()->branch_id)
                ->where('status', 'Uncleaned')
                ->whereFloorId(auth()->user()->roomboy_assigned_floor_id)
                ->orderBy('time_to_clean', 'asc')
                ->get(),

            'unassignedRooms' => Room::whereBranchId(auth()->user()->branch_id)
                ->where('status', 'Uncleaned')
                ->where(
                    'floor_id',
                    '!=',
                    auth()->user()->roomboy_assigned_floor_id
                )
                ->get(),
        ]);
    }

    public function startCleaning($room_id)
    {

        $room = Room::where('id', $room_id)->first();

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



        if (auth()->user()->roomboy_cleaning_room_id != null) {
            $this->dialog()->error(
                $title = 'Error',
                $message = 'You are already cleaning a room'
            );
        } else {
            $room->update([
                'status' => 'Cleaning',
                'started_cleaning_at' => \Carbon\Carbon::now(),
            ]);

            auth()
                ->user()
                ->update([
                    'roomboy_cleaning_room_id' => $room_id,
                ]);

            $now = \Carbon\Carbon::now();
            $hour = (int) $now->format('H');
            $shift = $now->format('H:i');

            if ($hour >= 8 && $hour < 20) {
                $shift_schedule = 'AM';
                $shift_date = $now->format('F j, Y');
            } else {
                $shift_schedule = 'PM';
                // For times between 00:00 and 07:59 the PM shift started the previous day (8pm)
                $shift_date = $hour < 8 ? $now->copy()->subDay()->format('F j, Y') : $now->format('F j, Y');
            }

            // $shift = \Carbon\Carbon::now()->format('H:i');
            // $hour = \Carbon\Carbon::parse($shift)->hour;

            // if ($hour >= 8 && $hour < 20) {
            //     $shift_schedule = 'AM';
            // } else {
            //     $shift_schedule = 'PM';
            // }

            DB::beginTransaction();
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
    }

    public function finishCleaning()
    {
        $room = Room::where(
            'id',
            auth()->user()->roomboy_cleaning_room_id
        )->first();

        $record_count = RoomBoyReport::where('roomboy_id', auth()->user()->id)
            ->whereDate('created_at', now())
            ->count();

        $getlastRecord = RoomBoyReport::where('room_id', $room->id)
            ->where('roomboy_id', auth()->user()->id)
            ->orderBy('id', 'desc')
            ->first();

        if (now()->diffInMinutes($room->started_cleaning_at) < 15) {
            $this->dialog()->error(
                $title = 'Error',
                $message = 'You need to clean for at least 15 minutes'
            );
        } else {
            if($room->time_to_clean === null)
            {
                $room->update([
                    'time_to_clean' => \Carbon\Carbon::parse($room->started_cleaning_at)->addMinutes(15),
                ]);
            }
            DB::beginTransaction();

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

            auth()
                ->user()
                ->update([
                    'roomboy_cleaning_room_id' => null,
                ]);

            $room->update([
                'status' => 'Available',
                'is_priority' => 1,
                'started_cleaning_at' => null,
                'time_to_clean' => null,
            ]);

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
}
