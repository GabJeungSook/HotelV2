<?php

namespace App\Http\Livewire\BackOffice\Reports;

use Livewire\Component;
use App\Models\CleaningHistory;
use App\Models\User;
use App\Models\Rate;
use App\Models\StayingHour;
use Carbon\Carbon;

class RoomBoyPenaltyReport extends Component
{
    public $roomboy_id;
    public $shift;
    public $date;

    public $totalPenalty = 0;
    public $penaltyRecords = [];

    public function mount()
    {
        $this->date = now()->toDateString();
        $this->loadPenaltyRecords();
    }

    public function updatedRoomboyId()
    {
        $this->loadPenaltyRecords();
    }

    public function updatedShift()
    {
        $this->loadPenaltyRecords();
    }

    public function updatedDate()
    {
        $this->loadPenaltyRecords();
    }

    public function loadPenaltyRecords()
    {
        $branchId = auth()->user()->branch_id;

        // Get 6-hour staying hour for this branch
        $sixHourStaying = StayingHour::where('branch_id', $branchId)
            ->where('number', 6)
            ->first();

        // Get all cleaning histories where cleaning was delayed (exceeded 4 hours)
        $query = CleaningHistory::where('branch_id', $branchId)
            ->where('delayed_cleaning', true)
            ->with(['user', 'room.type', 'floor'])
            ->when($this->roomboy_id, fn($q) => $q->where('user_id', $this->roomboy_id))
            ->when($this->date, fn($q) => $q->whereDate('end_time', $this->date))
            ->orderBy('end_time', 'desc');

        // Filter by shift if selected
        if ($this->shift) {
            $query->where(function($q) {
                $q->whereRaw("HOUR(end_time) >= ? AND HOUR(end_time) < ?",
                    $this->shift === 'AM' ? [8, 20] : [20, 24])
                  ->orWhereRaw("HOUR(end_time) >= ? AND HOUR(end_time) < ?",
                    $this->shift === 'PM' ? [0, 8] : [99, 99]);
            });
        }

        $records = $query->get();

        $this->penaltyRecords = [];
        $this->totalPenalty = 0;

        foreach ($records as $record) {
            // Get guest name from the room's last checkin
            $guestName = 'N/A';
            $checkoutTime = null;

            if ($record->room) {
                $lastCheckin = \App\Models\CheckinDetail::where('room_id', $record->room_id)
                    ->where('check_out_at', '<=', $record->end_time)
                    ->orderBy('check_out_at', 'desc')
                    ->with('guest')
                    ->first();

                if ($lastCheckin) {
                    $guestName = $lastCheckin->guest->name ?? 'N/A';
                    $checkoutTime = Carbon::parse($lastCheckin->check_out_at);
                }
            }

            // Calculate duration from checkout to finish cleaning
            $startTime = Carbon::parse($record->start_time);
            $endTime = Carbon::parse($record->end_time);

            if ($checkoutTime) {
                $durationMinutes = $checkoutTime->diffInMinutes($endTime);
            } else {
                $durationMinutes = $startTime->diffInMinutes($endTime);
            }

            $durationHours = floor($durationMinutes / 60);
            $durationMins = $durationMinutes % 60;

            // Get penalty amount (6-hour rate for room type)
            $penaltyAmount = 0;
            if ($record->room && $record->room->type_id && $sixHourStaying) {
                $rate = Rate::where('branch_id', $branchId)
                    ->where('type_id', $record->room->type_id)
                    ->where('staying_hour_id', $sixHourStaying->id)
                    ->first();

                $penaltyAmount = $rate->amount ?? 0;
            }

            // Determine shift based on end_time
            $hour = (int) $endTime->format('H');
            $recordShift = ($hour >= 8 && $hour < 20) ? 'AM' : 'PM';

            $this->penaltyRecords[] = [
                'id' => $record->id,
                'room_number' => $record->room->number ?? 'N/A',
                'floor' => $record->floor->number ?? 'N/A',
                'roomboy_name' => $record->user->name ?? 'N/A',
                'guest_name' => $guestName,
                'checkout_time' => $checkoutTime ? $checkoutTime->format('g:i A') : 'N/A',
                'duration' => $durationHours . 'h ' . $durationMins . 'm',
                'duration_minutes' => $durationMinutes,
                'penalty_amount' => $penaltyAmount,
                'shift' => $recordShift,
                'date' => $endTime->format('F d, Y'),
            ];

            $this->totalPenalty += $penaltyAmount;
        }
    }

    public function render()
    {
        $roomboys = User::where('branch_id', auth()->user()->branch_id)
            ->whereHas('roles', fn($q) => $q->where('name', 'roomboy'))
            ->get(['id', 'name']);

        return view('livewire.back-office.reports.room-boy-penalty-report', [
            'roomboys' => $roomboys,
        ]);
    }

    public function resetFilters()
    {
        $this->reset(['roomboy_id', 'shift']);
        $this->date = now()->toDateString();
        $this->loadPenaltyRecords();
    }
}
