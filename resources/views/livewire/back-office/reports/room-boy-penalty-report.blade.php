<div class="max-w-full mx-auto py-8 px-4 sm:px-6 lg:px-8">

    {{-- Filters --}}
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">

            {{-- Roomboy --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Room Boy</label>
                <select wire:model="roomboy_id"
                        class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All</option>
                    @foreach($roomboys as $rb)
                        <option value="{{ $rb->id }}">{{ $rb->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Shift --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Shift</label>
                <select wire:model="shift"
                        class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All</option>
                    <option value="AM">AM</option>
                    <option value="PM">PM</option>
                </select>
            </div>

            {{-- Date --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                <input type="date"
                       wire:model="date"
                       class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
            </div>

            {{-- Buttons --}}
            <div class="flex items-end gap-2">
                <button wire:click="loadPenaltyRecords" type="button"
                        class="w-full md:w-auto inline-flex justify-center rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">
                    Apply
                </button>

                <button wire:click="resetFilters" type="button"
                        class="w-full md:w-auto inline-flex justify-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Reset
                </button>

                <button onclick="window.print()" type="button"
                        class="w-full md:w-auto inline-flex justify-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                    Print
                </button>
            </div>

        </div>
    </div>

    {{-- Report --}}
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden" id="printable-report">

        {{-- Report Header --}}
        <div class="px-4 py-4 border-b border-gray-200 text-center">
            <h1 class="text-xl font-bold text-gray-900 uppercase">Room Boy Penalty Reports</h1>
            <p class="text-sm text-gray-600">
                Shift {{ $shift ?: 'All' }} - {{ $date ? \Carbon\Carbon::parse($date)->format('F d, Y') : 'All Dates' }}
            </p>
            @if($roomboy_id)
                @php
                    $selectedRoomboy = $roomboys->firstWhere('id', $roomboy_id);
                @endphp
                <p class="text-sm text-gray-600 mt-1">
                    Room Boy Name: <span class="font-semibold">{{ $selectedRoomboy->name ?? 'N/A' }}</span>
                </p>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border border-gray-300 px-3 py-2 text-center text-sm font-semibold text-gray-800">RM#</th>
                        <th class="border border-gray-300 px-3 py-2 text-center text-sm font-semibold text-gray-800">FLOOR</th>
                        <th class="border border-gray-300 px-3 py-2 text-center text-sm font-semibold text-gray-800">ROOM BOY</th>
                        <th class="border border-gray-300 px-3 py-2 text-center text-sm font-semibold text-gray-800">GUEST NAME</th>
                        <th class="border border-gray-300 px-3 py-2 text-center text-sm font-semibold text-gray-800">CHECK-OUT</th>
                        <th class="border border-gray-300 px-3 py-2 text-center text-sm font-semibold text-gray-800">DURATION</th>
                        <th class="border border-gray-300 px-3 py-2 text-center text-sm font-semibold text-gray-800">AMOUNT</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($penaltyRecords as $record)
                        <tr class="{{ $record['duration_minutes'] > 360 ? 'bg-red-50' : '' }}">
                            <td class="border border-gray-300 px-3 py-3 text-sm text-center text-gray-900 font-semibold">
                                {{ $record['room_number'] }}
                            </td>

                            <td class="border border-gray-300 px-3 py-3 text-sm text-center text-gray-900">
                                {{ $record['floor'] }}
                            </td>

                            <td class="border border-gray-300 px-3 py-3 text-sm text-center text-gray-900">
                                {{ $record['roomboy_name'] }}
                            </td>

                            <td class="border border-gray-300 px-3 py-3 text-sm text-center text-gray-900">
                                {{ $record['guest_name'] }}
                            </td>

                            <td class="border border-gray-300 px-3 py-3 text-sm text-center text-gray-900">
                                {{ $record['checkout_time'] }}
                            </td>

                            <td class="border border-gray-300 px-3 py-3 text-sm text-center text-gray-900">
                                {{ $record['duration'] }}
                            </td>

                            <td class="border border-gray-300 px-3 py-3 text-sm text-center text-gray-900 font-semibold">
                                {{ number_format($record['penalty_amount'], 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="border border-gray-300 px-3 py-6 text-sm text-center text-gray-500">
                                No penalty records found for the selected filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                @if(count($penaltyRecords) > 0)
                <tfoot>
                    <tr class="bg-gray-100">
                        <td colspan="6" class="border border-gray-300 px-3 py-3 text-sm text-right font-bold text-gray-900">
                            TOTAL
                        </td>
                        <td class="border border-gray-300 px-3 py-3 text-sm text-center font-bold text-red-600">
                            {{ number_format($totalPenalty, 2) }}
                        </td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>

        {{-- Notes --}}
        <div class="px-4 py-3 border-t border-gray-200 bg-gray-50 text-sm text-gray-600">
            <p><strong>Duration</strong> - Time from check-out until finish cleaning</p>
            <p><strong>Amount</strong> - Penalty based on 6-hour room rate</p>
        </div>

        {{-- Footer --}}
        <div class="p-6 border-t border-gray-200">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-10">
                <div class="text-gray-700">
                    <div class="text-sm font-semibold">Prepared By:</div>
                    <div class="mt-10 w-64 border-b border-gray-400"></div>
                </div>

                <div class="text-gray-700">
                    <div class="text-sm font-semibold">Verified By:</div>
                    <div class="mt-10 w-64 border-b border-gray-400"></div>
                </div>
            </div>
        </div>

    </div>
</div>

<style>
    @media print {
        body * {
            visibility: hidden;
        }
        #printable-report, #printable-report * {
            visibility: visible;
        }
        #printable-report {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
        }
    }
</style>
