<?php

namespace App\Http\Livewire\Frontdesk;

use App\Models\FrontdeskCategory;
use App\Models\FrontdeskInventory;
use App\Models\FrontdeskMenu;
use App\Models\Guest;
use App\Models\PosOrder;
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

    // ──────── POS register state ────────
    // attach-to-room toggle, guest search and discount inputs drive the
    // unified checkout flow (CheckoutService writes pos_orders +
    // transactions; legacy pos_transactions is read-only history now).
    public $attachToRoom = false;
    public $guestSearch = '';
    public $selectedGuestId = null;
    public $selectedGuestData = null; // ['id', 'name', 'room_number', 'floor_id', 'room_id', 'open_pos_total']
    public $discountAmount = 0;
    public $discountReason = '';

    // Cash tendered by the customer at the register. Cashier enters this
    // on the confirm modal; the system computes change against the total.
    // Ignored for room-charge sales (no cash collected).
    public $cashTendered = 0;

    // Receipt modal: opened on successful checkout, dismissible.
    public $showReceiptModal = false;
    public $receiptOrderId = null;

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

        // Attach-to-room sales must have a guest selected. Block early
        // so the confirm modal doesn't show "ROOM CHARGE — RM —".
        if ($this->attachToRoom && $this->selectedGuestId === null) {
            $this->notification()->error(
                'Pick a guest',
                'Search and select a guest to attach this sale to, or turn off "Attach to room".'
            );
            return;
        }

        // Cash tendered is intentionally NOT pre-filled with the total.
        // Forces the cashier to look at the bill they actually received and
        // type it in — pre-filling encourages confirming with a wrong amount
        // when the customer hands over a different denomination.
        if (!$this->attachToRoom) {
            $this->cashTendered = 0;
        }

        $this->showCheckoutConfirm = true;
    }

    // ──────── Attach-to-room + guest search ────────

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
            ->with(['checkInDetail.room.floor', 'checkInDetail.room.type'])
            ->find($guestId);

        if (!$guest) {
            $this->notification()->error('Guest not found', 'That guest is no longer checked in.');
            return;
        }

        $room = $guest->checkInDetail->room ?? null;

        // Only allow charging to rooms that are Occupied
        if (!$room || $room->status !== 'Occupied') {
            $this->notification()->error('Room not occupied', 'Cannot charge to this room - it is not currently occupied.');
            return;
        }

        // Open POS balance: sum POS line totals (transaction_type_id=9)
        // attached to this guest where the parent pos_order is not voided.
        // Each check-in creates a new Guest record, so guest_id naturally
        // scopes to the current stay.
        $openTotal = (int) TransactionModel::where('guest_id', $guest->id)
            ->where('transaction_type_id', 9)
            ->whereNotNull('order_id')
            ->whereExists(function ($query) {
                $query->from('pos_orders')
                    ->whereColumn('pos_orders.id', 'transactions.order_id')
                    ->whereNull('pos_orders.voided_at');
            })
            ->sum('payable_amount');

        $this->selectedGuestId = $guest->id;
        $this->selectedGuestData = [
            'id'                 => $guest->id,
            'name'               => $guest->name ?: ('Guest #' . $guest->id),
            'room_number'        => $room?->number,
            'room_id'            => $room?->id,
            'floor_id'           => $room?->floor_id,
            'floor_number'       => $room?->floor?->number,
            'type_name'          => $room?->type?->name,
            'open_pos_total'     => $openTotal,
            'checkin_detail_id'  => $guest->checkInDetail?->id,
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
     * Cash change due to the customer = tendered − total. Floored at 0
     * (negative means cashier hasn't entered enough yet — reviewCheckout
     * blocks the sale in that case).
     */
    public function getChangeDueProperty()
    {
        $tendered = max(0, (int) $this->cashTendered);
        $total = (int) $this->discountedTotal;
        return max(0, $tendered - $total);
    }

    /**
     * Whether the cashier can submit the order from the confirm modal.
     * Room-charge sales: always true (no cash to validate).
     * Cash sales: tendered must cover the total AND cart must be non-empty.
     */
    public function getCanConfirmOrderProperty(): bool
    {
        if (empty($this->cart)) return false;
        if ($this->attachToRoom) return true;
        return (int) $this->cashTendered >= (int) $this->discountedTotal
            && (int) $this->discountedTotal > 0;
    }

    /**
     * Live search results for the attach-to-room guest picker.
     * Empty when attachToRoom is off — keeps render() cheap.
     */
    public function getGuestSearchResultsProperty()
    {
        if (!$this->attachToRoom) {
            return collect();
        }
        $term = trim($this->guestSearch);
        if ($term === '' || mb_strlen($term) < 1) {
            return collect();
        }

        return Guest::where('branch_id', auth()->user()->branch_id)
            ->whereHas('checkInDetail', fn ($q) => $q->where('is_check_out', false))
            // Only show guests in Occupied rooms (not Available/Maintenance/etc)
            ->whereHas('checkInDetail.room', fn ($q) => $q->where('status', 'Occupied'))
            ->with(['checkInDetail.room.floor', 'checkInDetail.room.type'])
            ->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhereHas('checkInDetail.room', fn ($r) => $r->where('number', 'like', "%{$term}%"));
            })
            ->limit(10)
            ->get()
            ->map(function ($g) {
                $room = $g->checkInDetail?->room;
                return [
                    'id'           => $g->id,
                    'name'         => $g->name ?: ('Guest #' . $g->id),
                    'room_number'  => $room?->number,
                    'floor_number' => $room?->floor?->number,
                    'type_name'    => $room?->type?->name,
                ];
            });
    }

    public function cancelCheckout()
    {
        $this->showCheckoutConfirm = false;
        $this->cashTendered = 0;
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
            app(StockService::class)->transfer(
                (int) $this->stockIn_menu_id,
                (float) $this->stockIn_quantity,
                [
                    'branch_id' => auth()->user()->branch_id,
                    'reason'    => $this->stockIn_reason !== '' ? $this->stockIn_reason : null,
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
     * Uses the frontdeskInventory relationship which filters by branch_id.
     * Returns 0 if the inventory row doesn't exist.
     */
    private function stockFor(int $menuId): float
    {
        $menu = FrontdeskMenu::with('frontdeskInventory')->find($menuId);
        return $menu ? (float) ($menu->frontdeskInventory?->number_of_serving ?? 0) : 0.0;
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
        // Clearing the cart should also reset attach-to-room, the selected
        // guest, discount, and cash tendered — they all belong to "this sale".
        $this->resetForNewSale();
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

        $this->checkoutV2();
    }

    /**
     * Routes through CheckoutService so transactions land in
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
            $context['guest_id']           = $this->selectedGuestId;
            $context['room_id']            = $this->selectedGuestData['room_id']  ?? null;
            $context['floor_id']           = $this->selectedGuestData['floor_id'] ?? null;
            $context['checkin_detail_id']  = $this->selectedGuestData['checkin_detail_id'] ?? null;
            // Room-charge: no cash collected.
            $context['paid_amount']   = 0;
            $context['change_amount'] = 0;
        } else {
            // Walk-in cash: cashier must have tendered at least the total.
            // CheckoutService also enforces this defensively, but catching
            // it here gives a clearer toast and keeps the modal open.
            if ((int) $this->cashTendered < (int) $this->discountedTotal) {
                $this->notification()->error(
                    'Not enough cash',
                    'Cash tendered (₱' . number_format($this->cashTendered, 2)
                        . ') is less than the total (₱' . number_format($this->discountedTotal, 2) . ').'
                );
                return;
            }
            $context['paid_amount']   = (int) $this->cashTendered;
            $context['change_amount'] = (int) $this->changeDue;
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

        // Reset every per-sale state so nothing leaks into the next order.
        $this->resetForNewSale();
        $this->showCheckoutConfirm = false;

        // Open the printable receipt modal — the user prints from here via
        // the browser's native print dialog (any printer, including thermal).
        $this->receiptOrderId = $order->id;
        $this->showReceiptModal = true;

        $orderRef = sprintf('OR-%05d', $order->id);
        $msg = $order->payment_method === null
            ? "Charged to RM {$this->resolveRoomNumberForOrder($order)} ({$orderRef})"
            : "Cash sale recorded ({$orderRef})";

        $this->notification()->success('Transaction Complete', $msg);
    }

    public function closeReceiptModal(): void
    {
        $this->showReceiptModal = false;
        $this->receiptOrderId = null;
    }

    /**
     * Nuke every property that belongs to a single sale. Called after a
     * successful checkout, on Clear cart, and as a defensive guard
     * anywhere we want to start fresh. Properties that survive (search
     * filters, current shift, current category) are intentional.
     */
    private function resetForNewSale(): void
    {
        $this->cart = [];
        $this->discountAmount = 0;
        $this->discountReason = '';
        $this->cashTendered = 0;
        $this->attachToRoom = false;
        $this->guestSearch = '';
        $this->selectedGuestId = null;
        $this->selectedGuestData = null;
    }

    private function resolveRoomNumberForOrder(PosOrder $order): string
    {
        if ($order->room_id === null) return '—';
        $room = \App\Models\Room::find($order->room_id);
        return $room?->number ?? (string) $order->room_id;
    }

    // ──────── Void flow ────────

    public function confirmVoidOrder($posOrderId)
    {
        $order = $this->loadVoidableOrder($posOrderId);
        if (!$order) return;

        $this->dialog()->confirm([
            'title'       => sprintf('Void Order OR-%05d?', $order->id),
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
            sprintf('Order OR-%05d reversed. Stock restored.', $order->id)
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
            $this->notification()->error('Already voided', sprintf('Order OR-%05d was already voided.', $order->id));
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
        $menuQuery = FrontdeskMenu::with('frontdeskInventory')
            ->where('branch_id', auth()->user()->branch_id);

        if ($this->selectedCategory) {
            $menuQuery->where('frontdesk_category_id', $this->selectedCategory);
        }

        if ($this->search) {
            $menuQuery->where('name', 'like', '%' . $this->search . '%');
        }

        $orders = collect();
        if ($this->current_shift) {
            // Cash-only shift total. Voided orders excluded; room-charge
            // orders excluded (no cash hits the drawer for those).
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

            // Bulk-resolve guest names and room numbers for room-charge
            // orders so the cashier sees WHO they charged, not just "ROOM".
            $guestIds = $posOrders->pluck('guest_id')->filter()->unique();
            $roomIds  = $posOrders->pluck('room_id')->filter()->unique();
            $guestNames  = $guestIds->isNotEmpty()
                ? \App\Models\Guest::whereIn('id', $guestIds)->pluck('name', 'id')
                : collect();
            $roomNumbers = $roomIds->isNotEmpty()
                ? \App\Models\Room::whereIn('id', $roomIds)->pluck('number', 'id')
                : collect();

            $orders = $posOrders->map(fn ($o) => [
                'order_id'       => $o->id,
                'order_ref'      => sprintf('OR-%05d', $o->id),
                'date_time'      => $o->created_at,
                'time_short'     => $o->created_at?->format('g:i A'),
                'items'          => $o->lineItems,
                'amount'         => (int) $o->total,
                'item_count'     => (float) $o->lineItems->sum('quantity'),
                'payment_method' => $o->payment_method, // 'cash' | null (room-charge)
                'voided_at'      => $o->voided_at,
                'discount'       => (int) $o->discount_amount,
                'guest_id'       => $o->guest_id,
                'guest_name'     => $o->guest_id ? ($guestNames[$o->guest_id] ?? null) : null,
                'room_number'    => $o->room_id  ? ($roomNumbers[$o->room_id] ?? null) : null,
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
            $this->total_pos = 0;
        }

        // Receipt context (only populated when showReceiptModal is open).
        $receipt = null;
        if ($this->showReceiptModal && $this->receiptOrderId !== null) {
            $order = PosOrder::with(['lineItems' => fn ($q) => $q->orderBy('id')])->find($this->receiptOrderId);
            if ($order) {
                $room = $order->room_id !== null ? \App\Models\Room::find($order->room_id) : null;
                $guest = $order->guest_id !== null ? Guest::find($order->guest_id) : null;
                $guestName = $guest ? ($guest->name ?: ('Guest #' . $guest->id)) : null;
                $receipt = [
                    'order'        => $order,
                    'branch'       => auth()->user()->branch,
                    'cashier_name' => auth()->user()->name,
                    'room_number'  => $room?->number,
                    'guest_name'   => $guestName,
                ];
            }
        }

        return view('livewire.frontdesk.point-of-sale', [
            'menus' => $menuQuery->get()->sortBy(fn ($m) => (float) ($m->frontdeskInventory?->number_of_serving ?? 0) <= 0 ? 1 : 0)->values(),
            'categories' => FrontdeskCategory::where('branch_id', auth()->user()->branch_id)->get(),
            'orders' => $orders,
            'receipt' => $receipt,
        ]);
    }
}
