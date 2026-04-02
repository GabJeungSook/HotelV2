<x-kiosk-layout-update>
<div class="min-h-screen flex items-center justify-center bg-gray-50 px-4">

    <div class="bg-white rounded-3xl shadow-lg border border-blue-200 p-10 max-w-md w-full text-center">

        {{-- Animated Checkmark --}}
        <div class="flex justify-center">
            <svg class="w-24 h-24" viewBox="0 0 52 52">
                <circle class="check-circle"
                        cx="26" cy="26" r="25"
                        fill="none" />
                <path class="check-mark"
                      fill="none"
                      d="M14 27l7 7 16-16" />
            </svg>
        </div>

        <h1 class="mt-6 text-3xl font-extrabold text-gray-800 uppercase">
            Checkout Successful
        </h1>

        <p class="mt-3 text-gray-600">
            Please proceed to the front desk to finish the check out process.
        </p>
         <p class="mt-3 text-gray-600">
            Thank you!
        </p>

        <div class="mt-8">
            <a href="{{ route('kiosk.house-rules') }}"
               class="block w-full bg-[#00a0f5] hover:bg-[#0088d4] text-white font-semibold py-3 rounded-xl transition duration-200">
               Back to Home
            </a>
        </div>

    </div>

</div>

{{-- Animation Styles --}}
<style>
.check-circle {
    stroke: #00a0f5;
    stroke-width: 2;
    stroke-dasharray: 157;
    stroke-dashoffset: 157;
    animation: draw-circle 0.6s ease-out forwards;
}

.check-mark {
    stroke: #00a0f5;
    stroke-width: 3;
    stroke-linecap: round;
    stroke-linejoin: round;
    stroke-dasharray: 48;
    stroke-dashoffset: 48;
    animation: draw-check 0.4s 0.6s ease-out forwards;
}

@keyframes draw-circle {
    to {
        stroke-dashoffset: 0;
    }
}

@keyframes draw-check {
    to {
        stroke-dashoffset: 0;
    }
}
</style>
</x-kiosk-layout-update>
