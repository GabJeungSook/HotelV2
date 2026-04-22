<div>
  <div x-animate x-data class="mx-2 sm:mx-5">
    {{-- Header with Blue Background --}}
    <div class="bg-[#009EF5] rounded-lg p-3 sm:p-4">

      {{-- Mobile: Stack vertically, Desktop: Side by side --}}
      <div class="flex flex-col lg:flex-row lg:items-start gap-3 lg:gap-4">

        {{-- Left: User Profile Card --}}
        <div class="bg-white rounded-lg px-4 py-3 sm:px-6 sm:py-4 flex flex-row lg:flex-col items-center lg:min-w-[140px] gap-3 lg:gap-0">
          <div class="w-12 h-12 sm:w-16 sm:h-16 rounded-full border-4 border-gray-300 bg-gray-100 flex items-center justify-center text-gray-400 text-lg sm:text-xl font-bold flex-shrink-0">
            {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
          </div>
          <div class="flex flex-col lg:items-center">
            <h1 class="text-sm sm:text-base font-bold text-gray-800 lg:mt-2">{{ strtoupper(auth()->user()->name) }}</h1>
            @php
                $cleaning_rooms_count = App\Models\Room::beingCleanedBy(auth()->id())->count();
            @endphp
            <p wire:poll.1s class="text-xs text-gray-500 flex items-center mt-1">
               <span class="uppercase">status:</span>
                @if ($cleaning_rooms_count == 0)
                    <span class="ml-1 inline-flex items-center rounded px-2 py-0.5 text-xs font-bold text-white bg-red-500 uppercase">
                      Not Cleaning
                    </span>
                @else
                    <span class="ml-1 inline-flex items-center rounded px-2 py-0.5 text-xs font-bold text-white bg-green-500 uppercase">
                      Cleaning
                    </span>
                @endif
            </p>
          </div>
        </div>

        {{-- Center: Dashboard Title + Stats --}}
        <div class="flex-1">
          {{-- Title Row with Buttons --}}
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-3 gap-2">
            <h2 class="text-xl sm:text-2xl font-bold text-white uppercase tracking-wide">Dashboard</h2>
            <div class="flex items-center gap-2">
              {{-- Refresh Button --}}
              <button onclick="window.location.reload()"
                 class="inline-flex items-center px-3 py-2 text-sm text-[#009EF5] bg-white border border-white rounded-full hover:bg-gray-100 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 sm:mr-1">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                </svg>
                <span class="hidden sm:inline">Refresh</span>
              </button>
              {{-- View Cleaning History Button --}}
              <a href="{{ route('roomboy.cleaning-history') }}"
                 class="inline-flex items-center px-3 py-2 text-sm text-[#009EF5] bg-white border border-white rounded-full hover:bg-gray-100 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 sm:mr-1">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12s-3.75 6.75-9.75 6.75S2.25 12 2.25 12z" />
                  <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.5" fill="none"/>
                </svg>
                <span class="hidden sm:inline">History</span>
              </a>
            </div>
          </div>

          {{-- Stats Cards Row --}}
          @php
              $floorIds = auth()->user()->floors()->pluck('floors.id')->toArray();
              $totalUncleaned = App\Models\Room::whereBranchId(auth()->user()->branch_id)
                  ->where('status', 'Uncleaned')
                  ->whereIn('floor_id', $floorIds)
                  ->count();
              $inProgress = App\Models\Room::beingCleanedBy(auth()->id())->count();
              $urgentCount = App\Models\Room::whereBranchId(auth()->user()->branch_id)
                  ->where('status', 'Uncleaned')
                  ->whereIn('floor_id', $floorIds)
                  ->where('check_out_time', '<=', now()->subHours(2))
                  ->count();
              $cleanedToday = App\Models\CleaningHistory::where('user_id', auth()->id())
                  ->whereDate('end_time', today())
                  ->count();
              // Penalty: rooms where time_to_clean has expired OR checkout > 4 hours ago
              $penaltyCount = App\Models\Room::whereBranchId(auth()->user()->branch_id)
                  ->where('status', 'Uncleaned')
                  ->whereIn('floor_id', $floorIds)
                  ->where(function($q) {
                      $q->where('time_to_clean', '<=', now())
                        ->orWhere('check_out_time', '<=', now()->subHours(4));
                  })
                  ->count();
          @endphp
          <div class="grid grid-cols-5 gap-2 sm:gap-3">
            <div class="bg-white rounded-lg px-2 py-2 sm:px-4 sm:py-3 text-center">
              <div class="text-[10px] sm:text-xs text-gray-500 uppercase tracking-wide">To Clean</div>
              <div class="text-xl sm:text-3xl font-bold text-gray-900 mt-1">{{ $totalUncleaned }}</div>
            </div>
            <div class="bg-white rounded-lg px-2 py-2 sm:px-4 sm:py-3 text-center">
              <div class="text-[10px] sm:text-xs text-gray-500 uppercase tracking-wide">In Progress</div>
              <div class="text-xl sm:text-3xl font-bold text-gray-900 mt-1">{{ $inProgress }}</div>
            </div>
            <div class="bg-white rounded-lg px-2 py-2 sm:px-4 sm:py-3 text-center">
              <div class="text-[10px] sm:text-xs text-gray-500 uppercase tracking-wide">Urgent (2h+)</div>
              <div class="text-xl sm:text-3xl font-bold mt-1 {{ $urgentCount > 0 ? 'text-red-600' : 'text-gray-900' }}">{{ $urgentCount }}</div>
            </div>
            <div class="bg-white rounded-lg px-2 py-2 sm:px-4 sm:py-3 text-center">
              <div class="text-[10px] sm:text-xs text-gray-500 uppercase tracking-wide">Done Today</div>
              <div class="text-xl sm:text-3xl font-bold text-gray-900 mt-1">{{ $cleanedToday }}</div>
            </div>
            <div class="bg-white rounded-lg px-2 py-2 sm:px-4 sm:py-3 text-center">
              <div class="text-[10px] sm:text-xs text-red-500 uppercase tracking-wide font-semibold">Penalty</div>
              <div class="text-xl sm:text-3xl font-bold mt-1 text-red-600">{{ $penaltyCount }}</div>
            </div>
          </div>
        </div>

      </div>
    </div>

    {{-- Floor Filter Tabs --}}
    @if (!request()->routeIs('roomboy.cleaning-history'))
    <div class="mt-3 flex flex-wrap gap-2">
        @php
            $userFloors = auth()->user()->floors()
                ->whereNotNull('floors.number')
                ->orderBy('floors.id')
                ->get()
                ->unique('id')
                ->values();
        @endphp
        <button
            onclick="Livewire.emit('filterByFloor', 'all')"
            class="px-4 py-1.5 text-sm font-medium rounded-full transition-colors bg-white text-gray-700 border border-gray-300 hover:bg-gray-100"
            id="floor-tab-all">
            ALL
        </button>
        @foreach($userFloors as $floor)
            <button
                onclick="Livewire.emit('filterByFloor', {{ $floor->id }})"
                class="px-4 py-1.5 text-sm font-medium rounded-full transition-colors bg-white text-gray-700 border border-gray-300 hover:bg-gray-100"
                id="floor-tab-{{ $floor->id }}">
                {{ strtoupper($floor->number) }} FLOOR
            </button>
        @endforeach
    </div>
    @endif

    {{-- Main Content --}}
    <div>
        @if (request()->routeIs('roomboy.cleaning-history'))
            <livewire:roomboy.cleaning-history />
        @else
            <livewire:roomboy.main />
        @endif
    </div>
  </div>
</div>
