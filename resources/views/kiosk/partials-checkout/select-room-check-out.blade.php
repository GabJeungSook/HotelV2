<div class="pt-10 ">
  <div class="flex items-end justify-between">
    <div>
      <h1 class="font-bold text-red-600">CHECK-OUT</h1>
      <h1 class="text-3xl uppercase font-extrabold text-gray-600">Enter Room Number</h1>
    </div>
  </div>
<div class="mt-5">
  <div class="flex justify-center ">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-24 w-24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />
    </svg>
  </div>
  <div class="flex justify-center mt-16">
      <input wire:model="room_number" type="number" id="room_number" class="text-center p-4 text-2xl focus:outline-none w-full mx-14 rounded-md" autofocus autocomplete="off" />
  </div>
  <small class="flex justify-center mt-3 font-medium text-red-600">*Input Your Room Number Here*</small>
</div>

<div class="fixed bottom-20 right-0 left-0">
  <div class="flex justify-center">
    @if ($room_number)
     <button
          wire:click="findRoom"
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
    @endif
  </div>
</div>

<script>
    const roomInput = document.getElementById('room_number');
    roomInput.addEventListener('blur', () => {
        roomInput.focus();
    });
</script>

  </div>
