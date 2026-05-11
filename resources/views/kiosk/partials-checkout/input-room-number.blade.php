<div class="pt-10 ">
    <div class="flex items-end justify-between">
        <div>
            <h1 class="font-bold text-red-600">CHECK-OUT</h1>
            <h1 class="text-3xl uppercase font-extrabold text-gray-600">Enter Room Number</h1>
        </div>
    </div>
    <div class="mt-5">
        <div class="flex justify-center ">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                stroke="currentColor" class="h-24 w-24">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
            </svg>
        </div>
        <div class="flex justify-center mt-16">
            <input wire:model="room_number" type="text" id="room_number"
                class="text-center p-4 text-2xl focus:outline-none w-full mx-14 rounded-md border-2 border-gray-300 focus:border-blue-500"
                placeholder="Enter Room Number" autofocus autocomplete="off" />
        </div>
    </div>

    <div class="fixed bottom-20 right-0 left-0 px-4">
        <div class="flex justify-center">
            @if ($room_number)
                <button wire:click="validateRoom" wire:loading.attr="disabled"
                    wire:loading.class="opacity-50 cursor-not-allowed"
                    class="font-bold text-2xl px-12 py-5 text-white bg-green-600 hover:bg-green-700 active:bg-green-800 rounded-2xl flex items-center justify-center gap-3 min-w-[200px] shadow-lg transition-all">
                    <span wire:loading.remove wire:target="validateRoom">NEXT</span>
                    <span wire:loading wire:target="validateRoom">LOADING...</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor" wire:loading.remove wire:target="validateRoom">
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
