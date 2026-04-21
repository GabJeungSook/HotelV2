<?php

namespace App\Http\Livewire\BackOffice\Reports;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\CheckOutGuestReport;
use App\Models\Frontdesk;

class CheckoutGuest extends Component
{
    use WithPagination;

    public $frontdesk_id;
    public $shift;
    public $date;
    public $time;

    public $total_guest = 0;

    protected $paginationTheme = 'tailwind';

    public function mount()
    {
        $this->date = now()->toDateString();
    }

    public function updatingFrontdeskId()
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

    public function render()
    {
        $query = CheckOutGuestReport::query()
            ->whereHas('room', function ($q) {
                $q->where('branch_id', auth()->user()->branch_id);
            })
            ->with([
                'room:id,number,branch_id',
                'checkinDetail:id,guest_id,check_in_at,check_out_at,hours_stayed',
                'checkinDetail.guest:id,name,is_long_stay,number_of_days',
                'checkinDetail.extendedGuestReports:id,checkin_details_id,total_hours',
                'frontdesk:id,name',
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
            ->orderByDesc('created_at');

        $this->total_guest = $query->count();
        $reports = $query->paginate(50);

        return view('livewire.back-office.reports.checkout-guest', [
            'reports' => $reports,
            'frontdesks' => Frontdesk::where('branch_id', auth()->user()->branch_id)->get(['id', 'name']),
        ]);
    }

    public function resetFilters()
    {
        $this->reset(['frontdesk_id', 'shift', 'time']);
        $this->date = now()->toDateString();
        $this->resetPage();
    }
}
