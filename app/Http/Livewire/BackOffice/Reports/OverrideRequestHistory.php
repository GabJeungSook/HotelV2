<?php

namespace App\Http\Livewire\BackOffice\Reports;

use App\Models\OverrideRequest;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

class OverrideRequestHistory extends Component
{
    use WithPagination;

    public $weekStart = null;
    public $date_from;
    public $date_to;
    public $status = '';
    public $transaction_type = '';
    public $requester_id = '';
    public $supervisor_id = '';

    protected $paginationTheme = 'tailwind';

    public function mount()
    {
        if (!$this->weekStart) {
            $this->date_from = now()->startOfWeek()->toDateString();
            $this->date_to = now()->toDateString();
        }
    }

    public function updatingStatus()
    {
        $this->resetPage();
    }

    public function updatingTransactionType()
    {
        $this->resetPage();
    }

    public function updatingRequesterId()
    {
        $this->resetPage();
    }

    public function updatingSupervisorId()
    {
        $this->resetPage();
    }

    public function render()
    {
        $branchId = auth()->user()->branch_id;

        // Determine date range
        if ($this->weekStart) {
            $weekStart = Carbon::parse($this->weekStart)->startOfWeek();
            $weekEnd = $weekStart->copy()->endOfWeek();
        } else {
            $weekStart = $this->date_from ? Carbon::parse($this->date_from)->startOfDay() : now()->startOfWeek();
            $weekEnd = $this->date_to ? Carbon::parse($this->date_to)->endOfDay() : now()->endOfDay();
        }

        $query = OverrideRequest::query()
            ->forBranch($branchId)
            ->with(['requester', 'supervisor', 'guest', 'fromRoom', 'toRoom', 'checkinDetail'])
            ->whereBetween('created_at', [$weekStart, $weekEnd])
            ->when($this->status, fn($q) => $q->where('status', $this->status))
            ->when($this->transaction_type, fn($q) => $q->where('transaction_type', $this->transaction_type))
            ->when($this->requester_id, fn($q) => $q->where('requester_id', $this->requester_id))
            ->when($this->supervisor_id, fn($q) => $q->where('supervisor_id', $this->supervisor_id))
            ->orderByDesc('created_at');

        $totalCount = $query->count();
        $requests = $query->paginate(50);

        // Get users for filters
        $requesters = User::where('branch_id', $branchId)
            ->whereHas('roles', fn($q) => $q->whereIn('name', ['frontdesk', 'admin']))
            ->orderBy('name')
            ->get(['id', 'name']);

        $supervisors = User::where('branch_id', $branchId)
            ->whereHas('roles', fn($q) => $q->whereIn('name', ['admin', 'back_office', 'superadmin']))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('livewire.back-office.reports.override-request-history', [
            'requests' => $requests,
            'totalCount' => $totalCount,
            'requesters' => $requesters,
            'supervisors' => $supervisors,
        ]);
    }

    public function resetFilters()
    {
        $this->reset(['status', 'transaction_type', 'requester_id', 'supervisor_id']);
        if (!$this->weekStart) {
            $this->date_from = now()->startOfWeek()->toDateString();
            $this->date_to = now()->toDateString();
        }
        $this->resetPage();
    }

    public function getResponseTime($request): string
    {
        if (!$request->responded_at) {
            return '';
        }

        $created = Carbon::parse($request->created_at);
        $responded = Carbon::parse($request->responded_at);
        $diffSeconds = $created->diffInSeconds($responded);

        if ($diffSeconds < 60) {
            return $diffSeconds . ' sec';
        } elseif ($diffSeconds < 3600) {
            $mins = intdiv($diffSeconds, 60);
            return $mins . ' min';
        } else {
            $hours = intdiv($diffSeconds, 3600);
            $mins = intdiv($diffSeconds % 3600, 60);
            return $hours . 'h ' . $mins . 'm';
        }
    }
}
