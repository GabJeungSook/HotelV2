<div class="p-6">
    <h2 class="text-xl font-semibold text-gray-800 mb-6">Check In Information</h2>

    <div class="grid grid-cols-4 md:grid-cols-4 gap-6">
        {{-- Left: Guest Details --}}
        <div class="space-y-4 col-span-1 text-sm text-gray-700">
            <div>
                <label class="block font-medium mb-1">QR Code</label>
               <div class="p-2 bg-gray-100 rounded-md">{{$guest->qr_code}}</div>
            </div>
            <div>
                <label class="block font-medium mb-1">Name</label>
                <div class="p-2 bg-gray-100 rounded-md">{{$guest->name}}</div>
            </div>
            <div>
                <label class="block font-medium mb-1">Contact Number</label>
                <div class="p-2 bg-gray-100 rounded-md">{{ $guest->contact == 'N/A' ? 'N/A' : '09' . $guest->contact }}</div>
            </div>
            <div>
                <label class="block font-medium mb-1">Room Number</label>
                <div class="p-2 bg-gray-100 rounded-md">{{ $room->number }}</div>
            </div>
            @if ($guest->is_long_stay)
            <div>
                <label class="block font-medium mb-1">Days</label>
                <div class="p-2 bg-gray-100 rounded-md">{{ $guest->number_of_days }}</div>
            </div>
            @else
            <div>
                <label class="block font-medium mb-1">Staying Hours</label>
                <div class="p-2 bg-gray-100 rounded-md">{{ $stayingHour?->number }}</div>
            </div>
            @endif
        </div>

        {{-- Right: Billing Details --}}
        <div class="border rounded-md col-span-3 bg-gray-50 p-4 shadow-sm text-sm text-gray-700">
            <h3 class="text-lg font-semibold text-gray-700 mb-4">Billing Statement</h3>

            <div class="flex justify-between text-xl mb-2">
                <span class="text-gray-600">Room Rate:</span>
                <span class="text-gray-800 font-medium">₱ {{ number_format($guest->static_amount, 2) }}</span>
            </div>
            <div class="flex justify-between text-xl mb-2">
                <span class="text-gray-600">Additional Charges:</span>
                <span class="text-gray-800 font-medium">₱ {{ number_format($additional_charges, 2) }}</span>
            </div>
             @if($has_discount)
            <div class="flex justify-between text-xl mb-2">
                <span class="text-red-600">Discount: (Senior & PWD)</span>
                <span class="text-red-600 font-medium">– ₱ {{ number_format($discount_amount, 2) }}</span>
            </div>
            @endif
            <hr class="my-2">

            <div class="flex justify-between text-3xl font-semibold text-gray-800 mb-4">
                <span>Total:</span>
                <span>₱ {{ number_format($total, 2) }}</span>
            </div>

            <div class="mt-20 flex items-center justify-between gap-6">
    <!-- Label -->
    <label class="font-semibold text-xl whitespace-nowrap">
        Amount Paid
    </label>

    <!-- Input -->
    <div class="relative w-1/2">
        <span class="absolute left-0 top-1/2 -translate-y-1/2 text-gray-700 font-semibold text-3xl">
            ₱
        </span>
<input
    wire:model.defer="amountPaid"
    type="number"
    autofocus
    min="0"
    placeholder="0.00"
    class="
        w-full
        text-right
        text-3xl
        font-semibold
        bg-transparent
        border-0
        border-b-2
        border-gray-400
        focus:border-blue-600
        focus:outline-none
        focus:ring-0
        focus:shadow-none
        appearance-none

        pl-8
        pb-1
    "
/>
    </div>
</div>

        </div>
    </div>

    <div class="flex justify-end mt-6 space-x-2">
        <div class="flex justify-between items-center w-full">
            <div>
                @if ($rate->has_discount)
                <label class="inline-flex items-center">
                    <input type="checkbox" wire:model="has_discount" class="form-checkbox rounded text-[#1877F2] focus:ring-[#1877F2] border-gray-300" />
                    <span class="ml-2 text-sm text-gray-700">Grant Discount</span>
                </label>
                @endif

            </div>
            <div class="flex space-x-2">
                <button wire:click="cancelCheckIn" class="px-4 py-2 bg-red-500 text-gray-50 rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-opacity-50">
                    Cancel
                </button>
                <button wire:click="proceedCheckIn" wire:loading.attr="disabled" wire:target="proceedCheckIn" class="px-4 py-2 bg-[#10b981] text-white rounded-md hover:bg-[#0ca06f] focus:outline-none focus:ring-2 focus:ring-[#10b981] focus:ring-opacity-50 disabled:opacity-50 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="proceedCheckIn">Check-In</span>
                    <span wire:loading wire:target="proceedCheckIn">Processing...</span>
                </button>
            </div>
        </div>
    </div>

    {{-- modal for submission --}}
    <x-modal wire:model.defer="change_modal" align="center">
      <x-card>
        <div>
          <div class="header flex space-x-1 border-b items-end justify-between py-0.5">
            <h2 class="text-lg uppercase text-gray-600 font-bold">Confirmation</h2>
          </div>
          <div class="mt-3">
            <div class="space-y-4">
              <dl class="mt-8 p-2 divide-y divide-gray-400 text-sm lg:col-span-5 lg:mt-0">
                {{-- change: $excess_amount --}}
                <div class="flex justify-between py-2">
                    <div class="w-full divide-y divide-gray-400 ">
                        {{-- <div class="flex justify-between items-center py-2">
                            <dt class="text-gray-600 text-lg font-bold">Amount Paid:</dt>
                            <dd class="text-gray-800 text-lg font-bold">₱ {{ number_format($amountPaid, 2) }}</dd>
                        </div> --}}
                        <div class="flex justify-between items-center py-2">
                            <dt class="text-gray-600 text-2xl font-bold">Excess Amount:</dt>
                            <dd class="text-gray-800 text-2xl font-bold">₱ {{ number_format($excess_amount, 2) }}</dd>
                        </div>
                    </div>
                </div>
                 {{-- add checkbox for save excess --}}
                    <div class="flex items-center">
                        <label class="inline-flex flex-col items-start pt-5">
                            <span class="flex items-center">
                                <input type="checkbox" wire:model="save_excess" class="form-checkbox rounded text-[#1877F2] focus:ring-[#1877F2] border-gray-300" />
                                <span class="ml-2 text-lg text-gray-700">Save excess amount</span>
                            </span>
                            <span class="text-xs text-gray-600 mt-1 ml-6">Save this amount as a deposit to the guest's account.</span>
                        </label>
                    </div>
              </dl>
            </div>
          </div>
        </div>

        <x-slot name="footer">
          <div class="flex justify-end s gap-x-2">
              <x-button red label="Close" x-on:click="close" />
              <x-button emerald label="Check-In" wire:click="saveCheckIn" wire:loading.attr="disabled" spinner="saveCheckIn" />
          </div>
        </x-slot>
      </x-card>
    </x-modal>
</div>
