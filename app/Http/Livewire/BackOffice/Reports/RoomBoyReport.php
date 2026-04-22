<?php

namespace App\Http\Livewire\BackOffice\Reports;

use App\Models\CheckInDetail;
use App\Models\CleaningHistory;
use App\Models\Rate;
use App\Models\RoomBoyReport as reportQuery;
use App\Models\StayingHour;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

class RoomBoyReport extends Component
{
    use WithPagination;

    public $activeTab = 'activity'; // 'activity' or 'penalties'

    // Activity filters
    public $roomboy_id;
    public $shift;
    public $date;
    public $total_cleaned = 0;

    // Penalty filters
    public $penaltyDateFrom;
    public $penaltyDateTo;
    public $penalties = [];
    public $totalPenaltyAmount = 0;

    protected $paginationTheme = 'tailwind';

    public function mount()
    {
        $this->date = now()->toDateString();
        $this->penaltyDateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->penaltyDateTo = now()->format('Y-m-d');
    }

    public function setTab($tab)
    {
        $this->activeTab = $tab;
        if ($tab === 'penalties' && empty($this->penalties)) {
            $this->loadPenalties();
        }
    }

    public function updatingRoomboyId()
    {
        $this->resetPage();
    }

    public function updatingShift()
    {
        $this->resetPage();
    }

    public function updatingDate()
    {
        $this->resetPage();
    }

    public function loadPenalties()
    {
        $branchId = auth()->user()->branch_id;
        $dateFrom = Carbon::parse($this->penaltyDateFrom)->startOfDay();
        $dateTo = Carbon::parse($this->penaltyDateTo)->endOfDay();

        // Get 6-hour base rates for penalty calculation
        $sixHourStaying = StayingHour::where('branch_id', $branchId)
            ->where('number', 6)
            ->first();

        $baseRatesByType = $sixHourStaying
            ? Rate::where('branch_id', $branchId)
                ->where('staying_hour_id', $sixHourStaying->id)
                ->pluck('amount', 'type_id')
                ->toArray()
            : [];

        // Get completed cleanings within date range
        $cleaningHistories = CleaningHistory::where('branch_id', $branchId)
            ->whereNotNull('end_time')
            ->whereBetween('end_time', [$dateFrom, $dateTo])
            ->with(['room.type', 'room.floor', 'user'])
            ->get();

        // Get checkout details for matching (filter via room's branch)
        $occupyingDetails = CheckInDetail::whereHas('room', function ($q) use ($branchId) {
                $q->where('branch_id', $branchId);
            })
            ->where('is_check_out', true)
            ->whereBetween('check_out_at', [$dateFrom->copy()->subHours(24), $dateTo])
            ->with('guest')
            ->get();

        $this->penalties = [];
        $this->totalPenaltyAmount = 0;

        foreach ($cleaningHistories as $cleaning) {
            $checkoutDetail = $occupyingDetails
                ->where('room_id', $cleaning->room_id)
                ->where('is_check_out', true)
                ->filter(function ($d) use ($cleaning) {
                    $checkoutAt = Carbon::parse($d->check_out_at);
                    $cleaningEnd = Carbon::parse($cleaning->end_time);
                    return $checkoutAt->lte($cleaningEnd);
                })
                ->sortByDesc('check_out_at')
                ->first();

            if (!$checkoutDetail) {
                continue;
            }

            $guestName = $checkoutDetail->guest->name ?? 'N/A';
            $checkoutTime = Carbon::parse($checkoutDetail->check_out_at);
            $cleaningEnd = Carbon::parse($cleaning->end_time);

            $durationMinutes = $checkoutTime->diffInMinutes($cleaningEnd);

            // Only include if cleaning took MORE than 4 hours (240 minutes)
            if ($durationMinutes <= 240) {
                continue;
            }

            $durationHours = floor($durationMinutes / 60);
            $durationMins = $durationMinutes % 60;

            $penaltyAmount = 0;
            if ($cleaning->room && $cleaning->room->type_id) {
                $penaltyAmount = $baseRatesByType[$cleaning->room->type_id] ?? 0;
            }

            $this->penalties[] = [
                'date' => $cleaningEnd->format('M d, Y'),
                'room_number' => $cleaning->room->number ?? 'N/A',
                'room_type' => $cleaning->room->type->name ?? 'N/A',
                'floor' => $cleaning->room->floor->number ?? 'N/A',
                'roomboy_name' => $cleaning->user->name ?? 'N/A',
                'guest_name' => $guestName,
                'checkout_time' => $checkoutTime->format('g:i A'),
                'cleaning_end' => $cleaningEnd->format('g:i A'),
                'duration' => $durationHours . 'h ' . $durationMins . 'm',
                'amount' => $penaltyAmount,
            ];

            $this->totalPenaltyAmount += $penaltyAmount;
        }

        // Sort by date descending
        usort($this->penalties, function ($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
    }

    public function render()
    {
        $roomboys = User::whereHas('roles', fn ($q) => $q->where('name', 'roomboy'))->get(['id', 'name']);

        $query = reportQuery::query()
            ->whereHas('room', function ($q) {
                $q->where('branch_id', auth()->user()->branch_id);
            })
            ->with([
                'room:id,number,branch_id',
                'roomboy:id,name',
            ])
            ->when($this->roomboy_id, fn ($q) => $q->where('roomboy_id', $this->roomboy_id))
            ->when($this->shift, fn ($q) => $q->where('shift', $this->shift))
            ->when($this->date, fn ($q) => $q->whereDate('created_at', $this->date))
            ->orderByDesc('created_at');

        $this->total_cleaned = (clone $query)->where('is_cleaned', true)->count();
        $reports = $query->paginate(50);

        return view('livewire.back-office.reports.room-boy-report', [
            'reports' => $reports,
            'roomboys' => $roomboys,
        ]);
    }

    public function resetFilters()
    {
        $this->reset(['roomboy_id', 'shift']);
        $this->date = now()->toDateString();
        $this->resetPage();
    }

    public function resetPenaltyFilters()
    {
        $this->penaltyDateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->penaltyDateTo = now()->format('Y-m-d');
        $this->loadPenalties();
    }
}
