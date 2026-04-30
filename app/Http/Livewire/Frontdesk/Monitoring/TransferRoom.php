<?php

namespace App\Http\Livewire\Frontdesk\Monitoring;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\User;
use App\Models\CashOnDrawer;
use App\Models\CheckinDetail;
use App\Models\Floor;
use App\Models\Guest;
use App\Models\OverrideRequest;
use App\Models\Rate;
use App\Models\Room;
use App\Models\StayingHour;
use App\Models\TemporaryCheckInKiosk;
use App\Models\Transaction;
use App\Models\TransferedGuestReport;
use App\Models\TransferReason;
use App\Models\Type;
use App\Services\KioskBatchService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use WireUi\Traits\Actions;

class TransferRoom extends Component
{
    use Actions;
    public $guest;
    public $room;
    public $new_room;
    public $excess_amount;
    public $payable_amount;

    public $room_count;
    public $has_rate = false;
    public $assigned_frontdesk = [];

    //selections
    public $types;
    public $selected_type_id;
    public $floors;
    public $selected_floor_id;
    public $rooms;
    public $selected_room_id;
    public $selected_status;
    public $reasons;
    public $selected_reason;
    public $enabled = false;
    public $save_pay_modal = false;
    public $save_excess = false;
    public $is_override = false;
    public $authorization_modal = false;
    public $test_modal = false;
    public $code;
    public $current_room_rate;
    public $new_room_rate;

    // New properties for supervisor override system
    public $override_request_modal = false;
    public $selected_supervisor_id = null;
    public $supervisors = [];
    public $request_submitted_modal = false;
    public $current_override_request_id = null;

    public function mount($record)
    {
        $this->assigned_frontdesk = auth()->user()->assigned_frontdesks;
        $this->guest =  $this->guest = Guest::where('branch_id', auth()->user()->branch_id)
                ->where('id', $record)
                ->first();

        $is_long_stay = $this->guest->is_long_stay;
        $days_stayed = $this->guest->number_of_days;
        $amount = $this->guest->checkInDetail->rate->amount;
        $this->current_room_rate = $is_long_stay ? $amount * $days_stayed : $amount;


        $this->room = Room::where('branch_id', auth()->user()->branch_id)
                ->where('id', $this->guest->checkInDetail->room_id)
                ->first();
        $this->types = Type::where(
            'branch_id',
            auth()->user()->branch_id
        )->get();
        $this->floors = Floor::where(
            'branch_id',
            auth()->user()->branch_id
        )->get();

        $this->reasons = TransferReason::where('branch_id', auth()->user()->branch_id)
            ->get();

        // Load supervisors for the branch
        $this->loadSupervisors();
    }

    /**
     * Load supervisors for the current branch
     */
    private function loadSupervisors()
    {
        $this->supervisors = User::role('supervisor')
            ->where('branch_id', auth()->user()->branch_id)
            ->get();
    }

    /**
     * Check if supervisor override system should be used
     */
    private function useSupervisorOverride(): bool
    {
        return User::role('supervisor')
            ->where('branch_id', auth()->user()->branch_id)
            ->exists();
    }

    /**
     * Check if force auto-override is enabled for the branch
     */
    private function isForceAutoOverrideEnabled(): bool
    {
        $branch = Branch::find(auth()->user()->branch_id);
        return $branch->auto_approve_enabled ?? false;
    }

