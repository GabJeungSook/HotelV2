<div class="mt-4">
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
                            <th class="px-3 py-2 text-center font-medium">#</th>
                            <th class="px-3 py-2 text-center font-medium">ROOM #</th>
                            <th class="px-3 py-2 text-center font-medium">FLOOR #</th>
                            <th class="px-3 py-2 text-center font-medium">CHECKOUT TIME</th>
                            <th class="px-3 py-2 text-center font-medium">TIME TO CLEAN</th>
                            <th class="px-3 py-2 text-center font-medium">ACTION</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($rooms as $index => $room)
                            @php
                                $checkoutTime = \Carbon\Carbon::parse($room->check_out_time);
                                $hoursAgo = now()->diffInHours($checkoutTime);
                                $rowBg = $hoursAgo >= 2 ? 'bg-red-50' : ($index % 2 == 0 ? 'bg-white' : 'bg-gray-50');
                            @endphp
                            <tr wire:key="uncleaned-{{ $room->id }}" class="{{ $rowBg }}">
                                <td class="px-3 py-2 text-center text-gray-600">{{ $index + 1 }}.</td>
                                <td class="px-3 py-2 text-center font-semibold text-gray-900">{{ $room->number }}</td>
                                <td class="px-3 py-2 text-center text-gray-700">{{ $room->floor->number ?? $room->floor_id }}</td>
                                <td class="px-3 py-2 text-center text-gray-800">{{ $checkoutTime->format('g:i A') }}</td>
                                <td class="px-3 py-2 text-center">
                                    @if ($room->time_to_clean)
                                        @php $expires = \Carbon\Carbon::parse($room->time_to_clean); @endphp
                                        <x-countdown :$expires>
                                            <span class="font-mono text-sm text-gray-700">
                                                <span x-text="timer.hours">{{ $component->hours() }}</span>:<span x-text="timer.minutes">{{ $component->minutes() }}</span>:<span x-text="timer.seconds">{{ $component->seconds() }}</span>
                                            </span>
                                        </x-countdown>
                                    @else
                                        <span class="text-gray-400">--:--:--</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <button
                                        wire:loading.attr="disabled"
                                        wire:target="startCleaning,finishCleaning"
                                        wire:loading.class="opacity-50 cursor-not-allowed"
                                        class="inline-flex items-center gap-1 bg-[#009EF5] text-white hover:bg-[#0080cc] px-3 py-1 rounded text-xs font-medium disabled:opacity-50"
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
                            <th class="px-3 py-2 text-center font-medium">#</th>
                            <th class="px-3 py-2 text-center font-medium">ROOM #</th>
                            <th class="px-3 py-2 text-center font-medium">FLOOR #</th>
                            <th class="px-3 py-2 text-center font-medium">STARTED CLEANING</th>
                            <th class="px-3 py-2 text-center font-medium">ACTION</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($cleaningRooms as $index => $cleaning_room)
                            @php
                                $startedAt = \Carbon\Carbon::parse($cleaning_room->started_cleaning_at);
                                $elapsedHours = now()->diffInHours($startedAt);
                                // Red warning if cleaning takes 2+ hours
                                $rowBg = $elapsedHours >= 2 ? 'bg-red-100' : ($index % 2 == 0 ? 'bg-green-50' : 'bg-green-100');
                            @endphp
                            <tr wire:key="cleaning-{{ $cleaning_room->id }}" class="{{ $rowBg }}">
                                <td class="px-3 py-2 text-center text-gray-600">{{ $index + 1 }}.</td>
                                <td class="px-3 py-2 text-center font-semibold text-gray-900">{{ $cleaning_room->number }}</td>
                                <td class="px-3 py-2 text-center text-gray-700">{{ $cleaning_room->floor->number ?? $cleaning_room->floor_id }}</td>
                                <td class="px-3 py-2 text-center {{ $elapsedHours >= 2 ? 'text-red-600 font-semibold' : 'text-gray-700' }}">{{ \Carbon\Carbon::parse($cleaning_room->started_cleaning_at)->diffForHumans() }}</td>
                                <td class="px-3 py-2 text-center">
                                    <button
                                        wire:loading.attr="disabled"
                                        wire:target="finishCleaning,startCleaning"
                                        wire:loading.class="opacity-50 cursor-not-allowed"
                                        class="inline-flex items-center gap-1 bg-[#F97373] text-white hover:bg-[#e05555] px-3 py-1 rounded text-xs font-medium disabled:opacity-50"
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
</div>
