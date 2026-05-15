<?php

namespace App\Http\Livewire\Frontdesk\GuestTransactions;

use App\Models\Guest;
use App\Models\User;
use Livewire\Component;
use App\Models\HotelItems;
use WireUi\Traits\Actions;
use App\Models\Transaction;
use App\Models\CheckinDetail;
use App\Support\ShiftResolver;
use Illuminate\Support\Facades\DB;

class CheckOutGuest extends Component
{
    use Actions;
    public $record;
    public $hasConfirmedRoomKeyHandedOver = false;
    public $roomKeyHandedOver;
    public $claimableDeposits;
    public $deposit_remote_and_key;
    public $deposit_except_remote_and_key;
    public $has_damaged_remote_and_key = false;
    public $assigned_frontdesk = [];

    // damage modal

    public $damage_modal = false;

    public $item_id_damage;

    public $items;

    public $item_price_damage;

    public $additional_amount_damage;

    public $total_amount_damage;

    public $damage_charges_type;



    public function mount($record)
    {
        $this->record = Guest::find($record);
        $this->deposit_remote_and_key = Transaction::where('branch_id', auth()->user()->branch_id)
            ->where('guest_id', $this->record->id)
            ->where('transaction_type_id', 2)
            ->where('remarks', 'Deposit From Check In (Room Key & TV Remote)')
            ->first()
            ?->deposit_amount ?? 0;
        $this->deposit_except_remote_and_key = Transaction::where('branch_id', auth()->user()->branch_id)
            ->where('guest_id', $this->record->id)
            ->where('transaction_type_id', 2)
            ->where('remarks', '!=', 'Deposit From Check In (Room Key & TV Remote)')
            ->first()
            ?->deposit_amount ?? 0;

        $this->assigned_frontdesk = auth()->user()->assigned_frontdesks;

        $this->has_damaged_remote_and_key = Transaction::where('branch_id', auth()->user()->branch_id)
            ->where('guest_id', $this->record->id)
            ->where('transaction_type_id', 4)
            ->where('remarks', 'Guest Charged for Damage: Room Key & TV Remote')
            ->exists();

        $this->items = HotelItems::where(
            'branch_id',
            auth()->user()->branch_id
        )->get();





        $this->has_damaged_remote_and_key ? $this->roomKeyHandedOver = 'No' : $this->roomKeyHandedOver = 'Yes';
        $this->has_damaged_remote_and_key ? $this->hasConfirmedRoomKeyHandedOver = true : $this->hasConfirmedRoomKeyHandedOver = false;


    }

    public function updatedItemIdDamage()
    {
        $item = HotelItems::where('id', $this->item_id_damage)->first();
        $this->item_price_damage = $item->price;
        $this->additional_amount_damage = 0;
        //  $this->additional_amount_damage = $this->additional_amount_damage;

        $this->total_amount_damage = $this->item_price_damage + $this->additional_amount_damage;
    }

    public function confirmDamageCharges()
    {
        $this->damage_charges_type = 'save';
        $this->dialog()->confirm([
            'title' => 'Are you Sure?',
            'description' => 'Save the information?',
            'icon' => 'question',
            'accept' => [
                'label' => 'Yes, save it',
                'method' => 'addDamageCharges',
            ],
            'reject' => [
                'label' => 'No, cancel',
            ],
        ]);
    }

     public function confirmDamageChargesPay()
    {
        $this->damage_charges_type = 'savePay';
        $this->dialog()->confirm([
            'title' => 'Are you Sure?',
            'description' => 'Save the information?',
            'icon' => 'question',
            'accept' => [
                'label' => 'Yes, save it',
                'method' => 'addDamageCharges',
            ],
            'reject' => [
                'label' => 'No, cancel',
            ],
        ]);
    }

    public function confirmDamageChargesPayDeposit()
    {
        $this->damage_charges_type = 'savePayDeposit';
        $this->dialog()->confirm([
            'title' => 'Are you Sure?',
            'description' => 'Save the information?',
            'icon' => 'question',
            'accept' => [
                'label' => 'Yes, save it',
                'method' => 'addDamageCharges',
            ],
            'reject' => [
                'label' => 'No, cancel',
            ],
        ]);
    }

    public function addDamageCharges()
    {
        if (auth()->user()->branch->autorization_code == null) {
            $this->dialog()->error(
                $title = 'Missing Authorization Code',
                $description = 'Admin must add authorization code first'
            );
        } elseif (auth()->user()->branch->extension_time_reset == null) {
            $this->dialog()->error(
                $title = 'Missing Extension Time Reset',
                $description = 'Admin must add extension time reset first'
            );
        } else {
            $this->validate(
                [
                    'item_id_damage' => 'required',
                ],
                [
                    'item_id_damage.required' => 'This field is required',
                ]
            );
            DB::beginTransaction();
            $check_in_detail = CheckinDetail::where(
                'guest_id',
                $this->record->id
            )->first();
            $damage_charges = HotelItems::where(
                'branch_id',
                auth()->user()->branch_id
            )
                ->where('id', $this->item_id_damage)
                ->first();
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
                'branch_id' => $check_in_detail->guest->branch_id,
                'shift_log_id' => $shiftLogId,
                'checkin_detail_id' => $check_in_detail->id,
                'cash_drawer_id' => auth()->user()->cash_drawer_id,
                'room_id' => $check_in_detail->room_id,
                'guest_id' => $check_in_detail->guest_id,
                'floor_id' => $check_in_detail->room->floor_id,
                'transaction_type_id' => 4,
                'assigned_frontdesk_id' => json_encode(
                    $this->assigned_frontdesk
                ),
                'description' => 'Damage Charges',
                'payable_amount' => $this->total_amount_damage,
                'paid_amount' => 0,
                'change_amount' => 0,
                'deposit_amount' => 0,
                'paid_at' => null,
                'override_at' => null,
                'remarks' =>
                    'Guest Charged for Damage: (' .
                    1 .
                    ')' .
                    ' ' .
                    $damage_charges->name,
                'shift' => ShiftResolver::current(),
            ]);
            DB::commit();
            $this->damage_modal = false;
            $this->reset(
                'item_id_damage',
                'item_price_damage',
                'additional_amount_damage',
                'total_amount_damage'
            );

