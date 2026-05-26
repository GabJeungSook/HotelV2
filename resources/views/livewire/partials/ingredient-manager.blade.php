<div class="mt-4 border-t pt-3">
    <h3 class="text-sm font-semibold text-gray-700 mb-2">Ingredients (Recipe)</h3>

    <div class="mb-2">
        <input type="text" wire:model.debounce.300ms="ingredientSearch" placeholder="Search menu items..."
            class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
    </div>

    @if (strlen($ingredientSearch) >= 2 && count($this->ingredientSearchResults) > 0)
        <div class="bg-white border border-gray-200 rounded-md shadow-sm max-h-40 overflow-y-auto mb-2">
            @foreach ($this->ingredientSearchResults as $result)
                <button type="button"
                    wire:click="addIngredient('{{ $result['type'] }}', {{ $result['id'] }}, '{{ addslashes($result['name']) }}')"
                    class="block w-full text-left px-3 py-1.5 hover:bg-gray-50 text-sm border-b border-gray-100 last:border-0">
                    <span
                        class="inline-block uppercase text-xs font-medium bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded mr-1">{{ $result['type'] }}</span>
                    {{ $result['name'] }}
                    <span class="text-gray-400 text-xs ml-1">₱{{ number_format($result['price'], 2) }}</span>
                </button>
            @endforeach
        </div>
    @elseif(strlen($ingredientSearch) >= 2 && count($this->ingredientSearchResults) === 0)
        <p class="text-sm text-gray-400 mb-2">No items found.</p>
    @endif

    @if (count($ingredients) > 0)
        <div class="space-y-1">
            @foreach ($ingredients as $index => $ingredient)
                <div class="flex items-center gap-2 bg-gray-50 rounded-md px-2 py-1.5">
                    <span
                        class="inline-block uppercase text-xs font-medium px-1.5 py-0.5 rounded
                        {{ $ingredient['type'] === 'kitchen' ? 'bg-green-100 text-green-700' : ($ingredient['type'] === 'pub' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700') }}">
                        {{ $ingredient['type'] }}
                    </span>
                    <span class="flex-1 text-sm font-medium text-gray-700">{{ $ingredient['name'] }}</span>
                    <div class="flex items-center gap-1">
                        <label class="text-xs text-gray-500">Qty:</label>
                        <input type="number" wire:model.lazy="ingredients.{{ $index }}.quantity"
                            class="w-20 rounded-md border-gray-300 text-sm text-center shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                            min="0.01" step="0.01">
                    </div>
                    <button type="button" wire:click="removeIngredient({{ $index }})"
                        class="text-red-400 hover:text-red-600 text-sm font-medium px-1">
                        ✕
                    </button>
                </div>
            @endforeach
        </div>
    @else
        <p class="text-xs text-gray-400">No ingredients added. This item will use its own stock only.</p>
    @endif
</div>
