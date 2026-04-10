{{-- <div>

  <div class="mt-5">

    <div class="">
      <div class="sm:flex sm:items-center ">
        <div class="sm:flex-auto">
          <div class="search flex items-center rounded-lg  px-3 py-1 w-72 border border-gray-200 shadow-sm">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="fill-gray-500" width="24" height="24">
              <path fill="none" d="M0 0h24v24H0z" />
              <path
                d="M11 2c4.968 0 9 4.032 9 9s-4.032 9-9 9-9-4.032-9-9 4.032-9 9-9zm0 16c3.867 0 7-3.133 7-7 0-3.868-3.133-7-7-7-3.868 0-7 3.132-7 7 0 3.867 3.132 7 7 7zm8.485.071l2.829 2.828-1.415 1.415-2.828-2.829 1.414-1.414z" />
            </svg>
            <input type="text" wire:model="search"
              class="outline:none  h-8 focus:ring-0 flex-1 border-0 focus:border-0" placeholder="Search">
          </div>
        </div>
        <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
          <x-button wire:click="$set('add_modal', true)" icon="plus" slate label="Add New" />
        </div>
      </div>
      <div class="mt-5 flex flex-col">
        <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-6 lg:-mx-8">
          <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
            <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
              <table class="min-w-full">
                <thead class="bg-gray-500">
                  <tr>
                    <th scope="col"
                      class=" w-64 py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-white sm:pl-6">

                    </th>
                    <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-white sm:pl-6">
                      STAYING HOUR
                    </th>
                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-white">AMOUNT</th>
                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-white">TYPE</th>
                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-white">STATUS</th>
                    <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                      <span class="sr-only">Edit</span>
                    </th>
                  </tr>
                </thead>
                <tbody class="bg-white">
                  @forelse ($types as $key => $room)
                    <tr class="border-y border-gray-200">
                      <th colspan="6" scope="colgroup"
                        class="bg-gray-50 px-4 py-2 text-left text-sm font-semibold uppercase text-gray-700 sm:px-6">
                        Room {{ $room->number }} ({{ $room->type->name }})
                      </th>
                    </tr>

                    @forelse ($room->rates as $rate)
                      <tr class="border-t border-gray-200">
                        <td class="whitespace-nowrap w-40 py-2 pl-4 pr-3 text-sm font-medium text-gray-700 sm:pl-6">

                        </td>
                        <td class="whitespace-nowrap py-2 pl-4 pr-3 text-sm font-medium text-gray-700 sm:pl-6">
                          {{ $rate->stayingHour->number }} HOURS</td>
                        </td>
                        <td class="whitespace-nowrap px-3 py-2 text-sm text-gray-700">
                          &#8369;{{ number_format($rate->amount, 2) }}</td>
                        <td class="whitespace-nowrap px-3 py-2 text-sm text-gray-700">Room {{ $rate->room->number ?? '—' }}</td>
                        <td class="whitespace-nowrap px-3 py-2 text-sm text-gray-700">
                          @switch($rate->is_available)
                            @case(1)
                              <x-button xs icon="users" positive label="Available" />
                            @break

                            @case(2)
                              <x-button xs icon="users" negative label="Unavailable" />
                            @break

                            @default
                          @endswitch
                        </td>
                        <td class="relative whitespace-nowrap py-2 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                          <x-button icon="pencil-alt" spinner="editRate({{ $rate->id }})"
                            wire:click="editRate({{ $rate->id }})" label="Edit" xs />
                        </td>
                      </tr>
                      @empty
                        <td colspan="6" class="text-center py-2">
                          <span>No data available...</span>
                        </td>
                      @endforelse
                      @empty
                        <td colspan="6" class="text-center py-2">
                          <span>No data available...</span>
                        </td>
                      @endforelse

                      <!-- More people... -->
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>


      <x-modal wire:model.defer="add_modal" max-width="lg">
        <x-card title="Add New">
          <div class="flex flex-col space-y-2">
            <x-native-select label="Select Number of Hours" wire:model="hours_id">
              <option selected hidden>Select Number of Hours</option>
              @foreach ($stayingHours as $hour)
                <option value="{{ $hour->id }}">{{ $hour->number }} hours</option>
              @endforeach
            </x-native-select>
            <x-input wire:model.defer="amount" label="Amount" placeholder="" />

            <x-native-select label="Select Room" wire:model="room_id">
              <option selected hidden>Select Room</option>
              @foreach ($types as $room)
                <option value="{{ $room->id }}">Room {{ $room->number }} ({{ $room->type->name }}) </option>
              @endforeach
            </x-native-select>
          </div>
          <x-slot name="footer">
            <div class="flex justify-end gap-x-2">
              <x-button flat label="Cancel" x-on:click="close" />
              <x-button spinner="saveRate" wire:click="saveRate" positive label="Save" />
            </div>
          </x-slot>
        </x-card>
      </x-modal>


    </div>
  </div>
  </div> --}}

