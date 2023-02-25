<?php

namespace App\Http\Livewire\Frontdesk\Monitoring;

use Livewire\Component;
use App\Models\Guest;
use App\Models\Transaction;
use App\Models\CheckinDetail;
use App\Models\HotelItems;
use App\Models\RequestableItem;
use WireUi\Traits\Actions;
use App\Models\ExtensionRate;
use App\Models\Type;
use App\Models\Floor;
use App\Models\Room;
use App\Models\Rate;
use App\Models\Menu;
use App\Models\Inventory;
use App\Models\AssignedFrontdesk;
use App\Models\StayExtension;
use Carbon\Carbon;
use DB;

class ManageGuestTransaction extends Component
{
    use Actions;
    public $guest;
    public $transaction;
    public $transaction_description;
    public $items;
    //Amenities
    public $amenities;
    public $item_id;
    public $item_quantity;
    public $item_price;
    public $subtotal;
    public $additional_amount;
    public $total_amount;
    //Damage Charges
    public $item_id_damage;
    public $item_price_damage;
    public $additional_amount_damage;
    public $total_amount_damage;
    //Deposit
    public $deposit_amount;
    public $deposit_remarks;
    public $deduction_amount;
    public $total_deposit;
    public $deposit_remote_and_key;
    public $deposit_except_remote_and_key;

    public $extension_rates = [];
    public $types = [];
    public $floors = [];
    public $rooms = [];

    //modals
    public $transfer_modal = false;
    public $deposit_modal = false;
    public $deposit_deduct_modal = false;
    public $extend_modal = false;
    public $damage_modal = false;
    public $amenities_modal = false;
    public $food_beverages_modal = false;
    public $autorization_modal = false;
    public $pay_modal = false;
    public $payWithDeposit_modal = false;
    public $payAllWithDeposit_modal = false;
    public $reminders_modal = false;
    public $override_modal = false;

    //extend
    public $extend_rate;
    public $get_hour;
    public $total_get_rate;
    public $remaining_hours;

    //transfer
    public $type_id;
    public $floor_id;
    public $room_id;
    public $old_status;
    public $reason;
    public $total;
    public $code;

    //food and beverages
    public $food_id;
    public $foods;
    public $food_price;
    public $food_quantity;
    public $food_total_amount;

    //pay
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

    //assigned frontdesk
    public $assigned_frontdesk = [];

    public function mount()
    {
        $this->assigned_frontdesk = auth()->user()->assigned_frontdesks;
        $this->guest = Guest::where('branch_id', auth()->user()->branch_id)
            ->where('id', request()->id)
            ->first();

        $this->item_quantity = 1;
        $this->item_price = 0;
        $this->subtotal = 0;
        $this->additional_amount = 0;
        $this->total_amount = 0;

        $this->item_price_damage = 0;
        $this->additional_amount_damage = 0;
        $this->total_amount_damage = 0;

        $this->food_price = 0;
        $this->food_quantity = 1;

        $this->extension_rates = ExtensionRate::where(
            'branch_id',
            auth()->user()->branch_id
        )->get();

        $this->types = Type::where(
            'branch_id',
            auth()->user()->branch_id
        )->get();

        $this->floors = Floor::where(
            'branch_id',
            auth()->user()->branch_id
        )->get();
    }

