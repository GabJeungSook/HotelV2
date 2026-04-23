<?php

namespace App\Http\Livewire\Supervisor;

use App\Models\OverrideRequest;
use App\Models\ActivityLog;
use Livewire\Component;
use WireUi\Traits\Actions;

class Dashboard extends Component
{
    use Actions;

    public $declineModal = false;
    public $declineRequestId = null;
    public $declineReason = '';
    public $search = '';

    protected $listeners = ['refreshDashboard' => '$refresh'];

    public function getOverrideRequestsProperty()
    {
        $query = OverrideRequest::with(['requester', 'guest', 'fromRoom', 'toRoom', 'transferReason'])
            ->forBranch(auth()->user()->branch_id)
            ->pending();

        if ($this->search) {
            $query->where(function ($q) {
                $q->whereHas('guest', function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%');
                })
                ->orWhereHas('fromRoom', function ($q) {
                    $q->where('number', 'like', '%' . $this->search . '%');
                })
                ->orWhereHas('toRoom', function ($q) {
                    $q->where('number', 'like', '%' . $this->search . '%');
                })
                ->orWhereHas('requester', function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%');
                });
            });
        }

        return $query->latest()->get();
    }

    public function getPendingCountProperty()
    {
        return OverrideRequest::forBranch(auth()->user()->branch_id)
            ->pending()
            ->count();
    }

    public function getApprovedCountProperty()
    {
        return OverrideRequest::forBranch(auth()->user()->branch_id)
            ->whereDate('created_at', today())
            ->approved()
            ->count();
    }

    public function getDeclinedCountProperty()
    {
        return OverrideRequest::forBranch(auth()->user()->branch_id)
            ->whereDate('created_at', today())
            ->declined()
            ->count();
    }

    public function approveRequest($requestId)
    {
        $request = OverrideRequest::findOrFail($requestId);

        if (!$request->isPending()) {
            $this->dialog()->error(
                $title = 'Error',
                $description = 'This request has already been processed.'
            );
            return;
        }

        $request->update([
            'status' => 'approved',
            'responded_at' => now(),
        ]);

        ActivityLog::create([
            'branch_id' => auth()->user()->branch_id,
            'user_id' => auth()->user()->id,
            'activity' => 'Override Approved',
            'description' => 'Approved override request for guest ' . $request->guest->name . ' - Transfer from Room #' . $request->fromRoom->number . ' to Room #' . $request->toRoom->number,
        ]);

        $this->dialog()->success(
            $title = 'Success',
            $description = 'Override request has been approved.'
        );

        $this->emit('refreshDashboard');
    }

    public function openDeclineModal($requestId)
    {
        $this->declineRequestId = $requestId;
        $this->declineReason = '';
        $this->declineModal = true;
    }

    public function declineRequest()
    {
        $this->validate([
            'declineReason' => 'required|min:5',
        ], [
            'declineReason.required' => 'Please provide a reason for declining.',
            'declineReason.min' => 'Reason must be at least 5 characters.',
        ]);

        $request = OverrideRequest::findOrFail($this->declineRequestId);

        if (!$request->isPending()) {
            $this->dialog()->error(
                $title = 'Error',
                $description = 'This request has already been processed.'
            );
            $this->declineModal = false;
            return;
        }

        $request->update([
            'status' => 'declined',
            'decline_reason' => $this->declineReason,
            'responded_at' => now(),
        ]);

        ActivityLog::create([
            'branch_id' => auth()->user()->branch_id,
            'user_id' => auth()->user()->id,
            'activity' => 'Override Declined',
            'description' => 'Declined override request for guest ' . $request->guest->name . ' - Reason: ' . $this->declineReason,
        ]);

        $this->declineModal = false;
        $this->declineRequestId = null;
        $this->declineReason = '';

        $this->dialog()->success(
            $title = 'Request Declined',
            $description = 'Override request has been declined.'
        );

        $this->emit('refreshDashboard');
    }

    public function render()
    {
        return view('livewire.supervisor.dashboard')->layout('components.supervisor-layout');
    }
}
