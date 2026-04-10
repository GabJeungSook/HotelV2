<div class="max-w-full mx-auto">
    <!-- CARD WRAPPER -->
    <div class="bg-white rounded-xl shadow-md p-8 space-y-10">

        <!-- ========================= -->
        <!-- GUEST INFORMATION SECTION -->
        <!-- ========================= -->
        <div>
            <h2 class="text-lg font-semibold text-gray-700 mb-4">GUEST INFORMATION</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Guest Name -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Name
                    </label>
                    <input type="text"
                           wire:model.defer="name"
                           class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500"
                           placeholder="Enter guest name">
                    @error('name')
                    <span class="text-sm text-red-600">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Contact Number -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Contact Number
                    </label>
                    <div class="relative">
                        <input type="number"
                               wire:model="contact_number"
                               class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500"
                               placeholder="09XXXXXXXXX">
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================== -->
        <!-- CHECK-IN INFORMATION SECTION -->
        <!-- ============================== -->
        <div>
            <h2 class="text-lg font-semibold text-green-700 mb-4">CHECK-IN INFORMATION</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                <!-- Room Type -->
                <div>
                    <x-native-select label="Room Type" wire:model="type_id">
                        <option hidden selected>Select Room Type</option>
                            @foreach ($types as $type)
                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                            @endforeach
                    </x-native-select>
                    @error('type_id')
                    @enderror
                </div>

                <!-- Room -->
                <div>
                    <x-native-select label="Room " wire:model="room_id">
                        <option hidden selected>Select Room </option>
                            @if($type_id)
                                 @foreach ($rooms as $room)
                                <option value="{{ $room->id }}">{{ $room->numberWithFormat() }}</option>
                            @endforeach
                            @else
                             <option value="" disabled>Select Room Type First</option>
                            @endif
                    </x-native-select>
                    @error('room_id')
                    @enderror
                </div>

                <!-- Rate -->
                <div>
                     <x-native-select label="Rate " wire:model="rate_id">
                     <option hidden selected>Select Rate</option>
                        @if($room_id)
                        @foreach ($rates as $rate)
                        <option value="{{ $rate->id }}">
                            {{ $rate->stayingHour->number . ' Hours - ₱' . number_format($rate->amount, 2) }}</option>
                        @endforeach
                         @else
                             <option value="" disabled>Select Room First</option>
                        @endif
                    </x-native-select>
                    @error('rate_id')
                    @enderror
                </div>

                <!-- Long Stay -->
                {{-- <div class="flex items-center mt-6">
                    <input type="checkbox"
                           wire:model="long_stay"
                           class="h-5 w-5 rounded border-gray-300 text-green-600 focus:ring-green-500">
                    <label class="ml-2 text-sm text-gray-700">Long Stay</label>
                </div> --}}

            </div>
        </div>

        <!-- ========================= -->
        <!-- BUTTONS -->
        <!-- ========================= -->
        <div class="flex justify-end space-x-4 pt-4 border-t">
            <button
                wire:click="redirectBack"
                class="px-6 py-2 rounded-lg text-gray-600 hover:bg-gray-100 transition">
                Cancel
            </button>

            <button x-on:confirm="{
                    title: 'Are you sure?',
                    body: 'You are about to save this Check-In C/O guest.',
                    icon: 'warning',
                    method: 'saveCheckInCO'
                }"
                spinner="saveCheckInCO"
                class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 shadow transition flex items-center gap-2">
                Save

                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5"
                     fill="none" viewBox="0 0 24 24"
                     stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M5 13l4 4L19 7" />
                </svg>
            </button>
        </div>
    </div>
</div>
