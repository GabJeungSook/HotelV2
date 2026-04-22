<div class="max-w-full mx-auto py-8 px-4 sm:px-6 lg:px-8">

    {{-- Tabs --}}
    <div class="mb-6 border-b border-gray-200">
        <nav class="-mb-px flex space-x-8">
            <button type="button" wire:click="setTab('activity')"
                class="py-3 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'activity' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                Activity Report
            </button>
            <button type="button" wire:click="setTab('penalties')"
                class="py-3 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'penalties' ? 'border-red-500 text-red-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                Penalty Report
                @if(count($penalties) > 0)
                    <span class="ml-2 bg-red-100 text-red-600 px-2 py-0.5 rounded-full text-xs">{{ count($penalties) }}</span>
                @endif
            </button>
        </nav>
    </div>

    @if($activeTab === 'activity')
    {{-- ACTIVITY TAB --}}
    <div>
        {{-- Filters --}}
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-4 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Room Boy</label>
                    <select wire:model.defer="roomboy_id"
                            class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All</option>
                        @foreach($roomboys as $rb)
                            <option value="{{ $rb->id }}">{{ $rb->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Shift</label>
                    <select wire:model.defer="shift"
                            class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All</option>
                        <option value="AM">AM</option>
                        <option value="PM">PM</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                    <input type="date"
                           wire:model.defer="date"
                           class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                </div>

                <div class="flex items-end gap-2">
                    <button wire:click="$refresh" type="button"
                            class="w-full md:w-auto inline-flex justify-center rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">
                        Apply
                    </button>
                    <button wire:click="resetFilters" type="button"
                            class="w-full md:w-auto inline-flex justify-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Reset
                    </button>
                </div>
            </div>
        </div>

        {{-- Report --}}
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200 flex justify-between items-center">
                <div class="text-sm font-semibold text-gray-900">ROOM BOY ACTIVITY REPORT</div>
                <div class="text-sm font-semibold text-gray-700">Total Cleaned: {{ $total_cleaned }}</div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full border-collapse">
                    <thead>
                        <tr>
                            <th class="border border-gray-300 px-3 py-2 text-left text-sm font-semibold text-gray-800">DATE/TIME</th>
                            <th class="border border-gray-300 px-3 py-2 text-left text-sm font-semibold text-gray-800">ROOM #</th>
                            <th class="border border-gray-300 px-3 py-2 text-left text-sm font-semibold text-gray-800">ROOM BOY</th>
                            <th class="border border-gray-300 px-3 py-2 text-left text-sm font-semibold text-gray-800">SHIFT</th>
                            <th class="border border-gray-300 px-3 py-2 text-left text-sm font-semibold text-gray-800">STATUS</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reports as $r)
                            <tr>
                                <td class="border border-gray-300 px-3 py-3 text-sm text-gray-900">
                                    {{ \Carbon\Carbon::parse($r->created_at)->format('F d, Y h:i A') }}
                                </td>
                                <td class="border border-gray-300 px-3 py-3 text-sm text-gray-900">
                                    {{ $r->room?->number ?? '—' }}
                                </td>
                                <td class="border border-gray-300 px-3 py-3 text-sm text-gray-900">
                                    {{ $r->roomboy?->name ?? '—' }}
                                </td>
                                <td class="border border-gray-300 px-3 py-3 text-sm text-gray-900">
                                    {{ $r->shift ?? '—' }}
                                </td>
                                <td class="border border-gray-300 px-3 py-3 text-sm text-gray-900">
                                    {{ $r->is_cleaned ? 'CLEANED' : 'NOT CLEANED' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="border border-gray-300 px-3 py-6 text-sm text-center text-gray-500">
                                    No room boy records found for the selected filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t border-gray-200">
                {{ $reports->links() }}
            </div>
        </div>
    </div>

    @else
    {{-- PENALTIES TAB --}}
    <div>
        {{-- Filters --}}
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-4 mb-6">
            <div class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                    <input type="date" wire:model.defer="penaltyDateFrom" class="rounded-lg border-gray-300 focus:border-red-500 focus:ring-red-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                    <input type="date" wire:model.defer="penaltyDateTo" class="rounded-lg border-gray-300 focus:border-red-500 focus:ring-red-500">
                </div>
                <div class="flex gap-2">
                    <button wire:click="loadPenalties" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
                        Generate Report
                    </button>
                    <button wire:click="resetPenaltyFilters" class="border border-gray-300 px-4 py-2 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Reset
                    </button>
                </div>
            </div>
        </div>

        {{-- Summary Cards --}}
        @if(count($penalties) > 0)
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-4">
                <div class="text-sm text-gray-500">Total Penalties</div>
                <div class="text-2xl font-bold text-red-600">{{ count($penalties) }}</div>
            </div>
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-4">
                <div class="text-sm text-gray-500">Total Amount</div>
                <div class="text-2xl font-bold text-red-600">{{ number_format($totalPenaltyAmount, 2) }}</div>
            </div>
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-4">
                <div class="text-sm text-gray-500">Average Penalty</div>
                <div class="text-2xl font-bold text-gray-800">{{ number_format($totalPenaltyAmount / max(count($penalties), 1), 2) }}</div>
            </div>
        </div>
        @endif

        {{-- Report --}}
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200">
                <div class="text-sm font-semibold text-gray-900">ROOM BOY PENALTY REPORT</div>
                <div class="text-xs text-gray-500">Rooms that exceeded 4-hour cleaning time limit</div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full border-collapse">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="border border-gray-300 px-3 py-2 text-left text-sm font-semibold text-gray-800">#</th>
                            <th class="border border-gray-300 px-3 py-2 text-left text-sm font-semibold text-gray-800">DATE</th>
                            <th class="border border-gray-300 px-3 py-2 text-left text-sm font-semibold text-gray-800">RM#</th>
                            <th class="border border-gray-300 px-3 py-2 text-left text-sm font-semibold text-gray-800">TYPE</th>
                            <th class="border border-gray-300 px-3 py-2 text-left text-sm font-semibold text-gray-800">ROOM BOY</th>
                            <th class="border border-gray-300 px-3 py-2 text-left text-sm font-semibold text-gray-800">GUEST</th>
                            <th class="border border-gray-300 px-3 py-2 text-left text-sm font-semibold text-gray-800">CHECK-OUT</th>
                            <th class="border border-gray-300 px-3 py-2 text-left text-sm font-semibold text-gray-800">CLEANED</th>
                            <th class="border border-gray-300 px-3 py-2 text-left text-sm font-semibold text-gray-800">DURATION</th>
                            <th class="border border-gray-300 px-3 py-2 text-left text-sm font-semibold text-gray-800">EXCESS</th>
                            <th class="border border-gray-300 px-3 py-2 text-right text-sm font-semibold text-gray-800">AMOUNT</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($penalties as $index => $penalty)
                        <tr class="{{ $index % 2 == 0 ? 'bg-white' : 'bg-gray-50' }}">
                            <td class="border border-gray-300 px-3 py-2 text-sm text-gray-600">{{ $index + 1 }}</td>
                            <td class="border border-gray-300 px-3 py-2 text-sm">{{ $penalty['date'] }}</td>
                            <td class="border border-gray-300 px-3 py-2 text-sm font-medium">{{ $penalty['room_number'] }}</td>
                            <td class="border border-gray-300 px-3 py-2 text-sm">{{ $penalty['room_type'] }}</td>
                            <td class="border border-gray-300 px-3 py-2 text-sm font-medium">{{ $penalty['roomboy_name'] }}</td>
                            <td class="border border-gray-300 px-3 py-2 text-sm">{{ $penalty['guest_name'] }}</td>
                            <td class="border border-gray-300 px-3 py-2 text-sm">{{ $penalty['checkout_time'] }}</td>
                            <td class="border border-gray-300 px-3 py-2 text-sm">{{ $penalty['cleaning_end'] }}</td>
                            <td class="border border-gray-300 px-3 py-2 text-sm">{{ $penalty['duration'] }}</td>
                            <td class="border border-gray-300 px-3 py-2 text-sm text-red-600 font-medium">{{ $penalty['excess'] }}</td>
                            <td class="border border-gray-300 px-3 py-2 text-sm text-right font-medium">{{ number_format($penalty['amount'], 2) }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="11" class="border border-gray-300 px-3 py-8 text-center text-gray-500">
                                <div class="flex flex-col items-center">
                                    <svg class="w-12 h-12 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <p class="font-medium">No penalties found</p>
                                    <p class="text-sm">Click "Generate Report" to load data</p>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                    @if(count($penalties) > 0)
                    <tfoot class="bg-gray-100">
                        <tr>
                            <td colspan="10" class="border border-gray-300 px-3 py-3 text-right font-bold text-gray-800">TOTAL PENALTY</td>
                            <td class="border border-gray-300 px-3 py-3 text-right font-bold text-red-600 text-lg">{{ number_format($totalPenaltyAmount, 2) }}</td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>

            @if(count($penalties) > 0)
            <div class="p-4 bg-gray-50 border-t border-gray-200">
                <button onclick="window.print()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                    Print Report
                </button>
            </div>
            @endif
        </div>
    </div>
    @endif

</div>
