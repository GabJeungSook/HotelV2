<div class="grid max-w-full grid-cols-1 gap-6 mx-auto mt-5 lg:max-w-full lg:grid-flow-col-dense lg:grid-cols-1">
    <div class="space-y-6 lg:col-span-2 lg:col-start-1">
        <section aria-labelledby="applicant-information-title">
          <div class="bg-white border-b border-x rounded-md shadow-lg">
            <div x-cloak x-data="{ activeTab: '{{ $selectedFloorId }}' }" class="rounded-md px-4 py-5 sm:px-6">

                {{-- Currently Cleaning Section - Always at top --}}
                @php
                    $cleaning_rooms = App\Models\Room::beingCleanedBy(auth()->id())->with('floor')->get();
                @endphp
                @if ($cleaning_rooms->count() > 0)
                <div class="mb-6 p-4 border-2 border-green-500 bg-green-50 rounded-lg">
                    <h3 class="text-lg font-semibold mb-4 text-green-700 flex items-center gap-2">
                        <svg class="w-5 h-5 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                        Currently Cleaning ({{ $cleaning_rooms->count() }})
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                        @foreach ($cleaning_rooms as $cleaning_room)
                        <div class="bg-white shadow-lg border-l-4 border-green-500 p-4 rounded-md">
                            <div class="flex justify-between items-start">
                                <div>
                                    <span class="text-xl font-bold text-gray-800">Room {{ $cleaning_room->number }}</span>
                                    <span class="ml-2 px-2 py-0.5 text-xs bg-gray-200 text-gray-600 rounded">{{ $cleaning_room->floor->numberWithFormat() }}</span>
                                </div>
                            </div>
                            <div class="mt-2 text-sm text-gray-500">
                                Started: <span class="font-medium text-gray-700">{{ \Carbon\Carbon::parse($cleaning_room->started_cleaning_at)->diffForHumans() }}</span>
                            </div>
                            <button
                                class="mt-3 w-full bg-green-600 text-white hover:bg-green-700 flex items-center justify-center gap-2 px-4 py-2 rounded font-medium"
                                x-on:confirm="{
                                    title: 'Finish cleaning this room?',
                                    icon: 'question',
                                    method: 'finishCleaning',
                                    params: [{{ $cleaning_room->id }}]
                                }"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Finish Cleaning
                            </button>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Header with Tab Filters --}}
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-4">
                    <h2 class="text-lg font-medium leading-6 text-gray-900 flex items-center gap-2">
                        <svg class="w-6 h-6 text-[#009ff4]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                        Rooms To Clean
                        @php
                            $totalUncleaned = $this->totalUncleanedCount;
                        @endphp
                        @if($totalUncleaned > 0)
                            <span class="ml-2 inline-flex items-center justify-center px-2.5 py-1 text-sm font-bold leading-none text-white bg-red-600 rounded-full">
                                {{ $totalUncleaned }}
                            </span>
                        @endif
                    </h2>

                    {{-- Quick Filter Pills --}}
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            wire:click="getSelectedFloor('all')"
                            @click="activeTab = 'all'"
                            :class="activeTab === 'all'
                                ? 'bg-[#009ff4] text-white'
                                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                            class="px-4 py-2 rounded-full text-sm font-medium transition-all duration-200 flex items-center gap-1"
                        >
                            All Floors
                            @if($totalUncleaned > 0)
                                <span :class="activeTab === 'all' ? 'bg-white text-[#009ff4]' : 'bg-red-600 text-white'" class="ml-1 px-1.5 py-0.5 text-xs rounded-full">{{ $totalUncleaned }}</span>
                            @endif
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
                                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                                class="px-4 py-2 rounded-full text-sm font-medium transition-all duration-200 flex items-center gap-1"
                            >
                                {{ $floor->numberWithFormat() }}
                                @if($floorCount > 0)
                                    <span :class="activeTab == '{{ $floor->id }}' ? 'bg-white text-[#009ff4]' : 'bg-red-600 text-white'" class="ml-1 px-1.5 py-0.5 text-xs rounded-full">{{ $floorCount }}</span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Loading State --}}
                <div wire:loading class="p-8 text-center">
                    <div class="inline-flex items-center gap-2 text-gray-500">
                        <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Loading rooms...
                    </div>
                </div>

                {{-- Rooms List --}}
                <div wire:loading.remove>
                    @if($rooms->count() > 0)
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                            @foreach ($rooms as $index => $room)
                                @php
                                    $checkoutTime = \Carbon\Carbon::parse($room->check_out_time);
                                    $minutesAgo = now()->diffInMinutes($checkoutTime);
                                    $hoursAgo = now()->diffInHours($checkoutTime);

                                    // Color coding based on waiting time
                                    if ($hoursAgo >= 2) {
                                        $borderColor = 'border-red-500';
                                        $bgColor = 'bg-red-50';
                                        $urgencyLabel = 'URGENT';
                                        $urgencyBg = 'bg-red-600';
                                    } elseif ($hoursAgo >= 1) {
                                        $borderColor = 'border-yellow-500';
                                        $bgColor = 'bg-yellow-50';
                                        $urgencyLabel = 'WAITING';
                                        $urgencyBg = 'bg-yellow-500';
                                    } else {
                                        $borderColor = 'border-blue-500';
                                        $bgColor = 'bg-blue-50';
                                        $urgencyLabel = 'NEW';
                                        $urgencyBg = 'bg-blue-500';
                                    }
                                @endphp
                                <div class="{{ $bgColor }} shadow-lg border-l-4 {{ $borderColor }} p-4 rounded-md relative">
                                    {{-- Priority Badge --}}
                                    <span class="absolute top-2 right-2 px-2 py-0.5 text-xs font-bold text-white {{ $urgencyBg }} rounded">
                                        #{{ $index + 1 }}
                                    </span>

                                    {{-- Room Info --}}
                                    <div class="flex items-start gap-2">
                                        <span class="text-2xl font-bold text-gray-800">{{ $room->number }}</span>
                                        <span class="px-2 py-0.5 text-xs bg-gray-200 text-gray-600 rounded mt-1">{{ $room->floor->numberWithFormat() }}</span>
                                    </div>

                                    {{-- Checkout Time --}}
                                    <div class="mt-3 space-y-1">
                                        <div class="flex items-center gap-2 text-sm">
                                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            <span class="text-gray-600">Checkout:</span>
                                            <span class="font-medium text-gray-800">{{ $checkoutTime->format('g:i A') }}</span>
                                        </div>
                                        <div class="flex items-center gap-2 text-sm">
                                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            <span class="text-gray-600">Waiting:</span>
                                            <span class="font-semibold {{ $hoursAgo >= 2 ? 'text-red-600' : ($hoursAgo >= 1 ? 'text-yellow-600' : 'text-blue-600') }}">
                                                {{ $checkoutTime->diffForHumans() }}
                                            </span>
                                        </div>
                                    </div>

                                    {{-- Room Type --}}
                                    @if($room->type)
                                    <div class="mt-2">
                                        <span class="text-xs text-gray-500">{{ $room->type->name }}</span>
                                    </div>
                                    @endif

                                    {{-- Start Cleaning Button --}}
                                    <button
                                        class="mt-4 w-full bg-[#009ff4] text-white hover:bg-[#017dc0] flex items-center justify-center gap-2 px-4 py-3 rounded-lg font-medium transition-all duration-200"
                                        x-on:confirm="{
                                            title: 'Start cleaning Room {{ $room->number }}?',
                                            icon: 'question',
                                            method: 'startCleaning',
                                            params: [{{ $room->id }}]
                                        }"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        Start Cleaning
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="p-12 text-center">
                            <svg class="mx-auto h-16 w-16 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <h3 class="mt-4 text-lg font-medium text-gray-900">All Clean!</h3>
                            <p class="mt-2 text-gray-500">No rooms need cleaning right now.</p>
                        </div>
                    @endif
                </div>

            </div>
          </div>
        </section>
    </div>
</div>
