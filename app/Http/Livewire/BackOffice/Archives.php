<?php

namespace App\Http\Livewire\BackOffice;

use App\Models\ShiftLog;
use Carbon\Carbon;
use Livewire\Component;

class Archives extends Component
{
    public string $report = 'sales-v2';
    public string $selectedWeek = '';
    public array $availableWeeks = [];
    public bool $loaded = false;

    public function mount()
    {
        $this->loadAvailableWeeks();
        if (!empty($this->availableWeeks)) {
            $this->selectedWeek = $this->availableWeeks[0]['value'];
        }
    }

    public function getReportsProperty(): array
    {
        return [
            'sales-v2' => [
                'label' => 'Sales Report',
                'component' => 'back-office.sales-report-v2',
            ],
            'frontdesk-v2' => [
                'label' => 'Frontdesk Report',
                'component' => 'back-office.frontdesk-report-v2',
            ],
            'big-boss' => [
                'label' => 'Big Boss Report',
                'component' => 'back-office.reports.big-boss-report',
            ],
            'frontdesk-logs' => [
                'label' => 'Frontdesk Logs',
                'component' => 'back-office.reports.frontdesk-logs',
            ],
            'room-boy' => [
                'label' => 'Room Boy Report',
                'component' => 'back-office.reports.room-boy-report',
            ],
        ];
    }

    public function getActiveComponentProperty(): ?string
    {
        return $this->reports[$this->report]['component'] ?? null;
    }

    public function loadReport()
    {
        $this->loaded = true;
    }

    private function loadAvailableWeeks(): void
    {
        $currentWeekStart = now()->startOfWeek();

        // Only fetch distinct weeks that actually have shift data, excluding current week
        $shiftDates = ShiftLog::where('branch_id', auth()->user()->branch_id)
            ->whereNotNull('time_out')
            ->where('time_in', '<', $currentWeekStart)
            ->selectRaw('DATE(time_in) as shift_date')
            ->distinct()
            ->orderByDesc('shift_date')
            ->pluck('shift_date');

        $seenWeeks = [];
        $weeks = [];

        foreach ($shiftDates as $date) {
            $weekStart = Carbon::parse($date)->startOfWeek();
            $key = $weekStart->format('Y-m-d');

            if (isset($seenWeeks[$key])) {
                continue;
            }
            $seenWeeks[$key] = true;

            $weekEnd = $weekStart->copy()->endOfWeek();
            $weeks[] = [
                'value' => $key,
                'label' => $weekStart->format('F d') . ' - ' . $weekEnd->format('F d, Y'),
            ];
        }

        $this->availableWeeks = $weeks;
    }

    public function render()
    {
        return view('livewire.back-office.archives');
    }
}
