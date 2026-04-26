<div class="px-4 sm:px-6 lg:px-8 py-6">
    <div class="mb-4">
        <h1 class="text-2xl font-bold text-gray-900">Price &amp; Name Changes</h1>
        <p class="text-sm text-gray-600 mt-1">
            Audit log of every menu price or name change across Frontdesk, Kitchen, and Pub.
            Every edit on a menu item is recorded here automatically.
        </p>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-lg shadow ring-1 ring-gray-200 p-4 mb-4">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Source</label>
                <select wire:model.live="sourceFilter" class="w-full rounded-md border-gray-300 text-sm">
                    @foreach($sources as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">What changed</label>
                <select wire:model.live="fieldFilter" class="w-full rounded-md border-gray-300 text-sm">
                    @foreach($fields as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Search</label>
                <input type="text" wire:model.live.debounce.400ms="search" placeholder="Item or user name"
                    class="w-full rounded-md border-gray-300 text-sm" />
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">From</label>
                <input type="date" wire:model.live="dateFrom" class="w-full rounded-md border-gray-300 text-sm" />
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">To</label>
                <input type="date" wire:model.live="dateTo" class="w-full rounded-md border-gray-300 text-sm" />
            </div>
        </div>
        <div class="mt-3 flex justify-end">
            <button wire:click="clearFilters" class="text-sm text-gray-600 hover:text-gray-900 underline">Reset filters</button>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-lg shadow ring-1 ring-gray-200 overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-700">
                <tr>
                    <th class="px-3 py-2 text-left">When</th>
                    <th class="px-3 py-2 text-left">Source</th>
                    <th class="px-3 py-2 text-left">Item</th>
                    <th class="px-3 py-2 text-center">Field</th>
                    <th class="px-3 py-2 text-right">Old value</th>
                    <th class="px-3 py-2 text-right">New value</th>
                    <th class="px-3 py-2 text-left">Changed by</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($changes as $c)
                    @php
                        $name = $itemNames[$c->source_type][$c->menu_id] ?? '(menu #' . $c->menu_id . ')';
                        $actor = $c->changed_by_user_id ? ($userNames[$c->changed_by_user_id] ?? '(user #' . $c->changed_by_user_id . ')') : '—';
                        $isPrice = $c->field === 'price';
                    @endphp
                    <tr>
                        <td class="px-3 py-2 whitespace-nowrap text-gray-600">{{ $c->created_at->format('M j, Y g:i A') }}</td>
                        <td class="px-3 py-2 uppercase text-xs text-gray-500">{{ $c->source_type }}</td>
                        <td class="px-3 py-2 font-semibold text-gray-900">{{ $name }}</td>
                        <td class="px-3 py-2 text-center">
                            <span class="inline-block px-2 py-0.5 rounded text-xs font-bold {{ $isPrice ? 'text-blue-700 bg-blue-50' : 'text-purple-700 bg-purple-50' }}">{{ strtoupper($c->field) }}</span>
                        </td>
                        <td class="px-3 py-2 text-right text-gray-500 line-through">
                            @if($isPrice)&#8369;@endif{{ $c->old_value ?? '—' }}
                        </td>
                        <td class="px-3 py-2 text-right font-bold text-gray-900">
                            @if($isPrice)&#8369;@endif{{ $c->new_value ?? '—' }}
                        </td>
                        <td class="px-3 py-2 text-gray-700">{{ $actor }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-3 py-8 text-center text-gray-400">
                            No price or name changes match your filters.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3 border-t border-gray-100">
            {{ $changes->links() }}
        </div>
    </div>
</div>