     public function updatedSelectedTypeId()
    {
        $this->selected_floor_id = null;
        $this->selected_room_id = null;
        $this->enabled = false;
        $this->selected_status = null;
        $this->selected_reason = null;

        $kiosk_reservation = TemporaryCheckInKiosk::where('branch_id', auth()->user()->branch_id)
            ->pluck('room_id')->toArray();


        $this->rooms = Room::where('branch_id', auth()->user()->branch_id)
            ->where('type_id', $this->selected_type_id)
            ->where('floor_id', $this->selected_floor_id)
            ->whereNotIn('id', $kiosk_reservation)
            ->where('status', 'Available')
            ->get();
        $this->room_count = Room::where('branch_id', auth()->user()->branch_id)
                  ->where('status', 'Available')
                  ->where('type_id', $this->selected_type_id)
                  ->count();
        // Long-stay guests have hours_stayed = 24 × number_of_days (e.g. 48, 72),
        // which never matches a staying_hours row (only 6, 12, 18, 24 exist).
        // The lookup must use the 24-hour rate of the destination type and
        // multiply by number_of_days, mirroring the kiosk's long-stay flow.
        if ($this->guest->is_long_stay) {
            $longStayingHour = StayingHour::where('branch_id', auth()->user()->branch_id)
                ->where('number', 24)
                ->first();

            $this->new_room = $longStayingHour
                ? Rate::where('branch_id', auth()->user()->branch_id)
                    ->where('type_id', $this->selected_type_id)
                    ->where('is_available', true)
                    ->where('staying_hour_id', $longStayingHour->id)
                    ->first()
                : null;

            $this->new_room_rate = $this->new_room
                ? $this->new_room->amount * $this->guest->number_of_days
                : 0;
        } else {
            $hours = $this->guest->checkInDetail->hours_stayed;
            $this->new_room = Rate::where('branch_id', auth()->user()->branch_id)
                ->where('type_id', $this->selected_type_id)
                ->where('is_available', true)
                ->whereHas('stayingHour', function ($query) use ($hours) {
                    $query->where('branch_id', auth()->user()->branch_id)
                        ->where('number', '=', $hours);
                })
                ->first();

            $this->new_room_rate = $this->new_room ? $this->new_room->amount : 0;
        }

        $this->excess_amount =  ($this->new_room && isset($this->new_room_rate)) ? max(0, $this->current_room_rate - $this->new_room_rate) : 0;
        $this->payable_amount = ($this->new_room && isset($this->new_room_rate)) ? max(0, $this->new_room_rate - $this->current_room_rate) : 0;
    }

    public function updatedSelectedFloorId()
    {
         $kiosk_reservation = TemporaryCheckInKiosk::where('branch_id', auth()->user()->branch_id)
            ->pluck('room_id')->toArray();
        $this->rooms = Room::where('branch_id', auth()->user()->branch_id)
            ->where('type_id', $this->selected_type_id)
            ->where('floor_id', $this->selected_floor_id)
            ->whereNotIn('id', $kiosk_reservation)
            ->where('status', 'Available')
            ->get();

        if(!$this->rooms->isEmpty()) {
            $this->enabled = true;
        } else {
            $this->enabled = false;
        }
    }

    public function confirmTransfer()
    {
         $this->validate([
            'selected_type_id' => 'required',
            'selected_floor_id' => 'required',
            'selected_room_id' => 'required',
            'selected_status' => 'required',
            'selected_reason' => 'required',
        ], [
            'selected_type_id.required' => 'Please select a room type.',
            'selected_floor_id.required' => 'Please select a floor.',
            'selected_room_id.required' => 'Please select a room.',
            'selected_status.required' => 'Please select a status.',
            'selected_reason.required' => 'Please select a reason for transfer.',
        ]);

        if($this->is_override)
        {
            if(auth()->user()->branch->autorization_code == $this->code)
            {
                $this->authorization_modal = false;
                        if($this->excess_amount > 0) {
             $this->save_pay_modal = true;
            }else{
                $this->dialog()->confirm([
                'title' => 'Are you Sure?',
                'description' => 'transfer guest to new room?',
                'icon' => 'question',
                'accept' => [
                    'label' => 'Confirm Transfer',
                    'method' => 'saveTransfer',
                    'params' => $this->is_override,
                ],
                'reject' => [
                    'label' => 'Cancel',
                ],
            ]);
            }
            }else{
                $this->authorization_modal = true;
                $this->code = null;
                $this->dialog()->error(
                    $title = 'Oops',
                    $description = 'Wrong authorization code.'
                );
            }
        }else{
        if($this->excess_amount > 0) {
             $this->save_pay_modal = true;
        }else{
            $this->dialog()->confirm([
            'title' => 'Are you Sure?',
            'description' => 'transfer guest to new room?',
            'icon' => 'question',
            'accept' => [
                'label' => 'Confirm Transfer',
                'method' => 'saveTransfer',
                'params' => $this->is_override,
            ],
            'reject' => [
                'label' => 'Cancel',
            ],
        ]);
        }
        }
    }

