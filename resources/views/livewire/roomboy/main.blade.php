<div class="grid max-w-full grid-cols-1 gap-6 mx-auto mt-5 lg:max-w-full lg:grid-flow-col-dense lg:grid-cols-1">
    <div class="space-y-6 lg:col-span-2 lg:col-start-1">
        <section aria-labelledby="roomboy-dashboard">
          <div class="bg-white border rounded-md shadow-lg">
            <div x-cloak x-data="{ activeTab: '{{ $selectedFloorId }}' }" class="rounded-md px-4 py-5 sm:px-6">

                {{-- Currently Cleaning Section --}}
                @php
                    $cleaning_rooms = App\Models\Room::beingCleanedBy(auth()->id())->with('floor')->get();
                @endphp
                @if ($cleaning_rooms->count() > 0)
                <div class="mb-6">
                    <h3 class="text-sm font-semibold mb-3 text-green-700 uppercase tracking-wide flex items-center gap-2">
                        <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                        Currently Cleaning ({{ $cleaning_rooms->count() }})
                    </h3>
                    <div class="bg-green-50 border border-green-200 rounded-lg overflow-hidden">
                        <table class="min-w-full">
                            <thead class="bg-green-100">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-semibold text-green-800 uppercase">Room</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold text-green-800 uppercase">Floor</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold text-green-800 uppercase">Started</th>
                                    <th class="px-4 py-2 text-center text-xs font-semibold text-green-800 uppercase">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-green-200">
                                @foreach ($cleaning_rooms as $cleaning_room)
                                <tr class="hover:bg-green-100">
                                    <td class="px-4 py-3">
                                        <span class="text-lg font-bold text-gray-800">{{ $cleaning_room->number }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ $cleaning_room->floor->numberWithFormat() }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ \Carbon\Carbon::parse($cleaning_room->started_cleaning_at)->diffForHumans() }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <button
                                            class="bg-green-600 text-white hover:bg-green-700 px-4 py-1.5 rounded text-sm font-medium"
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

                {{-- Header with Filter Pills --}}
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-4">
                    <h2 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                        Rooms To Clean
                        @php
                            $totalUncleaned = $this->totalUncleanedCount;
                        @endphp
                        @if($totalUncleaned > 0)
                            <span class="inline-flex items-center justify-center px-2 py-0.5 text-sm font-bold text-white bg-red-600 rounded-full">
                                {{ $totalUncleaned }}
                            </span>
                        @endif
                    </h2>

                    {{-- Filter Pills --}}
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            wire:click="getSelectedFloor('all')"
                            @click="activeTab = 'all'"
                            :class="activeTab === 'all'
                                ? 'bg-[#009ff4] text-white'
                                : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                            class="px-3 py-1.5 rounded-full text-sm font-medium transition-colors"
                        >
                            All
                        </button>
                        @foreach($floors as $floor)
                            @php
                                $floorCount = App\Models\Room::whereBranchId($this->user->branch_id)
                                    ->where('status', 'Uncleaned')
                                    ->whereFloorId($floor->id)
                                    ->count();
                            @endphp
                            <button
                                type="button"
                                wire:click="getSelectedFloor({{ $floor->id }})"
                                @click="activeTab = '{{ $floor->id }}'"
                                :class="activeTab == '{{ $floor->id }}'
                                    ? 'bg-[#009ff4] text-white'
                                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                class="px-3 py-1.5 rounded-full text-sm font-medium transition-colors"
                            >
                                {{ $floor->numberWithFormat() }}
                                @if($floorCount > 0)
                                    <span class="ml-1 text-xs">({{ $floorCount }})</span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Loading State --}}
                <div wire:loading class="p-8 text-center text-gray-500">
                    <span class="italic">Loading rooms...</span>
                </div>

                {{-- Rooms Table --}}
                <div wire:loading.remove>
                    @if($rooms->count() > 0)
                        <div class="border border-gray-200 rounded-lg overflow-hidden max-h-[500px] overflow-y-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50 sticky top-0">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider w-12">#</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Room</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Floor</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Type</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Checkout</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Waiting</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach ($rooms as $index => $room)
                                        @php
                                            $checkoutTime = \Carbon\Carbon::parse($room->check_out_time);
                                            $hoursAgo = now()->diffInHours($checkoutTime);

                                            // Row color based on waiting time
                                            if ($hoursAgo >= 2) {
                                                $rowClass = 'bg-red-50 hover:bg-red-100';
                                                $waitingClass = 'text-red-600 font-semibold';
                                            } elseif ($hoursAgo >= 1) {
                                                $rowClass = 'bg-yellow-50 hover:bg-yellow-100';
                                                $waitingClass = 'text-yellow-600 font-semibold';
                                            } else {
                                                $rowClass = 'hover:bg-gray-50';
                                                $waitingClass = 'text-gray-600';
                                            }
                                        @endphp
                                        <tr class="{{ $rowClass }}">
                                            <td class="px-4 py-3 text-sm text-gray-500 font-medium">{{ $index + 1 }}</td>
                                            <td class="px-4 py-3">
                                                <span class="text-lg font-bold text-gray-800">{{ $room->number }}</span>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-600">{{ $room->floor->numberWithFormat() }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-600">{{ $room->type->name ?? '-' }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-800 font-medium">{{ $checkoutTime->format('g:i A') }}</td>
                                            <td class="px-4 py-3 text-sm {{ $waitingClass }}">{{ $checkoutTime->diffForHumans() }}</td>
                                            <td class="px-4 py-3 text-center">
                                                <button
                                                    class="bg-[#009ff4] text-white hover:bg-[#017dc0] px-4 py-1.5 rounded text-sm font-medium transition-colors"
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
                        <div class="p-12 text-center border border-gray-200 rounded-lg bg-gray-50">
                            <svg class="mx-auto h-12 w-12 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <h3 class="mt-3 text-lg font-medium text-gray-900">All Clean!</h3>
                            <p class="mt-1 text-sm text-gray-500">No rooms need cleaning right now.</p>
                        </div>
                    @endif
                </div>

            </div>
          </div>
        </section>
    </div>
</div>
