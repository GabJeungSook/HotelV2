<div class="h-[calc(100vh-9rem)] w-full flex bg-gray-100 overflow-hidden">

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
                @endphp
                @forelse ($menus as $menu)
                    @php
                        $stock = (float) ($stockLevels[$menu->id] ?? 0);
                        $outOfStock = $stock <= 0;
                        $lowStock   = $stock > 0 && $stock < 5;
                    @endphp
                    <button
                        type="button"
                        wire:click="addToCart({{ $menu->id }})"
                        @if($outOfStock) disabled @endif
                        class="group relative bg-white rounded-xl shadow-sm hover:shadow-md hover:ring-2 hover:ring-blue-400 active:scale-[0.98] transition text-left flex flex-col overflow-hidden border border-gray-100
                               {{ $outOfStock ? 'opacity-50 cursor-not-allowed' : '' }}">

                        @if ($menu->image)
                            <img src="{{ asset('storage/' . $menu->image) }}" alt="{{ $menu->name }}"
                                 class="aspect-square w-full object-cover bg-gray-100">
                        @else
                            <div class="aspect-square w-full bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center">
                                <span class="text-3xl font-bold text-blue-300/70">
                                    {{ strtoupper(mb_substr($menu->name, 0, 1)) }}
                                </span>
                            </div>
                        @endif

                        <!-- stock badge -->
                        @if($outOfStock)
                            <span class="absolute top-2 right-2 px-2 py-0.5 rounded-full bg-red-100 text-red-700 text-[10px] font-semibold uppercase">Out</span>
                        @elseif($lowStock)
                            <span class="absolute top-2 right-2 px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 text-[10px] font-semibold">Low: {{ (int) $stock }}</span>
                        @endif

                        <div class="p-2.5">
                            <p class="font-semibold text-sm text-gray-800 truncate" title="{{ $menu->name }}">{{ $menu->name }}</p>
                            <div class="flex justify-between items-baseline mt-0.5">
                                <p class="text-blue-600 font-bold text-sm">&#8369;{{ number_format($menu->price, 2) }}</p>
                                <p class="text-xs tabular-nums
                                    {{ $outOfStock ? 'text-red-600 font-semibold' : ($lowStock ? 'text-amber-700 font-semibold' : 'text-gray-500') }}">
                                    @if($outOfStock)
                                        0 left
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
            <h2 class="text-lg font-bold text-gray-700">Cart</h2>
            <div class="text-right">
                <p class="text-xs text-gray-400">Shift Total</p>
                <p class="text-sm font-bold text-green-600">&#8369;{{ number_format($total_pos, 2) }}</p>
            </div>
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
                        <button wire:click="removeFromCart({{ $index }})" class="w-7 h-7 bg-red-100 text-red-600 rounded flex items-center justify-center text-sm ml-1">
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
            <div class="flex justify-between font-bold text-lg">
                <span>Total</span>
                <span class="text-blue-600">&#8369;{{ number_format($this->cartTotal, 2) }}</span>
            </div>

            <button
                wire:click="reviewCheckout"
                wire:loading.attr="disabled"
                @if(empty($cart)) disabled @endif
                class="w-full mt-3 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white py-3 rounded-lg font-semibold">
                <span wire:loading.remove wire:target="reviewCheckout">
                    @if(empty($cart))
                        Add items to checkout
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
            $orderCount = $orders->count();
            $itemsTotalQty = $orders->sum('item_count');
            $filteredAmount = $orders->sum('amount');
            $hasSearch = trim($historySearch) !== '';
        @endphp
        <div class="fixed inset-0 z-50 bg-black bg-opacity-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-5xl max-h-[90vh] flex flex-col">

                <!-- HEADER -->
                <div class="px-6 py-4 border-b flex justify-between items-center">
                    <div>
                        <h2 class="text-xl font-bold text-gray-800">Purchase History</h2>
                        <p class="text-xs text-gray-500 mt-0.5">{{ $shiftType }} &middot; {{ $shiftDateLabel }} &middot; Started {{ $shiftStartLabel }}</p>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="printPurchaseHistory()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" />
                            </svg>
                            Print
                        </button>
                        <button wire:click="closeHistoryModal" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm rounded">Close</button>
                    </div>
                </div>

                <!-- SUMMARY STATS + SEARCH -->
                <div class="px-6 py-4 border-b bg-gray-50 grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                    <div class="bg-white rounded-lg border p-3">
                        <p class="text-xs uppercase text-gray-500">Orders {{ $hasSearch ? '(matched)' : '' }}</p>
                        <p class="text-2xl font-bold text-gray-800">{{ $orderCount }}</p>
                    </div>
                    <div class="bg-white rounded-lg border p-3">
                        <p class="text-xs uppercase text-gray-500">Items sold</p>
                        <p class="text-2xl font-bold text-gray-800">{{ number_format($itemsTotalQty, 0) }}</p>
                    </div>
                    <div class="bg-white rounded-lg border p-3">
                        <p class="text-xs uppercase text-gray-500">{{ $hasSearch ? 'Filtered total' : 'Shift total' }}</p>
                        <p class="text-2xl font-bold text-emerald-600">&#8369;{{ number_format($hasSearch ? $filteredAmount : $total_pos, 2) }}</p>
                    </div>
                    <div>
                        <label class="block text-xs uppercase text-gray-500 mb-1">Search item</label>
                        <div class="relative">
                            <input
                                type="text"
                                wire:model.debounce.250ms="historySearch"
                                placeholder="Type item name..."
                                class="w-full pl-3 pr-9 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-400 outline-none text-sm" />
                            @if($hasSearch)
                                <button type="button" wire:click="clearHistorySearch" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- ORDERS TABLE -->
                <div class="flex-1 overflow-y-auto px-6 py-4">
                    <div id="purchase-history-printable">
                        <div class="mb-4 pb-3 border-b print-only">
                            <h3 class="text-lg font-bold">Purchase History</h3>
                            <div class="grid grid-cols-2 gap-2 text-sm text-gray-700 mt-2">
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
                                <tr class="bg-gray-100 text-xs uppercase text-gray-600">
                                    <th class="border border-gray-300 px-3 py-2 text-left w-40">Time</th>
                                    <th class="border border-gray-300 px-3 py-2 text-left w-24">Order #</th>
                                    <th class="border border-gray-300 px-3 py-2 text-left">Items</th>
                                    <th class="border border-gray-300 px-3 py-2 text-right w-32">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($orders as $order)
                                    <tr class="hover:bg-gray-50 even:bg-gray-50/40">
                                        <td class="border border-gray-300 px-3 py-2 align-top">
                                            <div class="font-medium">{{ $order['date_time']->format('g:i A') }}</div>
                                            <div class="text-xs text-gray-500">{{ $order['date_time']->format('M j') }}</div>
                                        </td>
                                        <td class="border border-gray-300 px-3 py-2 align-top font-mono text-xs">#{{ $order['order_id'] }}</td>
                                        <td class="border border-gray-300 px-3 py-2 align-top">
                                            <ul class="space-y-0.5">
                                                @foreach($order['items'] as $item)
                                                    <li class="flex justify-between gap-3 text-sm">
                                                        <span class="text-gray-800">{{ $item->item_name }} <span class="text-gray-500">&times; {{ $item->quantity }}</span></span>
                                                        <span class="text-gray-500 tabular-nums">&#8369;{{ number_format($item->total, 2) }}</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </td>
                                        <td class="border border-gray-300 px-3 py-2 align-top text-right font-semibold tabular-nums">&#8369;{{ number_format($order['amount'], 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="border border-gray-300 px-3 py-10 text-center text-gray-400">
                                            @if($hasSearch)
                                                No orders match "<span class="font-medium">{{ $historySearch }}</span>".
                                                <button type="button" wire:click="clearHistorySearch" class="ml-2 text-blue-600 hover:underline">Clear search</button>
                                            @else
                                                No purchases recorded for this shift.
                                            @endif
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if($orders->isNotEmpty())
                                <tfoot>
                                    <tr class="bg-gray-100 font-bold">
                                        <td class="border border-gray-300 px-3 py-2" colspan="3">TOTAL{{ $hasSearch ? ' (filtered)' : '' }}</td>
                                        <td class="border border-gray-300 px-3 py-2 text-right tabular-nums">&#8369;{{ number_format($hasSearch ? $filteredAmount : $total_pos, 2) }}</td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <script>
            function printPurchaseHistory() {
                var content = document.getElementById('purchase-history-printable').innerHTML;
                var win = window.open('', '_blank', 'width=960,height=720');
                win.document.write(
                    '<!DOCTYPE html><html><head><title>Purchase History</title>' +
                    '<style>' +
                    'body { font-family: Arial, sans-serif; padding: 24px; color: #000; }' +
                    'table { width: 100%; border-collapse: collapse; margin-top: 8px; }' +
                    'th, td { border: 1px solid #000; padding: 8px; font-size: 12px; text-align: left; vertical-align: top; }' +
                    'th { background: #f3f4f6; }' +
                    '.text-right, td.text-right, th.text-right { text-align: right; }' +
                    '.font-bold { font-weight: bold; } .font-semibold { font-weight: 600; }' +
                    '.border-b { border-bottom: 1px solid #000; padding-bottom: 10px; margin-bottom: 14px; }' +
                    '.grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 24px; font-size: 12px; margin-top: 8px; }' +
                    'h3 { margin: 0; font-size: 16px; } tfoot td { background: #f9fafb; font-weight: bold; }' +
                    '</style></head><body>' + content + '</body></html>'
                );
                win.document.close();
                setTimeout(function () { win.focus(); win.print(); }, 400);
            }
        </script>
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

    @if($showCheckoutConfirm)
        <div class="fixed inset-0 z-50 bg-black bg-opacity-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md flex flex-col max-h-[90vh]">
                <div class="px-6 py-4 border-b">
                    <h2 class="text-xl font-bold text-gray-800">Confirm Checkout</h2>
                    <p class="text-sm text-gray-500 mt-1">Review the order before submitting.</p>
                </div>

                <div class="px-6 py-4 overflow-y-auto flex-1">
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
                            <tr class="font-bold text-base">
                                <td class="pt-3" colspan="3">Total</td>
                                <td class="pt-3 text-right text-blue-600">&#8369;{{ number_format($this->cartTotal, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="px-6 py-4 border-t flex justify-end gap-3">
                    <button
                        wire:click="cancelCheckout"
                        wire:loading.attr="disabled"
                        class="px-5 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-lg font-medium">
                        Cancel
                    </button>
                    <button
                        wire:click="checkout"
                        wire:loading.attr="disabled"
                        class="px-5 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-300 text-white rounded-lg font-semibold">
                        <span wire:loading.remove wire:target="checkout">Confirm &amp; Submit</span>
                        <span wire:loading wire:target="checkout">Processing...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>
