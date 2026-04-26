<?php

namespace App\Http\Livewire\Frontdesk;

use App\Models\FrontdeskCategory;
use App\Models\FrontdeskInventory;
use App\Models\FrontdeskMenu;
use App\Models\Guest;
use App\Models\PosOrder;
use App\Models\PosTransaction;
use App\Models\ShiftLog;
use App\Models\StockMovement;
use App\Models\Transaction as TransactionModel;
use App\Services\Pos\CheckoutService;
use App\Services\Pos\InsufficientStockException;
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

    // ──────── POS v2 (behind branch.pos_v2_enabled flag) ────────
    // When v2 is OFF, every property below stays at its default and the
    // v1 code path runs unchanged. When v2 is ON, the blade exposes the
    // attach-to-room toggle, guest search, and discount input — and the
    // checkout() method routes through CheckoutService instead of writing
    // legacy PosTransaction rows.
    public $v2Enabled = false;
    public $attachToRoom = false;
    public $guestSearch = '';
    public $selectedGuestId = null;
    public $selectedGuestData = null; // ['id', 'name', 'room_number', 'floor_id', 'room_id', 'open_pos_total']
    public $discountAmount = 0;
    public $discountReason = '';

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

        // v2 only: attach-to-room sales must have a guest selected. Block early
        // so the confirm modal doesn't show "ROOM CHARGE — RM —".
        if ($this->v2Enabled && $this->attachToRoom && $this->selectedGuestId === null) {
            $this->notification()->error(
                'Pick a guest',
                'Search and select a guest to attach this sale to, or turn off "Attach to room".'
            );
            return;
        }

        $this->showCheckoutConfirm = true;
    }

    // ──────── v2 helpers ────────

    public function toggleAttachToRoom()
    {
        $this->attachToRoom = !$this->attachToRoom;
        if (!$this->attachToRoom) {
            $this->clearSelectedGuest();
            $this->guestSearch = '';
        }
    }

    public function selectGuest($guestId)
    {
        $guest = Guest::where('branch_id', auth()->user()->branch_id)
            ->whereHas('checkInDetail', fn ($q) => $q->where('is_check_out', false))
            ->with(['checkInDetail.room.floor'])
            ->find($guestId);

        if (!$guest) {
            $this->notification()->error('Guest not found', 'That guest is no longer checked in.');
            return;
        }

        $room = $guest->checkInDetail->room ?? null;

        // Open POS balance: sum unpaid POS line totals (transaction_type_id=9)
        // attached to this guest where the parent order is not voided.
        $openTotal = (int) TransactionModel::where('guest_id', $guest->id)
            ->where('transaction_type_id', 9)
            ->whereNotNull('order_id')
            ->whereIn('order_id', PosOrder::whereNull('voided_at')->pluck('id'))
            ->sum('payable_amount');

        $this->selectedGuestId = $guest->id;
        $this->selectedGuestData = [
            'id'             => $guest->id,
            'name'           => trim(($guest->first_name ?? '') . ' ' . ($guest->last_name ?? '')) ?: ($guest->name ?? 'Guest #' . $guest->id),
            'room_number'    => $room?->number,
            'room_id'        => $room?->id,
            'floor_id'       => $room?->floor_id,
            'open_pos_total' => $openTotal,
        ];
    }

    public function clearSelectedGuest()
    {
        $this->selectedGuestId = null;
        $this->selectedGuestData = null;
    }

    public function getDiscountedTotalProperty()
    {
        $sub = (int) round($this->cartTotal);
        $disc = max(0, (int) $this->discountAmount);
        return max(0, $sub - $disc);
    }

    /**
     * Live search results for the attach-to-room guest picker.
     * Empty when v2 is off or attachToRoom is off — keeps render() cheap.
     */
    public function getGuestSearchResultsProperty()
    {
        if (!$this->v2Enabled || !$this->attachToRoom) {
            return collect();
        }
        $term = trim($this->guestSearch);
        if ($term === '' || mb_strlen($term) < 1) {
            return collect();
        }

        return Guest::where('branch_id', auth()->user()->branch_id)
            ->whereHas('checkInDetail', fn ($q) => $q->where('is_check_out', false))
            ->with(['checkInDetail.room'])
            ->where(function ($q) use ($term) {
                $q->where('first_name', 'like', "%{$term}%")
                  ->orWhere('last_name', 'like', "%{$term}%")
                  ->orWhere('name', 'like', "%{$term}%")
                  ->orWhereHas('checkInDetail.room', fn ($r) => $r->where('number', 'like', "%{$term}%"));
            })
            ->limit(10)
            ->get()
            ->map(function ($g) {
                $room = $g->checkInDetail?->room;
                return [
                    'id'          => $g->id,
                    'name'        => trim(($g->first_name ?? '') . ' ' . ($g->last_name ?? '')) ?: ($g->name ?? 'Guest #' . $g->id),
                    'room_number' => $room?->number,
                ];
            });
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

        $this->v2Enabled = (bool) (auth()->user()->branch?->pos_v2_enabled ?? false);
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

        // ──────── v2 path (behind branch.pos_v2_enabled) ────────
        if ($this->v2Enabled) {
            $this->checkoutV2();
            return;
        }

        // ──────── v1 path (legacy — preserved unchanged) ────────
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

    /**
     * v2 checkout: routes through CheckoutService so transactions land in
     * `transactions` (type=9) + `pos_orders`, with snapshot columns and
     * proper room-charge / discount support. Atomic — any failure (including
     * stock race) rolls back the entire order.
     */
    protected function checkoutV2(): void
    {
        // Build the cart shape CheckoutService expects.
        $cart = [];
        foreach ($this->cart as $item) {
            $cart[] = [
                'menu_id'    => (int) $item['menu_id'],
                'name'       => (string) $item['name'],
                'unit_price' => (int) round((float) $item['price']),
                'quantity'   => (float) $item['quantity'],
            ];
        }

        $context = [
            'branch_id'          => auth()->user()->branch_id,
            'user_id'            => auth()->user()->id,
            'shift_log_id'       => $this->current_shift->id,
            'discount_amount'    => (int) max(0, (int) $this->discountAmount),
            'discount_reason'    => trim((string) $this->discountReason) !== '' ? trim((string) $this->discountReason) : null,
            'assigned_frontdesk' => auth()->user()->assigned_frontdesks ?? null,
        ];

        if ($this->attachToRoom && $this->selectedGuestId !== null && $this->selectedGuestData !== null) {
            $context['guest_id'] = $this->selectedGuestId;
            $context['room_id']  = $this->selectedGuestData['room_id']  ?? null;
            $context['floor_id'] = $this->selectedGuestData['floor_id'] ?? null;
            // Room-charge: no cash collected.
            $context['paid_amount']   = 0;
            $context['change_amount'] = 0;
        } else {
            // Walk-in cash: assume full pay (the v2 UI doesn't yet capture
            // change tendered; matches v1 behavior of taking the cart total
            // as paid). Future enhancement: tendered/change UI input.
            $context['paid_amount']   = (int) $this->discountedTotal;
            $context['change_amount'] = 0;
        }

        try {
            $order = app(CheckoutService::class)->checkout($cart, $context);
        } catch (InsufficientStockException $e) {
            $this->showCheckoutConfirm = false;
            $this->notification()->error('Insufficient stock', $e->getMessage());
            return;
        } catch (\InvalidArgumentException $e) {
            $this->showCheckoutConfirm = false;
            $this->notification()->error('Sale blocked', $e->getMessage());
            return;
        } catch (\Throwable $e) {
            $this->showCheckoutConfirm = false;
            $this->notification()->error('Checkout failed', $e->getMessage());
            return;
        }

        // Reset cart + v2 state on success.
        $this->cart = [];
        $this->discountAmount = 0;
        $this->discountReason = '';
        $this->attachToRoom = false;
        $this->guestSearch = '';
        $this->clearSelectedGuest();
        $this->showCheckoutConfirm = false;

        $msg = $order->payment_method === null
            ? "Charged to RM {$this->resolveRoomNumberForOrder($order)} (Order #{$order->id})"
            : "Cash sale recorded (Order #{$order->id})";

        $this->notification()->success('Transaction Complete', $msg);
    }

    private function resolveRoomNumberForOrder(PosOrder $order): string
    {
        if ($order->room_id === null) return '—';
        $room = \App\Models\Room::find($order->room_id);
        return $room?->number ?? (string) $order->room_id;
    }

    // ──────── v2 void flow ────────

    public function confirmVoidOrder($posOrderId)
    {
        if (!$this->v2Enabled) return;

        $order = $this->loadVoidableOrder($posOrderId);
        if (!$order) return;

        $this->dialog()->confirm([
            'title'       => "Void Order #{$order->id}?",
            'description' => 'This will reverse the sale and restore stock. Cannot be undone.',
            'icon'        => 'warning',
            'accept'      => [
                'label'  => 'Yes, void',
                'method' => 'voidOrder',
                'params' => $order->id,
            ],
            'reject' => ['label' => 'Cancel'],
        ]);
    }

    public function voidOrder($posOrderId)
    {
        if (!$this->v2Enabled) return;

        $order = $this->loadVoidableOrder($posOrderId);
        if (!$order) return;

        try {
            app(CheckoutService::class)->void($order, auth()->id(), null);
        } catch (\Throwable $e) {
            $this->notification()->error('Void failed', $e->getMessage());
            return;
        }

        $this->notification()->success(
            'Order Voided',
            "Order #{$order->id} reversed. Stock restored."
        );
    }

    /**
     * Same-shift + same-user + not-already-voided gate. Returns null and
     * shows an error toast if the order isn't voidable by this user.
     */
    private function loadVoidableOrder($posOrderId): ?PosOrder
    {
        $order = PosOrder::find($posOrderId);
        if (!$order) {
            $this->notification()->error('Not found', 'That order no longer exists.');
            return null;
        }
        if ((int) $order->user_id !== (int) auth()->id()) {
            $this->notification()->error('Cannot void', 'Only the cashier who rang the sale can void it.');
            return null;
        }
        if ((int) $order->shift_log_id !== (int) ($this->current_shift->id ?? -1)) {
            $this->notification()->error('Cannot void', 'Voids are only allowed in the same shift.');
            return null;
        }
        if ($order->voided_at !== null) {
            $this->notification()->error('Already voided', "Order #{$order->id} was already voided.");
            return null;
        }
        return $order;
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
            if ($this->v2Enabled) {
                // ──────── v2: read from pos_orders + transactions ────────
                // Cash-only total (matches the total_pos column semantic).
                // Voided orders excluded; room-charge orders excluded (no
                // cash actually came into the drawer for those).
                $this->total_pos = (int) PosOrder::where('branch_id', auth()->user()->branch_id)
                    ->where('shift_log_id', $this->current_shift->id)
                    ->where('user_id', auth()->user()->id)
                    ->whereNull('voided_at')
                    ->where('payment_method', 'cash')
                    ->sum('total');

                $posOrders = PosOrder::where('branch_id', auth()->user()->branch_id)
                    ->where('shift_log_id', $this->current_shift->id)
                    ->where('user_id', auth()->user()->id)
                    ->with(['lineItems' => fn ($q) => $q->orderBy('id')])
                    ->orderByDesc('created_at')
                    ->get();

                $orders = $posOrders->map(fn ($o) => [
                    'order_id'       => $o->id,
                    'date_time'      => $o->created_at,
                    'items'          => $o->lineItems,
                    'amount'         => (int) $o->total,
                    'item_count'     => (float) $o->lineItems->sum('quantity'),
                    'payment_method' => $o->payment_method, // 'cash' | null (room-charge)
                    'voided_at'      => $o->voided_at,
                    'discount'       => (int) $o->discount_amount,
                    'guest_id'       => $o->guest_id,
                ])->values();

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
                // ──────── v1: legacy PosTransaction reads (preserved) ────────
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