    public function updated($item)
    {
        if ($item == 'item_id') {
            if ($this->item_id != 'Select Item') {
                if (
                    $this->item_quantity != '' &&
                    $this->additional_amount != ''
                ) {
                    $this->item_price = RequestableItem::where(
                        'branch_id',
                        auth()->user()->branch_id
                    )
                        ->where('id', $this->item_id)
                        ->first()->price;

                    $this->subtotal = $this->item_price * $this->item_quantity;
                    $this->total_amount =
                        $this->subtotal + $this->additional_amount;
                } elseif (
                    $this->item_quantity != '' &&
                    $this->additional_amount == ''
                ) {
                    $this->item_price = RequestableItem::where(
                        'branch_id',
                        auth()->user()->branch_id
                    )
                        ->where('id', $this->item_id)
                        ->first()->price;
                    $this->subtotal = $this->item_price * $this->item_quantity;
                    $this->total_amount = $this->subtotal + 0;
                } elseif (
                    $this->additional_amount != '' &&
                    $this->item_quantity == ''
                ) {
                    $this->item_price = RequestableItem::where(
                        'branch_id',
                        auth()->user()->branch_id
                    )
                        ->where('id', $this->item_id)
                        ->first()->price;
                    $this->subtotal = $this->item_price * 1;
                    $this->total_amount =
                        $this->subtotal + $this->additional_amount;
                } else {
                    $this->item_price = RequestableItem::where(
                        'branch_id',
                        auth()->user()->branch_id
                    )
                        ->where('id', $this->item_id)
                        ->first()->price;
                    $this->subtotal = $this->item_price * 1;
                    $this->total_amount = $this->subtotal + 0;
                }
            } else {
                $this->item_quantity = 1;
                $this->additional_amount = 0;
                $this->subtotal = 0;
                $this->total_amount = 0;
            }
        }

        if ($item == 'item_quantity') {
            if ($this->item_id != null) {
                if (
                    $this->item_quantity != '' &&
                    $this->additional_amount != ''
                ) {
                    $this->item_price = RequestableItem::where(
                        'branch_id',
                        auth()->user()->branch_id
                    )
                        ->where('id', $this->item_id)
                        ->first()->price;
                    $this->subtotal = $this->item_price * $this->item_quantity;
                    $this->total_amount =
                        $this->subtotal + $this->additional_amount;
                } elseif (
                    $this->item_quantity == '' &&
                    $this->additional_amount != ''
                ) {
                    $this->item_price = RequestableItem::where(
                        'branch_id',
                        auth()->user()->branch_id
                    )
                        ->where('id', $this->item_id)
                        ->first()->price;
                    $this->subtotal = $this->item_price * 1;
                    $this->total_amount =
                        $this->subtotal + $this->additional_amount;
                } elseif (
                    $this->additional_amount == '' &&
                    $this->item_quantity != ''
                ) {
                    $this->item_price = RequestableItem::where(
                        'branch_id',
                        auth()->user()->branch_id
                    )
                        ->where('id', $this->item_id)
                        ->first()->price;
                    $this->subtotal = $this->item_price * $this->item_quantity;
                    $this->total_amount = $this->subtotal + 0;
                } else {
                    $this->item_price = RequestableItem::where(
                        'branch_id',
                        auth()->user()->branch_id
                    )
                        ->where('id', $this->item_id)
                        ->first()->price;
                    $this->subtotal = $this->item_price * 1;
                    $this->total_amount = $this->subtotal + 0;
                }
            }
        }

        if ($item == 'additional_amount') {
            if ($this->item_id != null) {
                if ($this->item_quantity == '') {
                    $this->item_price = RequestableItem::where(
                        'branch_id',
                        auth()->user()->branch_id
                    )
                        ->where('id', $this->item_id)
                        ->first()->price;
                    $this->subtotal = $this->item_price * 1;
                    $this->total_amount =
                        $this->subtotal + $this->additional_amount;
                } elseif ($this->additional_amount == '') {
                    $this->item_price = RequestableItem::where(
                        'branch_id',
                        auth()->user()->branch_id
                    )
                        ->where('id', $this->item_id)
                        ->first()->price;
                    $this->subtotal = $this->item_price * $this->item_quantity;
                    $this->total_amount = $this->subtotal + 0;
                } elseif (
                    $this->additional_amount == '' &&
                    $this->item_quantity == ''
                ) {
                    $this->item_price = RequestableItem::where(
                        'branch_id',
                        auth()->user()->branch_id
                    )
                        ->where('id', $this->item_id)
                        ->first()->price;
                    $this->subtotal = $this->item_price * 1;
                    $this->total_amount = $this->subtotal + 0;
                } else {
                    $this->item_price = RequestableItem::where(
                        'branch_id',
                        auth()->user()->branch_id
                    )
                        ->where('id', $this->item_id)
                        ->first()->price;
                    $this->subtotal = $this->item_price * $this->item_quantity;
                    $this->total_amount =
                        $this->subtotal + $this->additional_amount;
                }
            } elseif ($this->additional_amount != '') {
                $this->total_amount =
                    $this->subtotal + $this->additional_amount;
            } else {
                $this->total_amount = 0;
            }
        }

        if ($item == 'item_id_damage') {
            if ($this->item_id_damage != 'Select Item') {
                if (
                    $this->item_id_damage != null &&
                    $this->additional_amount_damage == ''
                ) {
                    $this->item_price_damage = HotelItems::where(
                        'branch_id',
                        auth()->user()->branch_id
                    )
                        ->where('id', $this->item_id_damage)
                        ->first()->price;
                    $this->total_amount_damage = $this->item_price_damage + 0;
                } elseif (
                    $this->item_id_damage == null &&
                    $this->additional_amount_damage != ''
                ) {
                    $this->total_amount_damage =
                        0 + $this->additional_amount_damage;
                } else {
                    $this->item_price_damage = HotelItems::where(
                        'branch_id',
                        auth()->user()->branch_id
                    )
                        ->where('id', $this->item_id_damage)
                        ->first()->price;
                    $this->total_amount_damage =
                        $this->item_price_damage +
                        $this->additional_amount_damage;
                }
            } else {
                $this->additional_amount_damage = 0;
                $this->item_price_damage = 0;
                $this->total_amount_damage = 0;
            }
        }

        if ($item == 'additional_amount_damage') {
            if (
                $this->item_id_damage != null &&
                $this->additional_amount_damage == ''
            ) {
                $this->item_price_damage = HotelItems::where(
                    'branch_id',
                    auth()->user()->branch_id
                )
                    ->where('id', $this->item_id_damage)
                    ->first()->price;
                $this->total_amount_damage = $this->item_price_damage + 0;
            } elseif (
                $this->item_id_damage == null &&
                $this->additional_amount_damage != ''
            ) {
                $this->total_amount_damage =
                    0 + $this->additional_amount_damage;
            } else {
                $this->item_price_damage = HotelItems::where(
                    'branch_id',
                    auth()->user()->branch_id
                )
                    ->where('id', $this->item_id_damage)
                    ->first()->price;
                $this->total_amount_damage =
                    $this->item_price_damage + $this->additional_amount_damage;
            }
        }
    }

