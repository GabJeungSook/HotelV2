<?php

namespace App\Http\Livewire\Frontdesk;

use App\Models\FrontdeskCategory;
use App\Models\FrontdeskInventory;
use App\Models\FrontdeskMenu;
use App\Models\PosTransaction;
use App\Models\ShiftLog;
use Livewire\Component;
use WireUi\Traits\Actions;
use DB;

class PointOfSale extends Component
{
    use Actions;

    public $search = '';
    public $selectedCategory = null;
    public $cart = [];
    public $current_shift;
    public $total_pos = 0;

    public function mount()
    {
        $this->current_shift = ShiftLog::where('frontdesk_id', auth()->user()->id)
            ->where('cash_drawer_id', auth()->user()->cash_drawer_id)
            ->whereNull('time_out')
            ->orderBy('created_at', 'desc')
            ->first();
    }

    public function selectCategory($categoryId)
    {
        $this->selectedCategory = $this->selectedCategory == $categoryId ? null : $categoryId;
    }

    public function addToCart($menuId)
    {
        $menu = FrontdeskMenu::find($menuId);
        if (!$menu) return;

        foreach ($this->cart as $index => $item) {
            if ($item['menu_id'] == $menuId) {
                $this->cart[$index]['quantity']++;
                $this->cart[$index]['subtotal'] = $this->cart[$index]['quantity'] * $this->cart[$index]['price'];
                return;
            }
        }

        $this->cart[] = [
            'menu_id' => $menu->id,
            'name' => $menu->name,
            'price' => $menu->price,
            'quantity' => 1,
            'subtotal' => $menu->price,
        ];
    }

    public function incrementQuantity($index)
    {
        if (isset($this->cart[$index])) {
            $this->cart[$index]['quantity']++;
            $this->cart[$index]['subtotal'] = $this->cart[$index]['quantity'] * $this->cart[$index]['price'];
        }
    }

    public function decrementQuantity($index)
    {
        if (isset($this->cart[$index])) {
            $this->cart[$index]['quantity']--;
            if ($this->cart[$index]['quantity'] <= 0) {
                $this->removeFromCart($index);
            } else {
                $this->cart[$index]['subtotal'] = $this->cart[$index]['quantity'] * $this->cart[$index]['price'];
            }
        }
    }

    public function removeFromCart($index)
    {
        unset($this->cart[$index]);
        $this->cart = array_values($this->cart);
    }

    public function checkout()
    {
        if (empty($this->cart)) {
            $this->notification()->error(
                $title = 'Empty Cart',
                $description = 'Please add items before checking out.'
            );
            return;
        }

        DB::beginTransaction();

        foreach ($this->cart as $item) {
            PosTransaction::create([
                'shift_log_id' => $this->current_shift->id,
                'user_id' => auth()->user()->id,
                'branch_id' => auth()->user()->branch_id,
                'frontdesk_menu_id' => $item['menu_id'],
                'item_name' => $item['name'],
                'price' => $item['price'],
                'quantity' => $item['quantity'],
                'total' => $item['subtotal'],
            ]);

            $inventory = FrontdeskInventory::where('frontdesk_menu_id', $item['menu_id'])->first();
            if ($inventory && $inventory->number_of_serving >= $item['quantity']) {
                $inventory->decrement('number_of_serving', $item['quantity']);
            }
        }

        DB::commit();

        $this->cart = [];

        $this->notification()->success(
            $title = 'Transaction Complete',
            $description = 'POS transaction has been recorded successfully.'
        );
    }

    public function getCartTotalProperty()
    {
        return collect($this->cart)->sum('subtotal');
    }

    public function render()
    {
        $this->total_pos = PosTransaction::where('branch_id', auth()->user()->branch_id)
            ->where('shift_log_id', $this->current_shift->id)
            ->where('user_id', auth()->user()->id)
            ->sum('total');

        $menuQuery = FrontdeskMenu::where('branch_id', auth()->user()->branch_id);

        if ($this->selectedCategory) {
            $menuQuery->where('frontdesk_category_id', $this->selectedCategory);
        }

        if ($this->search) {
            $menuQuery->where('name', 'like', '%' . $this->search . '%');
        }

        return view('livewire.frontdesk.point-of-sale', [
            'menus' => $menuQuery->get(),
            'categories' => FrontdeskCategory::where('branch_id', auth()->user()->branch_id)->get(),
            'transactions' => PosTransaction::where('branch_id', auth()->user()->branch_id)
                ->where('shift_log_id', $this->current_shift->id)
                ->where('user_id', auth()->user()->id)
                ->latest()
                ->get(),
        ]);
    }
}
