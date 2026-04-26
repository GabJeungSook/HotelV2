<div class="px-4 sm:px-6 lg:px-8 py-6">
    <div class="mb-4 flex justify-between items-end">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Stock Movements</h1>
            <p class="text-sm text-gray-600 mt-1">
                Every change to inventory across POS, Kitchen, Pub, and admin
                stock entries. Each row is one audited event.
            </p>
        </div>
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
                <label class="block text-xs font-semibold text-gray-700 mb-1">Type</label>
                <select wire:model.live="typeFilter" class="w-full rounded-md border-gray-300 text-sm">
                    @foreach($types as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Search</label>
                <input type="text" wire:model.live.debounce.400ms="search" placeholder="Item, user, reason, or ref #"
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
            <button wire:click="clearFilters"
                class="text-sm text-gray-600 hover:text-gray-900 underline">
                Reset filters
            </button>
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
                    <th class="px-3 py-2 text-center">Type</th>
                    <th class="px-3 py-2 text-right">Quantity</th>
                    <th class="px-3 py-2 text-right">Balance after</th>
                    <th class="px-3 py-2 text-left">By</th>
                    <th class="px-3 py-2 text-left">Reason / Ref</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($movements as $m)
                    @php
                        $name = $itemNames[$m->source_type][$m->menu_id] ?? '(menu #' . $m->menu_id . ')';
                        $actor = $m->user_id ? ($userNames[$m->user_id] ?? '(user #' . $m->user_id . ')') : '—';
                        $typeColor = match ($m->type) {
                            'IN', 'VOID' => 'text-green-700 bg-green-50',
                            'OUT'        => 'text-red-700 bg-red-50',
                            'ADJUST'     => 'text-amber-700 bg-amber-50',
                            'OPENING'    => 'text-gray-700 bg-gray-100',
                            default      => 'text-gray-700 bg-gray-100',
                        };
                        $sign = match ($m->type) {
                            'IN', 'VOID', 'OPENING' => '+',
                            'OUT'                   => '-',
                            'ADJUST'                => '±',
                            default                 => '',
                        };
                    @endphp
                    <tr>
                        <td class="px-3 py-2 whitespace-nowrap text-gray-600">{{ $m->created_at->format('M j, Y g:i A') }}</td>
                        <td class="px-3 py-2 uppercase text-xs text-gray-500">{{ $m->source_type }}</td>
                        <td class="px-3 py-2 font-semibold text-gray-900">{{ $name }}</td>
                        <td class="px-3 py-2 text-center">
                            <span class="inline-block px-2 py-0.5 rounded text-xs font-bold {{ $typeColor }}">{{ $m->type }}</span>
                        </td>
                        <td class="px-3 py-2 text-right font-semibold">{{ $sign }}{{ rtrim(rtrim(number_format((float) $m->quantity, 2), '0'), '.') }}</td>
                        <td class="px-3 py-2 text-right">{{ rtrim(rtrim(number_format((float) $m->balance_after, 2), '0'), '.') }}</td>
                        <td class="px-3 py-2 text-gray-700">{{ $actor }}</td>
                        <td class="px-3 py-2 text-xs text-gray-500">
                            @if($m->reason) <div>{{ $m->reason }}</div> @endif
                            @if($m->ref_type)
                                <div class="text-gray-400">{{ $m->ref_type }}@if($m->ref_id) #{{ $m->ref_id }}@endif</div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-3 py-8 text-center text-gray-400">
                            No stock movements match your filters.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3 border-t border-gray-100">
            {{ $movements->links() }}
        </div>
    </div>
</div>
