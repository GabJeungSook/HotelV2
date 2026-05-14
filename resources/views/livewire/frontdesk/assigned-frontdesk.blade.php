<div>
  <div class="assigned">
    <ul role="list" class="divide-y divide-gray-200" x-animate>
      @forelse ($get_frontdesk as $frontdesk)
        @php
          $frontdesk = \App\Models\Frontdesk::where('id', $frontdesk)->first();
        @endphp
        <li class="flex py-2">
          <x-avatar md label="{{ $frontdesk->name[0] . '' . $frontdesk->name[1] }}" class="uppercase" />
          <div class="ml-3">
            <p class="text-sm font-medium text-gray-900">{{ $frontdesk->name }}</p>
            <p class="text-sm text-gray-500">{{ $frontdesk->number }}</p>
          </div>
          <div class="ml-auto">
            <div class="ml-3">
            <p class="text-sm font-medium text-gray-900 text-right">SHIFT</p>
            <p class="text-sm font-semibold text-gray-800 text-right">{{ $shift }}</p>
            <p class="text-sm font-semibold text-gray-800 text-right">{{ now()->format('Y-m-d H:i:s') }}</p>
          </div>
          </div>
        </li>
      @empty
        <div>Please assign a frontdesk...</div>
      @endforelse
    </ul>
    <div class="border-t pt-3 mt-2">
      <div class="flex items-center justify-between mb-2">
        <p class="text-sm font-semibold text-gray-700">Select Shift</p>
        <p class="text-xs text-gray-500">Default based on current time — you can override</p>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <button type="button" wire:click="$set('shift', 'AM')"
          class="flex flex-col items-center justify-center rounded-lg border-2 p-4 transition focus:outline-none
            {{ $shift === 'AM' ? 'border-blue-600 bg-blue-50 text-blue-700 ring-2 ring-blue-300 shadow' : 'border-gray-300 bg-white text-gray-500 hover:border-blue-400 hover:bg-blue-50' }}">
          <span class="text-2xl font-extrabold tracking-wide">AM</span>
          <span class="mt-1 text-[11px] font-medium">Morning Shift</span>
        </button>
        <button type="button" wire:click="$set('shift', 'PM')"
          class="flex flex-col items-center justify-center rounded-lg border-2 p-4 transition focus:outline-none
            {{ $shift === 'PM' ? 'border-indigo-600 bg-indigo-50 text-indigo-700 ring-2 ring-indigo-300 shadow' : 'border-gray-300 bg-white text-gray-500 hover:border-indigo-400 hover:bg-indigo-50' }}">
          <span class="text-2xl font-extrabold tracking-wide">PM</span>
          <span class="mt-1 text-[11px] font-medium">Evening Shift</span>
        </button>
      </div>
    </div>
    <div class="flex justify-between items-center border-t pt-2 mt-3">
       <x-native-select wire:model="drawer">
            <option selected hidden>Select Cash Drawer</option>
            @foreach ($cash_drawers as $drawer)
                <option value="{{ $drawer->id }}">{{ $drawer->name }}</option>
            @endforeach
        </x-native-select>
      <div class="flex gap-x-2">
        <x-button label="Log out" sm negative wire:click="logoutCurrentUser" spinner="logoutCurrentUser" />
        @if (collect($this->get_frontdesk)->count() > 0)
          <x-button label="Save" sm positive right-icon="save-as" x-on:confirm="{
          title: 'Are you sure?',
          description      : 'You want to save assigned frontdesk',
          icon: 'warning',
          method: 'saveFrontdesk'
      }" />
        @endif
      </div>
    </div>
  </div>
  <div class="mt-10">
    {{-- <div>
      <h1 class="border-b-2 font-bold text-green-600 uppercase">Frontdesk List</h1>
      <div class="mt-6 flow-root">
        <ul role="list" class="-my-5 divide-y divide-gray-200">
          @forelse ($frontdesks as $frontdesk)
            <li class="py-3">
              <div class="flex items-center space-x-4">
                <div class="flex-shrink-0">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-8 fill-green-600">
                    <path fill="none" d="M0 0h24v24H0z" />
                    <path d="M5 20h14v2H5v-2zm7-2a8 8 0 1 1 0-16 8 8 0 0 1 0 16z" />
                  </svg>
                </div>
                <div class="min-w-0 flex-1">
                  <p class="truncate text-sm font-medium text-gray-900">{{ $frontdesk->name }}</p>
                  <p class="truncate text-sm text-gray-500">{{ $frontdesk->number }}</p>
                </div>
                <div>
                  <x-button rounded label="Assign" wire:click="assignFrontdesk({{ $frontdesk->id }})"
                    spinner="assignFrontdesk({{ $frontdesk->id }})" slate sm right-icon="arrow-narrow-right" />
                </div>
              </div>
            </li>
          @empty
            <div>No frontdesk available</div>
          @endforelse

        </ul>
      </div>

    </div> --}}

  </div>

  {{-- Shift Capacity Reached Modal (non-dismissable) --}}
  @if($showExistingSessionModal)
  <div class="fixed inset-0 z-50 overflow-y-auto" x-data>
    {{-- Backdrop --}}
    <div class="fixed inset-0 bg-secondary-400 bg-opacity-60 transition-opacity"></div>

    {{-- Modal content --}}
    <div class="w-full min-h-full flex items-center justify-center p-4">
      <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6">
        <div class="text-center">
          <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-red-600">
              <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" />
            </svg>
          </div>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Shift Full</h3>
          <p class="text-sm text-gray-600 mb-3">
            This shift already has 2 frontdesk users. Please wait for the current shift to end or contact your manager.
          </p>
          @if(count($shiftUsers) > 0)
          <div class="space-y-2 mt-3">
            <p class="text-xs font-semibold text-gray-500 uppercase">Currently Logged In</p>
            @foreach($shiftUsers as $user)
              <div class="flex items-center justify-between bg-gray-50 rounded-lg px-4 py-2">
                <span class="text-sm font-medium text-gray-800">{{ $user['name'] }}</span>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
              </div>
            @endforeach
          </div>
          @endif
        </div>

        <div class="flex justify-center mt-6">
          <x-button
            negative
            label="Log out"
            wire:click="logoutCurrentUser"
            spinner="logoutCurrentUser"
          />
        </div>
      </div>
    </div>
  </div>
  @endif

  <x-modal wire:model.defer="partner_modal" max-width="sm" align="center">
    <x-card title="Partner's Name">

      <x-input label="Name" placeholder="enter name" wire:model.defer="name" />

      <x-slot name="footer">
        <div class="flex justify-end gap-x-4">
          <x-button flat label="Cancel" x-on:click="close" />
          <x-button positive label="Save and Proceed" right-icon="arrow-right" wire:click="savePartner"
            spinner="savePartner" />
        </div>
      </x-slot>
    </x-card>
  </x-modal>
</div>
