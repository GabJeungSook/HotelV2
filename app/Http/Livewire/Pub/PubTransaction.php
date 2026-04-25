<?php

namespace App\Http\Livewire\Pub;

use App\Models\PubMenu;
use App\Models\Guest;
use Livewire\Component;
use App\Models\PubInventory;
use App\Models\StockMovement;
use App\Models\Transaction as TransactionModel;
use App\Models\CheckinDetail;
use App\Services\Pos\StockService;
use WireUi\Traits\Actions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PubTransaction extends Component
{
    public $guest;
    public $food_id;
    public $food_price;
    public $food_quantity;
    public $food_total_amount;
    public $assigned_frontdesk;
    public $food_beverages_modal = false;

    use Actions;

    public function mount()
    {
        $this->assigned_frontdesk = auth()->user()->assigned_frontdesks;
        $this->food_price = 0;
    }

    public function addTransaction($id)
    {
        $this->guest = Guest::where('branch_id', auth()->user()->branch_id)->find($id);
        $this->food_beverages_modal = true;
    }

    public function updatedFoodId()
    {
        if ($this->food_id != 'Select Item') {
            $price = PubMenu::where('branch_id', auth()->user()->branch_id)
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
            $price = PubMenu::where('branch_id', auth()->user()->branch_id)
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
        return redirect()->route('pub.pub-transactions');
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

        $food = PubMenu::where('branch_id', auth()->user()->branch_id)
            ->where('id', $this->food_id)
            ->first();
        $inventory = PubInventory::where('branch_id', auth()->user()->branch_id)
            ->where('pub_menu_id', $this->food_id)
            ->first();
        if($inventory != null)
        {
            $transaction = TransactionModel::create([
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
                'Guest Added Food and Beverages: (The Pub) (' .$this->food_quantity .')' .' '.$food->name,
                // line-item snapshot — frozen at sale time
                'source_type' => StockMovement::SOURCE_PUB,
                'menu_id'     => $food->id,
                'item_name'   => $food->name,
                'unit_price'  => (int) $food->price,
                'quantity'    => $this->food_quantity,
            ]);
            //update stock (legacy path — kept during shadow-write phase)
            $new_stock =
                $inventory->number_of_serving - $this->food_quantity;
            $inventory->update([
                'number_of_serving' => $new_stock,
            ]);

            // SHADOW-WRITE: log to stock_movements without re-decrementing inventory.
            // Wrapped in try/catch so any failure here can never block the live flow.
            try {
                app(StockService::class)->out(
                    StockMovement::SOURCE_PUB,
                    (int) $this->food_id,
                    (float) $this->food_quantity,
                    [
                        'branch_id' => auth()->user()->branch_id,
                        'ref_type'  => 'transaction',
                        'ref_id'    => $transaction->id,
                        'user_id'   => auth()->id(),
                        'shadow'    => true,
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning('StockService shadow-write failed (pub)', [
                    'menu_id' => $this->food_id,
                    'qty'     => $this->food_quantity,
                    'error'   => $e->getMessage(),
                ]);
            }

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
        return view('livewire.pub.pub-transaction', [
            'guests' => Guest::where('branch_id', auth()->user()->branch_id)
                ->whereHas('checkInDetail', function ($query) {
                    $query->where('is_check_out', false);
                })->get(),
            'foods' => PubMenu::where('branch_id', auth()->user()->branch_id)->get(),
        ]);
    }
}