    public function overrideTransfer()
    {
         $this->validate([
            'selected_type_id' => 'required',
            'selected_floor_id' => 'required',
            'selected_room_id' => 'required',
            'selected_status' => 'required',
            'selected_reason' => 'required',
        ], [
            'selected_type_id.required' => 'Please select a room type.',
            'selected_floor_id.required' => 'Please select a floor.',
            'selected_room_id.required' => 'Please select a room.',
            'selected_status.required' => 'Please select a status.',
            'selected_reason.required' => 'Please select a reason for transfer.',
        ]);

        // SUPERVISOR MODULE ON HOLD — falling through to legacy authorization flow.
        // To re-enable, uncomment the block below and remove the two lines after it.
        // ----------------------------------------------------------------------
        // if ($this->useSupervisorOverride()) {
        //     if ($this->isForceAutoOverrideEnabled()) {
        //         $this->dialog()->confirm([
        //             'title' => 'Confirm Auto Override',
        //             'description' => 'Force auto-override is enabled. Are you sure you want to proceed with this transfer?',
        //             'icon' => 'question',
        //             'accept' => [
        //                 'label' => 'Yes, Proceed',
        //                 'method' => 'confirmAutoOverrideTransfer',
        //             ],
        //             'reject' => [
        //                 'label' => 'Cancel',
        //             ],
        //         ]);
        //     } else {
        //         $this->override_request_modal = true;
        //     }
        // } else {
            $this->authorization_modal = true;
            $this->is_override = true;
        // }
    }

    /**
     * Confirm and execute auto-override transfer
     */
    public function confirmAutoOverrideTransfer()
    {
        $this->createAutoApprovedOverrideRequest();
        $this->is_override = true;
        $this->proceedWithTransfer();
    }

    /**
     * Create an auto-approved override request (when auto_approve_enabled is enabled)
     */
    private function createAutoApprovedOverrideRequest()
    {
        $check_in_detail = CheckinDetail::where('guest_id', $this->guest->id)->first();
        $branch = auth()->user()->branch;

        OverrideRequest::create([
            'branch_id' => auth()->user()->branch_id,
            'requester_id' => auth()->user()->id,
            'supervisor_id' => $branch->auto_approve_enabled_by, // Supervisor who enabled auto-approve
            'guest_id' => $this->guest->id,
            'checkin_detail_id' => $check_in_detail->id,
            'transfer_reason_id' => $this->selected_reason,
            'transaction_type' => 'transfer',
            'from_room_id' => $this->room->id,
            'to_room_id' => $this->selected_room_id,
            'from_type_id' => $this->room->type_id,
            'to_type_id' => $this->selected_type_id,
            'current_amount' => $this->current_room_rate,
            'new_amount' => $this->new_room_rate ?? 0,
            'status' => 'auto_approved',
            'responded_at' => now(),
            'request_data' => [
                'selected_status' => $this->selected_status,
                'excess_amount' => $this->excess_amount,
                'payable_amount' => $this->payable_amount,
                'save_excess' => $this->save_excess,
            ],
        ]);
    }

