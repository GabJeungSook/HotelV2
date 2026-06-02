<div x-data="{
    tip: { show: false, room: '', status: '', desc: '', bed: '', x: 0, y: 0 },
    showTip(e, room, status, desc, bed) {
        this.tip = { show: true, room, status, desc, bed, x: e.clientX + 14, y: e.clientY - 10 };
    },
    moveTip(e) {
        if (this.tip.show) { this.tip.x = e.clientX + 14; this.tip.y = e.clientY - 10; }
    },
    hideTip() { this.tip.show = false; }
}" @mousemove.window="moveTip($event)">

  {{-- TOPBAR --}}
  <div class="flex items-center gap-3 px-4 py-3 border-b border-gray-200 flex-wrap bg-white">
    <a href="{{ route('frontdesk.room-monitoring') }}" class="text-gray-400 hover:text-gray-600 transition-colors" title="Back to Room Monitoring">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
      </svg>
    </a>
    <span class="text-sm font-semibold text-gray-800">Front Desk — Kiosk Room Queue</span>
    <span class="text-xs text-gray-500 font-mono" x-data x-text="(() => {
      const n = new Date();
      return n.toLocaleDateString('en-PH',{weekday:'short',month:'short',day:'numeric'}) + ' · ' +
             n.toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
    })()" x-init="setInterval(() => $el.textContent = (() => {
      const n = new Date();
      return n.toLocaleDateString('en-PH',{weekday:'short',month:'short',day:'numeric'}) + ' · ' +
             n.toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
    })(), 1000)"></span>
    <div class="flex-1"></div>
    {{-- Legend --}}
    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-yellow-300 text-yellow-900 border border-yellow-600 ring-2 ring-red-400 ring-offset-1">NOW — on kiosk</span>
    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-100 text-amber-800 border border-amber-400">NEXT — kiosk batch</span>
    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-600 text-white border border-emerald-700">AFTER — queued</span>
    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-blue-500 text-white border border-blue-700">CLEANED — least priority</span>
  </div>

  <div class="p-4 space-y-4">
    {{-- Branch capacity bar --}}
    @if (!empty($kioskBatchTotals))
      @php
        $tot = max(1, (int) $kioskBatchTotals['total']);
        $avail = (int) $kioskBatchTotals['available'];
        $occ = (int) $kioskBatchTotals['occupied'];
        $other = max(0, $tot - $avail - $occ);
        $availPct = round($avail / $tot * 100);
        $occPct = round($occ / $tot * 100);
        $otherPct = max(0, 100 - $availPct - $occPct);
      @endphp
      <div class="flex items-center gap-x-4 bg-white rounded-lg px-4 py-3 border border-gray-100 shadow-sm">
        <div class="text-xs text-gray-500 uppercase tracking-wider whitespace-nowrap font-medium">Branch capacity</div>
        <div class="flex-1 flex h-2 overflow-hidden rounded-full bg-gray-100">
          <div class="bg-emerald-500" style="width: {{ $availPct }}%" title="Available: {{ $avail }}"></div>
          <div class="bg-rose-500" style="width: {{ $occPct }}%" title="Occupied: {{ $occ }}"></div>
          <div class="bg-gray-300" style="width: {{ $otherPct }}%" title="Other: {{ $other }}"></div>
        </div>
        <div class="flex items-baseline gap-x-3 text-sm whitespace-nowrap">
          <span><span class="inline-block w-2 h-2 rounded-full bg-emerald-500 align-middle mr-1"></span><span class="font-semibold text-gray-900">{{ $avail }}</span> <span class="text-gray-500">ready</span></span>
          <span><span class="inline-block w-2 h-2 rounded-full bg-rose-500 align-middle mr-1"></span><span class="font-semibold text-gray-900">{{ $occ }}</span> <span class="text-gray-500">occupied</span></span>
          @if ($other > 0)
            <span><span class="inline-block w-2 h-2 rounded-full bg-gray-300 align-middle mr-1"></span><span class="font-semibold text-gray-900">{{ $other }}</span> <span class="text-gray-500">other</span></span>
          @endif
          <span class="text-gray-400">·</span>
          <span class="text-gray-500">{{ $tot }} total</span>
        </div>
      </div>
    @endif

    {{-- How-to-read --}}
    <div class="rounded-md bg-gray-50 border border-gray-200 p-3 text-xs text-gray-700 space-y-1">
      <p><span class="font-semibold text-gray-900">How to read:</span> <span class="font-semibold text-yellow-700">NOW</span> is the room currently visible on the kiosk. <span class="font-semibold text-amber-700">NEXT</span> are the upcoming rooms in priority order. <span class="font-semibold text-emerald-700">AFTER</span> are all remaining queued rooms grouped by floor. <span class="font-semibold text-blue-700">CLEANED</span> rooms are lowest priority and enter the queue last.</p>
    </div>

    @if (empty($kioskBatchData))
      <p class="text-sm text-gray-500">No kiosk batch data.</p>
    @else
      @foreach ($kioskBatchData as $typeBlock)
        <div class="border rounded-lg bg-white shadow-sm">
          {{-- Type header --}}
          <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
            <h3 class="font-semibold text-base text-gray-800 uppercase tracking-wide">{{ $typeBlock['type_name'] }}</h3>
            <div class="text-xs text-gray-600 space-x-3">
              <span><span class="font-semibold text-emerald-700">{{ $typeBlock['total_available'] }}</span> total available</span>
              <span class="text-gray-400">·</span>
              <span><span class="font-semibold">{{ $typeBlock['active_count'] }}</span> on kiosk</span>
              <span class="text-gray-400">·</span>
              <span><span class="font-semibold">{{ $typeBlock['waiting_count'] }}</span> in queue</span>
              @if ($typeBlock['picked_count'] > 0)
                <span class="text-gray-400">·</span>
                <span><span class="font-semibold text-amber-700">{{ $typeBlock['picked_count'] }}</span> waiting frontdesk</span>
              @endif
            </div>
          </div>

          <div class="px-4 py-3 space-y-3">
            {{-- Round-robin flow indicator --}}
            <div class="flex items-center gap-1 text-[9px] text-gray-500 px-2 py-1.5 bg-gray-50 rounded-md border border-gray-100 w-fit">
              <span class="px-1.5 py-0.5 rounded bg-yellow-300 text-yellow-900 font-bold border border-yellow-600">NOW</span>
              <span class="opacity-50">→</span>
              <span class="px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 font-bold border border-amber-400">NEXT</span>
              <span class="opacity-50">→</span>
              <span class="px-1.5 py-0.5 rounded bg-emerald-600 text-white font-bold border border-emerald-700">AFTER</span>
              <span class="opacity-50">→</span>
              <span class="px-1.5 py-0.5 rounded bg-blue-500 text-white font-bold border border-blue-700">CLEANED</span>
              <span class="ml-1 italic">round-robin cycle</span>
            </div>

            {{-- NOW row --}}
            <div class="flex flex-col gap-1.5">
              <div class="flex items-center gap-2">
                <span class="inline-flex items-center justify-center min-w-[42px] px-2 py-0.5 rounded text-[9px] font-bold bg-yellow-300 text-yellow-900 border border-yellow-600">NOW</span>
                <div class="flex gap-1 flex-wrap items-center">
                  @forelse ($typeBlock['now'] as $room)
                    <span class="inline-flex items-center justify-center min-w-[34px] h-7 px-1.5 rounded-md text-[11px] font-bold font-mono cursor-pointer transition-transform hover:scale-110 bg-yellow-300 text-yellow-900 border-[1.5px] border-yellow-600 ring-2 ring-red-400 ring-offset-1"
                      @mouseenter="showTip($event, '{{ $room['room_number'] }}', 'NOW — on kiosk', 'Currently shown on kiosk — guests can select this room now.', '{{ $typeBlock['type_name'] }}')"
                      @mouseleave="hideTip()">
                      {{ $room['room_number'] }}
                    </span>
                  @empty
                    <span class="text-xs text-gray-400 italic">No active room</span>
                  @endforelse
                  {{-- Picked rooms inline --}}
                  @foreach ($typeBlock['picked'] as $room)
                    <span class="inline-flex items-center justify-center min-w-[34px] h-7 px-1.5 rounded-md text-[11px] font-bold font-mono cursor-pointer transition-transform hover:scale-110 bg-amber-500 text-white border-[1.5px] border-amber-600 line-through opacity-75"
                      @mouseenter="showTip($event, '{{ $room['room_number'] }}', 'PICKED — waiting frontdesk', 'Guest selected this room. Waiting for frontdesk confirmation.', '{{ $typeBlock['type_name'] }}')"
                      @mouseleave="hideTip()">
                      {{ $room['room_number'] }}
                    </span>
                  @endforeach
                </div>
              </div>

              {{-- NEXT row --}}
              <div class="flex items-center gap-2">
                <span class="inline-flex items-center justify-center min-w-[42px] px-2 py-0.5 rounded text-[9px] font-bold bg-amber-100 text-amber-800 border border-amber-400">NEXT</span>
                <div class="flex gap-1 flex-wrap items-center">
                  @forelse ($typeBlock['next'] as $room)
                    <span class="inline-flex items-center justify-center min-w-[34px] h-7 px-1.5 rounded-md text-[11px] font-bold font-mono cursor-pointer transition-transform hover:scale-110 bg-amber-100 text-amber-800 border-[1.5px] border-amber-400"
                      @mouseenter="showTip($event, '{{ $room['room_number'] }}', 'NEXT — kiosk batch', 'Next batch to appear on kiosk once current room is taken.', '{{ $typeBlock['type_name'] }}')"
                      @mouseleave="hideTip()">
                      {{ $room['room_number'] }}
                    </span>
                  @empty
                    <span class="text-xs text-gray-400 italic">No upcoming rooms</span>
                  @endforelse
                </div>
              </div>
            </div>

            {{-- AFTER separator --}}
            <div class="flex items-center gap-2 my-1">
              <div class="flex-1 h-px bg-gray-200"></div>
              <span class="text-[9px] font-bold tracking-wider text-gray-500 uppercase whitespace-nowrap">After — queued rooms (round-robin)</span>
              <div class="flex-1 h-px bg-gray-200"></div>
            </div>

            {{-- AFTER floor rows --}}
            @if (!empty($typeBlock['after']))
              <div class="flex flex-col gap-1">
                @foreach ($typeBlock['after'] as $floorGroup)
                  <div class="flex items-start gap-1.5">
                    <span class="text-[9px] font-semibold text-gray-400 font-mono min-w-[20px] pt-1.5">F{{ $floorGroup['floor'] }}</span>
                    <div class="flex flex-wrap gap-1">
                      @foreach ($floorGroup['rooms'] as $room)
                        <span class="inline-flex items-center justify-center min-w-[34px] h-7 px-1.5 rounded-md text-[11px] font-bold font-mono cursor-pointer transition-transform hover:scale-110 bg-emerald-600 text-white border border-emerald-700"
                          @mouseenter="showTip($event, '{{ $room['number'] }}', 'AFTER — queued', 'Waiting in round-robin queue — will rotate to NEXT, then NOW.', '{{ $typeBlock['type_name'] }}')"
                          @mouseleave="hideTip()">
                          {{ $room['number'] }}
                        </span>
                      @endforeach
                    </div>
                  </div>
                @endforeach
              </div>
            @else
              <p class="text-xs text-gray-400 italic pl-6">No queued rooms</p>
            @endif

            {{-- CLEANED separator --}}
            @if (!empty($typeBlock['cleaned']))
              <div class="mt-2 pt-2 border-t-2 border-dashed border-gray-200 opacity-85">
                <div class="flex items-center gap-2 mb-2">
                  <span class="text-[9px] font-bold tracking-wider px-2 py-0.5 rounded bg-blue-50 text-blue-800 border border-blue-300">LEAST PRIORITY</span>
                  <span class="text-[9px] text-gray-500">Newly cleaned — enters queue last</span>
                </div>
                <div class="flex flex-wrap gap-1">
                  @foreach ($typeBlock['cleaned'] as $room)
                    <span class="inline-flex items-center justify-center min-w-[34px] h-7 px-1.5 rounded-md text-[11px] font-bold font-mono cursor-pointer transition-transform hover:scale-110 bg-blue-500 text-white border border-blue-700"
                      @mouseenter="showTip($event, '{{ $room['room_number'] }}', 'CLEANED — least priority', 'Newly cleaned — least priority. Enters queue last after all others cycle through.', '{{ $typeBlock['type_name'] }}')"
                      @mouseleave="hideTip()">
                      {{ $room['room_number'] }}
                    </span>
                  @endforeach
                </div>
              </div>
            @endif
          </div>
        </div>
      @endforeach
    @endif

    {{-- Footer --}}
    <div class="flex justify-between items-center pt-2">
      <a href="{{ route('frontdesk.room-monitoring') }}" class="inline-flex items-center gap-1 text-sm text-gray-600 hover:text-gray-800 transition-colors">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
        </svg>
        Back to Room Monitoring
      </a>
      <button wire:click="loadBatchData" wire:loading.attr="disabled" class="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" wire:loading.class="animate-spin" wire:target="loadBatchData" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        </svg>
        Refresh
      </button>
    </div>
  </div>

  {{-- Tooltip --}}
  <div x-show="tip.show" x-cloak
    class="fixed bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-800 pointer-events-none z-[999] shadow-lg max-w-[200px]"
    :style="'left: ' + tip.x + 'px; top: ' + tip.y + 'px'">
    <div class="font-bold font-mono text-sm" x-text="'Room ' + tip.room"></div>
    <div class="font-medium text-xs" x-text="tip.status + (tip.bed ? ' · ' + tip.bed : '')"></div>
    <div class="text-[10px] text-gray-500 mt-1 pt-1 border-t border-gray-100" x-text="tip.desc"></div>
  </div>
</div>
