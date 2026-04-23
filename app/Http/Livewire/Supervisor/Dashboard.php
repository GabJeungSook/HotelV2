<?php

namespace App\Http\Livewire\Supervisor;

use App\Models\OverrideRequest;
use App\Models\ActivityLog;
use App\Services\TransferService;
use App\Services\CancelService;
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

    public function confirmApprove($requestId)
    {
        $request = OverrideRequest::find($requestId);
        $isCancel = $request && $request->transaction_type === 'cancel';

        $this->dialog()->confirm([
            'title' => 'Approve Request?',
            'description' => $isCancel
                ? 'Are you sure you want to approve this cancel transaction request? This will permanently delete the guest record.'
                : 'Are you sure you want to approve this transfer request?',
            'icon' => 'question',
            'accept' => [
                'label' => 'Yes, Approve',
                'method' => 'approveRequest',
                'params' => $requestId,
            ],
            'reject' => [
                'label' => 'Cancel',
            ],
        ]);
    }

    public function approveRequest($requestId)
    {
        $request = OverrideRequest::with(['guest', 'fromRoom', 'toRoom', 'transferReason', 'requester'])
            ->findOrFail($requestId);

        if (!$request->isPending()) {
            $this->dialog()->error(
                $title = 'Error',
                $description = 'This request has already been processed.'
            );
            return;
        }

        // Update supervisor info
        $request->update([
            'supervisor_id' => auth()->user()->id,
            'responded_at' => now(),
        ]);

        // Handle based on transaction type
        if ($request->transaction_type === 'cancel') {
            $this->processCancelApproval($request);
        } else {
            $this->processTransferApproval($request);
        }

        $this->emit('refreshDashboard');
    }

    /**
     * Process transfer approval
     */
    private function processTransferApproval(OverrideRequest $request)
    {
        $transferService = new TransferService();
        $result = $transferService->completeTransfer($request, auth()->user()->id);

        if ($result['success']) {
            ActivityLog::create([
                'branch_id' => auth()->user()->branch_id,
                'user_id' => auth()->user()->id,
                'activity' => 'Override Approved & Transfer Completed',
                'description' => 'Approved override request for guest ' . ($request->guest->name ?? 'N/A') .
                    ' - Transfer from Room #' . ($request->fromRoom->number ?? 'N/A') .
                    ' to Room #' . ($request->toRoom->number ?? 'N/A') .
                    ' (Requested by: ' . ($request->requester->name ?? 'N/A') . ')',
            ]);

            $this->dialog()->success(
                $title = 'Success',
                $description = 'Transfer has been approved and completed automatically.'
            );
        } else {
            $request->update(['status' => 'approved']);

            $this->dialog()->warning(
                $title = 'Approved with Warning',
                $description = 'Request approved but transfer could not complete: ' . $result['message'] . '. Frontdesk can complete it manually.'
            );
        }
    }

    /**
     * Process cancel approval
     */
    private function processCancelApproval(OverrideRequest $request)
    {
        $cancelService = new CancelService();
        $result = $cancelService->completeCancel($request, auth()->user()->id);

        if ($result['success']) {
            ActivityLog::create([
                'branch_id' => auth()->user()->branch_id,
                'user_id' => auth()->user()->id,
                'activity' => 'Override Approved & Cancel Completed',
                'description' => 'Approved cancel request for guest ' . ($request->guest->name ?? 'N/A') .
                    ' - Room #' . ($request->fromRoom->number ?? 'N/A') .
                    ' (Requested by: ' . ($request->requester->name ?? 'N/A') . ')',
            ]);

            $this->dialog()->success(
                $title = 'Success',
                $description = 'Transaction has been cancelled successfully.'
            );
        } else {
            $request->update(['status' => 'approved']);

            $this->dialog()->warning(
                $title = 'Approved with Warning',
                $description = 'Request approved but cancel could not complete: ' . $result['message'] . '. Frontdesk can complete it manually.'
            );
        }
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
