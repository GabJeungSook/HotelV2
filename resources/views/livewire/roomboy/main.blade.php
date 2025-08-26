<div class="grid max-w-full grid-cols-1 gap-6 mx-auto mt-5 lg:max-w-full lg:grid-flow-col-dense lg:grid-cols-1">
    <div class="space-y-6 lg:col-span-2 lg:col-start-1">
        <section aria-labelledby="applicant-information-title">
          <div class="bg-white border-b border-x rounded-md shadow-lg">
            <div x-cloak x-data="{ activeTab: '{{ request()->get('floor', $floors[0]->id ?? null) }}' }" class="rounded-md px-4 py-5 sm:px-6">
                <h2 id="applicant-information-title" class="text-lg font-medium leading-6 text-gray-900">
                    Your Assigned Floors ({{ $floors->count() }})
                </h2>

                @if($floors && count($floors))
                    <div class="flex">
                        <!-- Tabs Sidebar -->
                        <nav class="flex flex-col w-48 border-r border-gray-200 bg-white mt-5" aria-label="Floors Tabs">
                            <!-- Summary Tab Button -->
                            <button
                                type="button"
                                wire:click="getSelectedFloor('summary')"
                                @click="activeTab = 'summary'"
                                :class="[
                                    activeTab == 'summary'
                                        ? 'text-[#009ff4] border-l-4 border-[#009ff4] bg-blue-50'
                                        : 'text-gray-600 hover:text-[#009ff4] hover:bg-blue-50',
                                    'relative group flex items-center gap-2 px-4 py-3 text-sm font-medium transition-all duration-200 focus:outline-none'
                                ]"
                            >
                                <svg class="w-4 h-4 transition-colors duration-200"
                                    :class="activeTab == 'summary'
                                        ? 'text-[#009ff4]'
                                        : 'text-gray-500 group-hover:text-[#009ff4]'"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="1.5"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M3 7h18M3 12h18M3 17h18" />
                                </svg>
                                Summary
                            </button>
                            @foreach($floors as $floor)
                                @php
                                    // Example: retrieve the count for this floor (0 if not found)
                                    $uncleanedCount =  App\Models\Room::whereBranchId($this->user->branch_id)
                                                        ->where('status', 'Uncleaned')
                                                        ->whereFloorId($floor->id)
                                                        ->orderBy('time_to_clean', 'asc')
                                                        ->count() ?? 0;
                                @endphp
                                <button
                                    type="button"
                                    wire:click="getSelectedFloor({{ $floor->id }})"
                                    @click="activeTab = '{{ $floor->id }}'"
                                    :class="[
                                        activeTab == '{{ $floor->id }}'
                                            ? 'text-[#009ff4] border-l-4 border-[#009ff4] bg-blue-50'
                                            : 'text-gray-600 hover:text-[#009ff4] hover:bg-blue-50',
                                        'relative group flex items-center gap-2 px-4 py-3 text-sm font-medium transition-all duration-200 focus:outline-none'
                                    ]"
                                >
                                    <svg class="w-4 h-4 transition-colors duration-200"
                                        :class="activeTab == '{{ $floor->id }}'
                                            ? 'text-[#009ff4]'
                                            : 'text-gray-500 group-hover:text-[#009ff4]'"
                                        xmlns="http://www.w3.org/2000/svg"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke-width="1.5"
                                        stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M6.429 9.75 2.25 12l4.179 2.25m0-4.5 5.571 3 5.571-3m-11.142 0L2.25 7.5 12 2.25l9.75 5.25-4.179 2.25m0 0L21.75 12l-4.179 2.25m0 0 4.179 2.25L12 21.75 2.25 16.5l4.179-2.25m11.142 0-5.571 3-5.571-3" />
                                    </svg>
                                    {{ $floor->numberWithFormat() }}

                                    {{-- Notification Badge --}}
                                    @if($uncleanedCount > 0)
                                        <span class="ml-2 inline-flex items-center justify-center px-1.5 py-0.5 text-2xs font-bold leading-none text-white bg-red-600 rounded-full">
                                            {{ $uncleanedCount }}
                                        </span>
                                    @endif
                                </button>
                            @endforeach
                        </nav>


                        <!-- Main Content -->
                        <div class="flex-1 p-4">
                            <div class="mt-1 p-4 border bg-gray-50" x-show="activeTab == 'summary'">
                            <div wire:loading class="italic">
                                Fetching Data...
                            </div>

                            <div wire:loading.remove>
                                <table class="w-full border-collapse">
                                    <tbody>
                                        @foreach ($floors as $floor)
                                            <!-- Floor group row -->
                                            <tr class="bg-gray-200 font-semibold text-[#009ff4]">
                                                <td class="p-2 border text-left" colspan="2">
                                                    {{ $floor->numberWithFormat() }}
                                                </td>
                                            </tr>
                                            <!-- Header for the floor's rooms -->
                                            <tr class="bg-gray-100">
                                                <th class="p-2 border text-left font-semibold">Room</th>
                                                <th class="p-2 border text-left font-semibold">Status</th>
                                            </tr>
                                            @php
                                                $rooms_on_floor = App\Models\Room::where('floor_id', $floor->id)->whereIn('status', ['Uncleaned', 'Cleaning'])->get();
                                            @endphp
                                            <!-- Rooms under this floor -->
                                            @forelse ($rooms_on_floor as $room)
                                                <tr>
                                                    <td class="p-2 border">Room {{ $room->number }}</td>
                                                    <td class="p-2 border">
                                                        <span class="px-2 py-1 text-xs rounded
                                                            @if ($room->status === 'Uncleaned')
                                                                bg-red-100 text-red-700
                                                            @elseif ($room->status === 'Cleaning')
                                                                bg-green-100 text-green-700
                                                            @else
                                                                bg-gray-100 text-gray-700
                                                            @endif">
                                                            {{ $room->status }}
                                                        </span>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td class="p-2 border italic text-gray-500 text-center" colspan="2">No rooms available</td>
                                                </tr>
                                            @endforelse
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                            @foreach($floors as $floor)
                                {{-- Currently Cleaning --}}
                                <div class="mt-1 p-4 border bg-gray-50" x-show="activeTab == '{{ $floor->id }}'">
                                    @php
                                        $room_id = App\Models\Room::where('id', auth()->user()->roomboy_cleaning_room_id)->where('floor_id', $floor->id)->first();
                                        $room = App\Models\Room::where('id', auth()->user()->roomboy_cleaning_room_id)->where('floor_id', $floor->id)->first()?->numberWithFormat();
                                        $start = App\Models\Room::where('id', auth()->user()->roomboy_cleaning_room_id)->where('floor_id', $floor->id)->first()?->started_cleaning_at;
                                    @endphp
                                    <h3 class="text-lg font-semibold mb-4">Currently Cleaning</h3>
                                    <div wire:loading class="italic">
                                        Fetching Data...
                                    </div>
                                    @if ($room)
                                        <div wire:loading.remove class="grid grid-cols-6 gap-4 mb-4">
                                            <div class="col-span-1 shadow-lg border p-4 border-[#009ff4] flex flex-col items-center justify-center text-center rounded-md">
                                                <span class="font-medium uppercase">{{ $room }}</span>
                                                <div class="flex flex-col items-center mt-2 space-y-1">
                                                    <span class="font-normal text-sm text-gray-500">Started Cleaning</span>
                                                    <span class="font-normal">{{ \Carbon\Carbon::parse($start)->diffForHumans() }}</span>
                                                </div>
                                                 <div class="mt-1">
                                                    <button
                                                        class="bg-[#009ff4] text-white hover:bg-[#017dc0] flex items-center gap-2 px-4 py-2 rounded"
                                                        x-on:confirm="{
                                                            title: 'Are you sure? you want to finish cleaning this room?',
                                                            icon: 'question',
                                                            method: 'finishCleaning',
                                                            params: [{{ $room_id->id }}]
                                                        }"
                                                    >
                                                        Finish Cleaning
                                                        <!-- Right arrow icon (Heroicons: arrow-narrow-right) -->
                                                        <svg class="w-5 h-5 ml-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>

                                        </div>
                                    @else
                                        <div wire:loading.remove class="mb-2">
                                            <span class="font-normal text-gray-400 italic">No room is currently being cleaned on this floor.</span>
                                        </div>
                                    @endif
                                </div>
                                {{-- Uncleaned Rooms --}}
                                <div class="mt-1 p-4 border bg-gray-50" x-show="activeTab == '{{ $floor->id }}'">
                                    <h3 class="text-lg font-semibold mb-4">Uncleaned Rooms</h3>
                                    <div wire:loading class="italic">
                                        Fetching Data...
                                    </div>
                                    @forelse ($rooms as $record)
                                    <div wire:loading.remove class="grid grid-cols-6 gap-4 mb-4">
                                        <div class="col-span-1 shadow-lg border p-4 border-[#009ff4] flex flex-col items-center justify-center text-center rounded-md">
                                            <span class="font-medium uppercase">Room # {{ $record->number }}</span>
                                            <span class="font-normal text-sm uppercase">Check Out Time: {{ \Carbon\Carbon::parse($record->check_out_time)->format('g:i A') }}</span>
                                            <hr class="w-full my-2 border-gray-300">
                                            <span class="text-lg font-semibold text-gray-800">Time to Clean</span>
                                            @php
                                            $expires = \Carbon\Carbon::parse($record->time_to_clean);
                                            @endphp
                                            <x-countdown :$expires>
                                                <span class="font-medium text-red-600 font-mono"
                                                x-text="timer.days">{{ $component->days() }}</span>
                                                :
                                                <span class="font-medium text-red-600 font-mono"
                                                x-text="timer.hours">{{ $component->hours() }}</span> :
                                                <span class="font-medium text-red-600 font-mono"
                                                x-text="timer.minutes">{{ $component->minutes() }}</span> :
                                                <span class="font-medium text-red-600 font-mono"
                                                x-text="timer.seconds">{{ $component->seconds() }}</span>
                                            </x-countdown>
                                            <div class="mt-1">
                                                @if ($loop->first)
                                                 <button
                                                        class="bg-[#009ff4] text-white hover:bg-[#017dc0] flex items-center gap-2 px-4 py-2 rounded"
                                                        x-on:confirm="{
                                                            title: 'Are you sure? you want to start cleaning this room?',
                                                            icon: 'question',
                                                            method: 'startCleaning',
                                                            params: [{{ $record->id }}]
                                                        }"
                                                    >
                                                        Start Cleaning
                                                        <!-- Right arrow icon (Heroicons: arrow-narrow-right) -->
                                                        <svg class="w-5 h-5 ml-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                                        </svg>
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    @empty
                                        <div wire:loading.remove class="mb-2">
                                            <span class="font-normal text-gray-400 italic">No uncleaned rooms on this floor</span>
                                        </div>

                                    @endforelse
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
          </div>
        </section>
    </div>
</div>
