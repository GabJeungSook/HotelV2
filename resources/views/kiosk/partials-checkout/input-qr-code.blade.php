<div class="pt-10 ">
  <div class="flex items-end justify-between">
    <div>
      <h1 class="font-bold text-red-600">CHECK-OUT</h1>
      <h1 class="text-3xl uppercase font-extrabold text-gray-600">Scan QR Code</h1>
    </div>
  </div>
<div class="mt-5">
  <div class="flex justify-center ">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-24 w-24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5Z" />
        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 6.75h.75v.75h-.75v-.75ZM6.75 16.5h.75v.75h-.75v-.75ZM16.5 6.75h.75v.75h-.75v-.75ZM13.5 13.5h.75v.75h-.75v-.75ZM13.5 19.5h.75v.75h-.75v-.75ZM19.5 13.5h.75v.75h-.75v-.75ZM19.5 19.5h.75v.75h-.75v-.75ZM16.5 16.5h.75v.75h-.75v-.75Z" />
    </svg>

      {{-- <h1 class="text-6xl text-center font-bold text-gray-700 mt-5">SCAN QR CODE (CHECK-OUT)</h1> --}}
  </div>
  <div class="flex justify-center mt-16">
      <input wire:model="qr_code" type="text" id="qr_code" class="text-center p-4 text-2xl focus:outline-none w-full mx-14 rounded-md border-2 border-gray-300 focus:border-blue-500" placeholder="Scan QR Code Here" autofocus autocomplete="off" />
  </div>
</div>

<div class="fixed bottom-20 right-0 left-0 px-4">
  <div class="flex justify-center">
    @if ($qr_code)
     <button
           wire:click="validateQR"
           wire:loading.attr="disabled"
           wire:loading.class="opacity-50 cursor-not-allowed"
          class="font-bold text-2xl px-12 py-5 text-white bg-green-600 hover:bg-green-700 active:bg-green-800 rounded-2xl flex items-center justify-center gap-3 min-w-[200px] shadow-lg transition-all">
          <span wire:loading.remove wire:target="validateQR">NEXT</span>
          <span wire:loading wire:target="validateQR">LOADING...</span>
          <svg xmlns="http://www.w3.org/2000/svg"
              class="w-8 h-8"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
              wire:loading.remove
              wire:target="validateQR">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M13 7l5 5m0 0l-5 5m5-5H6" />
          </svg>
      </button>
    @endif
  </div>
</div>

<script>
    const qrInput = document.getElementById('qr_code');
    qrInput.addEventListener('blur', () => {
        qrInput.focus();
    });
</script>

  </div>

