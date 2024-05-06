<?php

namespace App\Http\Livewire\Kitchen;

use App\Models\Menu;
use App\Models\Guest;
use Livewire\Component;
use App\Models\Inventory;
use App\Models\Transaction as TransactionModel;
use App\Models\CheckinDetail;
use WireUi\Traits\Actions;
use Illuminate\Support\Facades\DB;

class Transaction extends Component
{
    public $guest;
    public $food_id;
    public $food_price;
    public $food_quantity;
    public $food_total_amount;
    public $food_beverages_modal = false;

    use Actions;

    public function mount()
    {
        $this->assigned_frontdesk = auth()->user()->assigned_frontdesks;
        $this->food_price = 0;
    }

    public function addTransaction($id)
    {
        $this->guest = Guest::find($id);
        $this->food_beverages_modal = true;
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

    public function closeModal()
    {
        return redirect()->route('kitchen.transactions');
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
        if($inventory != null)
        {
            TransactionModel::create([
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
                'Guest Added Food and Beverages: (Kitchen) (' .$this->food_quantity .')' .' '.$food->name,
            ]);
            //update stock
            $new_stock =
                $inventory->number_of_serving - $this->food_quantity;
            $inventory->update([
                'number_of_serving' => $new_stock,
            ]);

            $this->food_beverages_modal = false;
            $this->dialog()->success(
                $title = 'Success',
                $description = 'Transaction Added Successfully',
            );
        }else{
            $this->dialog()->error(
                $title = 'Out Of Stock',
                $description = 'This item is out of stock',
            );
        }
        DB::commit();

        // return redirect()->route('kitchen.transactions');
    }

    public function render()
    {
        return view('livewire.kitchen.transaction', [
            'guests' => Guest::whereHas('checkInDetail', function ($query) {
                $query->where('is_check_out', false);
            })->get(),
            'foods' => Menu::where('branch_id', auth()->user()->branch_id)->get(),
        ]);
    }
}
