<?php

namespace App\Http\Livewire\BackOffice\Reports;

use App\Models\CheckinDetail;
use App\Models\CleaningHistory;
use App\Models\Rate;
use App\Models\RoomBoyReport as reportQuery;
use App\Models\ShiftLog;
use App\Models\StayingHour;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

class RoomBoyReport extends Component
{
    use WithPagination;

    public $weekStart = null;
    public $activeTab = 'activity'; // 'activity' or 'penalties'

    // Activity filters
    public $roomboy_id;
    public $shift;
    public $date;
    public $total_cleaned = 0;

    // Penalty filters - shift-based like Z-Read
    public $selectedShiftLogId = '';
    public $availableShiftSessions = [];
    public $penalties = [];
    public $totalPenaltyAmount = 0;

    protected $paginationTheme = 'tailwind';

    public function mount()
    {
        $this->date = now()->toDateString();
        $this->loadAvailableShiftSessions();
        if (!empty($this->availableShiftSessions)) {
            $this->selectedShiftLogId = end($this->availableShiftSessions)['id'];
        }
    }

    public function updatedSelectedShiftLogId()
    {
        $this->loadPenalties();
    }

    public function setTab($tab)
    {
        $this->activeTab = $tab;
        if ($tab === 'penalties') {
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
        $session = $this->getSelectedSession();
        if (!$session) {
            $this->penalties = [];
            $this->totalPenaltyAmount = 0;
            return;
        }

        $branchId = auth()->user()->branch_id;
        $timeIn = Carbon::parse($session['time_in']);
        $timeOut = Carbon::parse($session['time_out']);

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

        // Get ALL completed cleanings within shift time window (same as Z-Read)
        $cleaningHistories = CleaningHistory::where('branch_id', $branchId)
            ->whereNotNull('end_time')
            ->whereBetween('end_time', [$timeIn, $timeOut])
            ->with(['room:id,number,type_id,floor_id', 'room.type:id,name', 'room.floor:id,number', 'user:id,name'])
            ->orderBy('end_time')
            ->get();

        // Get room IDs from cleaning histories for targeted checkout query
        $roomIds = $cleaningHistories->pluck('room_id')->unique()->toArray();

        if (empty($roomIds)) {
            $this->penalties = [];
            $this->totalPenaltyAmount = 0;
            return;
        }

        // Get checkout details - look back to catch delays from prior shift
        // Same approach as BigBoss Z-Read uses
        $occupyingDetails = CheckinDetail::whereIn('room_id', $roomIds)
            ->where('is_check_out', true)
            ->whereNotNull('check_out_at')
            ->whereBetween('check_out_at', [$timeIn->copy()->subDays(7), $timeOut])
            ->with('guest:id,name')
            ->get()
            ->groupBy('room_id');

        $this->penalties = [];
        $this->totalPenaltyAmount = 0;

        foreach ($cleaningHistories as $cleaning) {
            $roomCheckouts = $occupyingDetails->get($cleaning->room_id);
            if (!$roomCheckouts) {
                continue;
            }

            $cleaningEnd = Carbon::parse($cleaning->end_time);

            // Find the most recent checkout before this cleaning ended
            $checkoutDetail = $roomCheckouts
                ->filter(function ($d) use ($cleaningEnd) {
                    return Carbon::parse($d->check_out_at)->lte($cleaningEnd);
                })
                ->sortByDesc('check_out_at')
                ->first();

            if (!$checkoutDetail) {
                continue;
            }

            $checkoutTime = Carbon::parse($checkoutDetail->check_out_at);
            $durationSeconds = $checkoutTime->diffInSeconds($cleaningEnd);
            $durationMinutes = intdiv($durationSeconds, 60);

            // Only include if cleaning took MORE than 4 hours (240 minutes)
            if ($durationMinutes <= 240) {
                continue;
            }

            $durationHours = intdiv($durationMinutes, 60);
            $durationMins = $durationMinutes % 60;

            // Calculate excess time over 4 hours
            $excessMinutes = $durationMinutes - 240;
            $excessHours = intdiv($excessMinutes, 60);
            $excessMins = $excessMinutes % 60;

            $penaltyAmount = $baseRatesByType[$cleaning->room->type_id ?? 0] ?? 0;

            $this->penalties[] = [
                'date' => $cleaningEnd->format('M d, Y'),
                'room_number' => $cleaning->room->number ?? 'N/A',
                'room_type' => $cleaning->room->type->name ?? 'N/A',
                'floor' => $cleaning->room->floor->number ?? 'N/A',
                'roomboy_name' => $cleaning->user->name ?? 'N/A',
                'guest_name' => $checkoutDetail->guest->name ?? 'N/A',
                'checkout_time' => $checkoutTime->format('g:i A'),
                'cleaning_end' => $cleaningEnd->format('g:i A'),
                'duration' => $durationHours . 'h ' . $durationMins . 'm',
                'excess' => $excessHours . 'h ' . $excessMins . 'm',
                'amount' => $penaltyAmount,
            ];

            $this->totalPenaltyAmount += $penaltyAmount;
        }

        // Sort by cleaning end time
        usort($this->penalties, function ($a, $b) {
            return strcmp($a['cleaning_end'], $b['cleaning_end']);
        });
    }

    private function loadAvailableShiftSessions(): void
    {
        $weekStart = $this->weekStart ? Carbon::parse($this->weekStart)->startOfWeek() : now()->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        $shiftLogs = ShiftLog::query()
            ->where('branch_id', auth()->user()->branch_id)
            ->whereNotNull('time_out')
            ->whereBetween('time_in', [$weekStart, $weekEnd])
            ->with('frontdesk:id,name')
            ->orderBy('time_in', 'asc')
            ->get();

        $sessions = [];
        foreach ($shiftLogs as $log) {
            $shiftType = $this->getShiftType($log->time_in);
            $shiftDate = $log->time_in->format('Y-m-d');
            $key = $shiftType . '_' . $shiftDate;

            if (!isset($sessions[$key])) {
                $sessions[$key] = [
                    'id' => $log->id,
                    'log_ids' => [],
                    'time_in' => $log->time_in,
                    'time_out' => $log->time_out,
                    'shift_type' => $shiftType,
                    'shift_date' => $shiftDate,
                    'frontdesks' => [],
                ];
            }

            $sessions[$key]['log_ids'][] = $log->id;
            $sessions[$key]['frontdesks'][] = $log->frontdesk?->name ?? 'Unknown';

            if ($log->time_in < $sessions[$key]['time_in']) {
                $sessions[$key]['time_in'] = $log->time_in;
            }
            if ($log->time_out > $sessions[$key]['time_out']) {
                $sessions[$key]['time_out'] = $log->time_out;
            }
        }

        $this->availableShiftSessions = collect($sessions)
            ->sortBy('time_in')
            ->map(function ($s) {
                $frontdeskNames = implode(', ', array_unique($s['frontdesks']));
                return [
                    'id' => $s['log_ids'][0],
                    'label' => $s['shift_type'] . ' ' . $s['time_in']->format('M j')
                             . ' - ' . $frontdeskNames
                             . ' (' . $s['time_in']->format('g:i A') . ' - ' . $s['time_out']->format('g:i A') . ')',
                    'time_in' => $s['time_in']->toIso8601String(),
                    'time_out' => $s['time_out']->toIso8601String(),
                ];
            })
            ->values()
            ->toArray();
    }

    private function getShiftType(Carbon $timeIn): string
    {
        $hour = $timeIn->hour;
        return ($hour >= 6 && $hour < 20) ? 'AM' : 'PM';
    }

    private function getSelectedSession(): ?array
    {
        return collect($this->availableShiftSessions)
            ->firstWhere('id', $this->selectedShiftLogId);
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
        $this->loadAvailableShiftSessions();
        if (!empty($this->availableShiftSessions)) {
            $this->selectedShiftLogId = end($this->availableShiftSessions)['id'];
        }
        $this->loadPenalties();
    }
}
