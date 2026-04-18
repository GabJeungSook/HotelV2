<div class="h-full w-full flex bg-gray-100 overflow-hidden">

    <!-- LEFT SIDE -->
    <div class="flex-1 flex flex-col bg-gray-100">

        <!-- HEADER -->
        <div class="bg-white px-6 py-4 border-b flex justify-between items-center">
            <input
                type="text"
                wire:model.debounce.300ms="search"
                placeholder="Search menu..."
                class="w-96 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-400 outline-none"
            />
            <div class="flex items-center gap-3">
                <button
                    wire:click="openHistoryModal"
                    class="px-4 py-2 bg-gray-800 hover:bg-gray-900 text-white text-sm rounded-lg flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    View Purchase History
                </button>
                <div>
                    <p class="text-sm font-semibold">{{ auth()->user()->name }}</p>
                    <p class="text-xs text-gray-400">POS - {{ auth()->user()->cash_drawer->name ?? '' }}</p>
                </div>
            </div>
        </div>

        <!-- CATEGORY -->
        <div class="bg-white px-6 py-4 border-b">
            <h2 class="font-semibold text-gray-700 mb-3">Choose Category</h2>
            <div class="flex gap-3 flex-wrap">
                <button
                    wire:click="selectCategory(null)"
                    class="px-4 py-2 rounded-lg text-sm {{ !$selectedCategory ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }}">
                    All
                </button>
                @foreach ($categories as $category)
                    <button
                        wire:click="selectCategory({{ $category->id }})"
                        class="px-4 py-2 rounded-lg text-sm {{ $selectedCategory == $category->id ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }}">
                        {{ $category->name }}
                    </button>
                @endforeach
            </div>
        </div>

        <!-- PRODUCTS (SCROLLABLE) -->
        <div class="flex-1 overflow-y-auto p-6">
            <div class="grid grid-cols-3 gap-6">
                @forelse ($menus as $menu)
                    <div class="bg-white rounded-xl shadow hover:shadow-lg transition p-4">
                        @if ($menu->image)
                            <img src="{{ asset('storage/' . $menu->image) }}" alt="{{ $menu->name }}" class="h-40 w-full object-cover rounded-lg mb-3">
                        @else
                            <div class="h-40 bg-gray-200 rounded-lg mb-3 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 text-gray-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z" />
                                </svg>
                            </div>
                        @endif

                        <h3 class="font-semibold text-gray-700">{{ $menu->name }}</h3>
                        <p class="text-blue-600 font-bold mb-3">&#8369;{{ number_format($menu->price, 2) }}</p>

                        <button
                            wire:click="addToCart({{ $menu->id }})"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-lg text-sm">
                            Add to Cart
                        </button>
                    </div>
                @empty
                    <div class="col-span-3 text-center py-10 text-gray-400">
                        No menu items found.
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- BILLING PANEL -->
    <div class="w-96 bg-white border-l flex flex-col h-full">

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
                wire:click="checkout"
                wire:loading.attr="disabled"
                class="w-full mt-3 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-300 text-white py-3 rounded-lg font-semibold">
                <span wire:loading.remove wire:target="checkout">Checkout</span>
                <span wire:loading wire:target="checkout">Processing...</span>
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
                        <button onclick="printPurchaseHistory()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded flex items-center gap-2">
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
                        <div class="mb-4 pb-3 border-b">
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
                                <tr class="bg-gray-100">
                                    <th class="border border-gray-400 px-3 py-2 text-left">DATE/TIME</th>
                                    <th class="border border-gray-400 px-3 py-2 text-left">ORDER ID</th>
                                    <th class="border border-gray-400 px-3 py-2 text-left">ITEMS</th>
                                    <th class="border border-gray-400 px-3 py-2 text-right">AMOUNT</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($orders as $order)
                                    <tr>
                                        <td class="border border-gray-400 px-3 py-2 align-top">{{ $order['date_time']->format('F j, Y g:i A') }}</td>
                                        <td class="border border-gray-400 px-3 py-2 align-top">{{ $order['order_id'] }}</td>
                                        <td class="border border-gray-400 px-3 py-2 align-top">
                                            @foreach($order['items'] as $item)
                                                <div>{{ $item->item_name }} &ndash; {{ $item->quantity }}{{ $item->quantity > 1 ? 'pcs' : 'pc' }}</div>
                                            @endforeach
                                        </td>
                                        <td class="border border-gray-400 px-3 py-2 align-top text-right">&#8369;{{ number_format($order['amount'], 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="border border-gray-400 px-3 py-6 text-center text-gray-400">No purchases recorded for this shift.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if($orders->isNotEmpty())
                                <tfoot>
                                    <tr class="bg-gray-50 font-bold">
                                        <td class="border border-gray-400 px-3 py-2" colspan="3">TOTAL</td>
                                        <td class="border border-gray-400 px-3 py-2 text-right">&#8369;{{ number_format($total_pos, 2) }}</td>
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

</div>
