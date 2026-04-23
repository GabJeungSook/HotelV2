<?php

namespace App\Http\Livewire\Supervisor;

use App\Models\OverrideRequest;
use Livewire\Component;
use Livewire\WithPagination;

class Archives extends Component
{
    use WithPagination;

    public $search = '';
    public $statusFilter = '';
    public $dateFrom = '';
    public $dateTo = '';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = OverrideRequest::with(['requester', 'supervisor', 'guest', 'fromRoom', 'toRoom', 'transferReason'])
            ->forBranch(auth()->user()->branch_id)
            ->whereIn('status', ['approved', 'declined', 'auto_approved']);

        if ($this->search) {
            $query->whereHas('guest', function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->dateFrom) {
            $query->whereDate('created_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->whereDate('created_at', '<=', $this->dateTo);
        }

        $overrideRequests = $query->latest()->paginate(15);

        return view('livewire.supervisor.archives', [
            'overrideRequests' => $overrideRequests,
        ])->layout('components.supervisor-layout');
    }
}
