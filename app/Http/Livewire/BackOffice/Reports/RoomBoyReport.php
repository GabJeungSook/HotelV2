<?php

namespace App\Http\Livewire\BackOffice\Reports;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\RoomBoyReport as reportQuery;
use App\Models\User;

class RoomBoyReport extends Component
{
    use WithPagination;

    public $roomboy_id;
    public $shift;
    public $date;

    public $total_cleaned = 0;

    protected $paginationTheme = 'tailwind';

    public function mount()
    {
        $this->date = now()->toDateString();
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

    public function render()
    {
        $roomboys = User::whereHas('roles', fn($q) => $q->where('name', 'roomboy'))->get(['id', 'name']);

        $query = reportQuery::query()
            ->whereHas('room', function ($q) {
                $q->where('branch_id', auth()->user()->branch_id);
            })
            ->with([
                'room:id,number,branch_id',
                'roomboy:id,name',
            ])
            ->when($this->roomboy_id, fn($q) => $q->where('roomboy_id', $this->roomboy_id))
            ->when($this->shift, fn($q) => $q->where('shift', $this->shift))
            ->when($this->date, fn($q) => $q->whereDate('created_at', $this->date))
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
}
