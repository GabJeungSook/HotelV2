<div class="p-6">
    <div class="max-w-7xl mx-auto">
        {{-- Header --}}
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Room Boy Penalty Report</h1>
            <p class="text-gray-500 text-sm">Rooms that exceeded 4-hour cleaning time limit</p>
        </div>

        {{-- Filters --}}
        <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
            <div class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                    <input type="date" wire:model="dateFrom" class="border border-gray-300 rounded px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                    <input type="date" wire:model="dateTo" class="border border-gray-300 rounded px-3 py-2 text-sm">
                </div>
                <div>
                    <button wire:click="generateReport" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded text-sm font-medium">
                        Generate Report
                    </button>
                </div>
            </div>
        </div>

        {{-- Report Content --}}
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            {{-- Print Header (hidden on screen) --}}
            <div class="hidden print:block p-4 border-b">
                <h2 class="text-xl font-bold text-center">ROOM BOY PENALTY REPORT</h2>
                <p class="text-center text-sm text-gray-600">
                    {{ \Carbon\Carbon::parse($dateFrom)->format('F d, Y') }} - {{ \Carbon\Carbon::parse($dateTo)->format('F d, Y') }}
                </p>
            </div>

            {{-- Summary Cards --}}
            @if(count($penalties) > 0)
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 p-4 bg-gray-50 print:hidden">
                <div class="bg-white rounded-lg p-4 border">
                    <div class="text-sm text-gray-500">Total Penalties</div>
                    <div class="text-2xl font-bold text-red-600">{{ count($penalties) }}</div>
                </div>
                <div class="bg-white rounded-lg p-4 border">
                    <div class="text-sm text-gray-500">Total Amount</div>
                    <div class="text-2xl font-bold text-red-600">{{ number_format($totalPenaltyAmount, 2) }}</div>
                </div>
                <div class="bg-white rounded-lg p-4 border">
                    <div class="text-sm text-gray-500">Average Penalty</div>
                    <div class="text-2xl font-bold text-gray-800">{{ number_format($totalPenaltyAmount / count($penalties), 2) }}</div>
                </div>
            </div>
            @endif

            {{-- Table --}}
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-100 border-b">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700">#</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700">DATE</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700">RM#</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700">TYPE</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700">FLOOR</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700">ROOM BOY</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700">GUEST</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700">CHECK-OUT</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700">CLEANED</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700">DURATION</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-700">AMOUNT</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($penalties as $index => $penalty)
                        <tr class="{{ $index % 2 == 0 ? 'bg-white' : 'bg-gray-50' }}">
                            <td class="px-4 py-3 text-gray-600">{{ $index + 1 }}</td>
                            <td class="px-4 py-3">{{ $penalty['date'] }}</td>
                            <td class="px-4 py-3 font-medium">{{ $penalty['room_number'] }}</td>
                            <td class="px-4 py-3">{{ $penalty['room_type'] }}</td>
                            <td class="px-4 py-3">{{ $penalty['floor'] }}</td>
                            <td class="px-4 py-3 font-medium">{{ $penalty['roomboy_name'] }}</td>
                            <td class="px-4 py-3">{{ $penalty['guest_name'] }}</td>
                            <td class="px-4 py-3">{{ $penalty['checkout_time'] }}</td>
                            <td class="px-4 py-3">{{ $penalty['cleaning_end'] }}</td>
                            <td class="px-4 py-3 text-red-600 font-medium">{{ $penalty['duration'] }}</td>
                            <td class="px-4 py-3 text-right font-medium">{{ number_format($penalty['amount'], 2) }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="11" class="px-4 py-8 text-center text-gray-400">
                                <div class="flex flex-col items-center">
                                    <svg class="w-12 h-12 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <p class="text-lg font-medium">No penalties found</p>
                                    <p class="text-sm">Click "Generate Report" to load data for the selected date range</p>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                    @if(count($penalties) > 0)
                    <tfoot class="bg-gray-100 border-t-2 border-gray-300">
                        <tr>
                            <td colspan="10" class="px-4 py-3 text-right font-bold text-gray-800">TOTAL PENALTY</td>
                            <td class="px-4 py-3 text-right font-bold text-red-600 text-lg">{{ number_format($totalPenaltyAmount, 2) }}</td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>

            {{-- Print Button --}}
            @if(count($penalties) > 0)
            <div class="p-4 bg-gray-50 border-t print:hidden">
                <button onclick="window.print()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded text-sm font-medium">
                    Print Report
                </button>
            </div>
            @endif
        </div>
    </div>
</div>
