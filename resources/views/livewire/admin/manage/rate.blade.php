<div>
  <div class="bg-white p-4 rounded-xl shadow-sm">

    {{-- Header: Buttons + Branch selector --}}
    <div class="flex flex-wrap justify-between items-end gap-4 mb-4">
      <div class="flex flex-wrap gap-2">
        <x-button wire:click="openRateModal" icon="currency-dollar" blue label="Set Rates"
          spinner="openRateModal" />
        <x-button wire:click="openAddHour" icon="plus" slate label="Add Staying Hour"
          spinner="openAddHour" />
      </div>

      <div class="flex flex-wrap items-end gap-3">
        {{-- Search --}}
        <div>
          <label class="block text-xs font-medium text-gray-500 mb-1">Search Room</label>
          <input type="text" wire:model.debounce.300ms="search" placeholder="Room #..."
            class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm w-36 focus:ring-blue-500 focus:border-blue-500" />
        </div>

        {{-- Type filter --}}
        <div>
          <label class="block text-xs font-medium text-gray-500 mb-1">Type</label>
          <select wire:model="filter_type_id"
            class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm w-40 focus:ring-blue-500 focus:border-blue-500">
            <option value="">All Types</option>
            @foreach ($types as $type)
              <option value="{{ $type->id }}">{{ $type->name }}</option>
            @endforeach
          </select>
        </div>

        {{-- Floor filter --}}
        <div>
          <label class="block text-xs font-medium text-gray-500 mb-1">Floor</label>
          <select wire:model="filter_floor_id"
            class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm w-32 focus:ring-blue-500 focus:border-blue-500">
            <option value="">All Floors</option>
            @foreach ($floors as $floor)
              <option value="{{ $floor->id }}">Floor {{ $floor->number }}</option>
            @endforeach
          </select>
        </div>

        {{-- Branch selector (superadmin only) --}}
        @if(auth()->user()->hasRole('superadmin'))
        <div>
          <x-native-select label="Branch" wire:model="branch_id">
            <option selected hidden>Select Branch</option>
            @foreach ($branches as $branch)
              <option value="{{ $branch->id }}">{{ $branch->name }}</option>
            @endforeach
          </x-native-select>
        </div>
        @endif
      </div>
    </div>

    {{-- Branch name display --}}
    @if(auth()->user()->hasRole('superadmin'))
    <div class="mb-4 text-xl font-semibold text-gray-600">
      {{ $branch_name ?? 'No Branch Selected' }}
    </div>
    @endif

    {{-- Selection count --}}
    @if(count($selected_rooms) > 0)
    <div class="mb-3 text-sm text-blue-600 font-medium">
      {{ count($selected_rooms) }} of {{ $rooms->count() }} room(s) selected
    </div>
    @endif

    {{-- Rooms table with rates --}}
    <div class="overflow-x-auto border border-gray-200 rounded-lg">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-3 py-3 text-left">
              <input type="checkbox" wire:model="select_all"
                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
            </th>
            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Room #</th>
            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Type</th>
            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Floor</th>
            @foreach ($stayingHours as $sh)
            <th class="px-3 py-3 text-center text-xs font-semibold text-gray-600 uppercase">{{ $sh->number }}h</th>
            @endforeach
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          @forelse ($rooms as $room)
          <tr class="{{ in_array((string)$room->id, $selected_rooms) ? 'bg-blue-50' : 'hover:bg-gray-50' }} transition-colors">
            <td class="px-3 py-2">
              <input type="checkbox" wire:model="selected_rooms" value="{{ $room->id }}"
                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
            </td>
            <td class="px-3 py-2 text-sm font-medium text-gray-900">{{ $room->number }}</td>
            <td class="px-3 py-2 text-sm text-gray-600">{{ $room->type->name ?? '—' }}</td>
            <td class="px-3 py-2 text-sm text-gray-600">{{ $room->floor->number ?? '—' }}</td>
            @foreach ($stayingHours as $sh)
              @php
                $rate = $room->rates->firstWhere('staying_hour_id', $sh->id);
              @endphp
              <td class="px-3 py-2 text-center text-sm {{ $rate ? 'text-gray-900 font-medium' : 'text-gray-300' }}">
                {{ $rate ? '₱' . number_format($rate->amount, 2) : '—' }}
              </td>
            @endforeach
          </tr>
          @empty
          <tr>
            <td colspan="{{ 4 + $stayingHours->count() }}" class="px-3 py-8 text-center text-sm text-gray-400">
              @if(!$branch_id)
                Select a branch to view rooms.
              @else
                No rooms found.
              @endif
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- Set Rates Modal --}}
  <x-modal wire:model.defer="rate_modal" align="center" max-width="lg">
    <x-card title="Set Rates for {{ count($selected_rooms) }} Room(s)">
      <div class="flex flex-col space-y-3">
        <p class="text-sm text-gray-500">Enter the rate amount for each staying hour. Leave blank to skip.</p>
        @foreach ($rate_amounts as $key => $entry)
        <div class="flex items-center gap-3">
          <label class="w-24 text-sm font-medium text-gray-700">{{ $entry['hours'] }} hours</label>
          <input type="number" wire:model="rate_amounts.{{ $key }}.amount" min="0" step="1"
            placeholder="₱ Amount"
            class="flex-1 border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-blue-500 focus:border-blue-500" />
        </div>
        @endforeach
      </div>
      <x-slot name="footer">
        <div class="flex justify-end gap-x-3">
          <x-button flat label="Cancel" x-on:click="close" />
          <x-button positive spinner="applyRates" wire:click="applyRates" label="Apply Rates" />
        </div>
      </x-slot>
    </x-card>
  </x-modal>

  {{-- Add Staying Hour Modal --}}
  <x-modal wire:model.defer="add_staying_hour_modal" align="center" max-width="lg">
    <x-card title="Add New Staying Hour">
      <div class="flex flex-col space-y-3">
        <x-input wire:model.defer="number" label="Number of Hours" type="number" min="1" placeholder="e.g. 6" />
      </div>
      <x-slot name="footer">
        <div class="flex justify-end gap-x-3">
          <x-button flat label="Cancel" x-on:click="close" />
          <x-button positive spinner="saveStayingHour" wire:click="saveStayingHour" label="Save" />
        </div>
      </x-slot>
    </x-card>
  </x-modal>
</div>
