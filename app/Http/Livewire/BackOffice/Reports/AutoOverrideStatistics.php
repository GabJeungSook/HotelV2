<?php

namespace App\Http\Livewire\BackOffice\Reports;

use App\Models\OverrideRequest;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

class AutoOverrideStatistics extends Component
{
    use WithPagination;

    public $weekStart = null;
    public $date_from;
    public $date_to;
    public $transaction_type = '';
    public $requester_id = '';
    public $view_mode = 'daily'; // daily, by_requester, details

    protected $paginationTheme = 'tailwind';

    public function mount()
    {
        if (!$this->weekStart) {
            $this->date_from = now()->startOfWeek()->toDateString();
            $this->date_to = now()->toDateString();
        }
    }

    public function updatingTransactionType()
    {
        $this->resetPage();
    }

    public function updatingRequesterId()
    {
        $this->resetPage();
    }

    public function updatingViewMode()
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

        $baseQuery = OverrideRequest::query()
            ->forBranch($branchId)
            ->where('status', 'auto_approved')
            ->whereBetween('created_at', [$weekStart, $weekEnd])
            ->when($this->transaction_type, fn($q) => $q->where('transaction_type', $this->transaction_type))
            ->when($this->requester_id, fn($q) => $q->where('requester_id', $this->requester_id));

        // Get users for filter
        $requesters = User::where('branch_id', $branchId)
            ->whereHas('roles', fn($q) => $q->whereIn('name', ['frontdesk', 'admin']))
            ->orderBy('name')
            ->get(['id', 'name']);

        $totalCount = (clone $baseQuery)->count();

        if ($this->view_mode === 'daily') {
            // Daily summary
            $requests = (clone $baseQuery)->get();

            $dailyStats = $requests->groupBy(fn($r) => Carbon::parse($r->created_at)->toDateString())
                ->map(function ($group, $date) {
                    return [
                        'date' => Carbon::parse($date)->format('F d, Y'),
                        'date_raw' => $date,
                        'count' => $group->count(),
                        'transfers' => $group->where('transaction_type', 'transfer')->count(),
                        'cancels' => $group->where('transaction_type', 'cancel')->count(),
                    ];
                })->sortByDesc('date_raw')->values();

            return view('livewire.back-office.reports.auto-override-statistics', [
                'dailyStats' => $dailyStats,
                'requesterStats' => null,
                'details' => null,
                'totalCount' => $totalCount,
                'requesters' => $requesters,
            ]);
        } elseif ($this->view_mode === 'by_requester') {
            // By requester summary
            $requests = (clone $baseQuery)->with('requester')->get();

            $requesterStats = $requests->groupBy('requester_id')->map(function ($group) {
                return [
                    'requester' => $group->first()->requester,
                    'count' => $group->count(),
                    'transfers' => $group->where('transaction_type', 'transfer')->count(),
                    'cancels' => $group->where('transaction_type', 'cancel')->count(),
                ];
            })->sortByDesc('count')->values();

            return view('livewire.back-office.reports.auto-override-statistics', [
                'dailyStats' => null,
                'requesterStats' => $requesterStats,
                'details' => null,
                'totalCount' => $totalCount,
                'requesters' => $requesters,
            ]);
        } else {
            // Detailed list
            $details = $baseQuery
                ->with(['requester', 'guest', 'fromRoom', 'toRoom'])
                ->orderByDesc('created_at')
                ->paginate(50);

            return view('livewire.back-office.reports.auto-override-statistics', [
                'dailyStats' => null,
                'requesterStats' => null,
                'details' => $details,
                'totalCount' => $totalCount,
                'requesters' => $requesters,
            ]);
        }
    }

    public function resetFilters()
    {
        $this->reset(['transaction_type', 'requester_id', 'view_mode']);
        if (!$this->weekStart) {
            $this->date_from = now()->startOfWeek()->toDateString();
            $this->date_to = now()->toDateString();
        }
        $this->resetPage();
    }
}