    /**
     * Submit override request to supervisor
     */
    public function submitOverrideRequest()
    {
        $this->validate([
            'selected_supervisor_id' => 'required',
        ], [
            'selected_supervisor_id.required' => 'Please select a supervisor.',
        ]);

        $check_in_detail = CheckinDetail::where('guest_id', $this->guest->id)->first();

        $overrideRequest = OverrideRequest::create([
            'branch_id' => auth()->user()->branch_id,
            'requester_id' => auth()->user()->id,
            'supervisor_id' => $this->selected_supervisor_id,
            'guest_id' => $this->guest->id,
            'checkin_detail_id' => $check_in_detail->id,
            'transfer_reason_id' => $this->selected_reason,
            'transaction_type' => 'transfer',
            'from_room_id' => $this->room->id,
            'to_room_id' => $this->selected_room_id,
            'from_type_id' => $this->room->type_id,
            'to_type_id' => $this->selected_type_id,
            'current_amount' => $this->current_room_rate,
            'new_amount' => $this->new_room_rate ?? 0,
            'status' => 'pending',
            'request_data' => [
                'selected_status' => $this->selected_status,
                'excess_amount' => $this->excess_amount,
                'payable_amount' => $this->payable_amount,
                'save_excess' => $this->save_excess,
            ],
        ]);

        $this->override_request_modal = false;
        $this->request_submitted_modal = true;
    }

    /**
     * Proceed with transfer after approval (for auto-approve only)
     */
    private function proceedWithTransfer()
    {
        if ($this->excess_amount > 0) {
            $this->save_pay_modal = true;
        } else {
            $this->dialog()->confirm([
                'title' => 'Are you Sure?',
                'description' => 'Transfer guest to new room?',
                'icon' => 'question',
                'accept' => [
                    'label' => 'Confirm Transfer',
                    'method' => 'saveTransfer',
                    'params' => true,
                ],
                'reject' => [
                    'label' => 'Cancel',
                ],
            ]);
        }
    }

