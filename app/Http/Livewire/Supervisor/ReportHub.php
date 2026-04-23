<?php

namespace App\Http\Livewire\Supervisor;

use App\Models\OverrideRequest;
use App\Models\Transaction;
use Livewire\Component;

class ReportHub extends Component
{
    public $dateFrom;
    public $dateTo;

    public function mount()
    {
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    public function getTotalOverridesProperty()
    {
        return OverrideRequest::forBranch(auth()->user()->branch_id)
            ->whereBetween('created_at', [$this->dateFrom, $this->dateTo . ' 23:59:59'])
            ->count();
    }

    public function getApprovedOverridesProperty()
    {
        return OverrideRequest::forBranch(auth()->user()->branch_id)
            ->whereBetween('created_at', [$this->dateFrom, $this->dateTo . ' 23:59:59'])
            ->where('status', 'approved')
            ->count();
    }

    public function getDeclinedOverridesProperty()
    {
        return OverrideRequest::forBranch(auth()->user()->branch_id)
            ->whereBetween('created_at', [$this->dateFrom, $this->dateTo . ' 23:59:59'])
            ->where('status', 'declined')
            ->count();
    }

    public function getAutoApprovedOverridesProperty()
    {
        return OverrideRequest::forBranch(auth()->user()->branch_id)
            ->whereBetween('created_at', [$this->dateFrom, $this->dateTo . ' 23:59:59'])
            ->where('status', 'auto_approved')
            ->count();
    }

    public function getTransferTransactionsProperty()
    {
        return Transaction::where('branch_id', auth()->user()->branch_id)
            ->where('transaction_type_id', 7)
            ->whereBetween('created_at', [$this->dateFrom, $this->dateTo . ' 23:59:59'])
            ->count();
    }

    public function getOverrideTransfersProperty()
    {
        return Transaction::where('branch_id', auth()->user()->branch_id)
            ->where('transaction_type_id', 7)
            ->where('is_override', true)
            ->whereBetween('created_at', [$this->dateFrom, $this->dateTo . ' 23:59:59'])
            ->count();
    }

    public function render()
    {
        return view('livewire.supervisor.report-hub')->layout('components.supervisor-layout');
    }
}
