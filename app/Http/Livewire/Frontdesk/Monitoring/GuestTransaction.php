<?php

namespace App\Http\Livewire\Frontdesk\Monitoring;

use App\Models\ActivityLog;
use DB;
use Carbon\Carbon;
use App\Models\Menu;
use App\Models\Rate;
use App\Models\User;
use App\Models\Room;
use App\Models\Type;
use App\Models\Floor;
use App\Models\Guest;
use Livewire\Component;
use App\Models\Inventory;
use App\Models\HotelItems;
use WireUi\Traits\Actions;
use App\Models\StayingHour;
use App\Models\Transaction;
use App\Models\CheckinDetail;
use App\Models\ExtensionRate;
use App\Models\FrontdeskMenu;
use App\Models\StayExtension;
use App\Models\RequestableItem;
use App\Models\TransactionType;
use App\Models\AssignedFrontdesk;
use App\Models\CashOnDrawer;
use App\Models\FrontdeskInventory;
use App\Models\CheckOutGuestReport;
use App\Models\ExtendedGuestReport;
use App\Models\TransferReason;
use Filament\Forms\Components\Actions\Modal\Actions\Action;

class GuestTransaction extends Component
{
    use Actions;
    public $guest_id;
    public $assigned_frontdesk = [];
    public $bills_paid = [];
    public $bills_unpaid = [];
    public $authorization_type;
    public $has_rate = false;

    //modals
    public $transfer_modal = false;
    public $deposit_modal = false;
    public $deposit_deduct_modal = false;
    public $extend_modal = false;
    public $damage_modal = false;
    public $amenities_modal = false;
    public $food_beverages_modal = false;
    public $autorization_modal = false;
    public $autorization_cancel_modal = false;
    public $pay_modal = false;
    public $payWithDeposit_modal = false;
    public $payAllWithDeposit_modal = false;
    public $reminders_modal = false;
    public $override_modal = false;

    public $deposit_summary_modal = false;

    //Deposit
    public $deposit_amount;
    public $deposit_remarks;
    public $deduction_amount;
    public $total_deposit;
    public $deposit_remote_and_key;
    public $deposit_except_remote_and_key;
    // public $check_in_details;

    //extend
    public $extend_rate;
    public $get_hour;
    public $get_new_rate;
    public $total_get_rate;
    public $remaining_hours;
    public $extend_type;

    //food and beverages
    public $food_id;
    public $foods;
    public $food_price;
    public $food_quantity;
    public $food_subtotal;
    public $food_number_of_stock;
    public $food_total_amount;
    public $food_type;

    //Amenities
    public $amenities;
    public $item_id;
    public $item_quantity;
    public $item_price;
    public $subtotal;
    public $additional_amount;
    public $total_amount;
    public $amenities_type;

    //Damage Charges
    public $item_id_damage;
    public $item_price_damage;
    public $additional_amount_damage;
    public $total_amount_damage;
    public $damage_charges_type;

    //transfer
    public $type_id;
    public $floor_id;
    public $room_id;
    public $old_status;
    public $reason;

    public $reason_id;

    public $transfer_reason;
    public $total;
    public $code;

    public $extension_rates = [];
    public $types = [];
    public $floors = [];
    public $rooms = [];

    public $pay_transaction_id;
    public $pay_transaction_amount;
    public $pay_amount;
    public $pay_excess = 0;
    public $saveAsExcess;
    public $render_deposit;

    //payall
    public $pay_total_amount;

    //override
    public $remarks;
    public $override_amount;

    //check out
    public $reminderIndex = 0;
    public $is_checkout = false;
    public $reminders = [
        'Room key and remote handed over by the guest/room boy.',
        'Check room by the body.',
        'Call guest to check out in kiosk.',
        'Claim Deposit.',
        'Proceed Check Out.',
    ];


    public function mount()
    {
        $this->guest_id = request()->id;
        $this->assigned_frontdesk = auth()->user()->assigned_frontdesks;
        $this->extension_rates = ExtensionRate::where(
            'branch_id',
            auth()->user()->branch_id
        )->get();
        $this->foods = FrontdeskMenu::where('branch_id', auth()->user()->branch_id)
        ->whereHas('frontdeskInventory', function($query) {
            $query->where('number_of_serving', '>', 0);
        })->get();

        $this->amenities = RequestableItem::where(
            'branch_id',
            auth()->user()->branch_id
        )->get();
        $this->items = HotelItems::where(
            'branch_id',
            auth()->user()->branch_id
        )->get();

        $this->transfer_reason = TransferReason::where('branch_id', auth()->user()->branch_id)->get();
    }
    public function render()
    {
        $this->deposit_except_remote_and_key = Transaction::where(
            'branch_id',
            auth()->user()->branch_id
        )
            ->where('guest_id', $this->guest_id)
            ->where('transaction_type_id', 2)
            ->where(
                'remarks',
                '!=',
                'Deposit From Check In (Room Key & TV Remote)'
            )
            ->sum('payable_amount');

        $check_in_detail = CheckinDetail::where(
            'guest_id',
            $this->guest_id
        )->first();
        $this->total_deposit =
            $this->deposit_except_remote_and_key -
            $check_in_detail->total_deduction;

        $this->deposit_remote_and_key = Transaction::where(
            'branch_id',
            auth()->user()->branch_id
        )
            ->where('guest_id', $this->guest_id)
            ->where('transaction_type_id', 2)
            ->where('remarks', 'Deposit From Check In (Room Key & TV Remote)')
            ->sum('payable_amount');
        return view('livewire.frontdesk.monitoring.guest-transaction', [
            'transactions' => TransactionType::whereHas('transactions', function($query) {
                $query->where('branch_id', auth()->user()->branch_id)
                ->where('guest_id', $this->guest_id);
            })->get(),
            'guest' => Guest::where('id', $this->guest_id)->first(),
            'transaction_bills_paid' => Transaction::selectRaw(
                'sum(payable_amount) as total_payable_amount, transaction_type_id'
            )
                ->where('branch_id', auth()->user()->branch_id)
                ->where('guest_id', request()->id)
                ->whereNotNull('paid_at')
                ->groupBy('transaction_type_id')
                ->get(),
            'transaction_bills_unpaid' => Transaction::selectRaw(
                'sum(payable_amount) as total_payable_amount, transaction_type_id'
            )
                ->where('branch_id', auth()->user()->branch_id)
                ->where('guest_id', request()->id)
                ->whereNull('paid_at')
                ->groupBy('transaction_type_id')
                ->get(),
            'check_in_details' => CheckinDetail::where(
                'guest_id',
                $this->guest_id
            )->first(),
        ]);
    }

    public function closeModal()
    {
        return redirect()->route('frontdesk.guest-transaction', [
            'id' => $this->guest_id,
        ]);
    }

    public function updatedDepositModal()
    {
        // dd('sdsd');
        $this->check_in_details = CheckinDetail::where(
            'guest_id',
            $this->guest_id
        )->first();
        $this->deposit_remote_and_key = Transaction::where(
            'branch_id',
            auth()->user()->branch_id
        )
            ->where('guest_id', $this->guest_id)
            ->where('transaction_type_id', 2)
            ->where('remarks', 'Deposit From Check In (Room Key & TV Remote)')
            ->sum('payable_amount');

        $this->deposit_except_remote_and_key = Transaction::where(
            'branch_id',
            auth()->user()->branch_id
        )
            ->where('guest_id', $this->guest_id)
            ->where('transaction_type_id', 2)
            ->where(
                'remarks',
                '!=',
                'Deposit From Check In (Room Key & TV Remote)'
            )
            ->sum('payable_amount');
    }

    public function addNewDeposit()
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
            $this->validate([
                'deposit_amount' => 'required|gt:0',
                'deposit_remarks' => 'required',
            ]);