    public function saveTransfer()
    {

        $check_in_detail = CheckinDetail::where(
            'guest_id',
            $this->guest->id
        )->first();
        $reason = TransferReason::find($this->selected_reason);
        DB::beginTransaction();
        $users = User::role('frontdesk')->get();

            $threshold = now()->subMinutes(5)->timestamp;

            $onlineUsers = [];

            foreach ($users as $user) {
                if ($this->isUserOnline($user, $threshold)) {
                    $onlineUsers[] = $user->shiftLogs()->whereNull('time_out')->latest()->first();
                }
            }

            $shiftLogId = collect($onlineUsers)->where('frontdesk_id', auth()->user()->id)->first()->id ?? null;

        // Get override request info if exists
        $overrideRequestId = $this->current_override_request_id;
        $approvedByUserId = null;

        if ($overrideRequestId) {
            $overrideRequest = OverrideRequest::find($overrideRequestId);
            if ($overrideRequest && $overrideRequest->supervisor_id) {
                $approvedByUserId = $overrideRequest->supervisor_id;
            }
        }

        $transaction = Transaction::create([
            'branch_id' => auth()->user()->branch_id,
            'shift_log_id' => $shiftLogId,
            'checkin_detail_id' => $check_in_detail->id,
            'cash_drawer_id' => auth()->user()->cash_drawer_id,
            'room_id' => $this->selected_room_id,
            'guest_id' => $this->guest->id,
            'floor_id' => $this->selected_floor_id,
            'transaction_type_id' => 7,
            'assigned_frontdesk_id' => json_encode($this->assigned_frontdesk),
            'description' => 'Room Transfer',
            // 'payable_amount' => $this->payable_amount,
            'payable_amount' => 0,
            'paid_amount' => 0,
            'change_amount' => 0,
            'deposit_amount' => 0,
            'paid_at' => now()->toDateString(),
            'override_at' => null,
            'remarks' =>
                'Guest Transfered from Room #' .
                Room::where('id', $check_in_detail->room_id)->first()->number .
                ' (' .
                Type::where('id', $check_in_detail->type_id)->first()->name .
                ') to Room #' .
                Room::where('id', $this->selected_room_id)->first()->number .
                ' (' .
                Type::where('id', $this->selected_type_id)->first()->name .
                ') - Reason: ' .
                $reason->reason,
            'transfer_reason_id' => $this->selected_reason,
            'shift' => (now()->hour >= 8 && now()->hour < 20) ? 'AM' : 'PM',
            'is_override' => $this->is_override,
            'override_request_id' => $overrideRequestId,
            'approved_by_user_id' => $approvedByUserId,
        ]);

        if($this->save_excess)
        {
            $users = User::role('frontdesk')->get();

            $threshold = now()->subMinutes(5)->timestamp;

            $onlineUsers = [];

            foreach ($users as $user) {
                if ($this->isUserOnline($user, $threshold)) {
                    $onlineUsers[] = $user->shiftLogs()->whereNull('time_out')->latest()->first();
                }
            }

            $shiftLogId = collect($onlineUsers)->where('frontdesk_id', auth()->user()->id)->first()->id ?? null;
             Transaction::create([
                'branch_id' => auth()->user()->branch_id,
                'shift_log_id' => $shiftLogId,
                'checkin_detail_id' => $check_in_detail->id,
                'cash_drawer_id' => auth()->user()->cash_drawer_id,
                'room_id' => $this->guest->room_id,
                'guest_id' => $this->guest->id,
                'floor_id' => Room::where('id', $this->guest->room_id)->first()->floor->id,
                'transaction_type_id' => 2,
                'assigned_frontdesk_id' => json_encode($this->assigned_frontdesk),
                'description' => 'Deposit',
                'payable_amount' => $this->excess_amount,
                'paid_amount' => $this->excess_amount,
                'change_amount' => 0,
                'deposit_amount' => $this->excess_amount,
                'paid_at' => Carbon::now()->toDateTimeString(),
                'override_at' => null,
                'remarks' => 'Deposit From Transfer Room (Excess Amount)',
                'shift' => (now()->hour >= 8 && now()->hour < 20) ? 'AM' : 'PM',
                'is_override' => false,
            ]);

             //save cash on drawer
            CashOnDrawer::create([
                'branch_id' => auth()->user()->branch_id,
                'frontdesk_id' => auth()->user()->frontdesk->id,
                'cash_drawer_id' => auth()->user()->cash_drawer_id,
                'amount' => $this->excess_amount,
                'transaction_date' => now()->toDateString(),
                'transaction_type' => 'deposit',
                'shift' => (now()->hour >= 8 && now()->hour < 20) ? 'AM' : 'PM',
            ]);

             CheckinDetail::where('guest_id', $this->guest->id)->update([
                'total_deposit' => $this->guest->total_deposit + $this->excess_amount,
            ]);

        }

        if($this->selected_status === "Uncleaned")
        {
            Room::where('id', $check_in_detail->room_id)->update([
                'time_to_clean' => now()->addHours(4),
            ]);
        }

        // Stamp the source room's check_out_time with the transfer moment so the
        // roomboy queue (sorted by check_out_time ASC) ranks it as recently
        // vacated instead of inheriting the prior guest's checkout time. This
        // mirrors the write done by ManageGuestTransaction.php / GuestTransaction.php
        // on a regular checkout — without it, transferred-from rooms appear
        // hours/days behind in the queue.
        Room::where('id', $check_in_detail->room_id)->update([
            'status'         => $this->selected_status,
            'check_out_time' => now()->toDateTimeString(),
        ]);

        Room::where('id',  $this->selected_room_id)->update([
            'status' => 'Occupied',
        ]);

        $initial_deposit = auth()->user()->branch->initial_deposit;

         Guest::where('id', $this->guest->id)->update([
            'previous_room_id' => $check_in_detail->room_id,
            'room_id' => $this->selected_room_id,
            'type_id' => $this->selected_type_id,
            'rate_id' => $this->new_room ? $this->new_room->id : $this->guest->rate_id,
            'static_amount' => ($this->new_room_rate + $initial_deposit),
        ]);


        $new_room = Room::where('id',  $this->selected_room_id)->first();

        $transaction = Transaction::where('checkin_detail_id', $check_in_detail->id)->where('description', 'Guest Check In')->first();
        $transaction->update([
            'remarks' => 'Guest Checked In at room #' . $new_room->number,
            'payable_amount' => $this->new_room_rate,
            'paid_amount' => $this->new_room_rate,
            'change_amount' => 0,
            'paid_at' => Carbon::now()->toDateTimeString(),
            'room_id' => $this->selected_room_id,
            'floor_id' => $this->selected_floor_id,
        ]);

        // Move Deposit transactions (room key + excess) to the new room so
        // the audit trail and any room-scoped reports show the deposit under
        // the guest's current room, not the source room they transferred out
        // of. Without this the deposits stay tagged to the original kiosk
        // room and look misattributed.
        Transaction::where('checkin_detail_id', $check_in_detail->id)
            ->where('transaction_type_id', 2)
            ->update([
                'room_id' => $this->selected_room_id,
                'floor_id' => $this->selected_floor_id,
            ]);

        TransferedGuestReport::create([
            'checkin_detail_id' => $check_in_detail->id,
            'previous_room_id' => $check_in_detail->room_id,
            'new_room_id' => $new_room->id,
            'rate_id' => $this->guest->rate_id,
            'previous_amount' => $check_in_detail->static_room_amount,
            'new_amount' => $this->new_room_rate,
            'original_check_in_time' => $transaction->created_at,
        ]);

         CheckinDetail::where('guest_id', $this->guest->id)->update([
            'type_id' => $this->selected_type_id,
            'room_id' => $this->selected_room_id,
            'rate_id' => $this->new_room ? $this->new_room->id : $this->guest->rate_id,
            'static_room_amount' => $this->new_room_rate,
            'static_amount' => ($this->new_room_rate + $initial_deposit),
        ]);




        ActivityLog::create([
            'branch_id' => auth()->user()->branch_id,
            'user_id' => auth()->user()->id,
            'activity' => 'Room Transfer',
            'description' => 'Guest ' . $check_in_detail->guest->name . ' transferred from Room #' . Room::where('id', $check_in_detail->room_id)->first()->number . ' to Room #' . $new_room->number,
        ]);
        DB::commit();

        // Clear the kiosk picked slot for the source room (if any). When a
        // kiosk-checked-in guest is transferred, the source room cycles back
        // toward Available but the picked slot stays orphaned otherwise,
        // blocking the kiosk from showing other rooms on that floor/type.
        // refreshFloorSlot deletes the picked slot and refills with the
        // next-priority clean room on that floor (same logic used by
        // CheckInFromKiosk after a confirmed kiosk pick).
        $sourceFloorId = Room::where('id', $check_in_detail->room_id)->value('floor_id');
        if ($sourceFloorId !== null) {
            KioskBatchService::refreshFloorSlot(
                auth()->user()->branch_id,
                $check_in_detail->type_id,
                $sourceFloorId,
            );
        }

        // Also heal the destination type's batch. The destination room just
        // became Occupied, so any active slot pointing to it is now stale.
        // refreshIfStale repairs stale active slots in place (replaces with
        // next-available room) and removes stale picked slots. This makes
        // the kiosk recover instantly instead of waiting for the next render.
        KioskBatchService::refreshIfStale(
            auth()->user()->branch_id,
            $this->selected_type_id,
        );

        $this->dialog()->success(
            $title = 'Success',
            $description = 'Guest has been transferred successfully.'
        );

         return redirect()->route('frontdesk.guest-transaction', [
                'id' => $this->guest->id,
        ]);

    }

    public function cancelTransfer()
    {
        return redirect()->route('frontdesk.guest-transaction', ['id' => $this->guest->id]);
    }

    private function isUserOnline($user, $threshold) { return $user->sessions() ->where('last_activity', '>=', $threshold) ->exists(); }
    public function render()
    {
        return view('livewire.frontdesk.monitoring.transfer-room');
    }
}
