<?php

namespace App\Http\Livewire\Frontdesk\Monitoring;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\User;
use App\Models\CashOnDrawer;
use App\Models\CheckinDetail;
use App\Models\Guest;
use App\Models\NewGuestReport;
use App\Models\Rate;
use App\Models\Room;
use App\Models\StayingHour;
use App\Models\TemporaryCheckInKiosk;
use App\Models\Transaction;
use App\Services\KioskBatchService;
use Carbon\Carbon;
use DB;
use Livewire\Component;
use WireUi\Traits\Actions;

class CheckInFromKiosk extends Component
{
    use Actions;
    public $record;
    public $temporary_checkIn;
    public $additional_charges = 0;
    public $room;
    public $rate;
    public $stayingHour;
    public $guest;
    public $has_discount;
    public $discount_amount;

    public $is_longStay = false;
    public $total = 0;

    public $save_excess = false;

    public $amountPaid;
    public $excess = false;
    public $excess_amount = 0;
    public $room_static_amount = 0;

    //modals
    public $change_modal = false;
    public function mount($record)
    {
        $this->additional_charges = auth()->user()->branch->initial_deposit;

        $this->temporary_checkIn = TemporaryCheckInKiosk::where(
            'branch_id',
            auth()->user()->branch_id
        )
            ->where('id', $record)
            ->first();

        // Handle case where record doesn't exist (already processed or invalid)
        if (!$this->temporary_checkIn) {
            session()->flash('error', 'Check-in record not found or already processed.');
            return redirect()->route('frontdesk.room-monitoring');
        }

        $this->guest = Guest::where('branch_id', auth()->user()->branch_id)
            ->where('id', $this->temporary_checkIn->guest_id)
            ->first();

        // Handle case where guest doesn't exist
        if (!$this->guest) {
            session()->flash('error', 'Guest record not found.');
            return redirect()->route('frontdesk.room-monitoring');
        }
        $this->room_static_amount = $this->guest->static_amount;
        $this->room = Room::where('branch_id', auth()->user()->branch_id)
                ->where('id', $this->temporary_checkIn->room_id)
                ->first();
        $this->rate = Rate::where('branch_id', auth()->user()->branch_id)
                ->where('id', $this->guest->rate_id)
                ->first();
        $this->stayingHour = StayingHour::where(
                'branch_id',
                auth()->user()->branch_id
            )
                ->where('id', $this->rate->staying_hour_id)
                ->first();
            $this->has_discount = $this->guest->has_discount;
            $this->discount_amount = auth()->user()->branch->discount_amount;
            $this->is_longStay = (bool) $this->guest->is_long_stay;

            if ($this->has_discount) {
                $this->total = ($this->guest->static_amount + $this->additional_charges) - $this->discount_amount;
            } else {
                $this->total = $this->guest->static_amount + $this->additional_charges;
            }
    }

    public function cancelCheckIn()
    {
        $branchId = $this->temporary_checkIn->branch_id;
        $roomId = $this->temporary_checkIn->room_id;
        $guestId = $this->temporary_checkIn->guest_id;

        DB::transaction(function () use ($guestId) {
            // Only delete the guest if it has no CheckinDetail and no
            // transactions — mirror the safety rules used by the timeout
            // cleanup job so we never wipe out records that represent real
            // money or completed check-ins.
            if ($guestId) {
                Guest::where('id', $guestId)
                    ->whereDoesntHave('checkInDetail')
                    ->whereDoesntHave('transactions')
                    ->delete();
            }
            $this->temporary_checkIn->delete();
        });

        // Floor should reappear on the kiosk — the guest walked away, the
        // room was never actually occupied.
        KioskBatchService::returnToBatch($branchId, $roomId);

        return redirect()->route('frontdesk.room-monitoring');
    }

