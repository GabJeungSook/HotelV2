<?php

namespace App\Http\Livewire\BackOffice\Reports;

use App\Models\ShiftLog;
use Carbon\Carbon;
use Livewire\Component;

class FrontdeskLogs extends Component
{
    public $weekStart = null;
    public $date;

    public function render()
    {
        $weekStart = $this->weekStart ? Carbon::parse($this->weekStart)->startOfWeek() : now()->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        $query = ShiftLog::query()
            ->where('branch_id', auth()->user()->branch_id)
            ->with('frontdesk')
            ->when($this->date,
                fn($q) => $q->whereDate('time_in', $this->date),
                fn($q) => $q->whereBetween('time_in', [$weekStart, $weekEnd])
            )
            ->orderByDesc('time_in');

        $logs = $query->get()->map(function ($log) {
            $hour = $log->time_in->hour;
            $shiftType = ($hour >= 6 && $hour < 20) ? 'AM' : 'PM';

            return [
                'shift' => $log->time_in->format('F d, Y') . ' - ' . $shiftType,
                'frontdesk' => $log->frontdesk?->name ?? '—',
                'time_in' => $log->time_in->format('h:i A'),
                'time_out' => $log->time_out ? $log->time_out->format('h:i A') : '—',
            ];
        });

        return view('livewire.back-office.reports.frontdesk-logs', [
            'logs' => $logs,
        ]);
    }

    public function resetFilters()
    {
        $this->reset(['date']);
    }
}
