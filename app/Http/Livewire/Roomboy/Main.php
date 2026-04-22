<?php

namespace App\Http\Livewire\Roomboy;

use App\Models\Room;
use App\Models\Floor;
use App\Models\Guest;
use Livewire\Component;
use WireUi\Traits\Actions;
use App\Models\CheckinDetail;
use App\Models\RoomBoyReport;
use App\Models\CleaningHistory;
use Illuminate\Support\Facades\DB;

class Main extends Component
{
    use Actions;
    public $user;
    public $floors;
    public $rooms;
    public $selectedFloorId = 'all'; // Default to 'all' - show all rooms

    protected $listeners = ['filterByFloor', 'showDoneTodayModal', 'showPenaltyModal'];

    public $showDoneTodayModal = false;
    public $showPenaltyModal = false;
    public $doneTodayRooms = [];
    public $penaltyRooms = [];

    public function filterByFloor($floorId)
    {
        $this->selectedFloorId = $floorId;
    }

    public function showDoneTodayModal()
    {
        $this->doneTodayRooms = CleaningHistory::where('user_id', auth()->id())
            ->whereDate('end_time', today())
            ->with(['room', 'floor'])
            ->orderBy('end_time', 'desc')
            ->get();
        $this->showDoneTodayModal = true;
    }

    public function showPenaltyModal()
    {
        $floorIds = $this->floors->pluck('id')->toArray();
        $this->penaltyRooms = Room::whereBranchId(auth()->user()->branch_id)
            ->whereIn('status', ['Uncleaned', 'Cleaning'])
            ->whereIn('floor_id', $floorIds)
            ->where(function($q) {
                $q->where('time_to_clean', '<=', now())
                  ->orWhere('check_out_time', '<=', now()->subHours(4));
            })
            ->with('floor')
            ->orderBy('check_out_time', 'asc')
            ->get();
        $this->showPenaltyModal = true;
    }

    public function closeDoneTodayModal()
    {
        $this->showDoneTodayModal = false;
    }

    public function closePenaltyModal()
    {
        $this->showPenaltyModal = false;
    }

    public function mount()
    {
        $this->user = auth()->user();
        $this->floors = $this->user->floors()
            ->whereNotNull('floors.number')
            ->orderBy('floors.id')
            ->get()
            ->unique('id')
            ->values();
        $this->selectedFloorId = 'all';
    }

    public function getSelectedFloor($floorId)
    {
        $this->selectedFloorId = $floorId;
    }

    /**
     * Get all uncleaned rooms across all assigned floors
     * Sorted by checkout time (FIFO - earliest first)
     */
    public function getAllUncleanedRoomsProperty()
    {
        $floorIds = $this->floors->pluck('id')->toArray();

        return Room::whereBranchId(auth()->user()->branch_id)
            ->where('status', 'Uncleaned')
            ->whereIn('floor_id', $floorIds)
            ->with('floor')
            ->orderBy('check_out_time', 'asc')
            ->get();
    }

    /**
     * Get total count of all uncleaned rooms
     */
    public function getTotalUncleanedCountProperty()
    {
        $floorIds = $this->floors->pluck('id')->toArray();

        return Room::whereBranchId(auth()->user()->branch_id)
            ->where('status', 'Uncleaned')
            ->whereIn('floor_id', $floorIds)
            ->count();
    }

    public function startCleaning($room_id)
    {
        $room = Room::where('id', $room_id)->first();

        // Race condition protection: room may have been taken by another room boy
        if ($room->status !== 'Uncleaned') {
            $this->dialog()->error(
                $title = 'Error',
                $message = 'This room is already being cleaned by another room boy'
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

        if ($checkinDetail !== null) {
            $checkinDetail_id = $checkinDetail->id;
        } elseif ($guest !== null) {
            $fallback = CheckinDetail::where('guest_id', $guest->id)
                ->orderBy('id', 'desc')
                ->first();
            $checkinDetail_id = $fallback?->id;
        } else {
            $checkinDetail_id = null;
        }

        if ($checkinDetail_id === null) {
            $this->dialog()->error(
                $title = 'Cannot start cleaning',
                $message = 'Room '.$room->number.' has no check-in record. Contact front desk.'
            );
            return;
        }



        $room->update([
            'status' => 'Cleaning',
            'started_cleaning_at' => \Carbon\Carbon::now(),
            'cleaning_by_user_id' => auth()->id(),
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

        DB::commit();
    }

    public function getCleaningRoomsProperty()
    {
        return Room::beingCleanedBy(auth()->id())->get();
    }

    public function finishCleaning($id)
    {
        $room = Room::where('id', $id)->first();

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

        $room->update([
            'status' => 'Available',
            'is_priority' => 1,
            'started_cleaning_at' => null,
            'time_to_clean' => null,
            'cleaning_by_user_id' => null,
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

        DB::commit();

        $this->dialog()->success(
            $title = 'Success',
            $message = 'Room cleaned successfully'
        );
    }

    public function render()
    {
        $branchId = auth()->user()->branch_id;
        $floorIds = $this->floors->pluck('id')->toArray();

        $baseQuery = Room::whereBranchId($branchId)
            ->where('status', 'Uncleaned')
            ->whereIn('floor_id', $floorIds);

        $urgentThreshold = now()->subHours(2);

        if ($this->selectedFloorId === 'all') {
            $this->rooms = (clone $baseQuery)
                ->with(['floor', 'type'])
                ->orderByRaw('CASE WHEN check_out_time <= ? THEN 0 ELSE 1 END', [$urgentThreshold])
                ->orderBy('check_out_time', 'asc')
                ->get();
        } elseif ($this->selectedFloorId) {
            $this->rooms = Room::whereBranchId($branchId)
                ->where('status', 'Uncleaned')
                ->whereFloorId($this->selectedFloorId)
                ->with(['floor', 'type'])
                ->orderByRaw('CASE WHEN check_out_time <= ? THEN 0 ELSE 1 END', [$urgentThreshold])
                ->orderBy('check_out_time', 'asc')
                ->get();
        } else {
            $this->rooms = collect();
        }

        $floorCounts = (clone $baseQuery)
            ->select('floor_id', DB::raw('COUNT(*) as total'))
            ->groupBy('floor_id')
            ->pluck('total', 'floor_id');

        $totalUncleaned = $floorCounts->sum();

        $cleaningRooms = Room::beingCleanedBy(auth()->id())
            ->with('floor')
            ->orderBy('started_cleaning_at', 'asc')
            ->get();

        $cleanedToday = CleaningHistory::where('user_id', auth()->id())
            ->whereDate('end_time', today())
            ->count();

        return view('livewire.roomboy.main', [
            'floorCounts' => $floorCounts,
            'totalUncleaned' => $totalUncleaned,
            'cleaningRooms' => $cleaningRooms,
            'cleanedToday' => $cleanedToday,
        ]);
    }
}