    public function updatedHasDiscount()
    {
        //compute total amount
         if ($this->has_discount) {
                $this->total = ($this->guest->static_amount + $this->additional_charges) - $this->discount_amount;
            } else {
                $this->total = $this->guest->static_amount + $this->additional_charges;
        }
        //check if amount paid is greater than total
        if ($this->amountPaid > $this->total) {
            $this->excess = true;
            $this->excess_amount = $this->amountPaid - $this->total;
        } else {
            $this->excess = false;
            $this->excess_amount = 0;
        }
    }

    public function proceedCheckIn()
    {
        $this->validate([
            'amountPaid' => 'required|numeric',
        ],
        [
            'amountPaid.required' => 'Amount Paid is required',
            'amountPaid.numeric' => 'Amount Paid must be a number',
        ]);


        //check if amount paid is greater than total
        if ($this->amountPaid > $this->total) {
            $this->excess_amount = $this->amountPaid - $this->total;
            $this->change_modal = true;
        }else{
            if($this->amountPaid < $this->total)
            {
                 $this->dialog()->error(
                    $title = 'Oops !!!',
                    $description = 'Amount paid is less than the total payable amount.'
                );
            }else{
                $this->dialog()->confirm([
                'title'       => 'Save Transaction?',
                'description' => 'The guest payment is the exact amount. Do you want to proceed with the check-in?',
                'acceptLabel' => 'Yes, proceed',
                'method'      => 'saveCheckIn',
                'params'      => 'Saved',
                ]);
            }

        }
    }

