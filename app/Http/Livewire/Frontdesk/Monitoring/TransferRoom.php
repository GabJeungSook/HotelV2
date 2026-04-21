<?php

namespace App\Http\Livewire\Frontdesk\Monitoring;

use App\Models\ActivityLog;
use App\Models\User;
use App\Models\CashOnDrawer;
use App\Models\CheckinDetail;
use App\Models\Floor;
use App\Models\Guest;
use App\Models\Rate;
use App\Models\Room;
use App\Models\TemporaryCheckInKiosk;
use App\Models\Transaction;
use App\Models\TransferedGuestReport;
use App\Models\TransferReason;
use App\Models\Type;
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
        $hours = $this->guest->checkInDetail->hours_stayed;
        $this->new_room  = Rate::where('branch_id', auth()->user()->branch_id)
                        ->where('type_id', $this->selected_type_id)
                        ->where('is_available', true)
                        ->whereHas('stayingHour', function ($query) use ($hours) {
                            $query->where('branch_id', auth()->user()->branch_id)->where('number', '=', $hours);
                        })
                        ->first();
        $this->new_room_rate = $this->guest->is_long_stay ? ($this->new_room ? $this->new_room->amount * $this->guest->number_of_days : 0) : ($this->new_room ? $this->new_room->amount : 0);

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

        $this->authorization_modal = true;
        $this->is_override = true;
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

        Room::where('id', $check_in_detail->room_id)->update([
            'status' => $this->selected_status,
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
