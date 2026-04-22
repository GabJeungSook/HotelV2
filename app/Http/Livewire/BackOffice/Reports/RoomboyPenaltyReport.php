<?php

namespace App\Http\Livewire\BackOffice\Reports;

use App\Models\CheckInDetail;
use App\Models\CleaningHistory;
use App\Models\Rate;
use App\Models\StayingHour;
use Carbon\Carbon;
use Livewire\Component;

class RoomboyPenaltyReport extends Component
{
    public $dateFrom;
    public $dateTo;
    public $penalties = [];
    public $totalPenaltyAmount = 0;

    public function mount()
    {
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    public function generateReport()
    {
        $branchId = auth()->user()->branch_id;
        $dateFrom = Carbon::parse($this->dateFrom)->startOfDay();
        $dateTo = Carbon::parse($this->dateTo)->endOfDay();

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

        // Get checkout details for matching
        $occupyingDetails = CheckInDetail::where('branch_id', $branchId)
            ->where('is_check_out', true)
            ->whereBetween('check_out_at', [$dateFrom->copy()->subHours(24), $dateTo])
            ->with('guest')
            ->get();

        $this->penalties = [];
        $this->totalPenaltyAmount = 0;

        foreach ($cleaningHistories as $cleaning) {
            // Find the checkout that this cleaning was for
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

            // Skip if no matching checkout record
            if (!$checkoutDetail) {
                continue;
            }

            $guestName = $checkoutDetail->guest->name ?? 'N/A';
            $checkoutTime = Carbon::parse($checkoutDetail->check_out_at);
            $cleaningEnd = Carbon::parse($cleaning->end_time);

            // Calculate duration from checkout to cleaning end
            $durationMinutes = $checkoutTime->diffInMinutes($cleaningEnd);

            // Only include if cleaning took MORE than 4 hours (240 minutes)
            if ($durationMinutes <= 240) {
                continue;
            }

            $durationHours = floor($durationMinutes / 60);
            $durationMins = $durationMinutes % 60;

            // Get penalty amount (6-hour base rate for room type)
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
        return view('livewire.back-office.reports.roomboy-penalty-report');
    }
}
