<?php

namespace App\Http\Livewire\BackOffice\Reports;

use App\Models\OverrideRequest;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

class DeclineReasonsReport extends Component
{
    use WithPagination;

    public $weekStart = null;
    public $date_from;
    public $date_to;
    public $transaction_type = '';
    public $view_mode = 'summary'; // summary or details

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
            ->declined()
            ->whereBetween('created_at', [$weekStart, $weekEnd])
            ->when($this->transaction_type, fn($q) => $q->where('transaction_type', $this->transaction_type));

        if ($this->view_mode === 'summary') {
            // Group by decline reason
            $requests = (clone $baseQuery)->get();

            $summary = $requests->groupBy('decline_reason')->map(function ($group, $reason) {
                $transfers = $group->where('transaction_type', 'transfer')->count();
                $cancels = $group->where('transaction_type', 'cancel')->count();

                return [
                    'reason' => $reason ?: 'No reason provided',
                    'count' => $group->count(),
                    'transfers' => $transfers,
                    'cancels' => $cancels,
                ];
            })->sortByDesc('count')->values();

            $totalCount = $requests->count();

            return view('livewire.back-office.reports.decline-reasons-report', [
                'summary' => $summary,
                'details' => null,
                'totalCount' => $totalCount,
            ]);
        } else {
            // Detailed list
            $details = $baseQuery
                ->with(['requester', 'supervisor', 'guest', 'fromRoom', 'toRoom'])
                ->orderByDesc('created_at')
                ->paginate(50);

            $totalCount = $baseQuery->count();

            return view('livewire.back-office.reports.decline-reasons-report', [
                'summary' => null,
                'details' => $details,
                'totalCount' => $totalCount,
            ]);
        }
    }

    public function resetFilters()
    {
        $this->reset(['transaction_type', 'view_mode']);
        if (!$this->weekStart) {
            $this->date_from = now()->startOfWeek()->toDateString();
            $this->date_to = now()->toDateString();
        }
        $this->resetPage();
    }
}
