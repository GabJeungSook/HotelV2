<?php

namespace App\Http\Livewire\Kitchen;

use App\Models\Menu;
use App\Models\Guest;
use Livewire\Component;
use App\Models\Inventory;
use App\Models\StockMovement;
use App\Models\Transaction as TransactionModel;
use App\Models\CheckinDetail;
use App\Services\Pos\StockService;
use App\Services\Pos\InsufficientStockException;
use WireUi\Traits\Actions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Transaction extends Component
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

        // Out-of-stock guard before opening a DB transaction. Catches both
        // missing inventory rows and insufficient quantity. StockService::out
        // also enforces this atomically below, but pre-checking lets us show
        // the same dialog the legacy code did without any rollback noise.
        if ($inventory === null || (float) $inventory->number_of_serving < (float) $this->food_quantity) {
            $this->dialog()->error(
                $title = 'Out Of Stock',
                $description = 'This item is out of stock',
            );
            return;
        }

        // Pre-check ingredient stock (non-locking, for UX feedback)
        $insufficientIngredients = app(StockService::class)->checkIngredientAvailability(
            StockMovement::SOURCE_KITCHEN,
            (int) $this->food_id,
            (float) $this->food_quantity
        );
        if (!empty($insufficientIngredients)) {
            $names = collect($insufficientIngredients)->pluck('name')->implode(', ');
            $this->dialog()->error(
                $title = 'Ingredient Out Of Stock',
                $description = "Insufficient stock for ingredients: {$names}",
            );
            return;
        }

        DB::beginTransaction();
        try {
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
                'Guest Added Food and Beverages: (Kitchen) (' .$this->food_quantity .')' .' '.$food->name,
                // line-item snapshot — frozen at sale time
                'source_type' => StockMovement::SOURCE_KITCHEN,
                'menu_id'     => $food->id,
                'item_name'   => $food->name,
                'unit_price'  => (int) $food->price,
                'quantity'    => $this->food_quantity,
            ]);

            // Real stock decrement via StockService (single source of truth,
            // row-locked, atomic with the surrounding DB transaction). Throws
            // InsufficientStockException on race with another writer.
            app(StockService::class)->smartOut(
                StockMovement::SOURCE_KITCHEN,
                (int) $this->food_id,
                (float) $this->food_quantity,
                [
                    'branch_id' => auth()->user()->branch_id,
                    'ref_type'  => 'transaction',
                    'ref_id'    => $transaction->id,
                    'user_id'   => auth()->id(),
                ]
            );

            DB::commit();

            $this->food_beverages_modal = false;
            $this->dialog()->success(
                $title = 'Success',
                $description = 'Transaction Added Successfully',
            );
        } catch (InsufficientStockException $e) {
            DB::rollBack();
            $this->dialog()->error(
                $title = 'Out Of Stock',
                $description = 'This item is out of stock',
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Kitchen addFood failed', [
                'menu_id' => $this->food_id,
                'qty'     => $this->food_quantity,
                'error'   => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function render()
    {
        return view('livewire.kitchen.transaction', [
            'guests' => Guest::where('branch_id', auth()->user()->branch_id)
                ->whereHas('checkInDetail', function ($query) {
                    $query->where('is_check_out', false);
                })->get(),
            'foods' => Menu::where('branch_id', auth()->user()->branch_id)->get(),
        ]);
    }
}
