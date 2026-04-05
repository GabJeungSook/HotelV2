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
      <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 21v-4.875c0-.621.504-1.125 1.125-1.125h1.25c.621 0 1.125.504 1.125 1.125V21m0 0h4.5V3.545M12.75 21h7.5V10.75M2.25 21h1.5m18 0h-18M2.25 9l4.5-1.636M18.75 3l-1.5.545m0 6.205 3 1m1.5-.545M14.25 9.75a2.25 2.25 0 1 0 0-4.5 2.25 2.25 0 0 0 0 4.5Z" />
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
