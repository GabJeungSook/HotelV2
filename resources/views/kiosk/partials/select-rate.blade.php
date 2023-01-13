<div>

  <div class="flex items-end justify-between">
    <div>
      <h1 class="font-bold text-green-400">CHECK-IN</h1>
      <h1 class="text-5xl uppercase font-extrabold text-gray-50">Select rate </h1>
    </div>
    <div>
      @if ($steps == 1)
        <a href="{{ route('kiosk.dashboard') }}"
          class="bg-gradient-to-r from-red-500 via-red-500 to-transparent p-2 px-4 flex space-x-1 rounded-full">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
            class="w-6 text-white h-6">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 15.75L3 12m0 0l3.75-3.75M3 12h18" />
          </svg>
          <span class="font-semibold text-gray-100 uppercase">Back</span>
        </a>
      @else
        <button x-on:click="step--"
          class="bg-gradient-to-r from-red-500 via-red-500 to-transparent p-2 px-4 flex space-x-1 rounded-full">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
            stroke="currentColor" class="w-6 text-white h-6">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 15.75L3 12m0 0l3.75-3.75M3 12h18" />
          </svg>
          <span class="font-semibold text-gray-100 uppercase">Back</span>
        </button>
      @endif
    </div>
  </div>
  <div class="mt-10">
    <div class="grid grid-cols-5 gap-3">
      @foreach ($rates as $rate)
        <button wire:key="{{ $rate->id }}rate" wire:click="selectRate({{ $rate->id }})" type="button">
          <div class="bg-gray-50 h-40 relative overflow-hidden grid place-content-center rounded-2xl">
            <svg
              class="h-40 absolute top-0 -right-16 {{ $rate_id == $rate->id ? 'text-green-600 opacity-40' : 'text-gray-500 opacity-10' }}"
              xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1"
              viewBox="0 0 16 16" fill="currentColor">
              <path fill="currentColor"
                d="M6.16 4.6c1.114 0.734 1.84 1.979 1.84 3.394 0 0.002 0 0.004 0 0.006v-0c0-0.002 0-0.004 0-0.006 0-1.415 0.726-2.66 1.825-3.384 0.23-0.199 0.426-0.395 0.609-0.602l-4.874-0.007c0.19 0.214 0.386 0.41 0.593 0.594z">
              </path>
              <path fill="currentColor"
                d="M11.18 6.060c1.107-0.808 1.819-2.101 1.82-3.56v-0.5h1v-2h-12v2h1v0.5c0.001 1.459 0.713 2.752 1.808 3.551 0.672 0.43 1.121 1.13 1.192 1.939-0.093 0.848-0.551 1.564-1.209 2.003-1.081 0.814-1.772 2.078-1.79 3.503l-0 0.503h-1v2h12v-2h-1v-0.5c-0.018-1.429-0.709-2.692-1.769-3.492-0.68-0.454-1.138-1.169-1.23-1.996 0.071-0.831 0.52-1.532 1.169-1.946zM9 8c0.072 1.142 0.655 2.136 1.519 2.763 0.877 0.623 1.445 1.61 1.481 2.732l0 0.505h-1.77c-0.7-0.87-1.71-2-2.23-2s-1.53 1.13-2.23 2h-1.77v-0.5c0.036-1.127 0.604-2.114 1.459-2.723 0.886-0.642 1.468-1.635 1.54-2.766-0.063-1.124-0.641-2.091-1.498-2.683-0.914-0.633-1.499-1.662-1.502-2.827v-0.5h8v0.5c-0.003 1.166-0.587 2.195-1.479 2.813-0.88 0.607-1.458 1.574-1.521 2.678z">
              </path>
            </svg>
            <h1 class="text-[2rem] font-extrabold uppercase text-gray-700"> {{ $rate->stayingHour->number }} HOURS
            </h1>
            <h1 class="text-xl font-semibold text-red-600">&#8369;{{ number_format($rate->amount, 2) }}</h1>
          </div>
        </button>
      @endforeach
    </div>
    <h1 class="mt-5 text-2xl ml-3 font-bold text-white">OR</h1>
    <div class="mt-5">
      <div class="bg-gray-50 overflow-hidden  relative w-96 p-5 rounded-2xl">
        <h1 class="text-2xl font-bold text-gray-700">LONG STAY</h1>
        <div class="mt-5">
          <p class="text-gray-500">Enter number of days:</p>
          <input type="number" wire:model="longstay" class="text-2xl w-full rounded-lg relative">
        </div>
        <svg
          class="h-40 absolute top-0 -right-16 {{ $longstay != null ? 'text-green-600 opacity-40' : 'text-gray-500 opacity-10' }}"
          xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1"
          viewBox="0 0 16 16" fill="currentColor">
          <path fill="currentColor"
            d="M6.16 4.6c1.114 0.734 1.84 1.979 1.84 3.394 0 0.002 0 0.004 0 0.006v-0c0-0.002 0-0.004 0-0.006 0-1.415 0.726-2.66 1.825-3.384 0.23-0.199 0.426-0.395 0.609-0.602l-4.874-0.007c0.19 0.214 0.386 0.41 0.593 0.594z">
          </path>
          <path fill="currentColor"
            d="M11.18 6.060c1.107-0.808 1.819-2.101 1.82-3.56v-0.5h1v-2h-12v2h1v0.5c0.001 1.459 0.713 2.752 1.808 3.551 0.672 0.43 1.121 1.13 1.192 1.939-0.093 0.848-0.551 1.564-1.209 2.003-1.081 0.814-1.772 2.078-1.79 3.503l-0 0.503h-1v2h12v-2h-1v-0.5c-0.018-1.429-0.709-2.692-1.769-3.492-0.68-0.454-1.138-1.169-1.23-1.996 0.071-0.831 0.52-1.532 1.169-1.946zM9 8c0.072 1.142 0.655 2.136 1.519 2.763 0.877 0.623 1.445 1.61 1.481 2.732l0 0.505h-1.77c-0.7-0.87-1.71-2-2.23-2s-1.53 1.13-2.23 2h-1.77v-0.5c0.036-1.127 0.604-2.114 1.459-2.723 0.886-0.642 1.468-1.635 1.54-2.766-0.063-1.124-0.641-2.091-1.498-2.683-0.914-0.633-1.499-1.662-1.502-2.827v-0.5h8v0.5c-0.003 1.166-0.587 2.195-1.479 2.813-0.88 0.607-1.458 1.574-1.521 2.678z">
          </path>
        </svg>
      </div>
    </div>
  </div>
</div>
<div class="fixed bottom-20 right-0 left-0">
  <div class="flex justify-center">
    @if ($rate_id != null || $longstay != null)
      <x-button label="NEXT" wire:click="proceedFillUp" lg class="font-medium " right-icon="chevron-double-right"
        green />
    @endif
  </div>
</div>
