<?php

namespace App\Http\Livewire\Roomboy;

use App\Models\Room;
use App\Models\User;
use App\Models\Floor;
use App\Models\Guest;
use Livewire\Component;
use WireUi\Traits\Actions;
use App\Models\CheckinDetail;
use App\Models\RoomBoyReport;
use App\Models\CleaningHistory;
use App\Models\TransferedGuestReport;
use App\Services\KioskBatchService;
use App\Support\ShiftResolver;
use Illuminate\Support\Facades\DB;

class Main extends Component
{
    use Actions;
    public $user;
    public $floors;
    public $rooms;
    protected $listeners = ['showDoneTodayModal', 'showPenaltyModal'];

    public $showDoneTodayModal = false;
    public $showPenaltyModal = false;
    public $doneTodayRooms = [];
    public $penaltyRooms = [];

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
        DB::beginTransaction();

        $room = Room::where('id', $room_id)->lockForUpdate()->first();

        if (! $room) {
            DB::rollBack();
            $this->dialog()->error('Room Not Found', 'This room is no longer accessible.');
            return;
        }

        // Race condition protection: room may have been taken by another room boy
        if ($room->status !== 'Uncleaned') {
            DB::rollBack();
            $this->dialog()->error(
                $title = 'Error',
                $message = 'This room is already being cleaned by another room boy'
            );
            return;
        }

        // Enforce FIFO: room must be within the first N uncleaned rooms,
        // where N = number of roomboys currently online on overlapping floors.
        $floorIds = $this->floors->pluck('id')->toArray();
        $roomboyCount = $this->onlineRoomboyCount($floorIds);

        $allowedRoomIds = Room::where('branch_id', auth()->user()->branch_id)
            ->where('status', 'Uncleaned')
            ->whereIn('floor_id', $floorIds)
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
            DB::rollBack();
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
            // Note: room status flips Uncleaned → Cleaning. Uncleaned rooms
            // are never in the kiosk batch (filter is Available/Cleaned), so
            // this transition cannot create a stale slot. The defensive
            // refreshIfStale call below catches any drift caused by other
            // paths and is cheap (no-op if no stale slots exist).
        ]);

        $shift_schedule = ShiftResolver::current();
            $shift_date = ShiftResolver::deriveShiftDate(now(), $shift_schedule)->format('F j, Y');

            // Defensive: heal any stale kiosk slots for this type. Symmetric
            // with finishCleaning's maybeFillEmptySlot hook below.
            KioskBatchService::refreshIfStale($room->branch_id, $room->type_id);

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
        DB::beginTransaction();

        // Lock the room INSIDE the transaction so a concurrent frontdesk
        // saveCheckIn cannot flip status to Occupied between this read and
        // our final 'Available' write. Without the lock, the roomboy update
        // wins the race and overwrites Occupied — producing a "ghost room"
        // (status Available but with an active CheckinDetail).
        $room = Room::where('id', $id)
            ->where('branch_id', auth()->user()->branch_id)
            ->lockForUpdate()
            ->first();

        if (! $room) {
            DB::rollBack();
            $this->dialog()->error('Room Not Found', 'This room is no longer accessible.');
            return;
        }

        // Enforce sequential finishing: only the room that started cleaning
        // earliest can be finished first.
        $firstCleaningRoom = Room::where('cleaning_by_user_id', auth()->id())
            ->where('status', 'Cleaning')
            ->orderBy('started_cleaning_at', 'asc')
            ->first();

        if ($firstCleaningRoom && $firstCleaningRoom->id !== $room->id) {
            DB::rollBack();
            $this->dialog()->error(
                'Cannot Finish Cleaning',
                'You must finish cleaning Room '.$firstCleaningRoom->number.' first.'
            );
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
                "Room has unresolved previous guest: {$ghostName} (checked in {$ghostDate}). Please contact admin to fix this unresolved record."
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

        $this->rooms = (clone $baseQuery)
            ->with(['floor', 'type'])
            ->orderByRaw('CASE WHEN check_out_time <= ? THEN 0 ELSE 1 END', [$urgentThreshold])
            ->orderBy('check_out_time', 'asc')
            ->get();

        // Mark rooms that became Uncleaned via a transfer (not a real checkout)
        // by matching the latest TransferedGuestReport.previous_room_id to each
        // room. We attach the destination room number so the view can render a
        // "Transferred to RM X" hint, helping the roomboy distinguish a transfer
        // vacancy from a true checkout.
        if ($this->rooms->isNotEmpty()) {
            $roomIds = $this->rooms->pluck('id')->all();
            $latestTransfers = TransferedGuestReport::whereIn('previous_room_id', $roomIds)
                ->with('new_room:id,number')
                ->orderBy('created_at', 'desc')
                ->get()
                ->groupBy('previous_room_id')
                ->map(fn ($group) => $group->first());

            $this->rooms->each(function ($room) use ($latestTransfers) {
                $room->transferred_to_room_number = null;
                $report = $latestTransfers->get($room->id);
                if (! $report || ! $room->check_out_time) {
                    return;
                }
                $reportAt = \Carbon\Carbon::parse($report->created_at);
                $checkoutAt = \Carbon\Carbon::parse($room->check_out_time);
                if ($reportAt->gte($checkoutAt->copy()->subMinute())) {
                    $room->transferred_to_room_number = $report->new_room->number ?? null;
                }
            });
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

        $roomboyCount = $this->onlineRoomboyCount($floorIds);

        return view('livewire.roomboy.main', [
            'floorCounts' => $floorCounts,
            'totalUncleaned' => $totalUncleaned,
            'cleaningRooms' => $cleaningRooms,
            'cleanedToday' => $cleanedToday,
            'roomboyCount' => $roomboyCount,
        ]);
    }

    private function onlineRoomboyCount(array $floorIds): int
    {
        // The current roomboy is by definition logged in (they're running this request),
        // so always count them. Count OTHERS via Laravel's session-alive window — matches
        // the SESSION_LIFETIME so a roomboy with a backgrounded tab still counts as
        // "logged in" until Laravel itself would expire the session.
        $threshold = now()->subMinutes((int) config('session.lifetime', 120))->timestamp;

        $others = User::role('roomboy')
            ->where('id', '!=', auth()->id())
            ->whereHas('floors', fn ($q) => $q->whereIn('floors.id', $floorIds))
            ->whereHas('sessions', fn ($q) => $q->where('last_activity', '>=', $threshold))
            ->count();

        // TEMP DIAGNOSTIC — remove after debugging button count
        try {
            $rbIds = User::role('roomboy')->pluck('id');
            \Log::info('[roomboy_count_debug]', [
                'self_id' => auth()->id(),
                'my_floor_ids' => $floorIds,
                'threshold_ts' => $threshold,
                'threshold_human' => date('Y-m-d H:i:s', $threshold),
                'now_ts' => time(),
                'others_count' => $others,
                'final_count' => 1 + $others,
                'all_roomboy_ids' => $rbIds->all(),
                'roomboys_in_my_floors' => User::role('roomboy')
                    ->whereHas('floors', fn ($q) => $q->whereIn('floors.id', $floorIds))
                    ->pluck('id')->all(),
                'sessions_for_roomboys' => \DB::table('sessions')
                    ->whereIn('user_id', $rbIds)
                    ->get(['user_id', 'last_activity'])
                    ->map(fn ($s) => [
                        'user_id' => $s->user_id,
                        'last_activity' => $s->last_activity,
                        'ago_sec' => time() - $s->last_activity,
                    ])->all(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('[roomboy_count_debug] logging failed: '.$e->getMessage());
        }

        return 1 + $others;
    }
}
