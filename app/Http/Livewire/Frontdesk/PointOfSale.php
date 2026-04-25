<?php

namespace App\Http\Livewire\Frontdesk;

use App\Models\FrontdeskCategory;
use App\Models\FrontdeskInventory;
use App\Models\FrontdeskMenu;
use App\Models\PosTransaction;
use App\Models\ShiftLog;
use App\Models\StockMovement;
use App\Services\Pos\StockService;
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
    public $showHistoryModal = false;
    public $historySearch = '';
    public $showCheckoutConfirm = false;
    public $showStockInModal = false;
    public $stockIn_menu_id = null;
    public $stockIn_quantity = 0;
    public $stockIn_reason = '';

    public function openHistoryModal()
    {
        $this->historySearch = '';
        $this->showHistoryModal = true;
    }

    public function closeHistoryModal()
    {
        $this->showHistoryModal = false;
    }

    public function clearHistorySearch()
    {
        $this->historySearch = '';
    }

    public function reviewCheckout()
    {
        if (!$this->current_shift) {
            $this->notification()->error(
                $title = 'No Active Shift',
                $description = 'Please start a shift before making transactions.'
            );
            return;
        }

        if (empty($this->cart)) {
            $this->notification()->error(
                $title = 'Empty Cart',
                $description = 'Please add items before checking out.'
            );
            return;
        }

        $this->showCheckoutConfirm = true;
    }

    public function cancelCheckout()
    {
        $this->showCheckoutConfirm = false;
    }

    public function openStockInModal()
    {
        $this->reset(['stockIn_menu_id', 'stockIn_quantity', 'stockIn_reason']);
        $this->showStockInModal = true;
    }

    public function closeStockInModal()
    {
        $this->showStockInModal = false;
    }

    public function submitStockIn()
    {
        $this->validate([
            'stockIn_menu_id' => 'required|integer',
            'stockIn_quantity' => 'required|numeric|gt:0',
            'stockIn_reason' => 'nullable|string|max:255',
        ]);

        try {
            app(StockService::class)->in(
                StockMovement::SOURCE_FRONTDESK,
                (int) $this->stockIn_menu_id,
                (float) $this->stockIn_quantity,
                [
                    'branch_id' => auth()->user()->branch_id,
                    'reason'    => $this->stockIn_reason !== '' ? $this->stockIn_reason : null,
                    'ref_type'  => 'stock_in_form',
                    'user_id'   => auth()->id(),
                ]
            );

            $qty = $this->stockIn_quantity;
            $this->reset(['stockIn_menu_id', 'stockIn_quantity', 'stockIn_reason']);
            $this->showStockInModal = false;
            $this->notification()->success('Stock recorded', "Recorded {$qty} units.");
        } catch (\Throwable $e) {
            $this->notification()->error('Stock-In failed', $e->getMessage());
        }
    }

    public function mount()
    {
        $this->current_shift = ShiftLog::where('frontdesk_id', auth()->user()->id)
            ->where('cash_drawer_id', auth()->user()->cash_drawer_id)
            ->whereNull('time_out')
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$this->current_shift) {
            auth()->user()->update(['cash_drawer_id' => null]);
            return redirect()->route('frontdesk.dashboard');
        }
    }

    public function selectCategory($categoryId)
    {
        $this->selectedCategory = $this->selectedCategory == $categoryId ? null : $categoryId;
    }

    /**
     * Available stock for a frontdesk menu in the current branch.
     * Returns 0 if the inventory row doesn't exist.
     */
    private function stockFor(int $menuId): float
    {
        $row = FrontdeskInventory::where('branch_id', auth()->user()->branch_id)
            ->where('frontdesk_menu_id', $menuId)
            ->first();
        return $row ? (float) $row->number_of_serving : 0.0;
    }

    public function addToCart($menuId)
    {
        $menu = FrontdeskMenu::find($menuId);
        if (!$menu) return;

        $stock = $this->stockFor((int) $menuId);
        if ($stock <= 0) {
            $this->notification()->error('Out of stock', "{$menu->name} is unavailable.");
            return;
        }

        foreach ($this->cart as $index => $item) {
            if ($item['menu_id'] == $menuId) {
                if (($item['quantity'] + 1) > $stock) {
                    $this->notification()->error('Stock limit', "Only {$stock} {$menu->name} in stock.");
                    return;
                }
                $this->cart[$index]['quantity']++;
                $this->cart[$index]['subtotal'] = $this->cart[$index]['quantity'] * (float) $this->cart[$index]['price'];
                return;
            }
        }

        $this->cart[] = [
            'menu_id'  => $menu->id,
            'name'     => $menu->name,
            'price'    => (float) $menu->price,
            'quantity' => 1,
            'subtotal' => (float) $menu->price,
        ];
    }

    public function incrementQuantity($index)
    {
        if (!isset($this->cart[$index])) return;

        $line = $this->cart[$index];
        $stock = $this->stockFor((int) $line['menu_id']);

        if (($line['quantity'] + 1) > $stock) {
            $this->notification()->error('Stock limit', "Only {$stock} {$line['name']} in stock.");
            return;
        }

        $this->cart[$index]['quantity']++;
        $this->cart[$index]['subtotal'] = $this->cart[$index]['quantity'] * (float) $this->cart[$index]['price'];
    }

    public function decrementQuantity($index)
    {
        if (isset($this->cart[$index])) {
            $this->cart[$index]['quantity']--;
            if ($this->cart[$index]['quantity'] <= 0) {
                $this->removeFromCart($index);
            } else {
                $this->cart[$index]['subtotal'] = $this->cart[$index]['quantity'] * (float) $this->cart[$index]['price'];
            }
        }
    }

    public function confirmClearCart()
    {
        if (empty($this->cart)) return;

        $this->dialog()->confirm([
            'title'       => 'Clear cart?',
            'description' => 'This will remove every item from the cart.',
            'icon'        => 'question',
            'accept'      => [
                'label'  => 'Yes, clear',
                'method' => 'clearCart',
            ],
            'reject' => [
                'label' => 'Keep',
            ],
        ]);
    }

    public function clearCart()
    {
        $this->cart = [];
    }

    public function confirmRemoveFromCart($index)
    {
        $name = $this->cart[$index]['name'] ?? 'this item';

        $this->dialog()->confirm([
            'title'       => 'Remove from cart?',
            'description' => "Remove {$name} from the cart?",
            'icon'        => 'question',
            'accept'      => [
                'label'  => 'Yes, remove',
                'method' => 'removeFromCart',
                'params' => $index,
            ],
            'reject' => [
                'label' => 'Keep',
            ],
        ]);
    }

    public function removeFromCart($index)
    {
        unset($this->cart[$index]);
        $this->cart = array_values($this->cart);
    }

    public function checkout()
    {
        if (!$this->current_shift) {
            $this->notification()->error(
                $title = 'No Active Shift',
                $description = 'Please start a shift before making transactions.'
            );
            return;
        }

        if (empty($this->cart)) {
            $this->notification()->error(
                $title = 'Empty Cart',
                $description = 'Please add items before checking out.'
            );
            return;
        }

        // Pre-flight: validate every cart line against current stock BEFORE
        // creating any transaction. Block the whole sale if any item is short.
        $shortages = [];
        foreach ($this->cart as $item) {
            $available = $this->stockFor((int) $item['menu_id']);
            if ($available < (float) $item['quantity']) {
                $shortages[] = "{$item['name']}: have " . rtrim(rtrim(number_format($available, 2), '0'), '.')
                    . ', need ' . $item['quantity'];
            }
        }
        if (!empty($shortages)) {
            $this->showCheckoutConfirm = false;
            $this->notification()->error(
                'Insufficient stock',
                "Sale blocked.\n" . implode("\n", $shortages)
            );
            return;
        }

        DB::beginTransaction();
        try {
            foreach ($this->cart as $item) {
                PosTransaction::create([
                    'shift_log_id'      => $this->current_shift->id,
                    'user_id'           => auth()->user()->id,
                    'branch_id'         => auth()->user()->branch_id,
                    'frontdesk_menu_id' => $item['menu_id'],
                    'item_name'         => $item['name'],
                    'price'             => $item['price'],
                    'quantity'          => $item['quantity'],
                    'total'             => $item['subtotal'],
                ]);

                $inventory = FrontdeskInventory::where('branch_id', auth()->user()->branch_id)
                    ->where('frontdesk_menu_id', $item['menu_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$inventory || $inventory->number_of_serving < $item['quantity']) {
                    // Concurrent change between pre-flight and now — abort everything.
                    throw new \RuntimeException("Stock changed for {$item['name']} during checkout");
                }

                $inventory->decrement('number_of_serving', $item['quantity']);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->showCheckoutConfirm = false;
            $this->notification()->error('Checkout failed', $e->getMessage());
            return;
        }

        $this->cart = [];
        $this->showCheckoutConfirm = false;

        $this->notification()->success(
            $title = 'Transaction Complete',
            $description = 'POS transaction has been recorded successfully.'
        );
    }

    public function getCartTotalProperty()
    {
        return (float) collect($this->cart)->sum(fn ($l) => (float) $l['subtotal']);
    }

    public function getCartItemCountProperty()
    {
        return (int) collect($this->cart)->sum(fn ($l) => (int) $l['quantity']);
    }

    public function render()
    {
        $menuQuery = FrontdeskMenu::where('branch_id', auth()->user()->branch_id);

        if ($this->selectedCategory) {
            $menuQuery->where('frontdesk_category_id', $this->selectedCategory);
        }

        if ($this->search) {
            $menuQuery->where('name', 'like', '%' . $this->search . '%');
        }

        $orders = collect();
        if ($this->current_shift) {
            $this->total_pos = PosTransaction::where('branch_id', auth()->user()->branch_id)
                ->where('shift_log_id', $this->current_shift->id)
                ->where('user_id', auth()->user()->id)
                ->sum('total');

            $transactions = PosTransaction::where('branch_id', auth()->user()->branch_id)
                ->where('shift_log_id', $this->current_shift->id)
                ->where('user_id', auth()->user()->id)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();

            // Group items created in the same checkout (same second) into one order
            $orders = $transactions
                ->groupBy(fn($t) => $t->created_at->format('Y-m-d H:i:s'))
                ->map(fn($items) => [
                    'order_id' => $items->min('id'),
                    'date_time' => $items->first()->created_at,
                    'items' => $items->values(),
                    'amount' => $items->sum('total'),
                    'item_count' => $items->sum('quantity'),
                ])
                ->sortByDesc('date_time')
                ->values();

            // Apply history search filter (case-insensitive match on any item name)
            if (trim($this->historySearch) !== '') {
                $needle = mb_strtolower(trim($this->historySearch));
                $orders = $orders->filter(function ($order) use ($needle) {
                    foreach ($order['items'] as $item) {
                        if (str_contains(mb_strtolower((string) $item->item_name), $needle)) {
                            return true;
                        }
                    }
                    return false;
                })->values();
            }
        } else {
            $this->total_pos = 0;
        }

        return view('livewire.frontdesk.point-of-sale', [
            'menus' => $menuQuery->get(),
            'categories' => FrontdeskCategory::where('branch_id', auth()->user()->branch_id)->get(),
            'orders' => $orders,
        ]);
    }
}