    public function saveCheckIn()
    {
        // Prevent duplicate check-in for the same guest/room
        $alreadyExists = CheckinDetail::where('guest_id', $this->guest->id)
            ->where('room_id', $this->guest->room_id)
            ->where('check_in_at', '>=', now()->subMinutes(5))
            ->exists();

        if ($alreadyExists) {
            $this->dialog()->error('Duplicate Check-In', 'This guest has already been checked in.');
            return;
        }

        DB::beginTransaction();

        $rate = Rate::where('id', $this->guest->rate_id)->first()->stayingHour->number;
        $room_number = Room::where('id', $this->guest->room_id)->first()->number;
        $assigned_frontdesk = auth()->user()->assigned_frontdesks;
         //update guest
         $this->guest->static_amount = $this->total;
         $this->guest->has_discount = $this->has_discount;
         $this->guest->discount_amount = $this->discount_amount;
         $this->guest->save();

         $decode_frontdesk = json_decode(
            $assigned_frontdesk,
            true
        );

        $extension_time_reset = Branch::where(
            'id',
            auth()->user()->branch_id
        )->first()->extension_time_reset;

         $number_of_hours = $rate;
         $next_extension_is_original = false;
         while ($number_of_hours >= $extension_time_reset) {
             $number_of_hours -= $extension_time_reset;
             $next_extension_is_original = true;
         }

         // Block check-in if room has unresolved previous guest
         // Changed from auto-close to block (2026-04-28) to ensure proper checkout flow
         $existingCheckin = CheckinDetail::where('room_id', $this->guest->room_id)
            ->where('is_check_out', false)
            ->with('guest:id,name')
            ->first();

         if ($existingCheckin) {
            $ghostName = $existingCheckin->guest->name ?? 'Unknown';
            $ghostDate = $existingCheckin->check_in_at
                ? \Carbon\Carbon::parse($existingCheckin->check_in_at)->format('M d, Y g:i A')
                : 'unknown date';
            $this->dialog()->error(
                'Room Has Active Guest',
                "Room has unresolved check-in: {$ghostName} (checked in {$ghostDate}). Please checkout the previous guest first via Guest Transaction."
            );
            return;
         }

         //save check-in details
         $checkin = CheckinDetail::create([
            'guest_id' => $this->guest->id,
            'frontdesk_id' => $decode_frontdesk[0],
            'type_id' => $this->guest->type_id,
            'room_id' => $this->guest->room_id,
            'rate_id' => $this->guest->rate_id,
            'static_room_amount' => $this->room_static_amount,
            'static_amount' => $this->guest->static_amount,
            'hours_stayed' => $this->is_longStay
                ? $rate * $this->guest->number_of_days
                : $rate,
            'total_deposit' => $this->save_excess
                ? $this->excess_amount + $this->additional_charges
                : $this->additional_charges,
            'check_in_at' => now(),
            'check_out_at' => $this->guest->is_long_stay
                ? now()->addDays($this->guest->number_of_days)
                : now()->addHours($this->stayingHour->number),
            'is_long_stay' => (bool) $this->is_longStay,
            'number_of_hours' => $number_of_hours,
            'next_extension_is_original' => $next_extension_is_original ? 1 : 0,
        ]);

        $users = User::role('frontdesk')->get();

            $threshold = now()->subMinutes(5)->timestamp;

            $onlineUsers = [];

            foreach ($users as $user) {
                if ($this->isUserOnline($user, $threshold)) {
                    $onlineUsers[] = $user->shiftLogs()->whereNull('time_out')->latest()->first();
                }
            }

            $shiftLogId = collect($onlineUsers)->where('frontdesk_id', auth()->user()->id)->first()->id ?? null;

        //create transaction for check-in
         Transaction::create([
            'branch_id' => auth()->user()->branch_id,
            'shift_log_id' => $shiftLogId,
            'checkin_detail_id' => $checkin->id,
            'cash_drawer_id' => auth()->user()->cash_drawer_id,
            'room_id' => $this->guest->room_id,
            'guest_id' => $this->guest->id,
            'floor_id' => Room::where('id', $this->guest->room_id)->first()->floor->id,
            'transaction_type_id' => 1,
            'assigned_frontdesk_id' => json_encode($assigned_frontdesk),
            'description' => 'Guest Check In',
            'payable_amount' => $this->room_static_amount,
            'paid_amount' => $this->amountPaid,
            'change_amount' =>
                $this->excess_amount != 0 ? $this->excess_amount : 0,
            'deposit_amount' => 0,
            'paid_at' => now(),
            'override_at' => null,
            'remarks' => 'Guest Checked In at room #' . $room_number,
            'shift' => (now()->hour >= 8 && now()->hour < 20) ? 'AM' : 'PM',
        ]);

        //save cash on drawer
        CashOnDrawer::create([
            'branch_id' => auth()->user()->branch_id,
            'frontdesk_id' => auth()->user()->frontdesk->id,
            'cash_drawer_id' => auth()->user()->cash_drawer_id,
            'amount' => $this->room_static_amount,
            'transaction_date' => now()->toDateString(),
            'transaction_type' => 'check-in',
            'shift' => (now()->hour >= 8 && now()->hour < 20) ? 'AM' : 'PM',
        ]);
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
            'checkin_detail_id' => $checkin->id,
            'cash_drawer_id' => auth()->user()->cash_drawer_id,
            'room_id' => $this->guest->room_id,
            'guest_id' => $this->guest->id,
            'floor_id' => Room::where('id', $this->guest->room_id)->first()->floor->id,
            'transaction_type_id' => 2,
            'assigned_frontdesk_id' => json_encode($assigned_frontdesk),
            'description' => 'Deposit',
            'payable_amount' => $this->additional_charges,
            'paid_amount' => $this->amountPaid,
            'change_amount' =>
                $this->excess_amount != 0 ? $this->excess_amount : 0,
            'deposit_amount' => $this->additional_charges,
            'paid_at' => now(),
            'override_at' => null,
            'remarks' => 'Deposit From Check In (Room Key & TV Remote)',
            'shift' => (now()->hour >= 8 && now()->hour < 20) ? 'AM' : 'PM',
        ]);

        CashOnDrawer::create([
            'branch_id' => auth()->user()->branch_id,
            'frontdesk_id' => auth()->user()->frontdesk->id,
            'cash_drawer_id' => auth()->user()->cash_drawer_id,
            'amount' => $this->additional_charges,
            'transaction_date' => now()->toDateString(),
            'transaction_type' => 'deposit',
            'shift' => (now()->hour >= 8 && now()->hour < 20) ? 'AM' : 'PM',
        ]);

        if ($this->save_excess) {
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
                'checkin_detail_id' => $checkin->id,
                'cash_drawer_id' => auth()->user()->cash_drawer_id,
                'room_id' => $this->guest->room_id,
                'guest_id' => $this->guest->id,
                'floor_id' => Room::where('id', $this->guest->room_id)->first()->floor->id,
                'transaction_type_id' => 2,
                'assigned_frontdesk_id' => json_encode($assigned_frontdesk),
                'description' => 'Deposit',
                'payable_amount' => $this->excess_amount,
                'paid_amount' => $this->amountPaid,
                'change_amount' => 0,
                'deposit_amount' => $this->excess_amount,
                'paid_at' => now(),
                'override_at' => null,
                'remarks' => 'Deposit From Check In (Excess Amount)',
                'shift' => (now()->hour >= 8 && now()->hour < 20) ? 'AM' : 'PM',
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
        }

        $now = Carbon::now();
        $hour = (int) $now->format('H');
        $shift = $now->format('H:i');

        if ($hour >= 8 && $hour < 20) {
            $shift_schedule = 'AM';
            $shift_date = $now->format('F j, Y');
        } else {
            $shift_schedule = 'PM';
            // For times between 00:00 and 07:59 the PM shift started the previous day (8pm)
            $shift_date = $hour < 8 ? $now->copy()->subDay()->format('F j, Y') : $now->format('F j, Y');
        }

        // $shift_date = Carbon::parse(auth()->user()->time_in)->format('F j, Y');
        // $shift = Carbon::parse(auth()->user()->time_in)->format('H:i');
        // $hour = Carbon::parse($shift)->hour;

        // if ($hour >= 8 && $hour < 20) {
        //     $shift_schedule = 'AM';
        // } else {
        //     $shift_schedule = 'PM';
        // }

         $decode_frontdesk = json_decode(
            auth()->user()->assigned_frontdesks,
            true
        );

         NewGuestReport::create([
            'branch_id' => auth()->user()->branch_id,
            'checkin_details_id' => $checkin->id,
            'room_id' => $checkin->room_id,
            'shift_date' => $shift_date,
            'shift' => $shift_schedule,
            'frontdesk_id' => $decode_frontdesk[0],
            'partner_name' => $decode_frontdesk[1],
        ]);

        $this->amountPaid = null;

        if($this->change_modal)
        {
            $this->change_modal = false;
        }

        Room::where('id', $this->guest->room_id)
            ->first()
            ->update([
                'status' => 'Occupied',
            ]);

        TemporaryCheckInKiosk::where('id', $this->temporary_checkIn->id)
            ->first()
            ->delete();

        $this->temporary_checkIn = null;

        ActivityLog::create([
            'branch_id' => auth()->user()->branch_id,
            'user_id' => auth()->user()->id,
            'activity' => 'Check In from Kiosk',
            'description' => 'Checked in guest ' . $this->guest->name . ' from kiosk',
        ]);

        DB::commit();

        // Per-floor independent rotation: the kiosk slot was flipped to
        // 'picked' when the guest reserved on the kiosk. Now that frontdesk
        // has confirmed the check-in (room is Occupied, hold removed), refresh
        // ONLY this floor's slot so the next clean room appears immediately.
        // Other floors in the batch are not touched. The "wait for confirm"
        // model still holds — cancellations before this point flip picked back
        // to active via returnToBatch.
        $floorId = Room::where('id', $this->guest->room_id)->value('floor_id');
        KioskBatchService::refreshFloorSlot(
            $this->guest->branch_id,
            $this->guest->type_id,
            $floorId,
        );

        $this->dialog()->success(
            $title = 'Success',
            $description = 'Guest Has been Check-in'
        );

        return redirect()->route('frontdesk.room-monitoring');
    }

    private function isUserOnline($user, $threshold) { return $user->sessions() ->where('last_activity', '>=', $threshold) ->exists(); }

    public function render()
    {
        return view('livewire.frontdesk.monitoring.check-in-from-kiosk');
    }
}
