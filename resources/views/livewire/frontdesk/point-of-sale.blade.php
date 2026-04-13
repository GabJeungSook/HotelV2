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

</div>
