<div class="mt-4" wire:poll.5s>
    {{-- Side by Side Tables --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        {{-- LEFT: Uncleaned Rooms --}}
        <div class="bg-white rounded shadow-sm overflow-hidden">
            <div class="bg-[#009EF5] px-4 py-2">
                <h3 class="text-sm font-semibold text-white uppercase">Uncleaned Rooms</h3>
            </div>
            <div class="overflow-x-auto" style="max-height: calc(100vh - 320px); overflow-y: auto;">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-100 text-gray-600 border-b border-gray-200 sticky top-0">
                        <tr>
                            <th class="px-2 py-2 text-center font-medium">#</th>
                            <th class="px-2 py-2 text-center font-medium">ROOM #</th>
                            <th class="px-2 py-2 text-center font-medium hidden sm:table-cell">FLOOR #</th>
                            <th class="px-2 py-2 text-center font-medium hidden md:table-cell">CHECKOUT TIME</th>
                            <th class="px-2 py-2 text-center font-medium">TIME TO CLEAN</th>
                            <th class="px-2 py-2 text-center font-medium">ACTION</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($rooms as $index => $room)
                            @php
                                $checkoutTime = \Carbon\Carbon::parse($room->check_out_time);
                                $checkoutTimestamp = $checkoutTime->timestamp * 1000;

                                // Calculate deadline (checkout + 4 hours)
                                if ($room->time_to_clean) {
                                    $deadline = \Carbon\Carbon::parse($room->time_to_clean);
                                } else {
                                    $deadline = $checkoutTime->copy()->addHours(4);
                                }
                                $deadlineTimestamp = $deadline->timestamp * 1000;
                                $isExpired = $deadline->isPast();

                                $hoursAgo = now()->diffInHours($checkoutTime);
                                $rowBg = $index % 2 == 0 ? 'bg-white' : 'bg-gray-50';
                            @endphp
                            <tr wire:key="uncleaned-{{ $room->id }}" class="{{ $rowBg }}">
                                <td class="px-2 py-2 text-center text-gray-600">{{ $index + 1 }}.</td>
                                <td class="px-2 py-2 text-center font-bold text-gray-900">{{ $room->number }}</td>
                                <td class="px-2 py-2 text-center text-gray-700 hidden sm:table-cell">{{ $room->floor->number ?? $room->floor_id }}</td>
                                <td class="px-2 py-2 text-center text-gray-800 hidden md:table-cell">{{ $checkoutTime->format('g:i A') }}</td>

                                {{-- TIME TO CLEAN: Real-time countdown --}}
                                <td class="px-2 py-2 text-center">
                                    @if ($isExpired)
                                        <span class="font-mono text-sm text-red-600 font-bold">0:00:00</span>
                                    @else
                                        <div x-data="{
                                            deadline: {{ $deadlineTimestamp }},
                                            now: Date.now(),
                                            init() { setInterval(() => this.now = Date.now(), 1000); },
                                            get remaining() {
                                                let diff = Math.max(0, this.deadline - this.now);
                                                if (diff <= 0) return '0:00:00';
                                                let h = Math.floor(diff / 3600000);
                                                let m = Math.floor((diff % 3600000) / 60000);
                                                let s = Math.floor((diff % 60000) / 1000);
                                                return h + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
                                            },
                                            get isLow() { return (this.deadline - this.now) < 1800000; },
                                            get isExpired() { return (this.deadline - this.now) <= 0; }
                                        }">
                                            <span class="font-mono text-sm"
                                                :class="isExpired ? 'text-red-600 font-bold' : (isLow ? 'text-red-600 font-semibold' : 'text-gray-700')"
                                                x-text="remaining"></span>
                                        </div>
                                    @endif
                                </td>

                                <td class="px-2 py-2 text-center">
                                    <button
                                        wire:loading.attr="disabled"
                                        wire:target="startCleaning,finishCleaning"
                                        wire:loading.class="opacity-50 cursor-not-allowed"
                                        class="inline-flex items-center gap-1 bg-[#009EF5] text-white hover:bg-[#0080cc] px-2 py-1.5 rounded text-xs font-medium disabled:opacity-50 whitespace-nowrap"
                                        x-on:confirm="{
                                            title: 'Start cleaning Room {{ $room->number }}?',
                                            icon: 'question',
                                            method: 'startCleaning',
                                            params: [{{ $room->id }}]
                                        }"
                                    >
                                        Start Cleaning
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-400 uppercase tracking-wide">
                                    No Rooms To Clean
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- RIGHT: Cleaning Rooms --}}
        <div class="bg-white rounded shadow-sm overflow-hidden">
            <div class="bg-[#009EF5] px-4 py-2">
                <h3 class="text-sm font-semibold text-white uppercase">Cleaning Rooms</h3>
            </div>
            <div class="overflow-x-auto" style="max-height: calc(100vh - 320px); overflow-y: auto;">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-100 text-gray-600 border-b border-gray-200 sticky top-0">
                        <tr>
                            <th class="px-2 py-2 text-center font-medium">#</th>
                            <th class="px-2 py-2 text-center font-medium">ROOM #</th>
                            <th class="px-2 py-2 text-center font-medium hidden sm:table-cell">FLOOR #</th>
                            <th class="px-2 py-2 text-center font-medium">STARTED CLEANING</th>
                            <th class="px-2 py-2 text-center font-medium">ACTION</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($cleaningRooms as $index => $cleaning_room)
                            @php
                                $startedAt = \Carbon\Carbon::parse($cleaning_room->started_cleaning_at);
                                $startTimestamp = $startedAt->timestamp * 1000;
                                $elapsedHours = now()->diffInHours($startedAt);
                                $cleaningRowBg = $elapsedHours >= 2 ? 'bg-red-50' : ($index % 2 == 0 ? 'bg-white' : 'bg-gray-50');
                            @endphp
                            <tr wire:key="cleaning-{{ $cleaning_room->id }}" class="{{ $cleaningRowBg }}">
                                <td class="px-2 py-2 text-center text-gray-600">{{ $index + 1 }}.</td>
                                <td class="px-2 py-2 text-center font-bold text-gray-900">{{ $cleaning_room->number }}</td>
                                <td class="px-2 py-2 text-center text-gray-700 hidden sm:table-cell">{{ $cleaning_room->floor->number ?? $cleaning_room->floor_id }}</td>

                                {{-- STARTED CLEANING: Human readable time ago with seconds --}}
                                <td class="px-2 py-2 text-center">
                                    <div x-data="{
                                        start: {{ $startTimestamp }},
                                        now: Date.now(),
                                        init() { setInterval(() => this.now = Date.now(), 1000); },
                                        get timeAgo() {
                                            let diff = Math.max(0, this.now - this.start);
                                            let totalSecs = Math.floor(diff / 1000);
                                            let h = Math.floor(totalSecs / 3600);
                                            let m = Math.floor((totalSecs % 3600) / 60);
                                            let s = totalSecs % 60;
                                            if (h > 0) return h + ' hr' + (h > 1 ? 's' : '') + ' ' + m + ' min' + (m !== 1 ? 's' : '') + ' ago';
                                            if (m > 0) return m + ' min' + (m !== 1 ? 's' : '') + ' ' + s + ' sec' + (s !== 1 ? 's' : '') + ' ago';
                                            return s + ' sec' + (s !== 1 ? 's' : '') + ' ago';
                                        },
                                        get hours() { return Math.floor((this.now - this.start) / 3600000); }
                                    }">
                                        <span class="text-sm"
                                            :class="hours >= 2 ? 'text-red-600 font-bold' : 'text-gray-700'"
                                            x-text="timeAgo"></span>
                                    </div>
                                </td>

                                <td class="px-2 py-2 text-center">
                                    <button
                                        wire:loading.attr="disabled"
                                        wire:target="finishCleaning,startCleaning"
                                        wire:loading.class="opacity-50 cursor-not-allowed"
                                        class="inline-flex items-center gap-1 bg-green-500 text-white hover:bg-green-600 px-2 py-1.5 rounded text-xs font-medium disabled:opacity-50 whitespace-nowrap"
                                        x-on:confirm="{
                                            title: 'Finish cleaning Room {{ $cleaning_room->number }}?',
                                            icon: 'question',
                                            method: 'finishCleaning',
                                            params: [{{ $cleaning_room->id }}]
                                        }"
                                    >
                                        Finish Cleaning
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-16 text-center text-gray-400 uppercase tracking-wide text-lg">
                                    No Active Cleaning
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    {{-- Done Today Modal --}}
    @if($showDoneTodayModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="closeDoneTodayModal"></div>
            <div class="relative bg-white rounded-lg shadow-xl transform transition-all sm:max-w-lg sm:w-full">
                <div class="bg-green-500 px-4 py-3 rounded-t-lg">
                    <h3 class="text-lg font-semibold text-white uppercase">Rooms Cleaned Today</h3>
                </div>
                <div class="px-4 py-4 max-h-96 overflow-y-auto">
                    @if(count($doneTodayRooms) > 0)
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="px-3 py-2 text-center font-medium text-gray-600">Room #</th>
                                    <th class="px-3 py-2 text-center font-medium text-gray-600">Floor</th>
                                    <th class="px-3 py-2 text-center font-medium text-gray-600">Finished At</th>
                                    <th class="px-3 py-2 text-center font-medium text-gray-600">Duration</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($doneTodayRooms as $history)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-3 py-2 text-center font-bold">{{ $history->room->number ?? 'N/A' }}</td>
                                        <td class="px-3 py-2 text-center">{{ $history->floor->number ?? 'N/A' }}</td>
                                        <td class="px-3 py-2 text-center">{{ \Carbon\Carbon::parse($history->end_time)->format('g:i A') }}</td>
                                        <td class="px-3 py-2 text-center">{{ $history->cleaning_duration }} mins</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <p class="text-gray-500 text-center py-8">No rooms cleaned today yet.</p>
                    @endif
                </div>
                <div class="bg-gray-50 px-4 py-3 rounded-b-lg flex justify-end">
                    <button wire:click="closeDoneTodayModal" class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Penalty Rooms Modal --}}
    @if($showPenaltyModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="closePenaltyModal"></div>
            <div class="relative bg-white rounded-lg shadow-xl transform transition-all sm:max-w-lg sm:w-full">
                <div class="bg-red-500 px-4 py-3 rounded-t-lg">
                    <h3 class="text-lg font-semibold text-white uppercase">Penalty Rooms (4h+ Exceeded)</h3>
                </div>
                <div class="px-4 py-4 max-h-96 overflow-y-auto">
                    @if(count($penaltyRooms) > 0)
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="px-3 py-2 text-center font-medium text-gray-600">Room #</th>
                                    <th class="px-3 py-2 text-center font-medium text-gray-600">Floor</th>
                                    <th class="px-3 py-2 text-center font-medium text-gray-600">Checkout</th>
                                    <th class="px-3 py-2 text-center font-medium text-gray-600">Exceeded By</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($penaltyRooms as $room)
                                    @php
                                        $checkoutTime = \Carbon\Carbon::parse($room->check_out_time);
                                        $deadline = $room->time_to_clean ? \Carbon\Carbon::parse($room->time_to_clean) : $checkoutTime->copy()->addHours(4);
                                        $exceededMins = now()->diffInMinutes($deadline);
                                        $exceededHrs = floor($exceededMins / 60);
                                        $exceededMinsRem = $exceededMins % 60;
                                    @endphp
                                    <tr class="hover:bg-red-50">
                                        <td class="px-3 py-2 text-center font-bold">{{ $room->number }}</td>
                                        <td class="px-3 py-2 text-center">{{ $room->floor->number ?? $room->floor_id }}</td>
                                        <td class="px-3 py-2 text-center">{{ $checkoutTime->format('g:i A') }}</td>
                                        <td class="px-3 py-2 text-center text-red-600 font-semibold">
                                            {{ $exceededHrs > 0 ? $exceededHrs . 'h ' : '' }}{{ $exceededMinsRem }}m
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <p class="text-gray-500 text-center py-8">No penalty rooms.</p>
                    @endif
                </div>
                <div class="bg-gray-50 px-4 py-3 rounded-b-lg flex justify-end">
                    <button wire:click="closePenaltyModal" class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
