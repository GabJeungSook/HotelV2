<?php

namespace App\Http\Livewire\Frontdesk;

use App\Models\ActivityLog;
use App\Models\CashOnDrawer;
use App\Models\CheckinDetail;
use App\Models\Guest;
use App\Models\OverrideRequest;
use App\Models\Rate;
use App\Models\Room;
use App\Models\Transaction;
use App\Models\TransferedGuestReport;
use App\Models\Type;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use WireUi\Traits\Actions;
use App\Services\CancelService;

class OverrideRequests extends Component
{
    use Actions;

    public $confirmModal = false;
    public $confirmCancelModal = false;
    public $selectedRequest = null;

    protected $listeners = ['refreshRequests' => '$refresh'];

    public function getPendingRequestsProperty()
    {
        return OverrideRequest::with(['guest', 'fromRoom', 'toRoom', 'transferReason', 'supervisor'])
            ->where('requester_id', auth()->user()->id)
            ->where('status', 'pending')
            ->latest()
            ->get();
    }

    public function getApprovedRequestsProperty()
    {
        return OverrideRequest::with(['guest', 'fromRoom', 'toRoom', 'transferReason', 'supervisor'])
            ->where('requester_id', auth()->user()->id)
            ->where('status', 'approved')
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

    // Check if guest has an active request (pending, approved, or auto_approved)
    public function guestHasActiveRequest($guestId)
    {
        return OverrideRequest::where('requester_id', auth()->user()->id)
            ->where('guest_id', $guestId)
            ->whereIn('status', ['pending', 'approved', 'auto_approved'])
            ->exists();
    }

    public function getCompletedRequestsProperty()
    {
        return OverrideRequest::with(['guest', 'fromRoom', 'toRoom', 'transferReason', 'supervisor'])
            ->where('requester_id', auth()->user()->id)
            ->whereIn('status', ['approved', 'auto_approved'])
            ->whereHas('transactions')
            ->whereDate('created_at', today())
            ->latest()
            ->get();
    }

    public function openConfirmModal($requestId)
    {
        $this->selectedRequest = OverrideRequest::with(['guest', 'fromRoom', 'toRoom', 'transferReason'])
            ->find($requestId);

        if (!$this->selectedRequest || $this->selectedRequest->status !== 'approved') {
            $this->dialog()->error(
                $title = 'Error',
                $description = 'This request is not approved or no longer exists.'
            );
            return;
        }

        $this->confirmModal = true;
    }

    public function openConfirmCancelModal($requestId)
    {
        $this->selectedRequest = OverrideRequest::with(['guest', 'fromRoom'])
            ->find($requestId);

        if (!$this->selectedRequest || $this->selectedRequest->status !== 'approved') {
            $this->dialog()->error(
                $title = 'Error',
                $description = 'This request is not approved or no longer exists.'
            );
            return;
        }

        $this->confirmCancelModal = true;
    }

    public function completeCancel()
    {
        if (!$this->selectedRequest) {
            return;
        }

        $cancelService = new CancelService();
        $result = $cancelService->completeCancel($this->selectedRequest, auth()->user()->id);

        if ($result['success']) {
            $this->confirmCancelModal = false;
            $this->selectedRequest = null;

            $this->dialog()->success(
                $title = 'Success',
                $description = 'Transaction has been cancelled successfully.'
            );

            $this->emit('refreshRequests');
        } else {
            $this->dialog()->error(
                $title = 'Error',
                $description = $result['message']
            );
        }
    }

    public function completeTransfer()
    {
        if (!$this->selectedRequest) {
            return;
        }

        $request = $this->selectedRequest;
        $requestData = $request->request_data ?? [];

        // Get the guest and check-in detail
        $guest = Guest::find($request->guest_id);
        $check_in_detail = CheckinDetail::where('guest_id', $guest->id)->first();

        if (!$guest || !$check_in_detail) {
            $this->dialog()->error(
                $title = 'Error',
                $description = 'Guest or check-in details not found.'
            );
            return;
        }

        // Check if guest is still in the original room
        if ($guest->room_id != $request->from_room_id) {
            $this->dialog()->error(
                $title = 'Error',
                $description = 'Guest has already been moved from the original room.'
            );
            $request->delete();
            $this->confirmModal = false;
            return;
        }

        // Check if target room is still available
        $toRoom = Room::find($request->to_room_id);
        if (!$toRoom || $toRoom->status !== 'Available') {
            $this->dialog()->error(
                $title = 'Error',
                $description = 'Target room is no longer available. Please create a new transfer request.'
            );
            $request->delete();
            $this->confirmModal = false;
            return;
        }

        DB::beginTransaction();
        try {
            $assigned_frontdesk = auth()->user()->assigned_frontdesks;
            $users = User::role('frontdesk')->get();
            $threshold = now()->subMinutes(5)->timestamp;
            $onlineUsers = [];

            foreach ($users as $user) {
                if ($this->isUserOnline($user, $threshold)) {
                    $onlineUsers[] = $user->shiftLogs()->whereNull('time_out')->latest()->first();
                }
            }

            $shiftLogId = collect($onlineUsers)->where('frontdesk_id', auth()->user()->id)->first()->id ?? null;

            // Get rate for new room
            $hours = $check_in_detail->hours_stayed;
            $new_room_rate_obj = Rate::where('branch_id', auth()->user()->branch_id)
                ->where('type_id', $request->to_type_id)
                ->where('is_available', true)
                ->whereHas('stayingHour', function ($query) use ($hours) {
                    $query->where('branch_id', auth()->user()->branch_id)->where('number', '=', $hours);
                })
                ->first();

            $new_room_rate = $guest->is_long_stay
                ? ($new_room_rate_obj ? $new_room_rate_obj->amount * $guest->number_of_days : 0)
                : ($new_room_rate_obj ? $new_room_rate_obj->amount : 0);

            $current_room_rate = $request->current_amount;
            $excess_amount = max(0, $current_room_rate - $new_room_rate);
            $selected_status = $requestData['selected_status'] ?? 'Uncleaned';
            $save_excess = $requestData['save_excess'] ?? false;

            // Create transfer transaction
            $transaction = Transaction::create([
                'branch_id' => auth()->user()->branch_id,
                'shift_log_id' => $shiftLogId,
                'checkin_detail_id' => $check_in_detail->id,
                'cash_drawer_id' => auth()->user()->cash_drawer_id,
                'room_id' => $request->to_room_id,
                'guest_id' => $guest->id,
                'floor_id' => $toRoom->floor_id,
                'transaction_type_id' => 7,
                'assigned_frontdesk_id' => json_encode($assigned_frontdesk),
                'description' => 'Room Transfer',
                'payable_amount' => 0,
                'paid_amount' => 0,
                'change_amount' => 0,
                'deposit_amount' => 0,
                'paid_at' => now()->toDateString(),
                'override_at' => null,
                'remarks' => 'Guest Transfered from Room #' . $request->fromRoom->number .
                    ' (' . Type::find($request->from_type_id)->name . ') to Room #' . $toRoom->number .
                    ' (' . Type::find($request->to_type_id)->name . ') - Reason: ' . $request->transferReason->reason,
                'transfer_reason_id' => $request->transfer_reason_id,
                'shift' => (now()->hour >= 8 && now()->hour < 20) ? 'AM' : 'PM',
                'is_override' => true,
                'override_request_id' => $request->id,
                'approved_by_user_id' => $request->supervisor_id,
            ]);

            // Handle excess amount deposit
            if ($save_excess && $excess_amount > 0) {
                Transaction::create([
                    'branch_id' => auth()->user()->branch_id,
                    'shift_log_id' => $shiftLogId,
                    'checkin_detail_id' => $check_in_detail->id,
                    'cash_drawer_id' => auth()->user()->cash_drawer_id,
                    'room_id' => $guest->room_id,
                    'guest_id' => $guest->id,
                    'floor_id' => Room::find($guest->room_id)->floor->id,
                    'transaction_type_id' => 2,
                    'assigned_frontdesk_id' => json_encode($assigned_frontdesk),
                    'description' => 'Deposit',
                    'payable_amount' => $excess_amount,
                    'paid_amount' => $excess_amount,
                    'change_amount' => 0,
                    'deposit_amount' => $excess_amount,
                    'paid_at' => Carbon::now()->toDateTimeString(),
                    'override_at' => null,
                    'remarks' => 'Deposit From Transfer Room (Excess Amount)',
                    'shift' => (now()->hour >= 8 && now()->hour < 20) ? 'AM' : 'PM',
                    'is_override' => false,
                ]);

                CashOnDrawer::create([
                    'branch_id' => auth()->user()->branch_id,
                    'frontdesk_id' => auth()->user()->frontdesk->id,
                    'cash_drawer_id' => auth()->user()->cash_drawer_id,
                    'amount' => $excess_amount,
                    'transaction_date' => now()->toDateString(),
                    'transaction_type' => 'deposit',
                    'shift' => (now()->hour >= 8 && now()->hour < 20) ? 'AM' : 'PM',
                ]);

                CheckinDetail::where('guest_id', $guest->id)->update([
                    'total_deposit' => $guest->total_deposit + $excess_amount,
                ]);
            }

            // Update room statuses
            if ($selected_status === "Uncleaned") {
                Room::where('id', $check_in_detail->room_id)->update([
                    'time_to_clean' => now()->addHours(4),
                ]);
            }

            Room::where('id', $check_in_detail->room_id)->update([
                'status' => $selected_status,
            ]);

            Room::where('id', $request->to_room_id)->update([
                'status' => 'Occupied',
            ]);

            $initial_deposit = auth()->user()->branch->initial_deposit;

            // Update guest
            Guest::where('id', $guest->id)->update([
                'previous_room_id' => $check_in_detail->room_id,
                'room_id' => $request->to_room_id,
                'type_id' => $request->to_type_id,
                'rate_id' => $new_room_rate_obj ? $new_room_rate_obj->id : $guest->rate_id,
                'static_amount' => ($new_room_rate + $initial_deposit),
            ]);

            // Update check-in transaction
            $checkInTransaction = Transaction::where('checkin_detail_id', $check_in_detail->id)
                ->where('description', 'Guest Check In')
                ->first();

            if ($checkInTransaction) {
                $checkInTransaction->update([
                    'remarks' => 'Guest Checked In at room #' . $toRoom->number,
                    'payable_amount' => $new_room_rate,
                    'paid_amount' => $new_room_rate,
                    'change_amount' => 0,
                    'paid_at' => Carbon::now()->toDateTimeString(),
                    'room_id' => $request->to_room_id,
                    'floor_id' => $toRoom->floor_id,
                ]);
            }

            // Create transfer report
            TransferedGuestReport::create([
                'checkin_detail_id' => $check_in_detail->id,
                'previous_room_id' => $check_in_detail->room_id,
                'new_room_id' => $toRoom->id,
                'rate_id' => $guest->rate_id,
                'previous_amount' => $check_in_detail->static_room_amount,
                'new_amount' => $new_room_rate,
                'original_check_in_time' => $checkInTransaction ? $checkInTransaction->created_at : now(),
            ]);

            // Update check-in detail
            CheckinDetail::where('guest_id', $guest->id)->update([
                'type_id' => $request->to_type_id,
                'room_id' => $request->to_room_id,
                'rate_id' => $new_room_rate_obj ? $new_room_rate_obj->id : $guest->rate_id,
                'static_room_amount' => $new_room_rate,
                'static_amount' => ($new_room_rate + $initial_deposit),
            ]);

            // Log activity
            ActivityLog::create([
                'branch_id' => auth()->user()->branch_id,
                'user_id' => auth()->user()->id,
                'activity' => 'Room Transfer (Override)',
                'description' => 'Guest ' . $guest->name . ' transferred from Room #' . $request->fromRoom->number . ' to Room #' . $toRoom->number . ' (Approved by: ' . ($request->supervisor->name ?? 'Auto') . ')',
            ]);

            // Mark override request as completed by updating status
            $request->update(['status' => 'auto_approved']); // Using auto_approved to indicate completed

            DB::commit();

            $this->confirmModal = false;
            $this->selectedRequest = null;

            $this->dialog()->success(
                $title = 'Success',
                $description = 'Guest has been transferred successfully.'
            );

            $this->emit('refreshRequests');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->dialog()->error(
                $title = 'Error',
                $description = 'Failed to complete transfer: ' . $e->getMessage()
            );
        }
    }

    public function confirmCancelRequest($requestId)
    {
        $this->dialog()->confirm([
            'title' => 'Cancel Request?',
            'description' => 'Are you sure you want to cancel this transfer request?',
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
                $description = 'Transfer request has been cancelled. You can request the transfer again from Room Monitoring.'
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

    private function isUserOnline($user, $threshold)
    {
        return $user->sessions()->where('last_activity', '>=', $threshold)->exists();
    }

    public function render()
    {
        return view('livewire.frontdesk.override-requests');
    }
}
