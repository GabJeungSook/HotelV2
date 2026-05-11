<div class="pt-6">
  <div class="flex items-end justify-between">
    <div>
      <h1 class="font-bold text-red-600 text-2xl">CHECK-OUT</h1>
      <h1 class="text-5xl uppercase font-extrabold text-gray-600">Bill Summary</h1>
    </div>

    <div>
      <button x-on:click="step--" wire:click="backToQR"
        class="bg-gray-50 outline-blue-500 border-2 border-blue-500 p-4 px-8 flex space-x-2 rounded-full">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
          stroke="currentColor" class="w-8 text-blue-500 h-8">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 15.75L3 12m0 0l3.75-3.75M3 12h18" />
        </svg>
        <span class="font-semibold text-blue-500 uppercase text-xl">Back</span>
      </button>
    </div>
  </div>

  <div class="mt-6">
    <div
      class="w-full flex flex-col lg:flex-row space-y-5 lg:space-y-0 lg:space-x-5 bg-gray-50 border-2 border-blue-500 bg-opacity-75 rounded-2xl">

      <div class="flex-1 p-6 px-6 md:px-12">
        <div class="bg-white rounded-xl p-8">
          <div class="flex flex-col space-y-4">
            <div
              class="relative flex flex-col justify-between overflow-hidden rounded-lg
              before:absolute before:bottom-[3.5rem] before:-left-3 before:h-6 before:w-6 before:rounded-full before:bg-white
              after:absolute after:bottom-[3.5rem] after:-right-3 after:h-6 after:w-6 after:rounded-full after:bg-white">

              {{-- DETAILS --}}
              <div class="flex flex-col justify-between flex-1">
                <section class="p-5">
                  <h1 class="font-bold text-gray-600 uppercase text-2xl">Check-in Details</h1>

                  <div class="mt-6 space-y-5 text-gray-600">
                    <div class="flex justify-between text-xl">
                      <dt class="font-medium">Name</dt>
                      <dd class="font-semibold">{{ $guest->name ?? 'N/A' }}</dd>
                    </div>

                    <div class="flex justify-between text-xl">
                      <dt class="font-medium">Contact No.</dt>
                      <dd class="font-semibold uppercase">{{ $guest->contact ?? 'N/A' }}</dd>
                    </div>

                    <div class="flex justify-between text-xl">
                      <dt class="font-medium">Room Number</dt>
                      <dd class="font-semibold">{{ $checkInDetail->room->number ?? 'N/A' }}</dd>
                    </div>

                    <div class="flex justify-between text-xl">
                      <dt class="font-medium">Check In time</dt>
                      <dd class="font-semibold">{{ $checkInDetail ? \Carbon\Carbon::parse($checkInDetail->check_in_at)->format('M d, Y g:i A') : 'N/A' }}</dd>
                    </div>

                    <div class="flex justify-between text-xl">
                      <dt class="font-medium">Initial Time</dt>
                     <dd class="font-semibold">
                        {{
                            $checkInDetail
                                ? (
                                    $checkInDetail->guest->is_long_stay
                                        ? ($checkInDetail->hours_stayed * $checkInDetail->guest->number_of_days) . ' hours'
                                        : $checkInDetail->hours_stayed . ' hours'
                                )
                                : 'N/A'
                        }}
                    </dd>
                    </div>

                    <div class="flex justify-between text-xl">
                      <dt class="font-medium">Total Extension Hours</dt>
                      <dd class="font-semibold">{{ $checkInDetail ? $extension_hours.' hours' : 'N/A' }}</dd>
                    </div>

                    <div class="flex justify-between text-xl">
                      <dt class="font-medium">Total Staying Hours</dt>
                      <dd class="font-semibold">
                        {{
                            $checkInDetail
                                ? (
                                    $checkInDetail->guest->is_long_stay
                                        ? (
                                            (($checkInDetail->hours_stayed * $checkInDetail->guest->number_of_days)
                                            + $extension_hours) . ' hours'
                                          )
                                        : ($checkInDetail->hours_stayed + $extension_hours) . ' hours'
                                  )
                                : 'N/A'
                        }}
                    </dd>
                    </div>
                  </div>
                </section>

                <section class="border-2 border-gray-400 border-dashed"></section>
              </div>

              {{-- AMOUNTS --}}
              <section class="p-5">
                <div class="space-y-4">

                  <div class="flex justify-between text-gray-700 text-xl">
                    <dt class="font-bold">Total Deposit</dt>
                    <dd class="font-semibold">&#8369;{{ $checkInDetail ? number_format($total_deposit, 2) : 0 }}</dd>
                  </div>

                  <div class="flex justify-between text-gray-700 text-xl">
                    <dt class="font-bold">Room Amount</dt>
                    <dd class="font-semibold">&#8369;{{ $checkInDetail ? number_format($room_amount, 2) : 0 }}</dd>
                  </div>

                  <div class="flex justify-between text-gray-700 text-xl">
                    <dt class="font-bold">Other Transactions</dt>
                    <dd class="font-semibold">&#8369;{{ $checkInDetail ? number_format($total_amount, 2) : 0 }}</dd>
                  </div>

                  <div class="flex justify-between text-green-600 pt-4 border-t-2 border-gray-300">
                    <dt class="font-bold text-3xl">Total</dt>
                    <dd class="font-bold text-3xl">
                      &#8369;{{ $checkInDetail ? number_format(($room_amount +  $total_amount), 2) : 0}}
                    </dd>
                  </div>
                </div>
              </section>

            </div>
          </div>
        </div>
      </div>

    </div>
  </div>

  <div class="mt-10 px-4">
    <div class="flex justify-center">
        <button
           x-on:confirm="{
            title: 'Confirm Check-Out',
            description: 'Are you sure you want to check-out? Please make sure all the details are correct before confirming.',
            icon: 'warning',
            method: 'confirmCheckOut',
            }"
           wire:loading.attr="disabled"
           wire:loading.class="opacity-50 cursor-not-allowed"
          class="font-bold text-3xl px-16 py-6 text-white bg-green-600 hover:bg-green-700 active:bg-green-800 rounded-2xl flex items-center justify-center gap-4 min-w-[320px] shadow-lg transition-all">
          <span wire:loading.remove wire:target="confirmCheckOut">CONFIRM CHECK-OUT</span>
          <span wire:loading wire:target="confirmCheckOut">PROCESSING...</span>
          <svg xmlns="http://www.w3.org/2000/svg"
              class="w-10 h-10"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
              wire:loading.remove
              wire:target="confirmCheckOut">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M5 13l4 4L19 7" />
          </svg>
        </button>
    </div>
  </div>
</div>