            DB::beginTransaction();
            $this->check_in_details = CheckinDetail::where(
                'guest_id',
                $this->guest_id
            )->first();
            $current_deposit = $this->check_in_details->total_deposit;
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
                'branch_id' => $this->check_in_details->guest->branch_id,
                'shift_log_id' => $shiftLogId,
                'checkin_detail_id' => $this->check_in_details->id,
                'cash_drawer_id' => auth()->user()->cash_drawer_id,
                'room_id' => $this->check_in_details->room_id,
                'guest_id' => $this->check_in_details->guest_id,
                'floor_id' => $this->check_in_details->room->floor_id,
                'transaction_type_id' => 2,
                'assigned_frontdesk_id' => json_encode(
                    $this->assigned_frontdesk
                ),
                'description' => 'Deposit',
                'payable_amount' => $this->deposit_amount,
                'paid_amount' => 0,
                'change_amount' => 0,
                'deposit_amount' => $this->deposit_amount,
                'paid_at' => now(),
                'override_at' => null,
                'remarks' => 'Guest Deposit: ' . $this->deposit_remarks,
                'shift' => (now()->hour >= 8 && now()->hour < 20) ? 'AM' : 'PM',
            ]);
            $this->check_in_details->update([
                'total_deposit' => $current_deposit + $this->deposit_amount,
            ]);

            ActivityLog::create([
                'branch_id' => auth()->user()->branch_id,
                'user_id' => auth()->user()->id,
                'activity' => 'Add Deposit',
                'description' => 'Added new deposit of ₱' . $this->deposit_amount . ' for guest ' . $this->check_in_details->guest->name,
            ]);

            $this->reset('deposit_amount', 'deposit_remarks');

            DB::commit();
            $this->dialog()->success(
                $title = 'Success',
                $description = 'Data successfully saved'
            );
            $this->deposit_modal = false;
        }
        return redirect()->route('frontdesk.guest-transaction', [
            'id' => $this->guest_id,
        ]);
    }

            public function updatedExtendRate()
            {
                $extension_time_reset = auth()->user()->branch->extension_time_reset;
                $remainingGuestHours = Guest::where('id', $this->guest_id)->first()->checkInDetail->number_of_hours;
                $selectedHour = ExtensionRate::where('id', $this->extend_rate)->first()->hour;

                $remaining = $extension_time_reset - $remainingGuestHours;
                $is_resettable = $remaining <= 0;

                if($extension_time_reset == null)
                {
                    $this->dialog()->error(
                        $title = 'Missing Extension Time Reset',
                        $description = 'Admin must add extension time reset first'
                    );
                    $this->reset('extend_rate', 'total_get_rate');
                    return;
                }

                if($is_resettable)
                {
                    $reset_rate = Rate::whereHas('stayingHour', function ($query) use ($selectedHour) {
                        $query->where('number', $selectedHour);
                    })->first();

                    if ($reset_rate == null) {
                        $this->dialog()->error(
                            $title = 'No Rate',
                            $description = 'There is no rate available for this hour'
                        );
                        $this->reset('extend_rate', 'total_get_rate');
                        return;
                    } else {
                        $this->total_get_rate = $reset_rate->amount;
                        $this->get_new_rate = auth()->user()->branch->extension_time_reset - $selectedHour;
                    }
                    return;
                }

                if ($remainingGuestHours >= $selectedHour) {
                    $this->total_get_rate = ExtensionRate::where('id', $this->extend_rate)->first()->amount;
                    $this->get_new_rate = $remainingGuestHours - $selectedHour;
                    return;
                }

                if($remainingGuestHours === 0)
                {
                    $reset_rate = Rate::whereHas('stayingHour', function ($query) use ($selectedHour){
                        $query->where('number', $selectedHour);
                    })->first();


                    if ($reset_rate == null) {
                    $this->dialog()->error(
                    $title = 'No Rate',
                    $description = 'There is no rate available for this hour'
                    );
                    $this->reset('extend_rate', 'total_get_rate');
                    }else{
                        $this->total_get_rate = $reset_rate->amount;
                        $this->get_new_rate = auth()->user()->branch->extension_time_reset - $selectedHour;
                    }
                    return;
                }

                // Logic for extension exceeding remaining hours
                if($remainingGuestHours >= $selectedHour)
                {
                    $addedTime = $remainingGuestHours - $selectedHour;
                }else{
                    $addedTime = $selectedHour - $remainingGuestHours;
                }
                 $total_added_time = auth()->user()->branch->extension_time_reset - $addedTime;

                $this->get_new_rate = $total_added_time;

                $newRate = auth()->user()->branch->extension_time_reset - $total_added_time;

                $rate = $selectedHour - $newRate;

                $firstRate = ExtensionRate::where('branch_id', auth()->user()->branch_id)
                                            ->where('hour', $rate)
                                            ->first()->amount;


                $secondRate = Rate::whereHas('stayingHour', function ($query) use ($newRate) {
                                    $query->where('number', $newRate);
                                })->first();
                if ($secondRate == null) {
                    $this->dialog()->error(
                    $title = 'No Rate',
                    $description = 'There is no rate available for this hour'
                    );
                    $this->reset('extend_rate', 'total_get_rate');
                    return;
                }

                $secondRate = $secondRate->amount;
                $this->total_get_rate = $firstRate + $secondRate;
            }

    // public function updatedExtendRate()
    // {
    //     $reseting_hour = auth()->user()->branch->extension_time_reset;

    //     $remaining_hour = Guest::where('id', $this->guest_id)->first()
    //         ->checkInDetail->number_of_hours;

    //     $selected_hour = ExtensionRate::where('id', $this->extend_rate)->first()
    //         ->hour;

    //     $total_remaning_hour = $remaining_hour - $selected_hour;


    //     if ($remaining_hour == $reseting_hour) {
    //         $extension = StayingHour::where(
    //             'branch_id',
    //             auth()->user()->branch_id
    //         )

    //             ->pluck('number')
    //             ->toArray();
    //         $notInrate = ExtensionRate::where(
    //             'branch_id',
    //             auth()->user()->branch_id
    //         )
    //             ->whereNotIn('hour', $extension)
    //             ->first()->hour;
    //         if ($selected_hour == $notInrate) {
    //             $this->dialog()->error(
    //                 $title = 'Sorry',
    //                 $description =
    //                     'You cannot extend with this selected hour,because this hour is not available in the regular rate. '
    //             );
    //         } else {
    //             $this->total_get_rate = Rate::where(
    //                 'branch_id',
    //                 auth()->user()->branch_id
    //             )
    //                 ->whereHas('stayingHour', function ($query) use (
    //                     $selected_hour
    //                 ) {
    //                     $query->where('number', $selected_hour);
    //                 })
    //                 ->first()->amount;
    //         }

    //         $this->get_new_rate = $remaining_hour - $selected_hour;
    //     } elseif ($total_remaning_hour >= 0) {
    //         $this->total_get_rate = ExtensionRate::where(
    //             'id',
    //             $this->extend_rate
    //         )->first()->amount;

    //         $this->get_new_rate = $remaining_hour - $selected_hour;
    //     } else {
    //         $added_time =
    //             $reseting_hour - ($remaining_hour - $selected_hour) * -1;

    //         $this->get_new_rate = $added_time;

    //         $new_rate = $reseting_hour - $added_time;
    //         $rate = $selected_hour - $new_rate;

    //         $first_rate = ExtensionRate::where(
    //             'branch_id',
    //             auth()->user()->branch_id
    //         )
    //             ->where('hour', $rate)
    //             ->first()->amount;

    //         $second_rate = Rate::whereHas('stayingHour', function ($query) use (
    //             $new_rate
    //         ) {
    //             $query->where('number', $new_rate);
    //         })->first();

    //         if ($second_rate == null) {
    //             $this->dialog()->error(
    //                 $title = 'No Rate',
    //                 $description = 'There is no rate available for this hour'
    //             );
    //             $this->reset('extend_rate', 'total_get_rate');
    //         } else {
    //             $second_rate = $second_rate->amount;
    //             $this->total_get_rate = $first_rate + $second_rate;
    //         }
    //     }
    // }

    public function saveExtend()
    {
        $this->extend_type = 'save';
        $this->dialog()->confirm([
            'title' => 'Are you Sure?',
            'description' => 'Save the information?',
            'icon' => 'question',
            'accept' => [
                'label' => 'Yes, save it',
                'method' => 'addExtend',
            ],
            'reject' => [
                'label' => 'No, cancel',
            ],
        ]);
    }

    public function savePayExtend()
    {
        $this->extend_type = 'savePay';
        $this->dialog()->confirm([
            'title' => 'Are you Sure?',
            'description' => 'Save the information?',
            'icon' => 'question',
            'accept' => [
                'label' => 'Yes, save it',
                'method' => 'addExtend',
            ],
            'reject' => [
                'label' => 'No, cancel',
            ],
        ]);
    }

    public function savePayDepositExtend()
    {
        $this->extend_type = 'savePayDeposit';
        $this->dialog()->confirm([
            'title' => 'Are you Sure?',
            'description' => 'Save the information?',
            'icon' => 'question',
            'accept' => [
                'label' => 'Yes, save it',
                'method' => 'addExtend',
            ],
            'reject' => [
                'label' => 'No, cancel',
            ],
        ]);
    }

    public function addExtend()
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
            $check_in_detail = CheckinDetail::where(
                'guest_id',
                $this->guest_id
            )->first();
            $rate = ExtensionRate::where('branch_id', auth()->user()->branch_id)
                ->where('id', $this->extend_rate)
                ->first();
            // dd($rate);

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
                'branch_id' => $check_in_detail->guest->branch_id,
                'shift_log_id' => $shiftLogId,
                'checkin_detail_id' => $check_in_detail->id,
                'cash_drawer_id' => auth()->user()->cash_drawer_id,
                'room_id' => $check_in_detail->room_id,
                'guest_id' => $check_in_detail->guest_id,
                'floor_id' => $check_in_detail->room->floor_id,
                'transaction_type_id' => 6,
                'assigned_frontdesk_id' => json_encode(
                    $this->assigned_frontdesk
                ),
                'description' => 'Extension',
                'payable_amount' => $this->total_get_rate,
                'paid_amount' => 0,
                'change_amount' => 0,
                'deposit_amount' => 0,
                'paid_at' => null,
                'override_at' => null,
                'remarks' => 'Guest Extension : ' . $rate->hour . ' hours',
                'shift' => (now()->hour >= 8 && now()->hour < 20) ? 'AM' : 'PM',
            ]);
            StayExtension::create([
                'guest_id' => $check_in_detail->guest_id,
                'extension_id' => $rate->id,
                'hours' => $rate->hour,
                'amount' => $this->total_get_rate,
                'frontdesk_ids' => json_encode($this->assigned_frontdesk),
            ]);

            if ($this->get_new_rate == 0) {
                $check_in_detail->update([
                    'number_of_hours' => 24,
                ]);
            } elseif ($this->get_new_rate < 0) {
                $positive_new_rate = $this->get_new_rate * -1;
                $check_in_detail->update([
                    'number_of_hours' => $positive_new_rate,
                ]);
            } else {
                $check_in_detail->update([
                    'number_of_hours' => $this->get_new_rate,
                ]);
            }

            $check_in_detail->update([
                'check_out_at' =>  \Carbon\Carbon::parse($check_in_detail->check_out_at)->addHours($rate->hour),
            ]);

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

            $extended_guest = ExtendedGuestReport::where('branch_id', auth()->user()->branch_id)->where('checkin_details_id', $check_in_detail->id)->first();

            if($extended_guest != null)
            {
                $extended_guest->update([
                   'number_of_extension' => $extended_guest->number_of_extension + 1,
                   'total_hours' => $extended_guest->total_hours + $rate->hour,
                ]);
            }else{
                ExtendedGuestReport::create([
                    'branch_id' => auth()->user()->branch_id,
                    'room_id' =>  $check_in_detail->room_id,
                    'checkin_details_id' => $check_in_detail->id,
                    'number_of_extension' => 1,
                    'total_hours' => $rate->hour,
                    'shift' => $shift_schedule,
                    'frontdesk_id' => $decode_frontdesk[0],
                    'partner_name' => $decode_frontdesk[1],
                ]);
            }
            ActivityLog::create([
                'branch_id' => auth()->user()->branch_id,
                'user_id' => auth()->user()->id,
                'activity' => 'Add Extension',
                'description' => 'Added new extension of ₱' . $this->total_get_rate . ' for guest ' . $check_in_detail->guest->name,
            ]);

            DB::commit();
            if($this->extend_type === 'savePay')
            {
                $this->payTransaction($transaction->id);
            }elseif($this->extend_type === 'savePayDeposit')
            {
                $this->payWithDeposit($transaction->id);
            }else{
                $this->dialog()->success(
                    $title = 'Success',
                    $description = 'Extend successfully saved'
                );
                $this->reset('extend_rate', 'get_new_rate');
                $this->extend_modal = false;

                $this->closeModal();

                return redirect()->route('frontdesk.guest-transaction', [
                    'id' => $this->guest_id,
                ]);
            }

        }

    }

    public function deductDeposit()
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
            $check_in_detail = CheckinDetail::where(
                'guest_id',
                $this->guest_id
            )->first();
            $this->validate([
                'deduction_amount' =>
                    'required|lte:' .
                    ($this->deposit_except_remote_and_key -
                        $check_in_detail->total_deduction),
            ]);
            DB::beginTransaction();
            $check_in_detail = CheckinDetail::where(
                'guest_id',
                $this->guest_id
            )->first();
            $current_deduction = $check_in_detail->total_deduction;
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
                'branch_id' => $check_in_detail->guest->branch_id,
                'shift_log_id' => $shiftLogId,
                'checkin_detail_id' => $check_in_detail->id,
                'cash_drawer_id' => auth()->user()->cash_drawer_id,
                'room_id' => $check_in_detail->room_id,
                'guest_id' => $check_in_detail->guest_id,
                'floor_id' => $check_in_detail->room->floor_id,
                'transaction_type_id' => 5,
                'assigned_frontdesk_id' => json_encode(
                    $this->assigned_frontdesk
                ),
                'description' => 'Cashout',
                'payable_amount' => $this->deduction_amount,
                'paid_amount' => 0,
                'change_amount' => 0,
                'deposit_amount' => $this->deduction_amount,
                'paid_at' => now(),
                'override_at' => null,
                'remarks' =>
                    'Guest Deduction of Deposit: ₱' .
                    $this->deduction_amount .
                    ' deducted.',
                'shift' => (now()->hour >= 8 && now()->hour < 20) ? 'AM' : 'PM',
            ]);

            $check_in_detail->update([
                'total_deduction' =>
                    $current_deduction + $this->deduction_amount,
            ]);
            ActivityLog::create([
                'branch_id' => auth()->user()->branch_id,
                'user_id' => auth()->user()->id,
                'activity' => 'Deduct Deposit',
                'description' => 'Deducted deposit of ₱' . $this->deduction_amount . ' for guest ' . $check_in_detail->guest->name,
            ]);

            DB::commit();
            $this->deposit_deduct_modal = false;
            $this->dialog()->success(
                $title = 'Success',
                $description = 'Data successfully saved'
            );
            $this->deposit_deduct_modal = false;

            return redirect()->route('frontdesk.guest-transaction', [
                'id' => $this->guest_id,
            ]);
        }
    }

    public function updatedFoodId()
    {
        if ($this->food_id != 'Select Item') {
            $food = FrontdeskMenu::where('branch_id', auth()->user()->branch_id)
                ->where('id', $this->food_id)
                ->first();
            if ($this->food_quantity == null || $this->food_quantity == 0) {
                $this->food_price = $food->price;
                $this->food_number_of_stock = $food->frontdeskInventory->number_of_serving;
                $this->food_subtotal = $food->price * 1;
                $this->food_total_amount = $food->price * 1;
            } else {
                $this->food_price = $food->price;
                $this->food_number_of_stock = $food->frontdeskInventory->number_of_serving;
                $this->food_subtotal = $food->price * $this->food_quantity;
                $this->food_total_amount = $food->price * $this->food_quantity;
            }
        } else {
            $this->food_price = 0;
        }
    }

    public function updatedFoodQuantity()
    {
        if ($this->food_id != 'Select Item') {
            $food = FrontdeskMenu::where('branch_id', auth()->user()->branch_id)
                ->where('id', $this->food_id)
                ->first();
            if ($this->food_quantity == null || $this->food_quantity == 0) {
                $this->food_price = $food->price;
                $this->food_number_of_stock = $food->frontdeskInventory->number_of_serving;
                $this->food_subtotal = $food->price * 1;
                $this->food_total_amount = $food->price * 1;
            } else {
                $this->food_price = $food->price;
                $this->food_number_of_stock = $food->frontdeskInventory->number_of_serving;
                $this->food_subtotal = $food->price * $this->food_quantity;
                $this->food_total_amount = $food->price * $this->food_quantity;
            }
        } else {
            $this->food_price = 0;
        }
    }

    public function confirmFood()
    {
        $this->food_type = 'save';
        $this->dialog()->confirm([
            'title' => 'Are you Sure?',
            'description' => 'Save the information?',
            'icon' => 'question',
            'accept' => [
                'label' => 'Yes, save it',
                'method' => 'addFood',
            ],
            'reject' => [
                'label' => 'No, cancel',
            ],
        ]);
    }

    public function confirmFoodPay()
    {
        $this->food_type = 'savePay';
        $this->dialog()->confirm([
            'title' => 'Are you Sure?',
            'description' => 'Save the information?',
            'icon' => 'question',
            'accept' => [
                'label' => 'Yes, save it',
                'method' => 'addFood',
            ],
            'reject' => [
                'label' => 'No, cancel',
            ],
        ]);
    }

    public function confirmFoodPayDeposit()
    {
        $this->food_type = 'saveDeposit';
        $this->dialog()->confirm([
            'title' => 'Are you Sure?',
            'description' => 'Save the information?',
            'icon' => 'question',
            'accept' => [
                'label' => 'Yes, save it',
                'method' => 'addFood',
            ],
            'reject' => [
                'label' => 'No, cancel',
            ],
        ]);
    }

    public function addFood()
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
        }elseif ($this->food_quantity > $this->food_number_of_stock){
            $this->dialog()->error(
            $title = 'Invalid Food Quantity',
            $description = 'Quantity must be less than or equal to number of stocks/serving left'
            );
        } else {
            $this->validate(
                [
                    'food_id' => 'required',
                    'food_quantity' => 'required|gt:0',
                ],
                [
                    'food_id.required' => 'This field is required',
                    'food_quantity.required' => 'This field is required',
                ]
            );
            DB::beginTransaction();
            $check_in_detail = CheckinDetail::where(
                'guest_id',
                $this->guest_id
            )->first();

            $food = FrontdeskMenu::where('branch_id', auth()->user()->branch_id)
                ->where('id', $this->food_id)
                ->first();
            $inventory = FrontdeskInventory::where(
                'branch_id',
                auth()->user()->branch_id
            )
                ->where('frontdesk_menu_id', $this->food_id)
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
                'transaction_type_id' => 9,
                'assigned_frontdesk_id' => json_encode(
                    $this->assigned_frontdesk
                ),
                'description' => 'Food and Beverages',
                'payable_amount' => $this->food_total_amount,
                'paid_amount' => 0,
                'change_amount' => 0,
                'deposit_amount' => 0,
                'paid_at' => null,
                'override_at' => null,
                'remarks' =>
                    'Guest Added Food and Beverages: (Front Desk) (' .$this->food_quantity .')' .' '.$food->name,
                'shift' => (now()->hour >= 8 && now()->hour < 20) ? 'AM' : 'PM',
            ]);
            //update stock
            $new_stock = $inventory->number_of_serving - $this->food_quantity;
            $inventory->update([
                'number_of_serving' => $new_stock,
            ]);

            ActivityLog::create([
                'branch_id' => auth()->user()->branch_id,
                'user_id' => auth()->user()->id,
                'activity' => 'Add Food and Beverages',
                'description' => 'Added new food and beverages of ₱' . $this->food_total_amount . ' for guest ' . $check_in_detail->guest->name,
            ]);

            DB::commit();
            $this->reset('food_id', 'food_price', 'food_number_of_stock', 'food_quantity', 'food_subtotal', 'food_total_amount');
            $this->food_beverages_modal = false;

            if($this->food_type === 'savePay')
            {
                $this->payTransaction($transaction->id);
            }elseif($this->food_type === 'savePayDeposit')
            {
                $this->payWithDeposit($transaction->id);
            }else
            {
                $this->dialog()->success(
                    $title = 'Success',
                    $description = 'Data successfully saved'
                );

                return redirect()->route('frontdesk.guest-transaction', [
                    'id' => $this->guest_id,
                ]);
            }

        }



    }

    public function updatedItemId()
    {
        $item = RequestableItem::where('id', $this->item_id)->first();
        $this->item_quantity = 1;

        $this->subtotal = $item->price * $this->item_quantity;
        $this->total_amount = $this->additional_amount + $this->subtotal;
    }

    public function updatedItemQuantity()
    {
        if ($this->item_quantity == null || $this->item_quantity == 0) {
            $this->item_quantity = 1;
        }
        $item = RequestableItem::where('id', $this->item_id)->first();
        $this->subtotal = $item->price * $this->item_quantity;
        $this->total_amount = $this->additional_amount + $this->subtotal;
    }

    public function updatedAdditionalAmount()
    {
        if ($this->additional_amount == null || $this->additional_amount == 0) {
            $this->total_amount = $this->subtotal;
        }else{
            $item = RequestableItem::where('id', $this->item_id)->first();
            $this->additional_amount = $this->additional_amount;
            $this->total_amount = $this->additional_amount + $this->subtotal;
        }

    }

    public function confirmAmenities()
    {
        $this->amenities_type = 'save';
        $this->dialog()->confirm([
            'title' => 'Are you Sure?',
            'description' => 'Save the information?',
            'icon' => 'question',
            'accept' => [
                'label' => 'Yes, save it',
                'method' => 'addAmenities',
            ],
            'reject' => [
                'label' => 'No, cancel',
            ],
        ]);
    }

    public function confirmAmenitiesPay()
    {
        $this->amenities_type = 'savePay';
        $this->dialog()->confirm([
            'title' => 'Are you Sure?',
            'description' => 'Save the information?',
            'icon' => 'question',
            'accept' => [
                'label' => 'Yes, save it',
                'method' => 'addAmenities',
            ],
            'reject' => [
                'label' => 'No, cancel',
            ],
        ]);
    }

    public function confirmAmenitiesPayDeposit()
    {
        $this->amenities_type = 'savePayDeposit';
        $this->dialog()->confirm([
            'title' => 'Are you Sure?',
            'description' => 'Save the information?',
            'icon' => 'question',
            'accept' => [
                'label' => 'Yes, save it',
                'method' => 'addAmenities',
            ],
            'reject' => [
                'label' => 'No, cancel',
            ],
        ]);
    }

    public function addAmenities()
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
                    'item_id' => 'required',
                    'item_quantity' => 'required',
                ],
                [
                    'item_id.required' => 'This field is required',
                    'item_quantity.required' => 'This field is required',
                ]
            );
            DB::beginTransaction();
            $check_in_detail = CheckinDetail::where(
                'guest_id',
                $this->guest_id
            )->first();
            $amenities = RequestableItem::where(
                'branch_id',
                auth()->user()->branch_id
            )
                ->where('id', $this->item_id)
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
                'transaction_type_id' => 8,
                'assigned_frontdesk_id' => json_encode(
                    $this->assigned_frontdesk
                ),
                'description' => 'Amenities',
                'payable_amount' => $this->total_amount,
                'paid_amount' => 0,
                'change_amount' => 0,
                'deposit_amount' => 0,
                'paid_at' => null,
                'override_at' => null,
                'remarks' =>
                    'Guest Added Amenities: (' .
                    $this->item_quantity .
                    ')' .
                    ' ' .
                    $amenities->name,
                'shift' => (now()->hour >= 8 && now()->hour < 20) ? 'AM' : 'PM',
            ]);

            ActivityLog::create([
                'branch_id' => auth()->user()->branch_id,
                'user_id' => auth()->user()->id,
                'activity' => 'Add Amenities',
                'description' => 'Added new amenities of ₱' . $this->total_amount . ' for guest ' . $check_in_detail->guest->name,
            ]);

            DB::commit();
            $this->amenities_modal = false;
            $this->reset(
                'item_id',
                'item_quantity',
                'additional_amount',
                'total_amount',
                'subtotal'
            );

            if($this->amenities_type === 'savePay')
            {
                $this->payTransaction($transaction->id);
            }elseif($this->amenities_type === 'savePayDeposit')
            {
                $this->payWithDeposit($transaction->id);
            }else{
                $this->amenities_modal = false;
                $this->dialog()->success(
                    $title = 'Success',
                    $description = 'Data successfully saved'
                );

                return redirect()->route('frontdesk.guest-transaction', [
                    'id' => $this->guest_id,
                ]);
            }

        }


    }

    public function updatedItemIdDamage()
    {
        $item = HotelItems::where('id', $this->item_id_damage)->first();
        $this->item_price_damage = $item->price;

        $this->additional_amount_damage = $this->additional_amount_damage;

        $this->total_amount_damage =
            $this->item_price_damage + $this->additional_amount_damage;
    }

    public function updatedAdditionalAmountDamage()
    {
        if (
            $this->additional_amount_damage == null ||
            $this->additional_amount_damage == 0
        ) {
            $this->additional_amount_damage = 0;
        }
        $this->additional_amount_damage = $this->additional_amount_damage;
        $this->total_amount_damage =
            $this->item_price_damage + $this->additional_amount_damage;
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
                $this->guest_id
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
                'shift' => (now()->hour >= 8 && now()->hour < 20) ? 'AM' : 'PM',
            ]);

            ActivityLog::create([
                'branch_id' => auth()->user()->branch_id,
                'user_id' => auth()->user()->id,
                'activity' => 'Add Damage Charges',
                'description' => 'Added new damage charges of ₱' . $this->total_amount_damage . ' for guest ' . $check_in_detail->guest->name,
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

                return redirect()->route('frontdesk.guest-transaction', [
                    'id' => $this->guest_id,
                ]);
            }
        }
    }

    public function updatedTransferModal()
    {
        $this->types = Type::where(
            'branch_id',
            auth()->user()->branch_id
        )->get();
        $this->floors = Floor::where(
            'branch_id',
            auth()->user()->branch_id
        )->get();
    }
    public function updatedFloorId()
    {
        $this->rooms = Room::where('branch_id', auth()->user()->branch_id)
            ->where('type_id', $this->type_id)
            ->where('floor_id', $this->floor_id)
            ->where('status', 'Available')
            ->get();

    }

    public function updatedTypeId()
    {

        $this->rooms = Room::where('branch_id', auth()->user()->branch_id)
            ->where('type_id', $this->type_id)
            ->where('floor_id', $this->floor_id)
            ->where('status', 'Available')
            ->get();
        $guestss = Guest::where('id', $this->guest_id)->first();
        $hours = $guestss->checkInDetail->hours_stayed;
        $new_room = Rate::where('branch_id', auth()->user()->branch_id)
            ->where('type_id', $this->type_id)
            ->where('is_available', true)
            ->whereHas('stayingHour', function ($query) use ($hours) {
                $query
                    ->where('branch_id', auth()->user()->branch_id)
                    ->where('number', '=', $hours);
            })
            ->first();

        if($new_room === null)
        {
           $this->has_rate = false;
        }else{
            $check_in_details = CheckinDetail::where(
                'guest_id',
                $this->guest_id
            )->first();
            $this->has_rate = true;
            if ($new_room->amount > $check_in_details->static_room_amount) {
                $this->total = $new_room->amount - $check_in_details->static_room_amount;
            } else {
                $this->total = 0;
            }
        }

    }
    public function saveTransfer()
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
            $this->validate([
                'type_id' => 'required',
                'floor_id' => 'required',
                'room_id' => 'required',
                'old_status' => 'required',
                'reason_id' => 'required',
            ]);
            $this->authorization_type = 'saveTransfer';
            $this->autorization_modal = true;
        }
    }

    public function savePayTransfer()
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
            $this->validate([
                'type_id' => 'required',
                'floor_id' => 'required',
                'room_id' => 'required',
                'old_status' => 'required',
                'reason_id' => 'required',
            ]);
            $this->authorization_type = 'savePay';
            $this->autorization_modal = true;
        }
    }

    public function savePayDepositTransfer()
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
            $this->validate([
                'type_id' => 'required',
                'floor_id' => 'required',
                'room_id' => 'required',
                'old_status' => 'required',
                'reason_id' => 'required',
            ]);
            $this->authorization_type = 'savePayDeposit';
            $this->autorization_modal = true;
        }
    }

    public function proceedTransfer()
    {
        $this->validate([
            'code' => 'required',
        ]);

        $autorization_code = auth()->user()->branch->autorization_code;

        if ($this->code != $autorization_code) {
            $this->dialog()->error(
                $title = 'Error',
                $description = 'Invalid Code'
            );
        } else {
            $this->dialog()->confirm([
                'title' => 'Are you Sure?',
                'description' => 'Save the information?',
                'icon' => 'question',
                'accept' => [
                    'label' => 'Yes, save it',
                    'method' => 'addTransfer',
                ],
                'reject' => [
                    'label' => 'No, cancel',
                ],
            ]);
        }
    }

    public function addTransfer()
    {
        $check_in_detail = CheckinDetail::where(
            'guest_id',
            $this->guest_id
        )->first();
        $reason = TransferReason::find($this->reason_id);
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
            'room_id' => $this->room_id,
            'guest_id' => $this->guest_id,
            'floor_id' => $this->floor_id,
            'transaction_type_id' => 7,
            'assigned_frontdesk_id' => json_encode($this->assigned_frontdesk),
            'description' => 'Room Transfer',
            'payable_amount' => $this->total,
            'paid_amount' => 0,
            'change_amount' => 0,
            'deposit_amount' => 0,
            'paid_at' => null,
            'override_at' => null,
            'remarks' =>
                'Guest Transfered from Room #' .
                Room::where('id', $check_in_detail->room_id)->first()->number .
                ' (' .
                Type::where('id', $check_in_detail->type_id)->first()->name .
                ') to Room #' .
                Room::where('id', $this->room_id)->first()->number .
                ' (' .
                Type::where('id', $this->type_id)->first()->name .
                ') - Reason: ' .
                $reason->reason,
            'transfer_reason_id' => $this->reason_id,
            'shift' => (now()->hour >= 8 && now()->hour < 20) ? 'AM' : 'PM',
        ]);

        if($this->old_status === "Uncleaned")
        {
            Room::where('id', $check_in_detail->room_id)->update([
                'time_to_clean' => now()->addHours(4),
            ]);

        }

        // Stamp source room's check_out_time on transfer so the roomboy queue
        // (sorted ASC by check_out_time) ranks it as recently vacated rather
        // than inheriting the prior guest's stale checkout. Mirrors the fix
        // already applied to TransferRoom::saveTransfer.
         Room::where('id', $check_in_detail->room_id)->update([
            'status'         => $this->old_status,
            'check_out_time' => now()->toDateTimeString(),
        ]);

        Room::where('id',  $this->room_id)->update([
            'status' => 'Occupied',
        ]);


        Guest::where('id', $this->guest_id)->update([
            'previous_room_id' => $check_in_detail->room_id,
            'room_id' => $this->room_id,
        ]);


        $new_room = Room::where('id',  $this->room_id)->first();
        CheckinDetail::where('guest_id', $this->guest_id)->update([
            'type_id' => $this->type_id,
            'room_id' => $this->room_id,
        ]);

        ActivityLog::create([
            'branch_id' => auth()->user()->branch_id,
            'user_id' => auth()->user()->id,
            'activity' => 'Room Transfer',
            'description' => 'Guest ' . $check_in_detail->guest->name . ' transferred from Room #' . Room::where('id', $check_in_detail->room_id)->first()->number . ' to Room #' . $new_room->number,
        ]);

        DB::commit();
        if($this->authorization_type == 'savePay')
        {
            $this->autorization_modal = false;
            $this->payTransaction($transaction->id);
        }elseif($this->authorization_type == 'savePayDeposit')
        {
            $this->autorization_modal = false;
            $this->payWithDeposit($transaction->id);
        }else{
            $this->dialog()->success(
                $title = 'Success',
                $description = 'Room Transferred'
            );
            $this->transfer_modal = false;
            $this->autorization_modal = false;
            $this->reset(
                'type_id',
                'floor_id',
                'room_id',
                'old_status',
                'reason',
                'total'
            );

            return redirect()->route('frontdesk.guest-transaction', [
                'id' => $this->guest_id,
            ]);
        }
    }

    public function payTransaction($transaction_id)
    {
        $transaction = Transaction::where('id', $transaction_id)->first();
        $this->pay_transaction_id = $transaction->id;
        $this->pay_transaction_amount = $transaction->payable_amount;
        $this->pay_modal = true;
    }

    public function updatedPayAmount()
    {
        $this->validate([
            'pay_amount' =>
                'required|numeric|min:' . $this->pay_transaction_amount . '',
        ]);

        if ($this->pay_amount > $this->pay_transaction_amount) {
            $this->pay_excess =
                $this->pay_amount - $this->pay_transaction_amount;
        } else {
            $this->pay_excess = 0;
        }
    }

    public function addPayment()
    {
        $this->validate([
            'pay_amount' =>
                'required|numeric|min:' . $this->pay_transaction_amount . '',
        ],
        [
            'pay_amount.required' => 'The amount field is required',
            'pay_amount.min' => 'The amount must be greater than or equal to the payable amount',

        ]);

        $transaction = Transaction::where(
            'id',
            $this->pay_transaction_id
        )->first();

        $check_in_detail = CheckinDetail::where('guest_id', $this->guest_id)->first();

        DB::beginTransaction();
        $transaction->update([
            'paid_amount' => $this->pay_amount,
            'change_amount' => $this->pay_excess,
            'paid_at' => now(),
            'deposit_amount' => $this->pay_excess,
        ]);

        //save cash on drawer
        CashOnDrawer::create([
            'branch_id' => auth()->user()->branch_id,
            'frontdesk_id' => auth()->user()->frontdesk->id,
            'cash_drawer_id' => auth()->user()->cash_drawer_id,
            'amount' => $transaction->payable_amount,
            'transaction_date' => now()->toDateString(),
            'transaction_type' => 'Payment from '.$transaction->description,
            'shift' => (now()->hour >= 8 && now()->hour < 20) ? 'AM' : 'PM',
        ]);

        if ($this->saveAsExcess == true) {
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
                'transaction_type_id' => 2,
                'assigned_frontdesk_id' => json_encode(
                    $this->assigned_frontdesk
                ),
                'description' => 'Deposit',
                'payable_amount' => $this->pay_excess,
                'paid_amount' => $this->pay_excess,
                'change_amount' => 0,
                'deposit_amount' => 0,
                'paid_at' => now(),
                'override_at' => null,
                'remarks' => 'Deposit from paying ' . $transaction->description,
                'shift' => (now()->hour >= 8 && now()->hour < 20) ? 'AM' : 'PM',
            ]);

             CashOnDrawer::create([
            'branch_id' => auth()->user()->branch_id,
            'frontdesk_id' => auth()->user()->frontdesk->id,
            'cash_drawer_id' => auth()->user()->cash_drawer_id,
            'amount' => $this->pay_excess,
            'transaction_date' => now()->toDateString(),
            'transaction_type' => 'deposit',
            'shift' => (now()->hour >= 8 && now()->hour < 20) ? 'AM' : 'PM',
        ]);

            $transaction->guest->checkInDetail->update([
                'total_deposit' =>
                    $transaction->guest->checkInDetail->total_deposit +
                    $this->pay_excess,
            ]);
        }

        ActivityLog::create([
            'branch_id' => auth()->user()->branch_id,
            'user_id' => auth()->user()->id,
            'activity' => 'Payment',
            'description' => 'Payment of ₱' . $this->pay_amount . ' for guest ' . $transaction->guest->name,
        ]);

        DB::commit();

        $this->dialog()->success(
            $title = 'Success',
            $description = 'Payment successfully saved'
        );
        $this->pay_modal = false;

        return redirect()->route('frontdesk.guest-transaction', [
            'id' => $this->guest_id,
        ]);
    }

    public function payWithDeposit($transaction_id)
    {
        $transaction = Transaction::where('id', $transaction_id)->first();
        $this->pay_transaction_id = $transaction->id;
        $this->pay_transaction_amount = $transaction->payable_amount;

        // Exclude room key deposit from available balance (locked until checkout)
        $roomKeyDeposit = Transaction::where('guest_id', $transaction->guest_id)
            ->where('transaction_type_id', 2)
            ->where('remarks', 'Deposit From Check In (Room Key & TV Remote)')
            ->sum('payable_amount');

        $this->render_deposit =
            $transaction->guest->checkInDetail->total_deposit -
            $transaction->guest->checkInDetail->total_deduction -
            $roomKeyDeposit;
        $this->payWithDeposit_modal = true;
    }

    public function addPaymentWithDeposit()
    {
        $transaction = Transaction::where(
            'id',
            $this->pay_transaction_id
        )->first();

        if ($this->render_deposit < $this->pay_transaction_amount) {
            $this->dialog()->error(
                $title = 'Insufficient deposit'
                // $description = 'you don\'t have enough deposit'
            );
        } else {
            DB::beginTransaction();
            $transaction->update([
                'paid_amount' => 0,
                'change_amount' => 0,
                'paid_at' => now(),
                'remarks' => ($transaction->remarks ? $transaction->remarks . ' | ' : '') . 'Paid with deposit',
            ]);
            $check_in_detail = CheckinDetail::where('guest_id', $this->guest_id)->first();
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
                'transaction_type_id' => 5,
                'assigned_frontdesk_id' => json_encode(
                    $this->assigned_frontdesk
                ),
                'description' => 'Cashout',
                'payable_amount' => $this->pay_transaction_amount,
                'paid_amount' => $this->pay_transaction_amount,
                'change_amount' => 0,
                'deposit_amount' => 0,
                'paid_at' => now(),
                'override_at' => null,
                'remarks' => 'Cashout from paying deposit ('.$transaction->description.')',
                'shift' => (now()->hour >= 8 && now()->hour < 20) ? 'AM' : 'PM',
            ]);

            //save deduction
            CashOnDrawer::create([
                'branch_id' => auth()->user()->branch_id,
                'frontdesk_id' => auth()->user()->frontdesk->id,
                'cash_drawer_id' => auth()->user()->cash_drawer_id,
                'deduction' => $this->pay_transaction_amount,
                'transaction_date' => now()->toDateString(),
                'transaction_type' => 'Payment from deposit - '.$transaction->description,
                'shift' => (now()->hour >= 8 && now()->hour < 20) ? 'AM' : 'PM',
            ]);

            $transaction->guest->checkInDetail->update([
                'total_deduction' =>
                    $transaction->guest->checkInDetail->total_deduction +
                    $this->pay_transaction_amount,
            ]);

            ActivityLog::create([
                'branch_id' => auth()->user()->branch_id,
                'user_id' => auth()->user()->id,
                'activity' => 'Payment with Deposit',
                'description' => 'Payment of ₱' . $this->pay_transaction_amount . ' with deposit for guest ' . $transaction->guest->name,
            ]);

            DB::commit();

            $this->dialog()->success(
                $title = 'Success',
                $description = 'Payment successfully saved'
            );
            $this->payWithDeposit_modal = false;
        }

        return redirect()->route('frontdesk.guest-transaction', [
            'id' => $this->guest_id,
        ]);
    }

    public function payAll()
    {
        $this->dialog()->confirm([
            'title' => 'Are you sure?',
            'description' => 'This will pay all the unpaid balances.',
            'icon' => 'question',
            'accept' => [
                'label' => 'Yes',
                'method' => 'payAllUnpaid',
            ],
            'reject' => [
                'label' => 'No, cancel',
                'method' => 'closeModal',
            ],
        ]);
    }

    public function payAllUnpaid()
    {
        $payable = Transaction::where('branch_id', auth()->user()->branch_id)
            ->where('guest_id', $this->guest_id)
            ->whereNull('paid_at')->sum('payable_amount');

        Transaction::where('branch_id', auth()->user()->branch_id)
            ->where('guest_id', $this->guest_id)
            ->whereNull('paid_at')
            ->update([
                'paid_at' => now(),
                'paid_amount' => DB::raw('payable_amount'),]);

        //save cash on drawer
        CashOnDrawer::create([
            'branch_id' => auth()->user()->branch_id,
            'frontdesk_id' => auth()->user()->frontdesk->id,
            'cash_drawer_id' => auth()->user()->cash_drawer_id,
            'amount' => $payable,
            'transaction_date' => now()->toDateString(),
            'transaction_type' => 'Paid all unpaid bills',
            'shift' => (now()->hour >= 8 && now()->hour < 20) ? 'AM' : 'PM',
        ]);

        ActivityLog::create([
            'branch_id' => auth()->user()->branch_id,
            'user_id' => auth()->user()->id,
            'activity' => 'Pay All Unpaid Balances',
            'description' => 'All unpaid balances are paid for guest ' . Guest::where('id', $this->guest_id)->first()->name,
        ]);

        $this->dialog()->success(
            $title = 'Success',
            $description = 'All unpaid balances are paid'
        );

        return redirect()->route('frontdesk.guest-transaction', [
            'id' => $this->guest_id,
        ]);
    }

    public function payAllWithDeposit($total)
    {
        $guest = Guest::where('id', $this->guest_id)->first();
        $this->pay_transaction_amount = $total;

        // Exclude room key deposit from available balance (locked until checkout)
        $roomKeyDeposit = Transaction::where('guest_id', $this->guest_id)
            ->where('transaction_type_id', 2)
            ->where('remarks', 'Deposit From Check In (Room Key & TV Remote)')
            ->sum('payable_amount');

        $this->render_deposit =
            $guest->checkInDetail->total_deposit -
            $guest->checkInDetail->total_deduction -
            $roomKeyDeposit;

        $this->payWithDeposit_modal = true;
    }

    public function addAllPaymentWithDeposit()
    {
        DB::beginTransaction();
        $transaction = Transaction::where(
            'branch_id',
            auth()->user()->branch_id
        )
            ->where('guest_id', $this->guest_id)
            ->first();
        $check_in_detail = CheckinDetail::where('guest_id', $this->guest_id)->first();
        Transaction::where('branch_id', auth()->user()->branch_id)
            ->where('guest_id', $this->guest_id)
            ->whereNull('paid_at')
            ->update([
                'paid_at' => now(),
                'paid_amount' => 0,
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
            'branch_id' => $transaction->branch_id,
            'shift_log_id' => $shiftLogId,
            'checkin_detail_id' => $check_in_detail->id,
            'cash_drawer_id' => auth()->user()->cash_drawer_id,
            'room_id' => $transaction->room_id,
            'guest_id' => $transaction->guest_id,
            'floor_id' => $transaction->floor_id,
            'transaction_type_id' => 5,
            'assigned_frontdesk_id' => json_encode($this->assigned_frontdesk),
            'description' => 'Cashout',
            'payable_amount' => $this->pay_transaction_amount,
            'paid_amount' => $this->pay_transaction_amount,
            'change_amount' => 0,
            'deposit_amount' => 0,
            'paid_at' => now(),
            'override_at' => null,
            'remarks' => 'Cashout from paying all unpaid balances',
            'shift' => (now()->hour >= 8 && now()->hour < 20) ? 'AM' : 'PM',
        ]);

        //save deduction
            CashOnDrawer::create([
                'branch_id' => auth()->user()->branch_id,
                'frontdesk_id' => auth()->user()->frontdesk->id,
                'cash_drawer_id' => auth()->user()->cash_drawer_id,
                'deduction' => $this->pay_transaction_amount,
                'transaction_date' => now()->toDateString(),
                'transaction_type' => 'Payment from deposit - '.$transaction->description,
                'shift' => (now()->hour >= 8 && now()->hour < 20) ? 'AM' : 'PM',
            ]);

        $transaction->guest->checkInDetail->update([
            'total_deduction' =>
                $transaction->guest->checkInDetail->total_deduction +
                $this->pay_transaction_amount,
        ]);

        ActivityLog::create([
            'branch_id' => auth()->user()->branch_id,
            'user_id' => auth()->user()->id,
            'activity' => 'Payment with Deposit',
            'description' => 'Payment of ₱' . $this->pay_transaction_amount . ' with deposit for guest ' . $transaction->guest->name,
        ]);

        DB::commit();

        $this->dialog()->success(
            $title = 'Success',
            $description = 'Payment successfully saved'
        );
        $this->payWithDeposit_modal = false;

        return redirect()->route('frontdesk.guest-transaction', [
            'id' => $this->guest_id,
        ]);
    }

    public function claimAll()
    {
        $this->dialog()->confirm([
            'title' => 'Are you sure you want to claim all deposit?',
            'icon' => 'question',
            'accept' => [
                'label' => 'Yes',
                'method' => 'claimAllDeposit',
            ],
            'reject' => [
                'label' => 'No, cancel',
                'method' => 'closeModal',
            ],
        ]);
    }

    public function claimAllDeposit()
    {
        $guest = Guest::where('id', $this->guest_id)->first();
        if ($this->is_checkout) {
            $balance =
                $this->deposit_remote_and_key +
                ($this->deposit_except_remote_and_key -
                    $guest->checkInDetail->total_deduction);
        } else {
            $balance =
                $this->deposit_except_remote_and_key -
                $guest->checkInDetail->total_deduction;
        }

        $transaction = Transaction::where(
            'branch_id',
            auth()->user()->branch_id
        )
            ->where('guest_id', $this->guest_id)
            ->first();
        DB::beginTransaction();
        $guest->checkInDetail->update([
            'total_deduction' =>
                $guest->checkInDetail->total_deduction + $balance,
        ]);

        if (!$this->is_checkout) {
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
                'checkin_detail_id' => $guest->checkInDetail->id,
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
                'shift' => (now()->hour >= 8 && now()->hour < 20) ? 'AM' : 'PM',
            ]);
        }

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
            'checkin_detail_id' => $guest->checkInDetail->id,
            'cash_drawer_id' => auth()->user()->cash_drawer_id,
            'room_id' => $transaction->room_id,
            'guest_id' => $transaction->guest_id,
            'floor_id' => $transaction->floor_id,
            'transaction_type_id' => 5,
            'assigned_frontdesk_id' => json_encode($this->assigned_frontdesk),
            'description' => 'Cashout',
            'payable_amount' => $balance,
            'paid_amount' => $balance,
            'change_amount' => 0,
            'deposit_amount' => 0,
            'paid_at' => now(),
            'override_at' => null,
            'remarks' => 'Cashout all deposits',
            'shift' => (now()->hour >= 8 && now()->hour < 20) ? 'AM' : 'PM',
        ]);

        ActivityLog::create([
            'branch_id' => auth()->user()->branch_id,
            'user_id' => auth()->user()->id,
            'activity' => 'Claim All Deposit',
            'description' => 'All deposits are claimed for guest ' . $transaction->guest->name,
        ]);

        DB::commit();
        $this->dialog()->success(
            $title = 'Success',
            $description = 'All deposits are claimed'
        );
        $this->reminderIndex = 4;
        $this->reminders_modal = true;

        // return redirect()->route('frontdesk.guest-transaction', [
        //     'id' => $this->guest_id,
        // ]);
    }

    public function checkOut()
    {
        $guest = Guest::where('id', $this->guest_id)->first();
       if($guest->has_kiosk_check_out)
        {

            $bills_unpaid = Transaction::selectRaw(
                'sum(payable_amount) as total_payable_amount, transaction_type_id'
            )
                ->where('branch_id', auth()->user()->branch_id)
                ->where('guest_id', $this->guest_id)
                ->whereNull('paid_at')
                ->groupBy('transaction_type_id')
                ->get();

            $total_payable = $bills_unpaid
                ->filter(function ($bill) {
                    return $bill->transaction_type->name != 'Deposit';
                })
                ->sum('total_payable_amount');

            if ($total_payable > 0) {
                $this->dialog()->confirm([
                    'title' => 'Unable to Check Out',
                    'description' => 'All unpaid balances must be paid first.',
                    'acceptLabel' => 'Ok',
                    'method' => 'closeModal',
                    'reject' => [
                        'label' => 'Cancel',
                        'method' => 'closeModal',
                    ],
                ]);
                // return redirect()->route('frontdesk.manage-guest', [
                //     'id' => $this->guest->id,
                // ]);
            } else {
                // return redirect()->route('frontdesk.check-out-guest', [
                //     'record' => $this->guest_id,
                // ]);
                //  $this->reminders_modal = true;
                $this->proceedCheckout();
            }
        }else{
             $this->dialog()->confirm([
                    'title' => 'Unable to Check Out',
                    'description' => 'Guest must check out using kiosk first.',
                    'acceptLabel' => 'Ok',
                    'method' => 'closeModal',
                    'reject' => [
                        'label' => 'Cancel',
                        'method' => 'closeModal',
                    ],
                ]);
        }

    }

    public function room_and_key_available()
    {
        $this->is_checkout = true;
        $this->reminderIndex++;
    }

    public function room_and_key_unavailable()
    {
        $this->is_checkout = false;
        $this->reminderIndex++;
    }

    public function incrementReminderIndex()
    {
        if ($this->reminderIndex < count($this->reminders) - 1) {
            $this->reminderIndex++;
        }
    }

    public function decrementReminderIndex()
    {
        if ($this->reminderIndex > 0) {
            $this->reminderIndex--;
        }
    }

    public function proceedCheckout()
    {
        $this->reminders_modal = false;
        $this->dialog()->confirm([
            'title' => 'Proceed Checkout?',
            'description' => 'continue to checkout guest.',
            'acceptLabel' => 'Ok',
            'method' => 'checkoutGuest',
        ]);
    }

    public function checkoutGuest()
    {
        $guest = Guest::where('id', $this->guest_id)->first();
        DB::beginTransaction();
        Room::where('branch_id', auth()->user()->branch_id)
            ->where('id', $guest->room_id)
            ->update([
                'status' => 'Uncleaned',
                'last_checkin_at' => $guest->checkInDetail->check_in_at,
                'last_checkout_at' => Carbon::now()->toDateTimeString(),
                'check_out_time' => Carbon::now()->toDateTimeString(),
                'time_to_clean' => now()->addHours(4),
            ]);
        $checkin = CheckinDetail::where('guest_id', $this->guest_id)->first();
        $checkin->update([
            'is_check_out' => true,
            'check_out_at' => Carbon::now()->toDateTimeString(),
        ]);
        // CheckinDetail::where('guest_id', $this->guest_id)->update([
        //     'is_check_out' => true,
        // ]);

        $shift_date = Carbon::parse(auth()->user()->time_in)->format('F j, Y');
        $shift = Carbon::parse(auth()->user()->time_in)->format('H:i');
        $hour = Carbon::parse($shift)->hour;

        if ($hour >= 8 && $hour < 20) {
            $shift_schedule = 'AM';
        } else {
            $shift_schedule = 'PM';
        }

        $decode_frontdesk = json_decode(
            auth()->user()->assigned_frontdesks,
            true
        );
        CheckOutGuestReport::create([
            'checkin_details_id' => $checkin->id,
            'room_id' => $checkin->room_id,
            'shift_date' => $shift_date,
            'shift' => (now()->hour >= 8 && now()->hour < 20) ? 'AM' : 'PM',
            'frontdesk_id' => $decode_frontdesk[0],
            'partner_name' => $decode_frontdesk[1],
        ]);

        ActivityLog::create([
            'branch_id' => auth()->user()->branch_id,
            'user_id' => auth()->user()->id,
            'activity' => 'Check Out',
            'description' => 'Checked out guest ' . $guest->name . ' from Room #' . $guest->room->number,
        ]);

        DB::commit();
        $this->dialog()->success(
            $title = 'Success',
            $description = 'Checkout successful'
        );

        return redirect()->route('frontdesk.room-monitoring');
    }

    public function override($transaction_id)
    {
        $transaction = Transaction::where('id', $transaction_id)->first();
        $this->pay_transaction_id = $transaction->id;
        $this->pay_transaction_amount = $transaction->payable_amount;
        $this->remarks = $transaction->remarks;
        $this->override_modal = true;
        $this->override_amount = null;
    }

    public function addOverride()
    {
        $this->validate([
            'override_amount' => 'required|numeric',
        ]);
        $this->authorization_type = 'override';
        $this->autorization_modal = true;
    }

    public function proceedOverride()
    {
        $this->validate([
            'code' => 'required',
        ]);

        $autorization_code = auth()->user()->branch->autorization_code;

        if ($this->code != $autorization_code) {
            $this->dialog()->error(
                $title = 'Error',
                $description = 'Invalid Code'
            );
        } else {
            $this->dialog()->confirm([
                'title' => 'Are you Sure?',
                'description' => 'Save the information?',
                'icon' => 'question',
                'accept' => [
                    'label' => 'Yes, save it',
                    'method' => 'saveOverride',
                ],
                'reject' => [
                    'label' => 'No, cancel',
                ],
            ]);
        }
    }

    public function saveOverride()
    {
        $transaction = Transaction::where(
            'id',
            $this->pay_transaction_id
        )->first();

        DB::beginTransaction();
        $transaction->update([
            'payable_amount' => $this->override_amount,
            'change_amount' => 0,
            'paid_at' => now(),
            'deposit_amount' => 0,
            'override_at' => now(),
            'remarks' =>
                $this->remarks .
                ' | Override Payable Amount: ₱' .
                number_format($this->override_amount, 2),
        ]);

        //save cash on drawer
        CashOnDrawer::create([
            'branch_id' => auth()->user()->branch_id,
            'frontdesk_id' => auth()->user()->frontdesk->id,
            'cash_drawer_id' => auth()->user()->cash_drawer_id,
            'amount' => $transaction->payable_amount,
            'transaction_date' => now()->toDateString(),
            'transaction_type' => 'Override Payment from '.$transaction->description,
            'shift' => (now()->hour >= 8 && now()->hour < 20) ? 'AM' : 'PM',
        ]);

        ActivityLog::create([
            'branch_id' => auth()->user()->branch_id,
            'user_id' => auth()->user()->id,
            'activity' => 'Override Transaction',
            'description' => 'Overrode transaction amount to ₱' . $this->override_amount . ' for guest ' . $transaction->guest->name,
        ]);

        DB::commit();

        $this->dialog()->success(
            $title = 'Success',
            $description = 'Override successfully saved'
        );
        $this->override_modal = false;
        $this->autorization_modal = false;

        return redirect()->route('frontdesk.guest-transaction', [
            'id' => $this->guest_id,
        ]);
    }

    public function chargeForDamages()
    {
        $this->reminders_modal = false;
        $this->damage_modal = true;
    }

    private function isUserOnline($user, $threshold) { return $user->sessions() ->where('last_activity', '>=', $threshold) ->exists(); }

    public function proceedCancel()
    {
        $this->validate([
            'code' => 'required',
        ]);

        $autorization_code = auth()->user()->branch->autorization_code;

        if ($this->code != $autorization_code) {
            $this->code = null; // Reset the code field
            $this->dialog()->error(
                $title = 'Error',
                $description = 'Invalid Code'
            );
        } else {
            $this->dialog()->confirm([
                'title' => 'Are you sure you want to cancel the transaction?',
                'description' => 'This action cannot be undone.',
                'icon' => 'question',
                'accept' => [
                    'label' => 'Yes, cancel it',
                    'method' => 'confirmCancel',
                ],
                'reject' => [
                    'label' => 'No',
                ],
            ]);
        }
    }

    public function confirmCancel()
    {
        DB::beginTransaction();
        $check_in_detail = CheckinDetail::where(
            'guest_id',
            $this->guest_id
        )->first();
        $check_in_detail->room->update([
            'status' => 'Available',
        ]);
        ActivityLog::create([
            'branch_id' => auth()->user()->branch_id,
            'user_id' => auth()->user()->id,
            'activity' => 'Cancel Transaction',
            'description' => 'Cancelled transaction for guest ' . $check_in_detail->guest->name,
        ]);

        $check_in_detail->delete();

        Transaction::where('guest_id', $this->guest_id)
            ->delete();

        Guest::where('id', $this->guest_id)->delete();


        DB::commit();

        $this->dialog()->success(
            $title = 'Success',
            $description = 'Transaction cancelled'
        );

        return redirect()->route('frontdesk.room-monitoring');
    }

    public function cancelTransaction()
    {
        $this->deposit_summary_modal = true;
        // $this->autorization_cancel_modal = true;
    }

    public function proceedAuthorization()
    {
        $this->deposit_summary_modal = false;
        $this->autorization_cancel_modal = true;
    }

    //transfer room page
    public function redirectToTransferRoom()
    {
        return redirect()->route('frontdesk.transfer-room', [
            'record' => $this->guest_id,
        ]);
    }


    //extend guest page
    public function redirectToExtendGuest()
    {
        return redirect()->route('frontdesk.extend-guest', [
            'record' => $this->guest_id,
        ]);
    }
}
