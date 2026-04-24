<div class="pt-10 ">
  <div class="flex items-end justify-between">
    <div>
      <h1 class="font-bold text-green-600">CHECK-IN</h1>
      <h1 class="text-3xl uppercase font-extrabold text-gray-600">Select room </h1>
    </div>
    <div>
      @if ($steps == 1)
        <a href="{{ route('kiosk.dashboard') }}"
        class="bg-gray-50 outline-blue-500 border border-blue-500 p-8 px-14 flex space-x-1 rounded-full">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
          class="w-14 text-blue-500 h-14">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 15.75L3 12m0 0l3.75-3.75M3 12h18" />
          </svg>
          <span class="font-semibold text-blue-500 uppercase">Back</span>
        </a>
      @else
        <button x-on:click="step--" wire:click="backRoom"
        class="bg-gray-50 outline-blue-500 border border-blue-500 p-8 px-14 flex space-x-1 rounded-full">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
            stroke="currentColor" class="w-6 text-blue-500 h-6">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 15.75L3 12m0 0l3.75-3.75M3 12h18" />
          </svg>
          <span class="font-semibold text-blue-500 uppercase">Back</span>
        </button>
      @endif
    </div>
  </div>
  <div class="mt-5">
    <div class="flex space-x-2">
      @foreach ($floors as $floor)
        @if ($floor->rooms->where('status', 'Available')->where('is_priority', true)->where('type_id', $type_id)->count() > 0)
          <button>
            <div wire:click="$set('floor_id', {{ $floor->id }})"
              class="border-2 py-2 px-4 rounded-full flex items-center justify-center transition-all {{ $floor_id == $floor->id ? 'text-white bg-green-600 border-green-600 shadow-md scale-105' : 'text-gray-600 bg-gray-50 border-blue-500' }}">
              <h1 class="font-bold text-lg">{{ $floor->numberWithFormat() }}</h1>
            </div>
          </button>
        @endif
      @endforeach
    </div>
    <div class="grid lg:grid-cols-5 sm:grid-cols-3 mt-5 gap-5">

      @forelse ($rooms as $room)
        <button wire:key="{{ $room->id }}room" wire:click="selectRoom({{ $room->id }})" type="button" class="transition-all duration-200 {{ $room_id == $room->id ? 'scale-105' : 'opacity-80 hover:opacity-100' }}">
          <div class="border-4 h-40 relative overflow-hidden rounded-2xl grid place-content-center transition-all duration-200 {{ $room_id == $room->id ? 'border-green-500 bg-green-50 shadow-2xl shadow-green-200 ring-4 ring-green-200' : 'border-gray-200 bg-gray-50' }}">
            @if ($room_id == $room->id)
              <div class="absolute top-2 right-2 bg-green-500 text-white rounded-full w-10 h-10 flex items-center justify-center shadow-lg z-10">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
              </div>
            @endif
            <svg
              class="lg:h-56 sm:h-44 absolute {{ $room_id == $room->id ? 'text-green-600 opacity-30' : 'text-gray-600 opacity-10' }} top-0 -right-10"
              xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="none">
              <path
                d="M10 7.99841C10 8.27456 9.77614 8.49841 9.5 8.49841C9.22386 8.49841 9 8.27456 9 7.99841C9 7.72227 9.22386 7.49841 9.5 7.49841C9.77614 7.49841 10 7.72227 10 7.99841ZM7.59806 2.00971C7.45117 1.98034 7.29885 2.01836 7.18301 2.11333C7.06716 2.2083 7 2.35021 7 2.5V13.4969C7 13.6467 7.06716 13.7886 7.18301 13.8836C7.29885 13.9785 7.45117 14.0166 7.59806 13.9872L12.5981 12.9872C12.8318 12.9404 13 12.7352 13 12.4969V3.5C13 3.26166 12.8318 3.05646 12.5981 3.00971L7.59806 2.00971ZM8 12.887V3.10991L12 3.90991V12.087L8 12.887ZM6 12.9969V11.9969H4V4H6V3H3.5C3.22386 3 3 3.22386 3 3.5V12.4969C3 12.773 3.22386 12.9969 3.5 12.9969H6Z"
                fill="currentColor"></path>
            </svg>
            <h1 class="font-bold relative lg:text-4xl sm:text-lg {{ $room_id == $room->id ? 'text-green-700' : 'text-gray-700' }}">{{ $room->numberWithFormat() }}</h1>
          </div>
        </button>
      @empty
        <div class="col-span-5 w-full  flex justify-center items-center mt-10">
          <h1 class="font-bold text-white relative text-4xl">No Priority rooms</h1>
        </div>
      @endforelse
    </div>
  </div>
</div>
<div class="fixed bottom-20 right-0 left-0">
  <div class="flex justify-center">
    @if ($room_id)
     <button
          wire:click="$set('steps', 3)"
          class="font-medium px-8 py-3 text-white bg-green-600 rounded-2xl flex items-center gap-2 shadow-xl ring-4 ring-green-200 animate-pulse hover:animate-none hover:bg-green-700 transition-colors">
          
          NEXT
          
          <svg xmlns="http://www.w3.org/2000/svg" 
              class="w-14 h-14" 
              fill="none" 
              viewBox="0 0 24 24" 
              stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M13 7l5 5m0 0l-5 5m5-5H6" />
          </svg>

      </button>
      {{-- <x-button label="NEXT" wire:click="$set('steps', 3)" lg class="font-medium " right-icon="chevron-double-right"
        spinner green /> --}}
    @endif
  </div>
</div>
