<?php

namespace App\Http\Livewire\BackOffice\Reports;

use App\Models\OverrideRequest;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Component;

class SupervisorApprovalsReport extends Component
{
    public $weekStart = null;
    public $date_from;
    public $date_to;
    public $supervisor_id = '';

    public function mount()
    {
        if (!$this->weekStart) {
            $this->date_from = now()->startOfWeek()->toDateString();
            $this->date_to = now()->toDateString();
        }
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

        // Get all override requests in the date range
        $requests = OverrideRequest::query()
            ->forBranch($branchId)
            ->whereIn('status', ['approved', 'declined'])
            ->whereBetween('created_at', [$weekStart, $weekEnd])
            ->when($this->supervisor_id, fn($q) => $q->where('supervisor_id', $this->supervisor_id))
            ->with('supervisor')
            ->get();

        // Group by supervisor and calculate statistics
        $supervisorStats = $requests->groupBy('supervisor_id')->map(function ($group) {
            $approved = $group->where('status', 'approved')->count();
            $declined = $group->where('status', 'declined')->count();
            $total = $group->count();
            $approvalRate = $total > 0 ? round(($approved / $total) * 100, 1) : 0;

            // Calculate average response time
            $responseTimes = $group->filter(fn($r) => $r->responded_at)->map(function ($r) {
                return Carbon::parse($r->created_at)->diffInMinutes(Carbon::parse($r->responded_at));
            });
            $avgResponseTime = $responseTimes->count() > 0 ? round($responseTimes->avg(), 1) : null;

            return [
                'supervisor' => $group->first()->supervisor,
                'total' => $total,
                'approved' => $approved,
                'declined' => $declined,
                'approval_rate' => $approvalRate,
                'avg_response_time' => $avgResponseTime,
            ];
        })->sortByDesc('total')->values();

        // Get supervisors for filter
        $supervisors = User::where('branch_id', $branchId)
            ->whereHas('roles', fn($q) => $q->whereIn('name', ['admin', 'back_office', 'superadmin']))
            ->orderBy('name')
            ->get(['id', 'name']);

        // Calculate totals
        $totals = [
            'total' => $supervisorStats->sum('total'),
            'approved' => $supervisorStats->sum('approved'),
            'declined' => $supervisorStats->sum('declined'),
            'approval_rate' => $supervisorStats->sum('total') > 0
                ? round(($supervisorStats->sum('approved') / $supervisorStats->sum('total')) * 100, 1)
                : 0,
        ];

        return view('livewire.back-office.reports.supervisor-approvals-report', [
            'supervisorStats' => $supervisorStats,
            'supervisors' => $supervisors,
            'totals' => $totals,
        ]);
    }

    public function resetFilters()
    {
        $this->reset(['supervisor_id']);
        if (!$this->weekStart) {
            $this->date_from = now()->startOfWeek()->toDateString();
            $this->date_to = now()->toDateString();
        }
    }

    public function formatResponseTime($minutes): string
    {
        if ($minutes === null) {
            return '—';
        }

        if ($minutes < 1) {
            return '< 1 min';
        } elseif ($minutes < 60) {
            return round($minutes) . ' min';
        } else {
            $hours = intdiv((int) $minutes, 60);
            $mins = (int) $minutes % 60;
            return $hours . 'h ' . $mins . 'm';
        }
    }
}
