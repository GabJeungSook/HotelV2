<div>
  <div x-animate x-data class="mx-5">
    {{-- Header Card with User Profile, Dashboard Title, and Stats --}}
    <div class="max-w-full px-4 py-4 mx-auto bg-white rounded-lg shadow-lg">
      <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">

        {{-- Left: User Profile --}}
        <div class="flex items-center space-x-4">
          <div class="flex-shrink-0">
            <div class="w-16 h-16 rounded-full bg-gray-200 flex items-center justify-center text-gray-500 text-xl font-bold">
              {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
            </div>
          </div>
          <div>
            <h1 class="text-xl font-bold text-gray-800">{{ strtoupper(auth()->user()->name) }}</h1>
            @php
                $cleaning_rooms_count = App\Models\Room::beingCleanedBy(auth()->id())->count();
            @endphp
            <p wire:poll.1s class="text-sm text-gray-500 flex items-center mt-1">
               <span class="uppercase">status:</span>
                @if ($cleaning_rooms_count == 0)
                    <span class="ml-2 inline-flex items-center rounded px-2 py-0.5 text-xs font-bold text-white bg-red-500 uppercase">
                      Not Cleaning
                    </span>
                @else
                    <span class="ml-2 inline-flex items-center rounded px-2 py-0.5 text-xs font-bold text-white bg-green-500 uppercase">
                      Cleaning
                    </span>
                @endif
            </p>
          </div>
        </div>

        {{-- Center: Dashboard Title --}}
        <div class="hidden lg:block">
          <h2 class="text-2xl font-bold text-[#009EF5] uppercase tracking-wide">Dashboard</h2>
        </div>

        {{-- Right: View Cleaning History Button --}}
        <div>
          <a href="{{ route('roomboy.cleaning-history') }}"
             class="inline-flex items-center px-4 py-2 text-sm text-[#009EF5] border border-[#009EF5] rounded-full hover:bg-[#009EF5] hover:text-white transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mr-2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12s-3.75 6.75-9.75 6.75S2.25 12 2.25 12z" />
              <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.5" fill="none"/>
            </svg>
            View Cleaning History
          </a>
        </div>
      </div>

      {{-- Stats Cards Row --}}
      @php
          $totalUncleaned = App\Models\Room::whereBranchId(auth()->user()->branch_id)
              ->where('status', 'Uncleaned')
              ->count();
          $inProgress = App\Models\Room::beingCleanedBy(auth()->id())->count();
          $urgentCount = App\Models\Room::whereBranchId(auth()->user()->branch_id)
              ->where('status', 'Uncleaned')
              ->where('check_out_time', '<=', now()->subHours(2))
              ->count();
          $cleanedToday = App\Models\CleaningHistory::where('user_id', auth()->id())
              ->whereDate('end_time', today())
              ->count();
      @endphp
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
        <div class="border border-gray-200 rounded px-4 py-3">
          <div class="text-xs text-gray-500 uppercase tracking-wide">To Clean</div>
          <div class="text-3xl font-bold text-gray-900 mt-1">{{ $totalUncleaned }}</div>
        </div>
        <div class="border border-gray-200 rounded px-4 py-3">
          <div class="text-xs text-gray-500 uppercase tracking-wide">In Progress</div>
          <div class="text-3xl font-bold text-gray-900 mt-1">{{ $inProgress }}</div>
        </div>
        <div class="border border-gray-200 rounded px-4 py-3">
          <div class="text-xs text-gray-500 uppercase tracking-wide">Urgent (2h+)</div>
          <div class="text-3xl font-bold mt-1 {{ $urgentCount > 0 ? 'text-red-600' : 'text-gray-900' }}">{{ $urgentCount }}</div>
        </div>
        <div class="border border-gray-200 rounded px-4 py-3">
          <div class="text-xs text-gray-500 uppercase tracking-wide">Done Today</div>
          <div class="text-3xl font-bold text-gray-900 mt-1">{{ $cleanedToday }}</div>
        </div>
      </div>
    </div>

    {{-- Main Content --}}
    <div wire:ignore>
        @if (request()->routeIs('roomboy.cleaning-history'))
            <livewire:roomboy.cleaning-history />
        @else
            <livewire:roomboy.main />
        @endif
    </div>
  </div>
</div>
