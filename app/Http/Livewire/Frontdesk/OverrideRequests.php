<?php

namespace App\Http\Livewire\Frontdesk;

use App\Models\OverrideRequest;
use Livewire\Component;
use WireUi\Traits\Actions;

class OverrideRequests extends Component
{
    use Actions;

    public $activeTab = 'pending';

    protected $listeners = ['refreshRequests' => '$refresh'];

    protected $queryString = ['activeTab'];

    public function getPendingRequestsProperty()
    {
        return OverrideRequest::with(['guest', 'fromRoom', 'toRoom', 'transferReason', 'supervisor'])
            ->where('requester_id', auth()->user()->id)
            ->where('status', 'pending')
            ->latest()
            ->get();
    }

    public function getDeclinedRequestsProperty()
    {
        // Show all declined requests for historical reference (last 30 days)
        return OverrideRequest::with(['guest', 'fromRoom', 'toRoom', 'transferReason', 'supervisor'])
            ->where('requester_id', auth()->user()->id)
            ->where('status', 'declined')
            ->where('created_at', '>=', now()->subDays(30))
            ->latest()
            ->get();
    }

    public function getApprovedRequestsProperty()
    {
        // Show all approved requests for historical reference (last 30 days)
        return OverrideRequest::with(['guest', 'fromRoom', 'toRoom', 'transferReason', 'supervisor'])
            ->where('requester_id', auth()->user()->id)
            ->whereIn('status', ['approved', 'auto_approved'])
            ->where('created_at', '>=', now()->subDays(30))
            ->latest()
            ->get();
    }

    // Check if guest has an active request (pending or auto_approved)
    public function guestHasActiveRequest($guestId)
    {
        return OverrideRequest::where('requester_id', auth()->user()->id)
            ->where('guest_id', $guestId)
            ->whereIn('status', ['pending', 'auto_approved'])
            ->exists();
    }

    public function confirmCancelRequest($requestId)
    {
        $this->dialog()->confirm([
            'title' => 'Cancel Request?',
            'description' => 'Are you sure you want to cancel this override request?',
            'icon' => 'question',
            'accept' => [
                'label' => 'Yes, Cancel',
                'method' => 'cancelRequest',
                'params' => $requestId,
            ],
            'reject' => [
                'label' => 'No',
            ],
        ]);
    }

    public function cancelRequest($requestId)
    {
        $request = OverrideRequest::find($requestId);

        if ($request && $request->status === 'pending' && $request->requester_id === auth()->user()->id) {
            $request->delete();
            $this->dialog()->success(
                $title = 'Cancelled',
                $description = 'Override request has been cancelled.'
            );
            $this->emit('refreshRequests');
        }
    }

    public function retryRequest($requestId)
    {
        $request = OverrideRequest::find($requestId);

        if ($request && $request->status === 'declined' && $request->requester_id === auth()->user()->id) {
            $guestId = $request->guest_id;

            // Don't delete - keep the declined record for historical reference
            // Just redirect to transfer room page to create a NEW request
            return redirect()->route('frontdesk.transfer-room', ['record' => $guestId]);
        }
    }

    public function render()
    {
        return view('livewire.frontdesk.override-requests');
    }
}
