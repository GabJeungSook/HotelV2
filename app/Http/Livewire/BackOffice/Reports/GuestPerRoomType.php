<?php

namespace App\Http\Livewire\BackOffice\Reports;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\CheckOutGuestReport;
use App\Models\Frontdesk;
use App\Models\Type;

class GuestPerRoomType extends Component
{
    use WithPagination;

    public $frontdesk_id;
    public $room_type_id;
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

    public function updatingRoomTypeId()
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
                $q->where('branch_id', auth()->user()->branch_id)
                  ->when($this->room_type_id, fn($q2) => $q2->where('type_id', $this->room_type_id));
            })
            ->with([
                'room:id,number,type_id,branch_id',
                'room.type:id,name',
                'checkinDetail:id,guest_id,check_in_at,check_out_at',
                'checkinDetail.guest:id,name',
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

        return view('livewire.back-office.reports.guest-per-room-type', [
            'reports' => $reports,
            'frontdesks' => Frontdesk::where('branch_id', auth()->user()->branch_id)->get(['id', 'name']),
            'room_types' => Type::where('branch_id', auth()->user()->branch_id)->get(['id', 'name']),
        ]);
    }

    public function resetFilters()
    {
        $this->reset(['frontdesk_id', 'room_type_id', 'shift', 'time']);
        $this->date = now()->toDateString();
        $this->resetPage();
    }
}
