<div class="pt-10">
  <div class="flex items-end justify-between">
    <div>
      <h1 class="font-bold text-red-600">CHECK-OUT</h1>
      <h1 class="text-3xl uppercase font-extrabold text-gray-600">Enter your room number</h1>
    </div>
  </div>
  <div class="mt-10 flex justify-center">
    <div class="w-full max-w-md">
      <form wire:submit.prevent="findRoom">
        <label class="block text-lg font-semibold text-gray-600 mb-2">Room Number</label>
        <input type="number" wire:model.defer="room_number"
               autofocus
               placeholder="Enter room number"
               class="w-full text-center text-4xl font-bold py-4 px-6 border-2 border-blue-500 rounded-2xl focus:border-green-500 focus:ring-green-500 text-gray-700"
               x-init="$el.focus()"
               @blur="$el.focus()">
        @error('room_number')
          <p class="text-red-500 text-sm mt-2">{{ $message }}</p>
        @enderror
        <div class="mt-8 flex justify-center">
          <button type="submit"
                  class="font-medium px-8 py-3 text-white bg-green-600 rounded-2xl flex items-center gap-2">
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
        </div>
      </form>
    </div>
  </div>
</div>
