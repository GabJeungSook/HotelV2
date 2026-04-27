<div class="max-w-full mx-auto py-8 px-4 sm:px-6 lg:px-8">

    {{-- Filters --}}
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">

            @if (!$weekStart)
                {{-- Date From --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                    <input type="date"
                           wire:model.defer="date_from"
                           class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                {{-- Date To --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                    <input type="date"
                           wire:model.defer="date_to"
                           class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            @endif

            {{-- Transaction Type --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                <select wire:model.defer="transaction_type"
                        class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All</option>
                    <option value="transfer">Transfer</option>
                    <option value="cancel">Cancel</option>
                </select>
            </div>

            {{-- View Mode --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">View Mode</label>
                <select wire:model.defer="view_mode"
                        class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="summary">Summary</option>
                    <option value="details">Detailed List</option>
                </select>
            </div>

            {{-- Buttons --}}
            <div class="flex items-end gap-2">
                <button wire:click="$refresh"
                        class="w-full md:w-auto inline-flex justify-center rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">
                    Apply
                </button>

                <button wire:click="resetFilters"
                        class="w-full md:w-auto inline-flex justify-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Reset
                </button>
            </div>

        </div>
    </div>

    {{-- Report --}}
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">

        <div class="px-4 py-3 border-b border-gray-200 flex justify-between items-center">
            <div class="text-sm font-semibold text-gray-900">
                DECLINE REASONS REPORT
            </div>
            <div class="text-sm font-semibold text-gray-700">
                Total Declined: {{ $totalCount }}
            </div>
        </div>

        <div class="overflow-x-auto">
            @if ($view_mode === 'summary' && $summary)
                {{-- Summary View --}}
                <table class="min-w-full border-collapse">
                    <thead>
                        <tr>
                            <th class="border border-gray-300 px-3 py-2 text-left text-sm font-semibold text-gray-800">DECLINE REASON</th>
                            <th class="border border-gray-300 px-3 py-2 text-center text-sm font-semibold text-gray-800">COUNT</th>
                            <th class="border border-gray-300 px-3 py-2 text-center text-sm font-semibold text-gray-800">TRANSFERS</th>
                            <th class="border border-gray-300 px-3 py-2 text-center text-sm font-semibold text-gray-800">CANCELS</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($summary as $item)
                            <tr>
                                <td class="border border-gray-300 px-3 py-3 text-sm text-gray-900">
                                    {{ $item['reason'] }}
                                </td>
                                <td class="border border-gray-300 px-3 py-3 text-sm text-gray-900 text-center font-semibold">
                                    {{ $item['count'] }}
                                </td>
                                <td class="border border-gray-300 px-3 py-3 text-sm text-center">
                                    @if ($item['transfers'] > 0)
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            {{ $item['transfers'] }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">0</span>
                                    @endif
                                </td>
                                <td class="border border-gray-300 px-3 py-3 text-sm text-center">
                                    @if ($item['cancels'] > 0)
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                            {{ $item['cancels'] }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">0</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="border border-gray-300 px-3 py-6 text-sm text-center text-gray-500">
                                    No declined requests found for the selected filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                {{-- Detailed View --}}
                <table class="min-w-full border-collapse">
                    <thead>
                        <tr>
                            <th class="border border-gray-300 px-3 py-2 text-left text-sm font-semibold text-gray-800">DATE</th>
                            <th class="border border-gray-300 px-3 py-2 text-left text-sm font-semibold text-gray-800">TYPE</th>
                            <th class="border border-gray-300 px-3 py-2 text-left text-sm font-semibold text-gray-800">REQUESTER</th>
                            <th class="border border-gray-300 px-3 py-2 text-left text-sm font-semibold text-gray-800">SUPERVISOR</th>
                            <th class="border border-gray-300 px-3 py-2 text-left text-sm font-semibold text-gray-800">GUEST</th>
                            <th class="border border-gray-300 px-3 py-2 text-left text-sm font-semibold text-gray-800">ROOM</th>
                            <th class="border border-gray-300 px-3 py-2 text-left text-sm font-semibold text-gray-800">DECLINE REASON</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($details as $request)
                            <tr>
                                <td class="border border-gray-300 px-3 py-3 text-sm text-gray-900">
                                    {{ \Carbon\Carbon::parse($request->created_at)->format('M d, Y h:i A') }}
                                </td>
                                <td class="border border-gray-300 px-3 py-3 text-sm text-gray-900">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                        {{ $request->transaction_type === 'transfer' ? 'bg-blue-100 text-blue-800' : 'bg-orange-100 text-orange-800' }}">
                                        {{ ucfirst($request->transaction_type) }}
                                    </span>
                                </td>
                                <td class="border border-gray-300 px-3 py-3 text-sm text-gray-900">
                                    {{ $request->requester?->name ?? '—' }}
                                </td>
                                <td class="border border-gray-300 px-3 py-3 text-sm text-gray-900">
                                    {{ $request->supervisor?->name ?? '—' }}
                                </td>
                                <td class="border border-gray-300 px-3 py-3 text-sm text-gray-900">
                                    {{ $request->guest_name ?? '—' }}
                                </td>
                                <td class="border border-gray-300 px-3 py-3 text-sm text-gray-900">
                                    @if ($request->transaction_type === 'transfer')
                                        {{ $request->from_room_number ?? '—' }} &rarr; {{ $request->to_room_number ?? '—' }}
                                    @else
                                        {{ $request->from_room_number ?? '—' }}
                                    @endif
                                </td>
                                <td class="border border-gray-300 px-3 py-3 text-sm text-gray-900">
                                    {{ $request->decline_reason ?? 'No reason provided' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="border border-gray-300 px-3 py-6 text-sm text-center text-gray-500">
                                    No declined requests found for the selected filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                {{-- Pagination --}}
                @if ($details && $details->hasPages())
                    <div class="p-4 border-t border-gray-200">
                        {{ $details->links() }}
                    </div>
                @endif
            @endif
        </div>

    </div>
</div>
