<?php

namespace App\Http\Livewire\BackOffice\Reports;

use Livewire\Component;
use App\Models\NewGuestReport as reportQuery;
use App\Models\Frontdesk;

class NewGuestReport extends Component
{
    public $frontdesk_id;
    public $shift;
    public $date;
    public $time;

    public $total_guest = 0;

    public function mount()
    {
        $this->date = now()->toDateString();
    }

    public function render()
    {
        $reports = reportQuery::query()
            ->where('branch_id', auth()->user()->branch_id)
            ->with([
                'room:id,number',
                'checkinDetail:id,guest_id,check_in_at,check_out_at,hours_stayed,frontdesk_id',
                'checkinDetail.guest:id,name',
                'checkinDetail.frontdesk:id,name',
                'checkinDetail.extendedGuestReports:id,checkin_details_id,total_hours',
            ])
            ->when($this->frontdesk_id, fn($q) =>
                $q->where('frontdesk_id', $this->frontdesk_id)
            )
            ->when($this->shift, fn($q) =>
                $q->where('shift', $this->shift)
            )
            ->when($this->date, fn($q) =>
                $q->whereDate('created_at', $this->date)
            )
            ->when($this->time, fn($q) =>
                $q->whereTime('created_at', '<=', $this->time)
            )
            ->orderByDesc('created_at')
            ->get();

        $this->total_guest = $reports->count();

        return view('livewire.back-office.reports.new-guest-report', [
            'reports' => $reports,
            'frontdesks' => Frontdesk::where('branch_id', auth()->user()->branch_id)->get(['id', 'name']),
        ]);
    }

    public function resetFilters()
    {
        $this->reset(['frontdesk_id', 'shift', 'time']);
        $this->date = now()->toDateString();
    }
}
