<div class="p-6">
    <h2 class="text-xl font-semibold text-gray-800 mb-6">Guest Information</h2>

    <div class="grid grid-cols-4 md:grid-cols-4 gap-6">
        {{-- Left: Guest Details --}}
        <div class="space-y-4 col-span-1text-sm text-gray-700">
            <div>
                <label class="block font-medium mb-1 mt-10">QR Code</label>
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
        </div>

        {{-- Right: Billing Details --}}
        <div class="border rounded-md  col-span-3 bg-gray-50 p-4 shadow-sm text-sm text-gray-700">

            {{-- <h3 class="text-lg font-semibold text-gray-700 mb-4">Billing Statement</h3> --}}
            <div class=" w-full text-lg mb-2">
                <div class="flex space-x-5 text-lg my-2 w-full">
                    <div class="w-full">
                        <x-native-select label="Type" wire:model="selected_type_id">
                            <option selected hidden>Select One</option>
                            @forelse ($types as $type)
                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                            @empty
                            <option>No Type Available</option>
                            @endforelse
                        </x-native-select>
                    </div>
                    <div class="w-full">
                        <x-native-select label="Floor" wire:model="selected_floor_id">
                            <option selected hidden>Select One</option>
                            @forelse ($floors as $floor)
                                <option value="{{ $floor->id }}">{{ $floor->numberWithFormat() }}</option>
                            @empty
                                <option>No Floor Available</option>
                            @endforelse
                        </x-native-select>
                    </div>
                </div>
                <div class="flex space-x-5 text-lg my-2 w-full">
                    <div class="w-full">
                @if (!$this->enabled)
                <x-native-select disabled label="Room">
                    <option selected>No Room Available</option>
                  </x-native-select>
                @else
                <x-native-select label="Room" wire:model="selected_room_id">
                    <option selected hidden>Select One</option>
                    @forelse ($rooms as $room)
                      <option value="{{ $room->id }}">{{ $room->numberWithFormat() }}</option>
                    @empty
                      <option>No Room Available</option>
                    @endforelse
                  </x-native-select>
                @endif
                    </div>
                    <div class="w-full">
                        @if(!$this->enabled)
                         <x-native-select disabled label="Status">
                            <option selected>No Room Available</option>
                         </x-native-select>
                        @else
                        <x-native-select label="Status" wire:model="selected_status">
                            <option selected hidden>Select One</option>
                            <option value="Uncleaned">Uncleaned</option>
                            <option value="Cleaned">Cleaned</option>
                        </x-native-select>
                        @endif
                    </div>
                </div>
                <div class="w-full">
                        @if(!$this->enabled)
                         <x-native-select disabled label="Reason">
                            <option selected>No Room Available</option>
                         </x-native-select>
                        @else
                        <x-native-select label="Reason" wire:model="selected_reason">
                            <option selected hidden>Select One</option>
                        @forelse ($reasons as $reason)
                        <option value="{{ $reason->id }}">{{ $reason->reason }}</option>
                        @empty
                        <option>No Reason Available</option>
                        @endforelse
                        </x-native-select>
                        @endif
                    </div>
                @if($guest->transferTransactions()->count() == 2)
                <div class="bg-red-100 text-red-800 p-2 rounded-md mt-4 flex justify-between items-center">
                    <p class="text-sm font-medium">Note: The guest have already transferred rooms twice and cannot be transferred further.</p>
                    <button class="text-sm font-medium text-red-100 hover:text-red-200 hover:bg-red-800 bg-red-600 px-3 rounded-sm" wire:click="overrideTransfer">Override</button>
                </div>
                @elseif($guest->extendTransactions()->count() > 0)
                <div class="bg-red-100 text-red-800 p-2 rounded-md mt-4 flex justify-between items-center">
                    <p class="text-sm font-medium">Note: The guest have already extended their stay and cannot be transferred.</p>
                    <button class="text-sm font-medium text-red-100 hover:text-red-200 hover:bg-red-800 bg-red-600 px-3 rounded-sm" wire:click="overrideTransfer">Override</button>
                </div>
                @elseif($guest->is_long_stay)
                  <div class="bg-blue-100 text-blue-800 p-2 rounded-md mt-4 flex justify-between items-center">
                    <p class="text-sm font-medium">Note: This is a long stay transaction. Calculation of rates are multiplied by the number of days stayed. ({{ $guest->number_of_days }} days)</p>
                </div>
                @endif
                <div class="flex justify-between text-xl my-2 mt-5">
                    <span class="text-gray-600">Current Room Rate:</span>
                <span class="text-gray-800 font-medium">&#8369; {{ number_format($current_room_rate ?? 0, 2) }}</span>
                </div>
                <div class="flex justify-between text-xl my-2">
                    <span class="text-gray-600">New Room Rate:</span>
                <span class="text-gray-800 font-medium">&#8369; {{ number_format($new_room_rate  ?? 0, 2) }}</span>
                </div>
                <div class="flex justify-between text-xl my-2">
                    <span class="text-gray-600">Excess Amount:</span>
                <span class="text-gray-800 font-medium">&#8369; {{ number_format($excess_amount ?? 0, 2) }}</span>
                </div>
            </div>

           <hr class="my-2 border-dashed border-gray-600">

           <div class="flex justify-between text-4xl font-semibold text-gray-800 mt-8 mb-4">
                <span>Payable Amount:</span>
                <span>&#8369; {{ number_format($payable_amount, 2) }}</span>
            </div>
        </div>
    </div>

    <div class="flex justify-end mt-6 space-x-2">
        <div class="flex justify-between items-center w-full">
            <div>
            </div>
            <div class="flex space-x-2">
                <button wire:click="cancelTransfer" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-300 focus:ring-opacity-50">
                    Cancel
                </button>
                @if($guest->transferTransactions()->count() < 2 && $guest->extendTransactions()->count() == 0)
                <button wire:click="confirmTransfer" class="px-4 py-2 bg-[#1877F2] text-white rounded-md hover:bg-[#5194ec] focus:outline-none focus:ring-2 focus:ring-[#1877F2] focus:ring-opacity-50">
                    Save & Pay
                </button>
                @endif
            </div>
        </div>
    </div>

    {{-- modal for submission --}}
    <x-modal wire:model.defer="save_pay_modal" align="center">
      <x-card>
        <div>
          <div class="header flex space-x-1 border-b items-end justify-between py-0.5">
            <h2 class="text-lg uppercase text-gray-600 font-bold">Confirmation</h2>
          </div>
          <div class="mt-3">
            <div class="space-y-4">
              <dl class="mt-8 p-2 divide-y divide-gray-400 text-sm lg:col-span-5 lg:mt-0">
                <div class="flex justify-between py-2">
                    <div class="w-full divide-y divide-gray-400 ">
                        <div class="flex justify-between items-center py-2">
                            <dt class="text-gray-600 text-2xl font-bold">Excess Amount:</dt>
                            <dd class="text-gray-800 text-2xl font-bold">&#8369; {{ number_format($excess_amount, 2) }}</dd>
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
              <x-button emerald label="Confirm" wire:click="saveTransfer" />
          </div>
        </x-slot>
      </x-card>
    </x-modal>

    {{-- modal for authorization code (legacy system) --}}
      <x-modal wire:model.defer="authorization_modal" align="center" max-width="md">
    <x-card>
      <div class="flex space-x-1">
        <h1 class=" text-xl font-bold text-gray-600">AUTHORIZATION CODE</h1>
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-5 h-5 fill-green-600">
          <path fill="none" d="M0 0h24v24H0z" />
          <path d="M17 14h-4.341a6 6 0 1 1 0-4H23v4h-2v4h-4v-4zM7 14a2 2 0 1 0 0-4 2 2 0 0 0 0 4z" />
        </svg>
      </div>
      <div class="mt-7">
        <input type="password" wire:model="code"
          class="w-full text-lg
      @error('code')
          border-red-500
      @enderror
        rounded-lg">
      </div>
      @error('code')
        <span class="text-sm text-red-500 mt-1">{{ $message }}</span>
      @enderror
      <div class="mt-5 flex justify-end items-center space-x-2">
        <x-button x-on:click="close" label="CANCEL" sm negative />
        <x-button label="PROCEED" sm positive wire:click="confirmTransfer" spinner="confirmTransfer" />

      </div>

    </x-card>
  </x-modal>

  {{-- Modal for Override Request (Supervisor System) - EXACT UI --}}
  <x-modal wire:model.defer="override_request_modal" align="center" max-width="2xl" blur="md">
    <div class="bg-white rounded-lg overflow-hidden">
      {{-- Red Header --}}
      <div class="bg-red-600 px-6 py-4">
        <h2 class="text-white text-xl font-bold">REVIEW OVERRIDE</h2>
        <p class="text-white text-sm font-medium">TRANSFER ROOM</p>
      </div>

      {{-- Content --}}
      <div class="p-6">
        <div class="flex gap-6">
          {{-- Left Column --}}
          <div class="flex-1 space-y-4">
            {{-- Requester --}}
            <div>
              <label class="block text-xs text-gray-500 mb-1">Requester:</label>
              <div class="bg-gray-100 rounded px-3 py-2 text-sm font-medium text-gray-700">
                {{ strtoupper(auth()->user()->name) }}
              </div>
            </div>

            {{-- Transaction Type --}}
            <div>
              <label class="block text-xs text-gray-500 mb-1">Transaction Type:</label>
              <div class="bg-gray-100 rounded px-3 py-2 text-sm font-medium text-gray-700">
                Transfer room
              </div>
            </div>

            {{-- Reason/s --}}
            <div>
              <label class="block text-xs text-gray-500 mb-1">Reason/s:</label>
              <div class="bg-gray-100 rounded px-3 py-2 text-sm font-medium text-gray-700 min-h-[80px]">
                @if($selected_reason)
                  {{ strtoupper(\App\Models\TransferReason::find($selected_reason)?->reason ?? 'N/A') }}
                @else
                  N/A
                @endif
              </div>
            </div>

            {{-- Supervisor Dropdown --}}
            <div>
              <label class="block text-xs text-gray-500 mb-1">Supervisor: <span class="text-red-500">*</span></label>
              <select wire:model="selected_supervisor_id"
                class="w-full bg-gray-100 border-0 rounded px-3 py-2 text-sm font-medium text-gray-700 focus:ring-2 focus:ring-red-500">
                <option value="">Select supervisor</option>
                @foreach($supervisors as $supervisor)
                  <option value="{{ $supervisor->id }}">{{ strtoupper($supervisor->name) }}</option>
                @endforeach
              </select>
              @error('selected_supervisor_id')
                <span class="text-xs text-red-500 mt-1">{{ $message }}</span>
              @enderror
            </div>
          </div>

          {{-- Right Column - Room Numbers --}}
          <div class="w-32 flex flex-col items-center justify-center">
            <label class="text-xs text-gray-500 mb-1">Transfer From Room #:</label>
            <div class="text-5xl font-bold text-gray-800 mb-4">
              {{ $room->number ?? '0' }}
            </div>

            {{-- Arrow Down --}}
            <div class="text-gray-400 my-2">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
              </svg>
            </div>

            <label class="text-xs text-gray-500 mb-1">To:</label>
            <div class="text-5xl font-bold text-red-500">
              @if($selected_room_id)
                {{ \App\Models\Room::find($selected_room_id)?->number ?? '0' }}
              @else
                0
              @endif
            </div>
          </div>
        </div>

        {{-- Buttons --}}
        <div class="flex gap-3 mt-6">
          <button wire:click="submitOverrideRequest"
            class="flex-1 bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-6 rounded transition-colors">
            REQUEST OVERRIDE
          </button>
          <button x-on:click="close"
            class="bg-gray-800 hover:bg-gray-900 text-white font-bold py-3 px-6 rounded transition-colors">
            CANCEL
          </button>
        </div>
      </div>
    </div>
  </x-modal>

  {{-- Modal for Request Submitted Success --}}
  <x-modal wire:model.defer="request_submitted_modal" align="center" max-width="md">
    <x-card>
      <div class="text-center py-6">
        <div class="bg-green-100 rounded-full p-4 w-20 h-20 mx-auto mb-4 flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
        </div>
        <h3 class="text-xl font-bold text-gray-700 mb-2">Request Submitted!</h3>
        <p class="text-gray-500 mb-4">Your override request has been sent to the supervisor.</p>
        <p class="text-sm text-gray-400">Check the <strong>Transfer Requests</strong> page for status updates.</p>
      </div>

      <x-slot name="footer">
        <div class="flex justify-center space-x-2">
          <x-button primary label="Go to Transfer Requests" href="{{ route('frontdesk.override-requests') }}" />
          <x-button flat label="Stay Here" x-on:click="close" />
        </div>
      </x-slot>
    </x-card>
  </x-modal>
</div>
