<?php

namespace App\Http\Livewire\Supervisor;

use App\Models\OverrideRequest;
use App\Models\ActivityLog;
use App\Models\Branch;
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

    // Override Summary Modal
    public $summaryModal = false;
    public $summaryDate;
    public $showOverride = true;
    public $showAutoOverride = true;
    public $showDeclined = true;

    // Force Auto-override
    public $forceAutoOverride = false;

    protected $listeners = ['refreshDashboard' => '$refresh'];

    public function mount()
    {
        $this->summaryDate = now()->format('Y-m-d');
        $this->forceAutoOverride = auth()->user()->branch->force_auto_override ?? false;
    }

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
            ->where('supervisor_id', auth()->id())
            ->whereDate('created_at', today())
            ->approved()
            ->count();
    }

    public function getDeclinedCountProperty()
    {
        return OverrideRequest::forBranch(auth()->user()->branch_id)
            ->where('supervisor_id', auth()->id())
            ->whereDate('created_at', today())
            ->declined()
            ->count();
    }

    public function getAutoApprovedCountProperty()
    {
        return OverrideRequest::forBranch(auth()->user()->branch_id)
            ->where('supervisor_id', auth()->id())
            ->whereDate('created_at', today())
            ->where('status', 'auto_approved')
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
            // Mark as auto_approved anyway (transfer failed but approval is complete)
            $request->update(['status' => 'auto_approved']);

            $this->dialog()->error(
                $title = 'Transfer Failed',
                $description = $result['message'] . ' The request has been closed.'
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
            // Mark as auto_approved anyway (cancel failed but approval is complete)
            $request->update(['status' => 'auto_approved']);

            $this->dialog()->error(
                $title = 'Cancel Failed',
                $description = $result['message'] . ' The request has been closed.'
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

    /**
     * Open Override Summary Modal
     */
    public function openSummaryModal()
    {
        $this->summaryDate = now()->format('Y-m-d');
        $this->showOverride = true;
        $this->showAutoOverride = true;
        $this->showDeclined = true;
        $this->summaryModal = true;
    }

    /**
     * Get Override Summary Data - filtered to show only this supervisor's records
     */
    public function getSummaryRequestsProperty()
    {
        $query = OverrideRequest::with(['requester', 'guest', 'fromRoom', 'toRoom', 'transferReason'])
            ->forBranch(auth()->user()->branch_id)
            ->where('supervisor_id', auth()->id())
            ->whereDate('created_at', $this->summaryDate);

        // Filter by status
        $statuses = [];
        if ($this->showOverride) {
            $statuses[] = 'approved';
        }
        if ($this->showAutoOverride) {
            $statuses[] = 'auto_approved';
        }
        if ($this->showDeclined) {
            $statuses[] = 'declined';
        }

        if (!empty($statuses)) {
            $query->whereIn('status', $statuses);
        } else {
            // If no filter selected, show none
            $query->whereRaw('1 = 0');
        }

        return $query->latest()->get();
    }

    /**
     * Toggle Force Auto-override with confirmation
     */
    public function toggleForceAutoOverride()
    {
        $newValue = !$this->forceAutoOverride;

        if ($newValue) {
            // Turning ON - show confirmation
            $this->dialog()->confirm([
                'title' => 'Enable Force Auto-override?',
                'description' => 'When enabled, all override requests will be automatically approved without supervisor approval. Are you sure?',
                'icon' => 'warning',
                'accept' => [
                    'label' => 'Yes, Enable',
                    'method' => 'confirmEnableAutoOverride',
                ],
                'reject' => [
                    'label' => 'Cancel',
                ],
            ]);
        } else {
            // Turning OFF - no confirmation needed
            $this->confirmDisableAutoOverride();
        }
    }

    public function confirmEnableAutoOverride()
    {
        Branch::where('id', auth()->user()->branch_id)->update([
            'force_auto_override' => true,
        ]);
        $this->forceAutoOverride = true;

        $this->dialog()->success(
            $title = 'Enabled',
            $description = 'Force auto-override has been enabled.'
        );
    }

    public function confirmDisableAutoOverride()
    {
        Branch::where('id', auth()->user()->branch_id)->update([
            'force_auto_override' => false,
        ]);
        $this->forceAutoOverride = false;

        $this->dialog()->success(
            $title = 'Disabled',
            $description = 'Force auto-override has been disabled.'
        );
    }

    public function render()
    {
        return view('livewire.supervisor.dashboard')->layout('components.supervisor-layout');
    }
}
