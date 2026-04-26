<div class="p-6 max-w-2xl">
    <h2 class="text-2xl font-semibold mb-1">Record Stock In</h2>
    <p class="text-sm text-gray-500 mb-6">Log incoming inventory (deliveries, restocks). Every entry creates an audit row in stock_movements.</p>

    <form wire:submit.prevent="submit" class="space-y-4 bg-white rounded shadow p-6">
        <div>
            <label class="block text-sm font-medium text-gray-700">Stock Source</label>
            <select wire:model="source_type" class="mt-1 block w-full rounded border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                <option value="frontdesk">Frontdesk POS</option>
                <option value="kitchen">Kitchen</option>
                <option value="pub">Pub</option>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Item</label>
            <select wire:model="menu_id" class="mt-1 block w-full rounded border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">— pick an item —</option>
                @foreach ($menus as $m)
                    <option value="{{ $m->id }}">{{ $m->name }}</option>
                @endforeach
            </select>
            @error('menu_id')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Quantity received</label>
            <input type="number" step="0.01" min="0.01"
                   wire:model.defer="quantity"
                   class="mt-1 block w-full rounded border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
            @error('quantity')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Reason / Note <span class="text-gray-400">(supplier, PO #, etc.)</span></label>
            <input type="text" wire:model.defer="reason"
                   placeholder="e.g. Supplier ABC PO #4521"
                   class="mt-1 block w-full rounded border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
        </div>

        <div class="pt-2">
            <button type="submit"
                    class="bg-indigo-600 text-white px-5 py-2 rounded hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Record Stock In
            </button>
        </div>
    </form>
</div>