            if($this->damage_charges_type === 'savePay')
            {
                $this->payTransaction($transaction->id);
            }elseif($this->damage_charges_type === 'savePayDeposit'){
                $this->payWithDeposit($transaction->id);
            }else{
                $this->dialog()->success(
                    $title = 'Success',
                    $description = 'Data successfully saved'
                );

                // return redirect()->route('frontdesk.guest-transaction', [
                //     'id' => $this->record->id,
                // ]);
            }
        }
    }

    private function isUserOnline($user, $threshold) { return $user->sessions() ->where('last_activity', '>=', $threshold) ->exists(); }

    public function hasHandedRemote($value)
    {

         $this->dialog()->confirm([
            'title'       => 'Are you Sure?',
            'description' => 'Save the information?',
            'acceptLabel' => 'Yes, save it',
            'method'      => 'roomKeyHandedOver',
            'params'      => $value,
        ]);

    }

    public function roomKeyHandedOver($value)
    {

        $this->roomKeyHandedOver = $value;
        // dd($this->roomKeyHandedOver,$this->has_damaged_remote_and_key);
        $this->hasConfirmedRoomKeyHandedOver = true;

        // if ($this->roomKeyHandedOver == 'Yes') {
        //     $balance =
        //         $this->deposit_remote_and_key +
        //         ($this->deposit_except_remote_and_key -
        //             $this->record->checkInDetail->total_deduction);
        // } elseif ($this->roomKeyHandedOver == 'No') {
        //     $balance =
        //         $this->deposit_except_remote_and_key -
        //         $this->record->checkInDetail->total_deduction;
        // }else{
        //     $balance =
        //         $this->deposit_remote_and_key +
        //         ($this->deposit_except_remote_and_key -
        //             $this->record->checkInDetail->total_deduction);
        // }

         $transaction = Transaction::where(
            'branch_id',
            auth()->user()->branch_id
        )
            ->where('guest_id', $this->record->id)
            ->first();

        DB::beginTransaction();
        // $this->record->checkInDetail->update([
        //     'total_deduction' =>
        //         $this->record->checkInDetail->total_deduction + $balance,
        // ]);
        $check_in_detail = CheckinDetail::where(
                'guest_id',
                $this->record->id
            )->first();

        if ($this->roomKeyHandedOver == 'No') {
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
                'branch_id' => $transaction->branch_id,
                'shift_log_id' => $shiftLogId,
                'checkin_detail_id' => $check_in_detail->id,
                'cash_drawer_id' => auth()->user()->cash_drawer_id,
                'room_id' => $transaction->room_id,
                'guest_id' => $transaction->guest_id,
                'floor_id' => $transaction->floor_id,
                'transaction_type_id' => 4,
                'assigned_frontdesk_id' => json_encode(
                    $this->assigned_frontdesk
                ),
                'description' => 'Damage Charges',
                'payable_amount' => $this->deposit_remote_and_key,
                'paid_amount' => $this->deposit_remote_and_key,
                'change_amount' => 0,
                'deposit_amount' => 0,
                'paid_at' => now(),
                'override_at' => null,
                'remarks' => 'Guest Charged for Damage: Room Key & TV Remote',
                'shift' => ShiftResolver::current(),
            ]);
        }
        DB::commit();


    }


    public function render()
    {
        if($this->roomKeyHandedOver == 'Yes' && !$this->has_damaged_remote_and_key)
        {
            $this->claimableDeposits = $this->record->transactions()->where('transaction_type_id', 2)->get();
        }elseif($this->roomKeyHandedOver == 'No' && $this->has_damaged_remote_and_key){
            $this->claimableDeposits = $this->record->transactions()
            ->where('transaction_type_id', 2)
            ->where('remarks', '!=', 'Deposit From Check In (Room Key & TV Remote)')
            ->get();
        }else {
            $this->claimableDeposits = $this->record->transactions()->where('transaction_type_id', 2)->get();
        }
        return view('livewire.frontdesk.guest-transactions.check-out-guest', [
            'deposits' => $this->claimableDeposits,
            'check_in_details' => CheckinDetail::where(
                'guest_id',
                $this->record->id)->first(),
            'transaction' => Transaction::where('branch_id', auth()->user()->branch_id)
                ->where('guest_id', $this->record->id)
                ->first()
        ]);
    }
}