    public function updatedFoodId()
    {
        if ($this->food_id != 'Select Item') {
            $price = Menu::where('branch_id', auth()->user()->branch_id)
                ->where('id', $this->food_id)
                ->first()->price;
            if ($this->food_quantity == null || $this->food_quantity == 0) {
                $this->food_price = $price * 1;
                $this->food_total_amount = $price * 1;
            } else {
                $this->food_price = $price * $this->food_quantity;
                $this->food_total_amount = $price * $this->food_quantity;
            }
        } else {
            $this->food_price = 0;
        }
    }

    public function updatedFoodQuantity()
    {
        if ($this->food_id != 'Select Item') {
            $price = Menu::where('branch_id', auth()->user()->branch_id)
                ->where('id', $this->food_id)
                ->first()->price;
            if ($this->food_quantity == null || $this->food_quantity == 0) {
                $this->food_price = $price;
                $this->food_total_amount = $price * 1;
            } else {
                $this->food_price = $price;
                $this->food_total_amount = $price * $this->food_quantity;
            }
        } else {
            $this->food_price = 0;
        }
    }

    public function addFood()
    {
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
            $this->guest->id
        )->first();

        $food = Menu::where('branch_id', auth()->user()->branch_id)
            ->where('id', $this->food_id)
            ->first();
        $inventory = Inventory::where('branch_id', auth()->user()->branch_id)
            ->where('menu_id', $this->food_id)
            ->first();
        Transaction::create([
            'branch_id' => $check_in_detail->guest->branch_id,
            'room_id' => $check_in_detail->room_id,
            'guest_id' => $check_in_detail->guest_id,
            'floor_id' => $check_in_detail->room->floor_id,
            'transaction_type_id' => 9,
            'assigned_frontdesk_id' => json_encode($this->assigned_frontdesk),
            'description' => 'Food and Beverages',
            'payable_amount' => $this->food_total_amount,
            'paid_amount' => 0,
            'change_amount' => 0,
            'deposit_amount' => 0,
            'paid_at' => null,
            'override_at' => null,
            'remarks' =>
                'Guest Added Food and Beverages: (' .
                $this->food_quantity .
                ')' .
                ' ' .
                $food->name,
        ]);
        //update stock
        $new_stock =
            $inventory->stock -
            $inventory->default_serving * $this->food_quantity;
        $inventory->update([
            'stock' => $new_stock,
        ]);

