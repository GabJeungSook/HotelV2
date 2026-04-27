<div class="max-w-full mx-auto py-8 px-4 sm:px-6 lg:px-8">

    {{-- Filters --}}
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-6 gap-4">

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

            {{-- Status --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select wire:model.defer="status"
                        class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="declined">Declined</option>
                </select>
            </div>

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

            {{-- Requester --}}
            <div>
                <x-select
                    label="Requester"
                    placeholder="All"
                    wire:model.defer="requester_id"
                    :searchable="true"
                    :clearable="true"
                >
                    @foreach ($requesters as $user)
                        <x-select.option label="{{ $user->name }}" value="{{ $user->id }}" />
                    @endforeach
                </x-select>
            </div>

            {{-- Supervisor --}}
            <div>
                <x-select
                    label="Supervisor"
                    placeholder="All"
                    wire:model.defer="supervisor_id"
                    :searchable="true"
                    :clearable="true"
                >
                    @foreach ($supervisors as $user)
                        <x-select.option label="{{ $user->name }}" value="{{ $user->id }}" />
                    @endforeach
                </x-select>
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
                OVERRIDE REQUEST HISTORY
            </div>
            <div class="text-sm font-semibold text-gray-700">
                Total Requests: {{ $totalCount }}
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse">
                <thead>
                    <tr>
                        <th class="border border-gray-300 px-3 py-2 text-left text-sm font-semibold text-gray-800">DATE</th>
                        <th class="border border-gray-300 px-3 py-2 text-left text-sm font-semibold text-gray-800">TYPE</th>
                        <th class="border border-gray-300 px-3 py-2 text-left text-sm font-semibold text-gray-800">STATUS</th>
                        <th class="border border-gray-300 px-3 py-2 text-left text-sm font-semibold text-gray-800">REQUESTER</th>
                        <th class="border border-gray-300 px-3 py-2 text-left text-sm font-semibold text-gray-800">SUPERVISOR</th>
                        <th class="border border-gray-300 px-3 py-2 text-left text-sm font-semibold text-gray-800">GUEST</th>
                        <th class="border border-gray-300 px-3 py-2 text-left text-sm font-semibold text-gray-800">ROOM</th>
                        <th class="border border-gray-300 px-3 py-2 text-left text-sm font-semibold text-gray-800">REASON</th>
                        <th class="border border-gray-300 px-3 py-2 text-left text-sm font-semibold text-gray-800">RESPONSE TIME</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($requests as $request)
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
                            <td class="border border-gray-300 px-3 py-3 text-sm">
                                @if ($request->status === 'approved')
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        Approved
                                    </span>
                                @elseif ($request->status === 'declined')
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        Declined
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                        Pending
                                    </span>
                                @endif
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
                                @if ($request->status === 'declined')
                                    {{ $request->decline_reason ?? '—' }}
                                @elseif ($request->transaction_type === 'cancel')
                                    {{ $request->cancel_reason ?? '—' }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="border border-gray-300 px-3 py-3 text-sm text-gray-900">
                                {{ $this->getResponseTime($request) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="border border-gray-300 px-3 py-6 text-sm text-center text-gray-500">
                                No override requests found for the selected filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if ($requests->hasPages())
            <div class="p-4 border-t border-gray-200">
                {{ $requests->links() }}
            </div>
        @endif

    </div>
</div>
