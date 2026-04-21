<div class="max-w-full mx-auto mt-4">
    <div class="bg-white border border-gray-200 rounded shadow-sm">
        <div x-cloak x-data="{ activeTab: '{{ $selectedFloorId }}' }" class="px-4 py-4 sm:px-5">

            @php
                $urgentCount = 0;
                foreach ($rooms as $r) {
                    if (now()->diffInHours(\Carbon\Carbon::parse($r->check_out_time)) >= 2) {
                        $urgentCount++;
                    }
                }
            @endphp

            {{-- Overview --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
                <div class="border border-gray-200 rounded px-4 py-3">
                    <div class="text-xs text-gray-500 uppercase tracking-wide">To Clean</div>
                    <div class="text-2xl font-bold text-gray-900 mt-1">{{ $totalUncleaned }}</div>
                </div>
                <div class="border border-gray-200 rounded px-4 py-3">
                    <div class="text-xs text-gray-500 uppercase tracking-wide">In Progress</div>
                    <div class="text-2xl font-bold text-gray-900 mt-1">{{ $cleaningRooms->count() }}</div>
                </div>
                <div class="border border-gray-200 rounded px-4 py-3">
                    <div class="text-xs text-gray-500 uppercase tracking-wide">Urgent (2h+)</div>
                    <div class="text-2xl font-bold mt-1 {{ $urgentCount > 0 ? 'text-red-600' : 'text-gray-900' }}">{{ $urgentCount }}</div>
                </div>
                <div class="border border-gray-200 rounded px-4 py-3">
                    <div class="text-xs text-gray-500 uppercase tracking-wide">Done Today</div>
                    <div class="text-2xl font-bold text-gray-900 mt-1">{{ $cleanedToday }}</div>
                </div>
            </div>

            {{-- In Progress --}}
            @if ($cleaningRooms->count() > 0)
                <div class="mb-5">
                    <h3 class="text-base font-semibold text-gray-800 mb-2">In Progress</h3>
                    <div class="border border-gray-200 rounded overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-gray-600 border-b border-gray-200">
                                <tr>
                                    <th class="px-4 py-2 text-left font-medium">Room</th>
                                    <th class="px-4 py-2 text-left font-medium">Floor</th>
                                    <th class="px-4 py-2 text-left font-medium">Started</th>
                                    <th class="px-4 py-2 text-left font-medium">Elapsed</th>
                                    <th class="px-4 py-2 text-right font-medium"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($cleaningRooms as $cleaning_room)
                                    <tr>
                                        <td class="px-4 py-3">
                                            <span class="text-2xl font-bold text-gray-900">{{ $cleaning_room->number }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700">{{ $cleaning_room->floor->numberWithFormat() }}</td>
                                        <td class="px-4 py-3 text-gray-700">{{ \Carbon\Carbon::parse($cleaning_room->started_cleaning_at)->format('g:i A') }}</td>
                                        <td class="px-4 py-3"
                                            x-data="{
                                                start: new Date('{{ \Carbon\Carbon::parse($cleaning_room->started_cleaning_at)->toIso8601String() }}').getTime(),
                                                now: Date.now(),
                                                timer: null,
                                                init() { this.timer = setInterval(() => this.now = Date.now(), 1000) },
                                                destroy() { clearInterval(this.timer) },
                                                get secs() { return Math.max(0, Math.floor((this.now - this.start) / 1000)) },
                                                get mins() { return Math.floor(this.secs / 60) },
                                                get label() {
                                                    const h = Math.floor(this.secs / 3600);
                                                    const m = String(Math.floor((this.secs % 3600) / 60)).padStart(2, '0');
                                                    const s = String(this.secs % 60).padStart(2, '0');
                                                    return h + ':' + m + ':' + s;
                                                }
                                            }">
                                            <span class="font-mono text-base"
                                                :class="mins >= 60 ? 'text-red-600 font-semibold' : (mins >= 30 ? 'text-amber-600' : 'text-gray-700')"
                                                x-text="label"></span>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <button
                                                wire:loading.attr="disabled"
                                                wire:target="finishCleaning,startCleaning"
                                                wire:loading.class="opacity-50 cursor-not-allowed"
                                                class="inline-flex items-center justify-center bg-green-600 text-white active:bg-green-800 px-6 py-3 rounded text-base font-medium min-w-[96px] disabled:opacity-50"
                                                x-on:confirm="{
                                                    title: 'Finish cleaning Room {{ $cleaning_room->number }}?',
                                                    icon: 'question',
                                                    method: 'finishCleaning',
                                                    params: [{{ $cleaning_room->id }}]
                                                }"
                                            >
                                                Finish
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- Header + Floor Filter --}}
            <div class="flex flex-col gap-3 mb-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-2">
                    <h2 class="text-base font-semibold text-gray-900">Rooms To Clean</h2>
                    <button
                        type="button"
                        wire:click="$refresh"
                        title="Refresh"
                        class="inline-flex items-center justify-center p-2 rounded border border-gray-300 bg-white text-gray-600 active:bg-gray-100 min-h-[40px] min-w-[40px]"
                    >
                        <svg wire:loading.remove wire:target="$refresh" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        <svg wire:loading wire:target="$refresh" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-opacity="0.25" fill="none"/>
                            <path fill="currentColor" d="M4 12a8 8 0 018-8V2a10 10 0 00-10 10h2z"/>
                        </svg>
                    </button>
                </div>

                <div class="flex flex-wrap gap-1.5">
                    <button
                        type="button"
                        wire:click="getSelectedFloor('all')"
                        @click="activeTab = 'all'"
                        :style="activeTab === 'all' ? 'background-color:#009EF5;border-color:#009EF5;color:#fff' : ''"
                        class="px-4 py-2 rounded border border-gray-300 bg-white text-gray-700 text-sm font-medium min-h-[40px]"
                    >
                        All
                    </button>
                    @foreach ($floors as $floor)
                        @php
                            $floorNumber = $floor->number ?? $floor->id;
                            $floorLabel = 'Floor '.$floorNumber;
                            $floorCount = $floorCounts[$floor->id] ?? 0;
                        @endphp
                        <button
                            type="button"
                            wire:key="floor-btn-{{ $floor->id }}"
                            wire:click="getSelectedFloor({{ $floor->id }})"
                            @click="activeTab = '{{ $floor->id }}'"
                            :style="activeTab == '{{ $floor->id }}' ? 'background-color:#009EF5;border-color:#009EF5;color:#fff' : ''"
                            class="px-4 py-2 rounded border border-gray-300 bg-white text-gray-700 text-sm font-medium min-h-[40px]"
                        >
                            <span>{{ $floorLabel }}</span>@if ($floorCount > 0)<span class="ml-1 text-xs opacity-70">({{ $floorCount }})</span>@endif
                        </button>
                    @endforeach
                </div>
            </div>

            <div wire:loading class="py-6 text-center text-sm text-gray-500">Loading rooms...</div>

            <div wire:loading.remove>
                @if ($rooms->count() > 0)
                    <div class="border border-gray-200 rounded overflow-x-auto" style="max-height: calc(100vh - 260px); overflow-y: auto;">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-gray-600 border-b border-gray-200 sticky top-0">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium w-10">#</th>
                                    <th class="px-4 py-2 text-left font-medium">Room</th>
                                    <th class="px-4 py-2 text-left font-medium">Floor</th>
                                    <th class="px-4 py-2 text-left font-medium">Type</th>
                                    <th class="px-4 py-2 text-left font-medium">Checkout</th>
                                    <th class="px-4 py-2 text-left font-medium">Waiting</th>
                                    <th class="px-4 py-2 text-left font-medium">Time to Clean</th>
                                    <th class="px-4 py-2 text-right font-medium"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($rooms as $index => $room)
                                    @php
                                        $checkoutTime = \Carbon\Carbon::parse($room->check_out_time);
                                        $waitedMins = (int) now()->diffInMinutes($checkoutTime);
                                        if ($waitedMins < 60) {
                                            $waitedLabel = $waitedMins . 'm';
                                        } elseif ($waitedMins < 1440) {
                                            $waitedLabel = intdiv($waitedMins, 60) . 'h ' . ($waitedMins % 60) . 'm';
                                        } else {
                                            $waitedLabel = intdiv($waitedMins, 1440) . 'd ' . intdiv($waitedMins % 1440, 60) . 'h';
                                        }

                                        $hoursAgo = $waitedMins / 60;
                                        if ($hoursAgo >= 2) {
                                            $waitingClass = 'text-red-600 font-semibold';
                                        } elseif ($hoursAgo >= 1) {
                                            $waitingClass = 'text-amber-700';
                                        } else {
                                            $waitingClass = 'text-gray-600';
                                        }

                                        $typeName = $room->type->name ?? '-';
                                        $typeShort = str_ireplace([' size Bed', ' Size Bed', ' size', ' Size'], '', $typeName);
                                    @endphp
                                    <tr>
                                        <td class="px-3 py-3 text-gray-500">{{ $index + 1 }}</td>
                                        <td class="px-4 py-3">
                                            <span class="text-2xl font-bold text-gray-900">{{ $room->number }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700">{{ $room->floor->numberWithFormat() }}</td>
                                        <td class="px-4 py-3 text-gray-700">{{ $typeShort }}</td>
                                        <td class="px-4 py-3 text-gray-800">{{ $checkoutTime->format('g:i A') }}</td>
                                        <td class="px-4 py-3 {{ $waitingClass }}">{{ $waitedLabel }}</td>
                                        <td class="px-4 py-3">
                                            @if ($room->time_to_clean)
                                                @php $expires = \Carbon\Carbon::parse($room->time_to_clean); @endphp
                                                <x-countdown :$expires>
                                                    <span class="font-mono text-sm" :class="timer.minutes < 5 && timer.hours < 1 ? 'text-red-600 font-semibold' : 'text-gray-700'">
                                                        <span x-text="timer.hours">{{ $component->hours() }}</span>:<span x-text="timer.minutes">{{ $component->minutes() }}</span>:<span x-text="timer.seconds">{{ $component->seconds() }}</span>
                                                    </span>
                                                </x-countdown>
                                            @else
                                                <span class="text-gray-400">--</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <button
                                                wire:loading.attr="disabled"
                                                wire:target="startCleaning,finishCleaning"
                                                wire:loading.class="opacity-50 cursor-not-allowed"
                                                class="inline-flex items-center justify-center bg-dlogo text-white active:bg-sky-700 px-6 py-3 rounded text-base font-medium min-w-[96px] disabled:opacity-50"
                                                x-on:confirm="{
                                                    title: 'Start cleaning Room {{ $room->number }}?',
                                                    icon: 'question',
                                                    method: 'startCleaning',
                                                    params: [{{ $room->id }}]
                                                }"
                                            >
                                                Start
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="py-10 text-center text-sm text-gray-500 border border-gray-200 rounded">
                        @if ($selectedFloorId !== 'all')
                            @php
                                $activeFloor = $floors->firstWhere('id', $selectedFloorId);
                                $activeFloorLabel = $activeFloor ? 'Floor '.($activeFloor->number ?? $activeFloor->id) : 'this floor';
                            @endphp
                            No rooms on {{ $activeFloorLabel }}.
                        @else
                            No rooms to clean.
                        @endif
                    </div>
                @endif
            </div>

        </div>
    </div>
</div>
