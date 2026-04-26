<div class="h-[calc(100vh-9rem)] w-full flex bg-gray-100 overflow-hidden">

    {{-- Print stylesheet: when the user prints, hide everything except the
         #purchase-history-printable region. Uses the browser's native
         window.print() — no popup, no extra script registration. --}}
    <style>
        /* ─────────────────────────────────────────────────────────────
           POS receipt — printer-agnostic.
           Same HTML renders cleanly on 58mm/80mm thermal rolls AND on
           A4/Letter. Single narrow column, monospace, no backgrounds.
           Browser's standard print dialog handles the printer choice.
           ───────────────────────────────────────────────────────────── */
        .pos-receipt {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, "Courier New", monospace;
            font-size: 12px;
            line-height: 1.4;
            color: #000;
            max-width: 280px;     /* ~75mm — fits 80mm thermal w/ margin; A4 is fine narrower */
            margin: 0 auto;
        }
        .pos-receipt .r-row { display: flex; justify-content: space-between; gap: 8px; }
        .pos-receipt .r-center { text-align: center; }
        .pos-receipt .r-strong { font-weight: 700; }
        .pos-receipt .r-small { font-size: 11px; }
        .pos-receipt .r-mt { margin-top: 4px; }
        .pos-receipt .r-item-name { font-weight: 600; margin-top: 4px; }
        .pos-receipt .r-rule {
            border-top: 1px dashed #000;
            margin: 6px 0;
        }

        @media print {
            body * { visibility: hidden !important; }

            #purchase-history-printable,
            #purchase-history-printable *,
            #pos-receipt-printable,
            #pos-receipt-printable * { visibility: visible !important; }

            #purchase-history-printable {
                position: absolute !important;
                left: 0; top: 0;
                width: 100%;
                padding: 16px;
                color: #000;
            }
            #purchase-history-printable .print-only-title { display: block !important; }
            #purchase-history-printable table { border-collapse: collapse; width: 100%; font-size: 12px; }
            #purchase-history-printable th,
            #purchase-history-printable td { border: 1px solid #000 !important; padding: 6px; }
            #purchase-history-printable thead { background: #f3f4f6 !important; -webkit-print-color-adjust: exact; }
            /* Screen-only columns (e.g. Void action button) must not print. */
            #purchase-history-printable .no-print { display: none !important; }

            /* POS receipt: position at top-left so thermal printers don't
               waste a strip of paper, and force black-on-white. */
            #pos-receipt-printable {
                position: absolute !important;
                left: 0; top: 0;
                width: 100%;
                padding: 4mm;
                color: #000 !important;
                background: #fff !important;
            }

            @page { margin: 4mm; }
        }
    </style>

    <!-- LEFT SIDE -->
    <div class="flex-1 flex flex-col bg-gray-100 overflow-hidden">

        <!-- HEADER (compact: action buttons + user) -->
        <div class="bg-white px-6 py-3 border-b flex justify-between items-center">
            <div class="flex items-center gap-2 text-sm text-gray-500">
                <span class="font-semibold text-gray-700">POS</span>
                <span>&middot;</span>
                <span>{{ auth()->user()->cash_drawer->name ?? '' }}</span>
            </div>
            <div class="flex items-center gap-3">
                <button
                    wire:click="openStockInModal"
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm rounded-lg flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                    </svg>
                    Stock In
                </button>
                <button
                    wire:click="openHistoryModal"
                    class="px-4 py-2 bg-gray-800 hover:bg-gray-900 text-white text-sm rounded-lg flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    View Purchase History
                </button>
                <div class="text-right">
                    <p class="text-sm font-semibold">{{ auth()->user()->name }}</p>
                    <p class="text-xs text-gray-400">{{ auth()->user()->cash_drawer->name ?? '' }}</p>
                </div>
            </div>
        </div>

        <!-- CATEGORY CHIPS + SEARCH -->
        <div class="bg-white px-6 py-3 border-b">
            <div class="flex items-center gap-3 flex-wrap">
                <button
                    wire:click="selectCategory(null)"
                    class="px-3 py-1.5 rounded-full text-sm transition {{ !$selectedCategory ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                    All
                </button>
                @foreach ($categories as $category)
                    <button
                        wire:click="selectCategory({{ $category->id }})"
                        class="px-3 py-1.5 rounded-full text-sm transition {{ $selectedCategory == $category->id ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                        {{ $category->name }}
                    </button>
                @endforeach
                <div class="flex-1 min-w-[200px] flex justify-end">
                    <div class="relative w-full max-w-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                        <input
                            type="text"
                            wire:model.debounce.300ms="search"
                            placeholder="Search menu..."
                            class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-400 outline-none" />
                    </div>
                </div>
            </div>
        </div>

        <!-- PRODUCTS (SCROLLABLE TILE GRID) -->
        <div class="flex-1 overflow-y-auto p-4">
            <div class="grid grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3">
                @php
                    $stockLevels = \App\Models\FrontdeskInventory::where('branch_id', auth()->user()->branch_id)
                        ->pluck('number_of_serving', 'frontdesk_menu_id');
                    $cartQuantities = collect($cart)->keyBy('menu_id')->map(fn($r) => $r['quantity']);
                @endphp
                @forelse ($menus as $menu)
                    @php
                        $stock = (float) ($stockLevels[$menu->id] ?? 0);
                        $outOfStock = $stock <= 0;
                        $lowStock   = $stock > 0 && $stock < 5;
                        $inCartQty  = (int) ($cartQuantities[$menu->id] ?? 0);
                        $inCart     = $inCartQty > 0;
                    @endphp
                    <button
                        type="button"
                        wire:click="addToCart({{ $menu->id }})"
                        @if($outOfStock) disabled @endif
                        class="group relative bg-white rounded-xl shadow-sm hover:shadow-md active:scale-[0.98] transition text-left flex flex-col overflow-hidden
                               {{ $inCart ? 'ring-2 ring-blue-500 border-blue-500' : 'border border-gray-100 hover:ring-2 hover:ring-blue-400' }}
                               {{ $outOfStock ? 'opacity-50 cursor-not-allowed' : '' }}">

                        @if ($menu->image)
                            <img src="{{ asset('storage/' . $menu->image) }}" alt="{{ $menu->name }}"
                                 class="aspect-square w-full object-cover bg-gray-100">
                        @else
                            <div class="aspect-square w-full bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center p-6">
                                <img src="{{ asset('images/homiLogo.png') }}" alt="HOMI"
                                     class="w-3/4 h-3/4 object-contain opacity-30 group-hover:opacity-50 transition" />
                            </div>
                        @endif

                        <!-- in-cart quantity badge (top-left) -->
                        @if($inCart)
                            <span class="absolute top-2 left-2 min-w-[24px] h-6 px-1.5 rounded-full bg-blue-600 text-white text-xs font-bold flex items-center justify-center shadow ring-2 ring-white">
                                ×{{ $inCartQty }}
                            </span>
                        @endif

                        <!-- stock badge (top-right) -->
                        @if($outOfStock)
                            <span class="absolute top-2 right-2 px-2 py-0.5 rounded-full bg-gray-200 text-gray-600 text-[10px] font-semibold uppercase tracking-wide">Unavailable</span>
                        @elseif($lowStock)
                            <span class="absolute top-2 right-2 px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 text-[10px] font-semibold">Low: {{ (int) $stock }}</span>
                        @endif

                        <div class="p-2.5 {{ $outOfStock ? 'grayscale' : '' }}">
                            <p class="font-semibold text-sm truncate {{ $outOfStock ? 'text-gray-500' : 'text-gray-800' }}" title="{{ $menu->name }}">{{ $menu->name }}</p>
                            <div class="flex justify-between items-baseline mt-0.5">
                                <p class="font-bold text-sm {{ $outOfStock ? 'text-gray-400' : 'text-blue-600' }}">&#8369;{{ number_format($menu->price, 2) }}</p>
                                <p class="text-xs tabular-nums
                                    {{ $outOfStock ? 'text-gray-400' : ($lowStock ? 'text-amber-700 font-semibold' : 'text-gray-500') }}">
                                    @if($outOfStock)
                                        out
                                    @else
                                        {{ rtrim(rtrim(number_format((float)$stock, 2, '.', ''), '0'), '.') }} left
                                    @endif
                                </p>
                            </div>
                        </div>
                    </button>
                @empty
                    <div class="col-span-full text-center py-16 text-gray-400">
                        @if($search)
                            No items match "{{ $search }}".
                        @else
                            No menu items in this category.
                        @endif
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- BILLING PANEL -->
    <div class="w-96 bg-white border-l flex flex-col self-stretch">

        <!-- BILL HEADER -->
        <div class="px-6 py-5 border-b shrink-0 flex justify-between items-center">
            <div class="flex items-center gap-2">
                <h2 class="text-lg font-bold text-gray-700">Cart</h2>
                @if($this->cartItemCount > 0)
                    <span class="px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 text-xs font-semibold">
                        {{ $this->cartItemCount }} {{ $this->cartItemCount === 1 ? 'item' : 'items' }}
                    </span>
                    <button wire:click="confirmClearCart" type="button"
                            class="ml-1 text-xs text-red-500 hover:text-red-700 hover:underline">
                        Clear
                    </button>
                @endif
            </div>
            <div class="text-right">
                <p class="text-xs text-gray-400">Shift Total</p>
                <p class="text-sm font-bold text-green-600">&#8369;{{ number_format($total_pos, 2) }}</p>
            </div>
        </div>

        <!-- ATTACH TO ROOM -->
        <div class="px-6 py-3 border-b shrink-0 bg-gray-50">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-semibold text-gray-700">Charge to a room?</p>
                    <p class="text-xs text-gray-500">
                        @if($attachToRoom)
                            Sale will be added to the guest's folio. No cash collected.
                        @else
                            Off &mdash; this is a cash walk-in sale.
                        @endif
                    </p>
                </div>
                <button type="button" wire:click="toggleAttachToRoom"
                    class="relative inline-flex h-6 w-11 items-center rounded-full transition {{ $attachToRoom ? 'bg-blue-600' : 'bg-gray-300' }}">
                    <span class="inline-block h-4 w-4 transform rounded-full bg-white transition {{ $attachToRoom ? 'translate-x-6' : 'translate-x-1' }}"></span>
                </button>
            </div>

            @if($attachToRoom)
                <div class="mt-3 space-y-2">
                    @if($selectedGuestData)
                        <!-- Selected guest preview -->
                        <div class="flex items-start justify-between rounded border border-blue-200 bg-blue-50 px-3 py-2">
                            <div class="text-sm">
                                <p class="font-semibold text-blue-900">
                                    RM {{ $selectedGuestData['room_number'] ?? '—' }}
                                    &middot; {{ $selectedGuestData['name'] }}
                                </p>
                                <p class="text-[11px] text-blue-700 flex flex-wrap gap-x-2">
                                    @if($selectedGuestData['floor_number'] ?? null)
                                        <span>Floor {{ $selectedGuestData['floor_number'] }}</span>
                                    @endif
                                    @if($selectedGuestData['type_name'] ?? null)
                                        <span>&middot;</span>
                                        <span>{{ $selectedGuestData['type_name'] }}</span>
                                    @endif
                                </p>
                                {{-- Open POS balance display intentionally hidden for now.
                                     Calculation still runs in selectGuest() so we can re-enable
                                     this line later if a credit-limit policy is introduced. --}}
                            </div>
                            <button type="button" wire:click="clearSelectedGuest"
                                class="text-xs text-blue-700 hover:text-blue-900 hover:underline">Change</button>
                        </div>
                    @else
                        <!-- Guest search input + dropdown -->
                        <input type="text" wire:model.debounce.250ms="guestSearch"
                            placeholder="Search by room # or guest name..."
                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                        @if(trim($guestSearch) !== '')
                            <div class="max-h-56 overflow-y-auto rounded border border-gray-200 bg-white divide-y">
                                @forelse($this->guestSearchResults as $g)
                                    <button type="button" wire:click="selectGuest({{ $g['id'] }})"
                                        class="w-full px-3 py-2 text-left hover:bg-blue-50">
                                        <div class="flex justify-between items-start gap-2">
                                            <span class="text-sm font-semibold text-gray-800 truncate">{{ $g['name'] }}</span>
                                            <span class="text-sm font-bold text-blue-700 whitespace-nowrap">RM {{ $g['room_number'] ?? '—' }}</span>
                                        </div>
                                        <div class="mt-0.5 text-[11px] text-gray-500 flex flex-wrap gap-x-2">
                                            @if($g['floor_number'])
                                                <span>Floor {{ $g['floor_number'] }}</span>
                                            @endif
                                            @if($g['type_name'])
                                                <span>&middot;</span>
                                                <span>{{ $g['type_name'] }}</span>
                                            @endif
                                        </div>
                                    </button>
                                @empty
                                    <div class="px-3 py-2 text-sm text-gray-400">No checked-in guests match.</div>
                                @endforelse
                            </div>
                        @endif
                    @endif
                </div>
            @endif
        </div>

        <!-- CART ITEMS (SCROLLABLE) -->
        <div class="flex-1 overflow-y-auto px-6 py-4 space-y-4">
            @forelse ($cart as $index => $item)
                <div class="flex justify-between items-center">
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-gray-700 truncate">{{ $item['name'] }}</p>
                        <p class="text-sm text-gray-400">&#8369;{{ number_format($item['price'], 2) }} x {{ $item['quantity'] }}</p>
                    </div>
                    <div class="flex items-center gap-2 ml-3">
                        <button wire:click="decrementQuantity({{ $index }})" class="w-7 h-7 bg-gray-200 rounded flex items-center justify-center text-sm">-</button>
                        <span class="w-6 text-center">{{ $item['quantity'] }}</span>
                        <button wire:click="incrementQuantity({{ $index }})" class="w-7 h-7 bg-blue-600 text-white rounded flex items-center justify-center text-sm">+</button>
                        <button wire:click="confirmRemoveFromCart({{ $index }})" class="w-7 h-7 bg-red-100 text-red-600 rounded flex items-center justify-center text-sm ml-1">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            @empty
                <div class="flex items-center justify-center h-full text-gray-400 text-sm">
                    No items in cart
                </div>
            @endforelse
        </div>

        <!-- TOTAL (FIXED BOTTOM) -->
        <div class="border-t px-6 py-4 space-y-2 shrink-0">
            <div class="flex justify-between text-sm text-gray-600">
                <span>Subtotal</span>
                <span>&#8369;{{ number_format($this->cartTotal, 2) }}</span>
            </div>

            <div class="space-y-1">
                <div class="flex justify-between items-center text-sm">
                    <label for="pos-discount" class="text-gray-600">Discount (&#8369;)</label>
                    <input id="pos-discount" type="number" min="0" step="1"
                        wire:model.lazy="discountAmount"
                        class="w-24 px-2 py-1 text-sm border border-gray-300 rounded text-right focus:ring-1 focus:ring-blue-500 focus:border-blue-500" />
                </div>
                @if((int) $discountAmount > 0)
                    <input type="text" wire:model.lazy="discountReason"
                        placeholder="Discount reason (e.g. regular customer)"
                        class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500" />
                @endif
            </div>

            <div class="flex justify-between font-bold text-lg pt-1 border-t">
                <span>Total</span>
                <span class="text-blue-600">&#8369;{{ number_format($this->discountedTotal, 2) }}</span>
            </div>

            <button
                wire:click="reviewCheckout"
                wire:loading.attr="disabled"
                @if(empty($cart)) disabled @endif
                class="w-full mt-3 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white py-3 rounded-lg font-semibold">
                <span wire:loading.remove wire:target="reviewCheckout">
                    @if(empty($cart))
                        Add items to checkout
                    @elseif($attachToRoom)
                        Charge to Room
                    @else
                        Review &amp; Checkout
                    @endif
                </span>
                <span wire:loading wire:target="reviewCheckout">Loading...</span>
            </button>
        </div>
    </div>

    @if($showHistoryModal)
        @php
            $shiftHour = optional($current_shift?->time_in)->hour;
            $shiftType = ($shiftHour !== null && $shiftHour >= 6 && $shiftHour < 20) ? 'AM Shift' : 'PM Shift';
            $shiftDateLabel = optional($current_shift?->time_in)->format('F j, Y') ?? '-';
            $shiftStartLabel = optional($current_shift?->time_in)->format('g:i A') ?? '-';
        @endphp
        <div class="fixed inset-0 z-50 bg-black bg-opacity-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-5xl max-h-[90vh] flex flex-col">
                <div class="px-6 py-4 border-b flex justify-between items-center">
                    <h2 class="text-xl font-bold text-gray-800">Purchase History</h2>
                    <div class="flex gap-2">
                        <button onclick="window.print()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" />
                            </svg>
                            Print
                        </button>
                        <button wire:click="closeHistoryModal" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm rounded">Close</button>
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto px-6 py-4">
                    <div id="purchase-history-printable">
                        <h3 class="text-lg font-bold print-only-title" style="display:none;">Purchase History</h3>
                        <div class="mb-4 pb-3 border-b">
                            <div class="grid grid-cols-2 gap-2 text-sm text-gray-700">
                                <div><span class="font-semibold">Shift:</span> {{ $shiftType }}</div>
                                <div><span class="font-semibold">Date:</span> {{ $shiftDateLabel }}</div>
                                <div><span class="font-semibold">Frontdesk:</span> {{ auth()->user()->name }}</div>
                                <div><span class="font-semibold">Cash Drawer:</span> {{ auth()->user()->cash_drawer->name ?? '-' }}</div>
                                <div><span class="font-semibold">Shift Started:</span> {{ $shiftStartLabel }}</div>
                                <div><span class="font-semibold">Shift Total:</span> &#8369;{{ number_format($total_pos, 2) }}</div>
                            </div>
                        </div>
                        <table class="w-full border-collapse text-sm">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="border border-gray-400 px-3 py-2 text-left">DATE/TIME</th>
                                    <th class="border border-gray-400 px-3 py-2 text-left">ORDER ID</th>
                                    <th class="border border-gray-400 px-3 py-2 text-left">TYPE</th>
                                    <th class="border border-gray-400 px-3 py-2 text-left">ITEMS</th>
                                    <th class="border border-gray-400 px-3 py-2 text-right">AMOUNT</th>
                                    <th class="border border-gray-400 px-3 py-2 text-center no-print">ACTION</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($orders as $order)
                                    @php
                                        $isVoided = !empty($order['voided_at']);
                                        $rowClass = $isVoided ? 'text-gray-400 line-through' : '';
                                    @endphp
                                    <tr class="{{ $rowClass }}">
                                        <td class="border border-gray-400 px-3 py-2 align-top">{{ $order['date_time']->format('F j, Y g:i A') }}</td>
                                        <td class="border border-gray-400 px-3 py-2 align-top">{{ $order['order_id'] }}</td>
                                        <td class="border border-gray-400 px-3 py-2 align-top">
                                            @if(($order['payment_method'] ?? null) === 'cash')
                                                <span class="text-green-700 font-semibold">CASH</span>
                                            @elseif(empty($order['payment_method']) && !empty($order['guest_id']))
                                                <span class="text-blue-700 font-semibold">ROOM</span>
                                            @else
                                                &mdash;
                                            @endif
                                            @if($isVoided)
                                                <span class="ml-1 px-1.5 py-0.5 rounded bg-red-100 text-red-700 text-[10px] font-semibold uppercase no-print">Voided</span>
                                            @endif
                                        </td>
                                        <td class="border border-gray-400 px-3 py-2 align-top">
                                            @foreach($order['items'] as $item)
                                                <div>{{ $item->item_name }} &ndash; {{ $item->quantity }}{{ $item->quantity > 1 ? 'pcs' : 'pc' }}</div>
                                            @endforeach
                                        </td>
                                        <td class="border border-gray-400 px-3 py-2 align-top text-right">&#8369;{{ number_format($order['amount'], 2) }}</td>
                                        <td class="border border-gray-400 px-3 py-2 align-top text-center no-print">
                                            @if(!$isVoided)
                                                <button type="button"
                                                    wire:click="confirmVoidOrder({{ $order['order_id'] }})"
                                                    class="px-2 py-1 text-xs bg-red-50 hover:bg-red-100 text-red-700 rounded border border-red-200">
                                                    Void
                                                </button>
                                            @else
                                                <span class="text-xs text-gray-400">&mdash;</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="border border-gray-400 px-3 py-6 text-center text-gray-400">No purchases recorded for this shift.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if($orders->isNotEmpty())
                                <tfoot>
                                    <tr class="bg-gray-50 font-bold">
                                        <td class="border border-gray-400 px-3 py-2" colspan="4">TOTAL (CASH, NON-VOIDED)</td>
                                        <td class="border border-gray-400 px-3 py-2 text-right">&#8369;{{ number_format($total_pos, 2) }}</td>
                                        <td class="border border-gray-400 px-3 py-2 no-print"></td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        </div>

    @endif

    @if($showStockInModal)
        <div class="fixed inset-0 z-50 bg-black bg-opacity-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md flex flex-col">
                <div class="px-6 py-4 border-b">
                    <h2 class="text-xl font-bold text-gray-800">Record Stock In</h2>
                    <p class="text-sm text-gray-500 mt-1">Log incoming inventory (delivery, restock).</p>
                </div>

                <form wire:submit.prevent="submitStockIn" class="px-6 py-4 space-y-4">
                    <div>
                        <x-select
                            label="Item"
                            placeholder="Type to search items..."
                            :options="$menus"
                            option-label="name"
                            option-value="id"
                            wire:model="stockIn_menu_id" />
                        @error('stockIn_menu_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Quantity received</label>
                        <input type="number" step="0.01" min="0.01"
                            wire:model.defer="stockIn_quantity"
                            class="mt-1 block w-full rounded border-gray-300 focus:border-emerald-500 focus:ring-emerald-500" />
                        @error('stockIn_quantity') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Reason / Note <span class="text-gray-400">(supplier, PO #, etc.)</span></label>
                        <input type="text" wire:model.defer="stockIn_reason"
                            placeholder="e.g. Supplier ABC PO #4521"
                            class="mt-1 block w-full rounded border-gray-300 focus:border-emerald-500 focus:ring-emerald-500" />
                    </div>

                    <div class="pt-2 flex justify-end gap-3">
                        <button type="button" wire:click="closeStockInModal"
                            class="px-5 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-lg font-medium">
                            Cancel
                        </button>
                        <button type="submit"
                            wire:loading.attr="disabled"
                            class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:bg-emerald-300 text-white rounded-lg font-semibold">
                            <span wire:loading.remove wire:target="submitStockIn">Record Stock In</span>
                            <span wire:loading wire:target="submitStockIn">Saving...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <x-modal wire:model.defer="showCheckoutConfirm" align="center" max-width="md">
        <x-card title="Confirm Checkout">
            <div class="space-y-3">
                <p class="text-sm text-gray-500">Review the order before submitting.</p>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase text-gray-500 border-b">
                            <th class="py-2">Item</th>
                            <th class="py-2 text-center">Qty</th>
                            <th class="py-2 text-right">Price</th>
                            <th class="py-2 text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($cart as $item)
                            <tr class="border-b last:border-b-0">
                                <td class="py-2">{{ $item['name'] }}</td>
                                <td class="py-2 text-center">{{ $item['quantity'] }}</td>
                                <td class="py-2 text-right">&#8369;{{ number_format($item['price'], 2) }}</td>
                                <td class="py-2 text-right">&#8369;{{ number_format($item['subtotal'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="text-sm text-gray-600">
                            <td class="pt-3" colspan="3">Subtotal</td>
                            <td class="pt-3 text-right">&#8369;{{ number_format($this->cartTotal, 2) }}</td>
                        </tr>
                        @if((int) $discountAmount > 0)
                            <tr class="text-sm text-amber-700">
                                <td colspan="3">
                                    Discount{{ trim((string) $discountReason) !== '' ? ' — ' . $discountReason : '' }}
                                </td>
                                <td class="text-right">&minus;&#8369;{{ number_format((int) $discountAmount, 2) }}</td>
                            </tr>
                        @endif
                        <tr class="font-bold text-base">
                            <td class="pt-2" colspan="3">Total</td>
                            <td class="pt-2 text-right text-blue-600">&#8369;{{ number_format($this->discountedTotal, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>

                <div class="rounded border px-3 py-2 text-sm
                    {{ ($attachToRoom && $selectedGuestData) ? 'border-blue-200 bg-blue-50 text-blue-900' : 'border-green-200 bg-green-50 text-green-900' }}">
                    @if($attachToRoom && $selectedGuestData)
                        <p class="font-semibold">Room Charge</p>
                        <p class="text-xs">
                            RM {{ $selectedGuestData['room_number'] ?? '—' }}
                            &middot; {{ $selectedGuestData['name'] }}
                            &middot; will be added to guest folio
                        </p>
                    @else
                        <p class="font-semibold">Cash Sale</p>
                        <p class="text-xs">Collect &#8369;{{ number_format($this->discountedTotal, 2) }} from customer.</p>
                    @endif
                </div>
            </div>

            <x-slot name="footer">
                <div class="flex justify-end gap-2">
                    <x-button flat label="Cancel" wire:click="cancelCheckout" />
                    <x-button primary
                        label="{{ $attachToRoom ? 'Confirm Room Charge' : 'Confirm & Submit' }}"
                        wire:click="checkout" spinner="checkout" />
                </div>
            </x-slot>
        </x-card>
    </x-modal>

    {{-- Receipt modal (server-rendered, browser-printed, printer-agnostic) --}}
    @if($showReceiptModal && $receipt)
        <div class="fixed inset-0 z-50 bg-black bg-opacity-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md max-h-[90vh] flex flex-col">
                <div class="px-6 py-3 border-b flex justify-between items-center no-print">
                    <h2 class="text-lg font-bold text-gray-800">Receipt</h2>
                    <div class="flex gap-2">
                        <button onclick="window.print()" type="button"
                            class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded">
                            Print
                        </button>
                        <button wire:click="closeReceiptModal" type="button"
                            class="px-3 py-1.5 bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm rounded">
                            Close
                        </button>
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto p-6 bg-gray-50">
                    @include('livewire.frontdesk.pos.receipt', [
                        'order'        => $receipt['order'],
                        'branch'       => $receipt['branch'],
                        'cashier_name' => $receipt['cashier_name'],
                        'room_number'  => $receipt['room_number'],
                        'guest_name'   => $receipt['guest_name'],
                    ])
                </div>
            </div>
        </div>
    @endif

</div>