<div>
  <div class="bg-white p-4 rounded-xl">
    <div class="flex justify-between mb-5">
      @if(auth()->user()->hasRole('superadmin') && $branch_id != null)
        <div class="flex space-x-4">
            <x-button wire:click="openAddHour" icon="plus" blue label="Add New Staying Hour" />
            <x-button wire:click="openAdd" icon="plus" blue label="Add New Rate" />
        </div>
      @elseif(auth()->user()->hasRole('admin'))
       <div class="flex space-x-4"></div>
            <x-button wire:click="openAddHour" icon="plus" blue label="Add New Staying Hour" />
            <x-button wire:click="openAdd" icon="plus" blue label="Add New Rate" />
        </div>
      @else
      <div></div>
      @endif
      @if(auth()->user()->hasRole('superadmin'))
          <x-native-select label="Branch" wire:model="branch_id">
              <option selected hidden>Select Branch</option>
                @foreach ($branches as $item)
                    <option value="{{ $item->id }}">{{ $item->name }}</option>
                @endforeach
          </x-native-select>
          @endif
    </div>
    @if(auth()->user()->hasRole('superadmin'))
    <div class="my-5 text-xl font-semibold text-gray-600">
      {{$branch_name ?? 'No Branch Selected'}}
    </div>
    @endif
    {{ $this->table }}
  </div>

    <x-modal wire:model.defer="add_staying_hour_modal" align="center" max-width="lg">
    <x-card>
      <div class="header flex space-x-2 items-center">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" class="fill-gray-600">
          <path fill="none" d="M0 0h24v24H0z" />
          <path
            d="M11 11V7h2v4h4v2h-4v4h-2v-4H7v-2h4zm1 11C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10zm0-2a8 8 0 1 0 0-16 8 8 0 0 0 0 16z" />
        </svg>
        <h1 class="text-lg font-semibold uppercase text-gray-600 ">Add New Staying Hour</h1>
      </div>
      <div class="flex mt-5 px-4 flex-col space-y-3">
        <x-input wire:model.defer="number" label="Number of Hours" placeholder="" />
      </div>
      <x-slot name="footer">
        <div class="flex justify-end gap-x-4">
          <x-button flat label="Cancel" x-on:click="close" />

          <x-button positive right-icon="save-as" spinner="saveStayingHour" wire:click="saveStayingHour" label="Save" />
        </div>
      </x-slot>
    </x-card>
  </x-modal>


  <x-modal wire:model.defer="add_modal" align="center" max-width="lg">
    <x-card>
      <div class="header flex space-x-2 items-center">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" class="fill-gray-600">
          <path fill="none" d="M0 0h24v24H0z" />
          <path
            d="M11 11V7h2v4h4v2h-4v4h-2v-4H7v-2h4zm1 11C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10zm0-2a8 8 0 1 0 0-16 8 8 0 0 0 0 16z" />
        </svg>
        <h1 class="text-lg font-semibold uppercase text-gray-600 ">Add New Rates</h1>
      </div>
      <div class="flex mt-5 px-4 flex-col space-y-3">
        <x-native-select label="Select Number of Hours" wire:model="hours_id">
          <option selected hidden>Select Number of Hours</option>
          @foreach ($stayingHours as $hour)
            <option value="{{ $hour->id }}">{{ $hour->number }} hours ({{$hour->branch->name}})</option>
          @endforeach
        </x-native-select>
        <x-input wire:model.defer="amount" label="Amount" placeholder="" />
        @php
          $rooms = App\Models\Room::where('branch_id', auth()->user()->hasRole('superadmin') ? $this->branch_id : auth()->user()->branch_id)->with('type')->get();
        @endphp
        <x-native-select label="Select Room" wire:model="room_id">
          <option selected hidden>Select Room</option>
          @foreach ($rooms as $room)
            <option value="{{ $room->id }}">Room {{ $room->number }} ({{ $room->type->name }}) </option>
          @endforeach
        </x-native-select>
      </div>
      <x-slot name="footer">
        <div class="flex justify-end gap-x-4">
          <x-button flat label="Cancel" x-on:click="close" />

          <x-button positive right-icon="save-as" spinner="saveRate" wire:click="saveRate" label="Save" />
        </div>
      </x-slot>
    </x-card>
  </x-modal>

  <x-modal wire:model.defer="edit_modal" align="center" max-width="lg">
    <x-card>
      <div class="header flex space-x-2 items-center">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="fill-gray-600" width="24" height="24">
          <path fill="none" d="M0 0h24v24H0z" />
          <path
            d="M16.757 3l-2 2H5v14h14V9.243l2-2V20a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h12.757zm3.728-.9L21.9 3.516l-9.192 9.192-1.412.003-.002-1.417L20.485 2.1z" />
        </svg>
        <h1 class="text-lg font-semibold uppercase text-gray-600 ">Update Rates</h1>
      </div>
      <div class="flex mt-5 px-4 flex-col space-y-3">
        <x-native-select label="Select Number of Hours" wire:model="hours_id">
          <option selected hidden>Select Number of Hours</option>
          @foreach ($stayingHours as $hour)
            <option value="{{ $hour->id }}">{{ $hour->number  }} hours ({{$hour->branch->name}})</option>
          @endforeach
        </x-native-select>
        <x-input wire:model.defer="amount" label="Amount" placeholder="" />
        @php
          $rooms = App\Models\Room::where('branch_id', auth()->user()->hasRole('superadmin') ? $this->branch_id : auth()->user()->branch_id)->with('type')->get();
        @endphp
        <x-native-select label="Select Room" wire:model="room_id">
          <option selected hidden>Select Room</option>
          @foreach ($rooms as $room)
            <option value="{{ $room->id }}">Room {{ $room->number }} ({{ $room->type->name }}) </option>
          @endforeach
        </x-native-select>
      </div>
      <x-slot name="footer">
        <div class="flex justify-end gap-x-4">
          <x-button flat label="Cancel" x-on:click="close" />

          <x-button positive right-icon="save-as" spinner="updateRates" wire:click="updateRates" label="Update" />
        </div>
      </x-slot>
    </x-card>
  </x-modal>

</div>