        DB::commit();
        $this->food_beverages_modal = false;
        $this->dialog()->success(
            $title = 'Success',
            $description = 'Data successfully saved'
        );
        return redirect()->route('frontdesk.manage-guest', [
            'id' => $this->guest->id,
        ]);
    }

    public function addAmenities()
    {
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
            $this->guest->id
        )->first();
        $amenities = RequestableItem::where(
            'branch_id',
            auth()->user()->branch_id
        )
            ->where('id', $this->item_id)
            ->first();

        Transaction::create([
            'branch_id' => $check_in_detail->guest->branch_id,
            'room_id' => $check_in_detail->room_id,
            'guest_id' => $check_in_detail->guest_id,
            'floor_id' => $check_in_detail->room->floor_id,
            'transaction_type_id' => 8,
            'assigned_frontdesk_id' => json_encode($this->assigned_frontdesk),
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
        ]);
        DB::commit();
        $this->amenities_modal = false;
        $this->dialog()->success(
            $title = 'Success',
            $description = 'Data successfully saved'
        );
        return redirect()->route('frontdesk.manage-guest', [
            'id' => $this->guest->id,
        ]);

        $this->extension_rates = ExtensionRate::where(
            'branch_id',
            auth()->user()->branch_id
        )->get();

        $this->types = Type::where(
            'branch_id',
            auth()->user()->branch_id
        )->get();

        $this->floors = Floor::where(
            'branch_id',
            auth()->user()->branch_id
        )->get();
    }

    public function addDamageCharges()
    {
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
            $this->guest->id
        )->first();
        $damage_charges = HotelItems::where(
            'branch_id',
            auth()->user()->branch_id
        )
            ->where('id', $this->item_id_damage)
            ->first();

        Transaction::create([
            'branch_id' => $check_in_detail->guest->branch_id,
            'room_id' => $check_in_detail->room_id,
            'guest_id' => $check_in_detail->guest_id,
            'floor_id' => $check_in_detail->room->floor_id,
            'transaction_type_id' => 4,
            'assigned_frontdesk_id' => json_encode($this->assigned_frontdesk),
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
        ]);
        DB::commit();
        $this->damage_modal = false;
        $this->dialog()->success(
            $title = 'Success',
            $description = 'Data successfully saved'
        );
        return redirect()->route('frontdesk.manage-guest', [
            'id' => $this->guest->id,
        ]);

        $this->extension_rates = ExtensionRate::where(
            'branch_id',
            auth()->user()->branch_id
        )->get();

        $this->types = Type::where(
            'branch_id',
            auth()->user()->branch_id
        )->get();

        $this->floors = Floor::where(
            'branch_id',
            auth()->user()->branch_id
        )->get();
    }

    public function render()
    {
        $this->transaction = Transaction::where(
            'branch_id',
            auth()->user()->branch_id
        )
            ->where('guest_id', request()->id)
            ->get();

        $bills_paid = Transaction::selectRaw(
            'sum(payable_amount) as total_payable_amount, transaction_type_id'
        )
            ->where('branch_id', auth()->user()->branch_id)
            ->where('guest_id', request()->id)
            ->whereNotNull('paid_at')
            ->groupBy('transaction_type_id')
            ->get();
        $bills_unpaid = Transaction::selectRaw(
            'sum(payable_amount) as total_payable_amount, transaction_type_id'
        )
            ->where('branch_id', auth()->user()->branch_id)
            ->where('guest_id', request()->id)
            ->whereNull('paid_at')
            ->groupBy('transaction_type_id')
            ->get();

        $this->items = HotelItems::where(
            'branch_id',
            auth()->user()->branch_id
        )->get();

        $this->amenities = RequestableItem::where(
            'branch_id',
            auth()->user()->branch_id
        )->get();

        $this->foods = Menu::where(
            'branch_id',
            auth()->user()->branch_id
        )->get();

        $check_in_detail = CheckinDetail::where(
            'guest_id',
            $this->guest->id
        )->first();

        $this->deposit_remote_and_key = Transaction::where(
            'branch_id',
            auth()->user()->branch_id
        )
            ->where('guest_id', $this->guest->id)
            ->where('transaction_type_id', 2)
            ->where('remarks', 'Deposit From Check In (Room Key & TV Remote)')
            ->sum('payable_amount');

        $this->deposit_except_remote_and_key = Transaction::where(
            'branch_id',
            auth()->user()->branch_id
        )
            ->where('guest_id', $this->guest->id)
            ->where('transaction_type_id', 2)
            ->where(
                'remarks',
                '!=',
                'Deposit From Check In (Room Key & TV Remote)'
            )
            ->sum('payable_amount');

        $this->total_deposit =
            $this->deposit_except_remote_and_key -
            $check_in_detail->total_deduction;

        return view('livewire.frontdesk.monitoring.manage-guest-transaction', [
            'amenities' => $this->amenities,
            'items' => $this->items,
            'foods' => $this->foods,
            'transactions' => $this->transaction->groupBy('description'),
            'transaction_bills_paid' => $bills_paid,
            'transaction_bills_unpaid' => $bills_unpaid,
            'check_in_details' => $check_in_detail,
        ]);
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
        $hours = $this->guest->checkInDetail->hours_stayed;
        $new_room = Rate::where('branch_id', auth()->user()->branch_id)
            ->where('type_id', $this->type_id)
            ->where('is_available', true)
            ->whereHas('stayingHour', function ($query) use ($hours) {
                $query
                    ->where('branch_id', auth()->user()->branch_id)
                    ->where('number', '=', $hours);
            })
            ->first();
        if ($new_room->amount > $this->guest->static_amount) {
            $this->total = $new_room->amount - $this->guest->static_amount;
        } else {
            $this->total = 0;
        }
    }

    public function saveTransfer()
    {
        $this->validate([
            'type_id' => 'required',
            'floor_id' => 'required',
            'room_id' => 'required',
            'old_status' => 'required',
            'reason' => 'required',
        ]);

        $this->autorization_modal = true;
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
        DB::beginTransaction();
        Transaction::create([
            'branch_id' => auth()->user()->branch_id,
            'room_id' => $this->room_id,
            'guest_id' => $this->guest->id,
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
                Room::where('id', $this->guest->checkInDetail->room_id)->first()
                    ->number .
                ' (' .
                Type::where('id', $this->guest->checkInDetail->type_id)->first()
                    ->name .
                ') to Room #' .
                Room::where('id', $this->room_id)->first()->number .
                ' (' .
                Type::where('id', $this->type_id)->first()->name .
                ') - Reason: ' .
                $this->reason,
        ]);

        Room::where('id', $this->room_id)->update([
            'status' => $this->old_status,
        ]);

        $this->dialog()->success(
            $title = 'Success',
            $description = 'Room Transfered'
        );
        DB::commit();
        $this->transfer_modal = false;
        $this->autorization_modal = false;
        return redirect()->route('frontdesk.manage-guest', [
            'id' => $this->guest->id,
        ]);
    }

    public function addNewDeposit()
    {
        $this->validate([
            'deposit_amount' => 'required|gt:0',
            'deposit_remarks' => 'required',
        ]);

        DB::beginTransaction();
        $check_in_detail = CheckinDetail::where(
            'guest_id',
            $this->guest->id
        )->first();
        $current_deposit = $check_in_detail->total_deposit;
        Transaction::create([
            'branch_id' => $check_in_detail->guest->branch_id,
            'room_id' => $check_in_detail->room_id,
            'guest_id' => $check_in_detail->guest_id,
            'floor_id' => $check_in_detail->room->floor_id,
            'transaction_type_id' => 2,
            'assigned_frontdesk_id' => json_encode($this->assigned_frontdesk),
            'description' => 'Deposit',
            'payable_amount' => $this->deposit_amount,
            'paid_amount' => 0,
            'change_amount' => 0,
            'deposit_amount' => $this->deposit_amount,
            'paid_at' => now(),
            'override_at' => null,
            'remarks' => 'Guest Deposit: ' . $this->deposit_remarks,
        ]);

        Room::where('id', $this->room_id)->update([
            'status' => $this->old_status,
        ]);
        $check_in_detail->update([
            'total_deposit' => $current_deposit + $this->deposit_amount,
        ]);

        DB::commit();
        $this->deposit_modal = false;
        $this->dialog()->success(
            $title = 'Success',
            $description = 'Data successfully saved'
        );
        return redirect()->route('frontdesk.manage-guest', [
            'id' => $this->guest->id,
        ]);
    }

    public function deductDeposit()
    {

        $check_in_detail = CheckinDetail::where(
            'guest_id',
            $this->guest->id
        )->first();
        $this->validate([
            'deduction_amount' =>
                'required|lte:' . ($this->deposit_except_remote_and_key - $check_in_detail->total_deduction),
        ]);
        DB::beginTransaction();
        $check_in_detail = CheckinDetail::where(
            'guest_id',
            $this->guest->id
        )->first();
        $current_deduction = $check_in_detail->total_deduction;
        Transaction::create([
            'branch_id' => $check_in_detail->guest->branch_id,
            'room_id' => $check_in_detail->room_id,
            'guest_id' => $check_in_detail->guest_id,
            'floor_id' => $check_in_detail->room->floor_id,
            'transaction_type_id' => 5,
            'assigned_frontdesk_id' => json_encode($this->assigned_frontdesk),
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
        ]);

        $check_in_detail->update([
            'total_deduction' => $current_deduction + $this->deduction_amount,
        ]);

        DB::commit();
        $this->deposit_modal = false;
        $this->deposit_deduct_modal = false;
        $this->dialog()->success(
            $title = 'Success',
            $description = 'Data successfully saved'
        );
        return redirect()->route('frontdesk.manage-guest', [
            'id' => $this->guest->id,
        ]);
    }

    public function closeModal()
    {
        return redirect()->route('frontdesk.manage-guest', [
            'id' => $this->guest->id,
        ]);
    }

    public function updatedExtendRate()
    {
        $rate = ExtensionRate::where('branch_id', auth()->user()->branch_id)
            ->where('id', $this->extend_rate)
            ->first();
        $reset_time = auth()->user()->branch->extension_time_reset;
        if (
            Transaction::where('guest_id', $this->guest->id)
                ->where('transaction_type_id', 6)
                ->count() > 0
        ) {
            $remaining_hour = $this->guest->checkInDetail->number_of_hours;
        } else {
            $remaining_hour = $this->guest->checkInDetail->hours_stayed;
        }
        $get_rate = $rate->amount;
        $get_hour = $rate->hour;
        $this->get_hour = $get_hour;

        if ($remaining_hour < $this->get_hour) {
            $total_remaining_hour = $remaining_hour - $this->get_hour;
            $rate = $total_remaining_hour * -1;

            $new_rate = $reset_time - $remaining_hour;

            if ($rate < 6) {
                $this->dialog()->error(
                    $title = 'Error',
                    $description = 'There is no rate below 6 hours'
                );
                $this->extend_rate = null;
                $this->extend_modal = false;
                return redirect()->route('frontdesk.manage-guest', [
                    'id' => $this->guest->id,
                ]);
            } else {
                if ($new_rate == 18) {
                    $new_rate1 = $reset_time - $new_rate;
                    $nextday_rate = $new_rate - $new_rate1;

                    $first_rate = Rate::where(
                        'branch_id',
                        auth()->user()->branch_id
                    )
                        ->where('type_id', $this->guest->checkInDetail->type_id)
                        ->whereHas('stayingHour', function ($query) use (
                            $nextday_rate
                        ) {
                            $query->where('number', $nextday_rate);
                        })
                        ->first()->amount;

                    $second_rate = ExtensionRate::where(
                        'branch_id',
                        auth()->user()->branch_id
                    )
                        ->where('hour', $new_rate1)
                        ->first()->amount;

                    $this->total_get_rate = $first_rate + $second_rate;
                } else {
                    $target = $new_rate;
                    $rate_array = Rate::where(
                        'branch_id',
                        auth()->user()->branch_id
                    )
                        ->where('type_id', $this->guest->checkInDetail->type_id)
                        ->get()
                        ->map(function ($query) {
                            return $query->stayingHour->number;
                        })
                        ->toArray();

                    $min_diff = PHP_INT_MAX;
                    $selected_number = null;

                    foreach ($rate_array as $number) {
                        // Calculate the difference between the target value and the current array element
                        $diff = $target - $number;

                        // Check if the difference is non-negative and smaller than the current minimum difference
                        if ($diff >= 0 && $diff < $min_diff) {
                            $min_diff = $diff;
                            $selected_number = $number;
                        }
                    }

                    if ($selected_number !== null) {
                        $result = $target - $selected_number;
                        // dd("The selected number is $selected_number, and the result is $result.");
                        $new_rate = $selected_number;

                        $first_rate =
                            Rate::where('branch_id', auth()->user()->branch_id)
                                ->where(
                                    'type_id',
                                    $this->guest->checkInDetail->type_id
                                )
                                ->whereHas('stayingHour', function (
                                    $query
                                ) use ($new_rate) {
                                    $query->where('number', $new_rate);
                                })
                                ->first()->amount ?? 0;

                        $second_rate =
                            ExtensionRate::where(
                                'branch_id',
                                auth()->user()->branch_id
                            )
                                ->where('hour', $result)
                                ->first()->amount ?? 0;

                        $this->total_get_rate = $first_rate + $second_rate;
                    } else {
                        echo 'No number in the array can be subtracted from the target value to result in a non-negative number.';
                    }
                }
            }
        } else {
            $total_remaining_hour =
                $reset_time - ($remaining_hour - $this->get_hour);
            if ($total_remaining_hour < 0) {
                $rate = $total_remaining_hour * -1;
                // dd($this->getHour - $rate);
                $first_rate = Rate::where(
                    'branch_id',
                    auth()->user()->branch_id
                )
                    ->where('type_id', $this->guest->checkInDetail->type_id)
                    ->whereHas('stayingHour', function ($query) use ($rate) {
                        $query->where('number', $this->get_hour - $rate);
                    })
                    ->first()->amount;
                $second_rate = ExtensionRate::where(
                    'branch_id',
                    auth()->user()->branch_id
                )
                    ->where('hour', $rate)
                    ->first()->amount;

                $this->total_get_rate = $first_rate + $second_rate;
            } else {
                $this->total_get_rate = $get_rate;
            }
        }
    }

    public function addExtend()
    {
        $check_in_detail = CheckinDetail::where(
            'guest_id',
            $this->guest->id
        )->first();
        $rate = ExtensionRate::where('branch_id', auth()->user()->branch_id)
            ->where('hour', $this->get_hour)
            ->first();

        DB::beginTransaction();
        Transaction::create([
            'branch_id' => $check_in_detail->guest->branch_id,
            'room_id' => $check_in_detail->room_id,
            'guest_id' => $check_in_detail->guest_id,
            'floor_id' => $check_in_detail->room->floor_id,
            'transaction_type_id' => 6,
            'assigned_frontdesk_id' => json_encode($this->assigned_frontdesk),
            'description' => 'Extension',
            'payable_amount' => $this->total_get_rate,
            'paid_amount' => 0,
            'change_amount' => 0,
            'deposit_amount' => 0,
            'paid_at' => null,
            'override_at' => null,
            'remarks' => 'Guest Extension : ' . $this->get_hour . ' hours',
        ]);
        StayExtension::create([
            'guest_id' => $check_in_detail->guest_id,
            'extension_id' => $rate->id,
            'hours' => $this->get_hour,
            'amount' => $this->total_get_rate,
            'frontdesk_ids' => json_encode($this->assigned_frontdesk),
        ]);

        $total_hour = $check_in_detail->number_of_hours - $this->get_hour;

        if ($total_hour < 0) {
            $new_rate = $total_hour * -1;

            $new_number_of_hours =
                auth()->user()->branch->extension_time_reset - $new_rate;

            $check_in_detail->update([
                'number_of_hours' => $new_number_of_hours,
            ]);
        } elseif ($total_hour == 0) {
            $check_in_detail->update([
                'number_of_hours' => auth()->user()->branch
                    ->extension_time_reset,
            ]);
        } else {
            $check_in_detail->update([
                'number_of_hours' => $total_hour,
            ]);
        }

        DB::commit();
        $this->dialog()->success(
            $title = 'Success',
            $description = 'Extend successfully saved'
        );
        $this->extend_modal = false;
        return redirect()->route('frontdesk.manage-guest', [
            'id' => $this->guest->id,
        ]);
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
        $transaction = Transaction::where(
            'id',
            $this->pay_transaction_id
        )->first();

        DB::beginTransaction();
        $transaction->update([
            'paid_amount' => $this->pay_amount,
            'change_amount' => $this->pay_excess,
            'paid_at' => now(),
            'deposit_amount' => $this->pay_excess,
        ]);

        if ($this->saveAsExcess == true) {
            Transaction::create([
                'branch_id' => $transaction->branch_id,
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
            ]);

            $transaction->guest->checkInDetail->update([
                'total_deposit' =>
                    $transaction->guest->checkInDetail->total_deposit +
                    $this->pay_excess,
            ]);
        }
        DB::commit();

        $this->dialog()->success(
            $title = 'Success',
            $description = 'Payment successfully saved'
        );
        $this->pay_modal = false;

        return redirect()->route('frontdesk.manage-guest', [
            'id' => $this->guest->id,
        ]);
    }

    public function payWithDeposit($transaction_id)
    {
        $transaction = Transaction::where('id', $transaction_id)->first();
        $this->pay_transaction_id = $transaction->id;
        $this->pay_transaction_amount = $transaction->payable_amount;
        $this->render_deposit =
            $transaction->guest->checkInDetail->total_deposit -
            $transaction->guest->checkInDetail->total_deduction;
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
                'paid_amount' => $this->pay_transaction_amount,
                'change_amount' => 0,
                'paid_at' => now(),
            ]);
            Transaction::create([
                'branch_id' => $transaction->branch_id,
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
                'remarks' => 'Cashout from paying deposit',
            ]);

            $transaction->guest->checkInDetail->update([
                'total_deduction' =>
                    $transaction->guest->checkInDetail->total_deduction +
                    $this->pay_transaction_amount,
            ]);

            DB::commit();

            $this->dialog()->success(
                $title = 'Success',
                $description = 'Payment successfully saved'
            );
            $this->payWithDeposit_modal = false;

            return redirect()->route('frontdesk.manage-guest', [
                'id' => $this->guest->id,
            ]);
        }
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
        Transaction::where('branch_id', auth()->user()->branch_id)
            ->where('guest_id', $this->guest->id)
            ->whereNull('paid_at')
            ->update(['paid_at' => now()]);

        $this->dialog()->success(
            $title = 'Success',
            $description = 'All unpaid balances are paid'
        );

        return redirect()->route('frontdesk.manage-guest', [
            'id' => $this->guest->id,
        ]);
    }

    public function payAllWithDeposit($total)
    {
        $this->pay_transaction_amount = $total;
        $this->render_deposit =
            $this->guest->checkInDetail->total_deposit -
            $this->guest->checkInDetail->total_deduction;

        $this->payWithDeposit_modal = true;
    }

    public function addAllPaymentWithDeposit()
    {
        DB::beginTransaction();
        $transaction = Transaction::where(
            'branch_id',
            auth()->user()->branch_id
        )
            ->where('guest_id', $this->guest->id)
            ->first();
        Transaction::where('branch_id', auth()->user()->branch_id)
            ->where('guest_id', $this->guest->id)
            ->whereNull('paid_at')
            ->update(['paid_at' => now()]);

        Transaction::create([
            'branch_id' => $transaction->branch_id,
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
        ]);

        $this->guest->checkInDetail->update([
            'total_deduction' =>
                $this->guest->checkInDetail->total_deduction +
                $this->pay_transaction_amount,
        ]);

        DB::commit();

        $this->dialog()->success(
            $title = 'Success',
            $description = 'Payment successfully saved'
        );
        $this->payWithDeposit_modal = false;

        return redirect()->route('frontdesk.manage-guest', [
            'id' => $this->guest->id,
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
        if ($this->is_checkout) {
            $balance =
                $this->deposit_remote_and_key +
                ($this->deposit_except_remote_and_key -
                    $this->guest->checkInDetail->total_deduction);
        } else {
            $balance =
                $this->deposit_except_remote_and_key -
                $this->guest->checkInDetail->total_deduction;
        }

        $transaction = Transaction::where(
            'branch_id',
            auth()->user()->branch_id
        )
            ->where('guest_id', $this->guest->id)
            ->first();
        DB::beginTransaction();
        $this->guest->checkInDetail->update([
            'total_deduction' =>
                $this->guest->checkInDetail->total_deduction + $balance,
        ]);

        if (!$this->is_checkout) {
            Transaction::create([
                'branch_id' => $transaction->branch_id,
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
            ]);
        }

        Transaction::create([
            'branch_id' => $transaction->branch_id,
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
        ]);

        DB::commit();
        $this->dialog()->success(
            $title = 'Success',
            $description = 'All deposits are claimed'
        );
        $this->reminderIndex = 4;
        $this->reminders_modal = true;
    }

    public function checkOut()
    {
        $bills_unpaid = Transaction::selectRaw(
            'sum(payable_amount) as total_payable_amount, transaction_type_id'
        )
            ->where('branch_id', auth()->user()->branch_id)
            ->where('guest_id', $this->guest->id)
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
            $this->reminders_modal = true;
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
        DB::beginTransaction();
        Room::where('branch_id', auth()->user()->branch_id)
            ->where('id', $this->guest->room_id)
            ->update([
                'status' => 'Uncleaned',
                'last_checkin_at' => $this->guest->checkInDetail->check_in_at,
                'last_checkout_at' => Carbon::now()->toDateTimeString(),
                'check_out_time' => Carbon::now()->toDateTimeString(),
                'time_to_clean' => now()->addHours(3),
            ]);

        CheckinDetail::where('guest_id', $this->guest->id)->update([
            'is_check_out' => true,
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
    }

    public function addOverride()
    {
        $this->validate([
            'override_amount' => 'required|numeric',
        ]);

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

        DB::commit();

        $this->dialog()->success(
            $title = 'Success',
            $description = 'Override successfully saved'
        );
        $this->pay_modal = false;

        return redirect()->route('frontdesk.manage-guest', [
            'id' => $this->guest->id,
        ]);
    }

    public function chargeForDamages()
    {
        $this->reminders_modal = false;
        $this->damage_modal = true;
    }
}
