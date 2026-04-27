<div class="grid {{auth()->user()->hasRole('frontdesk') ? 'grid-cols-3' : 'grid-cols-2'}} space-x-12" x-data="{ excess: @entangle('excess') }">
  <div class="col-span-2">
    <div class="flex space-x-4 items-center">
      <div class="search flex items-center rounded-lg  px-3 py-1 w-72 border border-gray-200 shadow-sm">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="fill-gray-500" width="24" height="24">
          <path fill="none" d="M0 0h24v24H0z" />
          <path
            d="M11 2c4.968 0 9 4.032 9 9s-4.032 9-9 9-9-4.032-9-9 4.032-9 9-9zm0 16c3.867 0 7-3.133 7-7 0-3.868-3.133-7-7-7-3.868 0-7 3.132-7 7 0 3.867 3.132 7 7 7zm8.485.071l2.829 2.828-1.415 1.415-2.828-2.829 1.414-1.414z" />
        </svg>
        <input type="text" wire:model="search" class="outline:none  h-8 focus:ring-0 flex-1 border-0 focus:border-0"
          placeholder="Search">
      </div>
      <x-native-select wire:model="filter_floor">
        <option selected hidden>Select Floors</option>
        <option value="">All</option>
        @foreach ($floors as $floor)
          <option value="{{ $floor->id }}" class="uppercase">{{ $floor->numberWithFormat() }}</option>
        @endforeach
      </x-native-select>
      <x-native-select wire:model="filter_status">
        <option selected hidden>Select Status</option>
        <option value="">All</option>
        <option value="Available">Available</option>
        <option value="Occupied">Occupied</option>
        <option value="Reserved">Reserved</option>
        <option value="Maintenance">Maintenance</option>
        <option value="Unavailable">Unavailable</option>
        {{-- <option value="Selected in Kiosk">Selected in Kiosk</option> --}}
        <option value="Uncleaned">Uncleaned</option>
        <option value="Cleaning">Cleaning</option>
        <option value="Cleaned">Cleaned</option>
      </x-native-select>
        <x-button wire:click="redirectToScanning" label="Scan QR Code" dark icon="qrcode"/>
        <x-button label="Check-In C/O" icon="check" emerald wire:click="redirectToCheckInCO"
        spinner="redirectToCheckInCO" />
        <x-button label="Kiosk Batch" icon="eye" primary wire:click="showKioskBatch"
        spinner="showKioskBatch" />
    </div>
    <div class="mt-5 flex flex-wrap gap-2 items-center justify-between bg-white rounded-lg px-4 py-3 shadow-sm border border-gray-100">
      <div class="flex flex-wrap gap-2 items-center">
        <span class="text-xs text-gray-500 font-medium uppercase tracking-wide mr-1">Status:</span>
        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-green-100 text-green-700 border border-green-200">
          <span class="w-1.5 h-1.5 bg-green-500 rounded-full mr-1.5"></span>Occupied
        </span>
        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-gray-700 text-white">
          <span class="w-1.5 h-1.5 bg-gray-300 rounded-full mr-1.5"></span>Reserved
        </span>
        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-violet-100 text-violet-700 border border-violet-200">
          <span class="w-1.5 h-1.5 bg-violet-500 rounded-full mr-1.5"></span>Maintenance
        </span>
        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-gray-800 text-white">
          <span class="w-1.5 h-1.5 bg-gray-400 rounded-full mr-1.5"></span>Unavailable
        </span>
        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-slate-100 text-slate-700 border border-slate-200">
          <span class="w-1.5 h-1.5 bg-slate-500 rounded-full mr-1.5"></span>Uncleaned
        </span>
        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-red-100 text-red-700 border border-red-200">
          <span class="w-1.5 h-1.5 bg-red-500 rounded-full mr-1.5"></span>Cleaning
        </span>
        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-blue-100 text-blue-700 border border-blue-200">
          <span class="w-1.5 h-1.5 bg-blue-500 rounded-full mr-1.5"></span>Cleaned
        </span>
      </div>
      <div class="flex items-center bg-gray-50 px-3 py-1.5 rounded-md">
        <span class="text-xs text-gray-500 font-medium">Total Rooms:</span>
        <span class="ml-1.5 text-sm font-bold text-gray-800">{{ $rooms->count() }}</span>
      </div>
    </div>
    <div class="overflow-auto max-h-[70vh] p-2 border mt-2 md:rounded-lg">
      {{-- <table class="min-w-full divide-y divide-gray-300">
        <thead class="bg-gray-50">
          <tr>
            <th scope="col"
              class="whitespace-nowrap py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6">Room
              Number</th>
            <th scope="col" class="whitespace-nowrap px-2 py-3.5 text-left text-sm font-semibold text-gray-900">Floor
            </th>
            <th scope="col" class="whitespace-nowrap px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
              Status</th>
            <th scope="col" class="relative whitespace-nowrap py-3.5 pl-3 pr-4 sm:pr-6">
              <span class="sr-only">Edit</span>
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">

          @forelse ($rooms as $room)
            <tr>
              <td class="whitespace-nowrap py-2 pl-4 pr-3 text-sm text-gray-500 sm:pl-6">{{ $room->number }}</td>
              <td class="whitespace-nowrap py-2 pl-4 pr-3 text-sm text-gray-500 sm:pl-6">{{ $room->floor->number }}</td>
              <td class="whitespace-nowrap py-2 pl-4 pr-3 text-sm text-gray-500 sm:pl-6">{{ $room->status }}</td>
            </tr>
          @empty
            <td colspan="3" class="text-center py-2">
              <span>No data available...</span>
            </td>
          @endforelse

          <!-- More transactions... -->
        </tbody>
      </table> --}}
      <table class="min-w-full divide-y border-separate border-spacing-y-1.5 divide-gray-300">
        <thead class="">
          <tr class="uppercase">
            <th scope="col" class="px-3 w-56 py-2 text-left text-sm font-semibold text-gray-800">ROOM</th>
            {{-- <th scope="col" class="px-3 w-72  py-2 text-left text-sm font-semibold text-gray-800">FLOOR</th> --}}
            <th scope="col" class="px-3 py-2 text-left text-sm font-semibold text-gray-800">ROOM TYPE</th>
            <th scope="col" class="px-3 py-2 text-left text-sm font-semibold text-gray-800">GUEST</th>
            <th scope="col" class="px-3 py-2 text-left text-sm font-semibold text-gray-800">SHIFT</th>
            <th scope="col" class="px-3 py-2 text-left text-sm font-semibold text-gray-800">STATUS</th>
          </tr>
        </thead>

        <tbody class="divide-y divide-gray-200">
          @forelse ($rooms as $room)
            @php
            // if($room->checkInDetails->first() != null)
            if($room->latestCheckInDetail != null)
            {
                $check_out_date = Carbon\Carbon::parse($room->latestCheckInDetail->check_out_at);
                // $check_out_date = Carbon\Carbon::parse($room->checkInDetails->first()->check_out_at);
                $one_hour_before = $check_out_date->subHour();
                $date_now = Carbon\Carbon::now();
                // $is_true = $date_now->isSameHour($one_hour_before);
                $is_true = $date_now->gt($check_out_date);
            }else{
                $check_out_date = null;
                $is_true = false;
            }

            @endphp
            {{-- @php
                $check_out_date = Carbon\Carbon::parse($room->checkInDetails->first()->check_out_at ?? null);
                $one_hour_before = $check_out_date->copy()->subHour(); // Create a copy of the checkout date and then subtract 1 hour
                $date_now = Carbon\Carbon::now();
                $is_true = $date_now->equalTo($one_hour_before); // Check if the current time is exactly equal to 1 hour before the checkout time
            @endphp --}}
            <tr class="rounded-md {{ $is_true ? 'bg-red-100' : 'bg-gray-100' }}">
              <td class="whitespace-nowrap rounded-l-lg py-3 pl-4  text-sm font-bold text-green-600 sm:pl-6">
                {{ $room->numberWithFormat() }}
                {{-- <p class="text-sm text-gray-500 font-normal">{{$room->type->name}}</p> --}}
                <p class="text-sm text-gray-500 font-normal">{{$room->floor->numberWithFormat()}}</p>
              </td>
              {{-- <td class="whitespace-nowrap px-3 py-3 text-sm text-gray-500">
                {{ $room->floor->numberWithFormat() }}
             </td> --}}
             <td class="whitespace-nowrap px-3 py-3 text-sm text-gray-500">
                {{ $room->type->name }}
                <p class="text-sm text-gray-500 font-normal">  ₱ {{ $room->status ===  'Occupied' ? number_format($room->latestCheckInDetail?->guest?->static_amount ?? 0, 2) : number_format($room->type->rates->first()->amount, 2) }}</p>
             </td>
             <td class="whitespace-nowrap px-3 py-3 text-sm text-gray-500">
                {{-- @if ($room->status == 'Occupied' && $room->checkInDetails->first() != null) --}}
                @if ($room->status == 'Occupied' && $room->latestCheckInDetail && $room->latestCheckInDetail->guest)
                    {{ $room->latestCheckInDetail->guest->name }}
                    <p class="text-sm text-gray-500 font-normal">
                        {{ $room->latestCheckInDetail->guest->qr_code }}
                    </p>
                @endif

             </td>
             <td class="whitespace-nowrap px-3 py-3 text-sm text-gray-500">
                {{-- @if ($room->status == 'Occupied' && $room->checkInDetails->first() != null) --}}
                @if ($room->status == 'Occupied' && $room->latestCheckInDetail != null)
                    {{ $room->newGuestReports->first()?->shift }}
                @endif

             </td>
              <td class="whitespace-nowrap px-3 py-3 text-sm text-gray-500">
                @switch($room->status)
                  @case('Occupied')
                    <x-badge class="font-normal" flat positive md label="Occupied" />
                  @break

                  @case('Reserved')
                    <x-badge class="font-normal" dark flat md label="Reserved" />
                  @break

                  @case('Maintenance')
                    <x-badge class="font-normal" dark flat md label="Maintenance" />
                  @break

                  @case('Unavailable')
                    <x-badge class="font-normal" dark flat md label="Unavailable" />
                  @break

                  @case('Uncleaned')
                    <x-badge class="font-normal" dark flat md label="Uncleaned" />
                  @break

                  @case('Cleaning')
                    <x-badge class="font-normal" dark flat md label="Cleaning" />
                  @break

                  @case('Cleaned')
                    <x-badge class="font-normal" dark flat md label="Cleaned" />
                  @break

                  @default
                @endswitch

              </td>
              <td class="whitespace-nowrap px-3 py-3 text-sm ">

                @php
                //   $check_out_date = Carbon\Carbon::parse($room->checkInDetails->first()->check_out_at ?? null);
                  $check_out_date = Carbon\Carbon::parse($room->latestCheckInDetail->check_out_at ?? null);
                @endphp

                @if ($room->status == 'Occupied')
                  <div class="flex space-x-1">
                   @php
                    $now = Carbon\Carbon::now();
                    $grace_end = $check_out_date->copy()->addMinutes(15);
                    @endphp

                    @if ($now > $grace_end)

                        <span class="inline-flex items-center rounded-md bg-red-500 px-2 py-1 text-sm font-medium text-white">
                            Over Time: {{ $check_out_date->diffForHumans() }}
                        </span>

                    @elseif ($now > $check_out_date)

                        <span class="inline-flex items-center rounded-md bg-yellow-500 px-2 py-1 text-sm font-medium text-white">
                            Grace Period
                        </span>

                        <div class="text-yellow-500">
                            <x-countdown :expires="$grace_end">
                                <span x-text="timer.minutes">{{ $component->minutes() }}</span>m :
                                <span x-text="timer.seconds">{{ $component->seconds() }}</span>s
                            </x-countdown>
                        </div>

                    @else

                        <h1>Time:</h1>
                        <div class="text-red-500">
                            <x-countdown :expires="$check_out_date">
                                <span x-text="timer.days">{{ $component->days() }}</span>d :
                                <span x-text="timer.hours">{{ $component->hours() }}</span>h :
                                <span x-text="timer.minutes">{{ $component->minutes() }}</span>m :
                                <span x-text="timer.seconds">{{ $component->seconds() }}</span>s
                            </x-countdown>
                        </div>

                    @endif

                  </div>
                @endif
              </td>

              <td class="whitespace-nowrap rounded-r-lg px-3 py-3 text-sm text-gray-500">
                {{-- @if ($room->status == 'Occupied' && $room->checkInDetails->first() != null) --}}
                @if ($room->status == 'Occupied' && $room->latestCheckInDetail != null)
                  <div class="flex space-x-2">
                    @if($is_true)
                    <x-button href="{{ route('frontdesk.extend-guest', ['record' => $room->latestCheckInDetail->guest_id]) }}" sm label="Extend" negative />

                    @endif
                    {{-- <x-button wire:click="viewDetails({{ $room->guest->first()->id }})" sm icon="eye" warning /> --}}
                    <x-button wire:click="viewDetails({{ $room->latestCheckInDetail->guest_id }})" sm icon="eye" warning />
                    {{-- <x-button href="{{ route('frontdesk.manage-guest', ['id' => $room->checkInDetails->first()->guest_id]) }}" label="Manage" class="hidden" positive sm right-icon="arrow-narrow-right" /> --}}
                        @if (auth()->user()->hasRole('frontdesk'))
                        {{-- <x-button href="{{ route('frontdesk.guest-transaction', ['id' => $room->guest->first()->id]) }}" label="Manage" positive sm right-icon="arrow-narrow-right" /> --}}
                        <x-button href="{{ route('frontdesk.guest-transaction', ['id' => $room->latestCheckInDetail->guest_id]) }}" label="Manage" positive sm right-icon="arrow-narrow-right" />
                        @else
                        {{-- <x-button wire:click="addTransaction({{$room->guest->first()->id}})" label="Add Transaction" slate sm right-icon="arrow-narrow-right" /> --}}
                        <x-button wire:click="addTransaction({{$room->latestCheckInDetail->guest_id}})" label="Add Transaction" slate sm right-icon="arrow-narrow-right" />
                        @endif
                  </div>
                @elseif($room->status == 'Reserved')
                <x-button
                label="Check-in" wire:click="checkInReserve({{ $room->id }})" positive sm right-icon="arrow-narrow-right" />
                @endif
              </td>
            </tr>
            @empty
            <tr>
              <td colspan="5" class="text-center py-2">
                <span class="italic text-xl font-normal text-gray-500">No checked-in guests</span>
              </td>
            @endforelse
          </tbody>
        </table>

      </div>
    </div>
    @if(auth()->user()->hasRole('frontdesk'))
    <div class="col-span-1 space-y-4">
      {{-- CHECK-IN GUEST Section --}}
      <div>
        <div wire:poll.1s>
          <div class="flex items-center justify-between">
            <h1 class="font-bold text-xl text-gray-700 flex items-center">
              <span class="w-2 h-2 bg-green-500 rounded-full mr-2 animate-pulse"></span>
              CHECK-IN GUEST
              <span class="ml-2 bg-green-100 text-green-700 text-sm font-semibold px-2 py-0.5 rounded-full">{{ $kiosks->count() }}</span>
            </h1>
          </div>
        </div>

        <div class="overflow-y-auto h-[200px] bg-white shadow-sm rounded-lg mt-3 border border-gray-200"
             style="scrollbar-width: thin; scrollbar-color: #d1d5db transparent;">
          <ul role="list" class="divide-y divide-gray-100" x-animate>
            @forelse($kiosks as $kiosk)
              @if (! $kiosk->guest)
                @continue
              @endif
              <li x-animate class="transition duration-200 ease-in-out hover:bg-green-50">
                <div class="flex items-center justify-between px-4 py-3">
                  <div class="flex items-center min-w-0 flex-1">
                    <div class="min-w-0 flex-1">
                      <p class="truncate text-sm font-semibold text-green-600 uppercase">
                        {{ $kiosk->guest->name }}
                        <span class="text-gray-400 font-normal">(RM #{{ $kiosk->guest?->room?->number }})</span>
                      </p>
                      <p class="flex items-center text-xs text-green-500 mt-0.5">
                        <svg class="mr-1 w-3.5 h-3.5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5zM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5z" />
                          <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 6.75h.75v.75h-.75v-.75zM6.75 16.5h.75v.75h-.75v-.75zM16.5 6.75h.75v.75h-.75v-.75zM13.5 13.5h.75v.75h-.75v-.75zM13.5 19.5h.75v.75h-.75v-.75zM19.5 13.5h.75v.75h-.75v-.75zM19.5 19.5h.75v.75h-.75v-.75zM16.5 16.5h.75v.75h-.75v-.75z" />
                        </svg>
                        {{ $kiosk->guest->qr_code }}
                      </p>
                    </div>
                  </div>
                  <div class="flex items-center space-x-1">
                    <button wire:click="redirectToCheckinFromKiosk({{ $kiosk->id }})" type="button"
                      class="p-1.5 rounded-full hover:bg-green-100 focus:outline-none transition" title="Approve Check-in">
                      <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                      </svg>
                    </button>
                    <button x-on:confirm="{
                          title: 'Are you sure you want to delete this?',
                          icon: 'warning',
                          method: 'deleteTempKiosk',
                          params: {{ $kiosk->id }},
                      }" type="button" class="p-1.5 rounded-full hover:bg-red-100 focus:outline-none transition" title="Reject">
                      <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0 1 16.138 21H7.862a2 2 0 0 1-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v3M4 7h16" />
                      </svg>
                    </button>
                  </div>
                </div>
              </li>
            @empty
              <div class="flex justify-center items-center py-8 text-gray-400">
                <div class="text-center">
                  <svg class="mx-auto h-8 w-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                  </svg>
                  <p class="mt-1 text-sm">No pending check-ins</p>
                </div>
              </div>
            @endforelse
          </ul>
        </div>
      </div>

{{-- CHECKOUT GUEST Section --}}
      <div>
        <div wire:poll.1s>
          <div class="flex items-center justify-between">
            <h1 class="font-bold text-xl text-gray-700 flex items-center">
              <span class="w-2 h-2 bg-blue-500 rounded-full mr-2"></span>
              CHECKOUT GUEST
              <span class="ml-2 bg-blue-100 text-blue-700 text-sm font-semibold px-2 py-0.5 rounded-full">{{ $checkOutKiosks->count() }}</span>
            </h1>
          </div>
        </div>

        <div class="overflow-y-auto h-[200px] bg-white shadow-sm rounded-lg mt-3 border border-gray-200"
             style="scrollbar-width: thin; scrollbar-color: #d1d5db transparent;">
          <ul role="list" class="divide-y divide-gray-100">
            @forelse($checkOutKiosks as $room)
              @php
                $guest = $room->latestCheckInDetail?->guest;
                $checkInDetail = $room->latestCheckInDetail;
              @endphp
              @if($guest && $checkInDetail)
              <li class="transition duration-200 ease-in-out hover:bg-blue-50">
                <div class="flex items-center justify-between px-4 py-3">
                  <div class="flex items-center min-w-0 flex-1">
                    <div class="min-w-0 flex-1">
                      <p class="truncate text-sm font-semibold text-blue-600 uppercase">
                        {{ $guest->name }}
                        <span class="text-gray-400 font-normal">(RM #{{ $room->number }})</span>
                      </p>
                      <p class="flex items-center text-xs text-blue-500 mt-0.5">
                        <svg class="mr-1 w-3.5 h-3.5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5zM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5z" />
                          <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 6.75h.75v.75h-.75v-.75zM6.75 16.5h.75v.75h-.75v-.75zM16.5 6.75h.75v.75h-.75v-.75zM13.5 13.5h.75v.75h-.75v-.75zM13.5 19.5h.75v.75h-.75v-.75zM19.5 13.5h.75v.75h-.75v-.75zM19.5 19.5h.75v.75h-.75v-.75zM16.5 16.5h.75v.75h-.75v-.75z" />
                        </svg>
                        {{ $guest->qr_code }}
                      </p>
                    </div>
                  </div>
                  <div class="flex items-center">
                    <a href="{{ route('frontdesk.guest-transaction', ['id' => $guest->id]) }}"
                       class="p-1.5 rounded-full hover:bg-blue-100 focus:outline-none transition" title="Manage Guest">
                      <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                      </svg>
                    </a>
                  </div>
                </div>
              </li>
              @endif
            @empty
              <div class="flex justify-center items-center py-8 text-gray-400">
                <div class="text-center">
                  <svg class="mx-auto h-8 w-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                  </svg>
                  <p class="mt-1 text-sm">No checkout guests</p>
                </div>
              </div>
            @endforelse
          </ul>
        </div>
      </div>
    </div>
    @endif

    {{-- FOR RESERVATIONS --}}

    <x-modal wire:model.defer="guest_details_modal" align="center">
      <x-card>
        <div>
          <div class="header flex space-x-1 border-b items-end justify-between py-0.5">
            <h2 class="text-lg uppercase text-gray-600 font-bold">Guest Details</h2>
          </div>
          <div class="mt-3">
            <div class="space-y-4">
              <dl class="mt-8 p-2 divide-y divide-gray-400 text-sm lg:col-span-5 lg:mt-0">
                @if ($guest_details)
                  <div class="flex items-center justify-between pb-4">
                    <dt class="text-gray-600">Name: </dt>
                    <dd class="font-medium uppercase text-gray-800">{{ $guest_details->name }}</dd>
                  </div>
                  <div class="flex items-center justify-between py-4">
                    <dt class="text-gray-600">Contact Number: </dt>
                    <dd class="font-medium text-gray-800">09{{ $guest_details->contact }}</dd>
                  </div>
                @endif
              </dl>
            </div>
          </div>
        </div>

        <x-slot name="footer">
          <div class="flex justify-end s gap-x-2">
            <x-button red label="Close" x-on:click="close" />
          </div>
        </x-slot>
      </x-card>
    </x-modal>

    <x-modal.card title="Check In Information" blur wire:model.defer="checkInModal">
      @if ($temporary_checkIn != null && $temporary_checkIn->guest)
        <div class="col-span-1 sm:col-span-2">
          <x-input class="text-gray-900" readonly label="QR Code" value="{{ $temporary_checkIn->guest->qr_code }}" />
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-2">
          <x-input class="text-gray-900" readonly label="Name" value="{{ $temporary_checkIn->guest->name }}" />
          <x-input class="text-gray-900" readonly label="Contact Number"
            value="{{ $temporary_checkIn->guest->contact == 'N/A' ? 'N/A' : '09' . $temporary_checkIn->guest->contact }}" />
          <x-input class="text-gray-900" readonly label="Room Number" value="{{ $temporary_checkIn->room->number }}" />
          @if ($temporary_checkIn->guest->is_long_stay)
            <x-input class="text-gray-900" readonly label="Days" value="{{ $temporary_checkIn->guest->number_of_days }}" />
          @else
            <x-input class="text-gray-900" readonly label="Staying Hours" value="{{ $stayingHour->number }}" />
          @endif
          <div class="col-span-1 sm:col-span-2">
            <label class="inline-flex items-center cursor-pointer">
                <input type="checkbox" wire:model="has_discount" class="form-checkbox h-5 w-5 text-blue-600 focus:ring-blue-500 border-gray-300 rounded" />
                <span class="ml-2 text-gray-700 font-medium">Grant Discount</span>
            </label>
        </div>
        </div>
        <div class="bg-gray-200 mt-2 p-4 rounded-md border border-dashed border-gray-500">
          <div class="text-lg font-medium mb-2">Billing Statement</div>
          <div class="grid grid-cols-2 gap-4">
            <div class="col-span-1 my-auto">
              <div class="text-sm font-medium mb-1">Room Rate:</div>
            </div>
            <div class="col-span-1 flex justify-end mr-1 text-gray-900">
                <span>₱ {{ number_format($temporary_checkIn->guest->static_amount, 2) }}</span>
              {{-- <x-input class="text-right" disabled value="{{ $temporary_checkIn->guest->static_amount }}" /> --}}
            </div>
            <div class="col-span-1 my-auto">
              <div class="text-sm font-medium mb-1">Additional Charges:</div>
            </div>
            <div class="col-span-1 flex justify-end mr-1 text-gray-900">
                <span>₱ {{ number_format($additional_charges, 2) }}</span>
              {{-- <x-input class="text-right" disabled value="{{ $additional_charges }}" /> --}}
            </div>
            @if($has_discount)
             <div class="col-span-1 my-auto">
              <div class="text-sm text-red-600 font-medium mb-1">Discount: (Senior & PWD)</div>
            </div>
            <div class="col-span-1 flex justify-end mr-1 text-red-600">
                <span>- ₱ {{ number_format($discount_amount, 2) }}</span>
            </div>
            @endif
            <div class="col-span-1 my-auto">
              <div class="text-sm font-medium mb-1">Total:</div>
            </div>
            <div class="col-span-1 flex justify-end mr-1 text-gray-900">
                <span>₱ {{ number_format($total, 2) }}</span>
              {{-- <x-input class="text-right" disabled value="{{ $total }}" /> --}}
            </div>
            <div class="col-span-1 my-auto">
              <div class="text-sm font-medium mb-1">Amount Paid:</div>
            </div>
            <div class="col-span-1">
              <x-input wire:model="amountPaid" type="number" placeholder="₱ 0.00" class="text-right pr-0" />
            </div>

             <template x-if="excess">
                <div class="col-span-1 my-auto">
                <div class="text-sm font-medium mb-1">Excess Amount:</div>
                </div>
            </template>
            <template x-if="excess" x-cloak>
                <div class="col-span-1">
                <x-input wire:model="excess_amount" disabled type="number" class="text-right" />
                <x-checkbox id="right-label" label="Save excess as deposit" wire:model.defer="save_excess" class="mx-2 mt-2" />
                </div>
            </template>

          </div>
        </div>
      @else
        <span>No data found</span>
      @endif
      <x-slot name="footer">
        <div class="flex justify-end gap-x-4">
          <div class="flex space-x-2">
            <x-button default label="Cancel" x-on:click="close" />
            <x-button dark label="Save" wire:click="saveCheckInDetails" />
          </div>
        </div>
      </x-slot>
    </x-modal.card>

    <x-modal.card title="Check In Information" blur wire:model.defer="checkInReserveModal">
      @if ($temporary_reserve != null && $temporary_reserve->guest)
        <div class="col-span-1 sm:col-span-2">
          <x-input disabled label="QR Code" value="{{ $temporary_reserve->guest->qr_code }}" />
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-2">
          <x-input disabled label="Name" value="{{ $temporary_reserve->guest->name }}" />
          <x-input disabled label="Contact Number"
            value="{{ $temporary_reserve->guest->contact == 'N/A' ? 'N/A' : '09' . $temporary_reserve->guest->contact }}" />
          <x-input disabled label="Room Number" value="{{ $temporary_reserve->room->number }}" />
          @if ($temporary_reserve->guest->is_long_stay)
            <x-input disabled label="Days" value="{{ $temporary_reserve->guest->number_of_days }}" />
          @else
            <x-input disabled label="Hours" value="{{ $stayingHour_reserve->number }}" />
          @endif
        </div>
        <div class="bg-gray-200 mt-2 p-4 rounded-md border border-dashed border-gray-500" x-animate>
          <div class="text-lg font-medium mb-2">Billing Statement</div>
          <div class="grid grid-cols-2 gap-4" x-animate>
            <div class="col-span-1 my-auto">
              <div class="text-sm font-medium mb-1">Room Rate:</div>
            </div>
            <div class="col-span-1">
              <x-input class="text-right" disabled value="{{ $temporary_reserve->guest->static_amount }}" />
            </div>
            <div class="col-span-1 my-auto">
              <div class="text-sm font-medium mb-1">Additional Charges:</div>
            </div>
            <div class="col-span-1">
              <x-input class="text-right" disabled value="{{ $additional_charges_reserve }}" />
            </div>
            <div class="col-span-1 my-auto">
              <div class="text-sm font-medium mb-1">Total:</div>
            </div>
            <div class="col-span-1">
              <x-input class="text-right" disabled value="{{ $total_reserve }}" />
            </div>
            <div class="col-span-1 my-auto">
              <div class="text-sm font-medium mb-1">Amount Paid:</div>
            </div>
            <div class="col-span-1">
              <x-input wire:model="amountPaid_reserve" type="number" placeholder="0.00" class="text-right pr-0" />
            </div>

            @if ($reserve_div)
              <div class="col-span-1 my-auto">
                <div class="text-sm font-medium mb-1">Excess Amount:</div>
              </div>
              <div class="col-span-1">
                <x-input wire:model="excess_amount_reserve" disabled type="number" class="text-right" />

              </div>
              <div class="col-span-1 flex justify-end">
              </div>
              <div class="col-span-1">
                <x-checkbox id="right-label" label="Save excess as deposit" wire:model.defer="save_excess_reserve" />
              </div>
            @endif
          </div>
        </div>
      @else
        <span>No data found</span>
      @endif
      <x-slot name="footer">
        <div class="flex justify-end gap-x-4">
          <div class="flex space-x-2">
            <x-button default label="Cancel" x-on:click="close" />
            <x-button dark label="Save" wire:click="saveReserveCheckInDetails" />
          </div>
        </div>
      </x-slot>
    </x-modal.card>

    <x-modal wire:model.defer="guestCheckInModal" align="center">
      <x-card title="CHECK-IN DETAILS">
        <div class="col-span-1 sm:col-span-2">
          <x-input disabled label="QR Code" value="{{ $checkInDetails['transaction_code'] ?? 'Null' }}" />
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-2">
          <x-input disabled label="Name" value="{{ $checkInDetails['guest_name'] ?? 'Null' }}" />
          <x-input disabled label="Contact Number" value="{{ $checkInDetails['guest_contact_number'] ?? 'Null' }}" />
          <x-input disabled label="Room Number" value="{{ $checkInDetails['room'] ?? 'Null' }}" />

          @if ($is_longStay)
            <x-input disabled label="Days" value="" />
          @else
            <x-input disabled label="Hours" value="{{ $checkInDetails['rate'] ?? 'Null' }}" />
          @endif

        </div>
        <div class="bg-gray-100 mt-2 p-4 rounded-md border border-dashed border-gray-300">
          <div class="text-lg font-medium mb-2">Billing Statement</div>
          <div class="grid grid-cols-2 gap-4">
            <div class="col-span-1 my-auto">
              <div class="text-sm font-medium mb-1">Room Rate: </div>
            </div>
            <div class="col-span-1">
              <x-input class="text-right" disabled value="&#8369;{{ $checkInDetails['room_rate'] ?? 0 }}.00" />
            </div>
            <div class="col-span-1 my-auto">
              <div class="text-sm font-medium mb-1">Additional Charges:</div>
            </div>
            <div class="col-span-1">
              <x-input class="text-right" disabled value="&#8369;200.00" />
            </div>
            <div class="col-span-1 my-auto">
              <div class="text-sm font-medium mb-1">Total:</div>
            </div>
            <div class="col-span-1">
              <x-input class="text-right" disabled value="&#8369;{{ number_format($total, 2) }}" />
            </div>
            <div class="col-span-1 my-auto">
              <div class="text-sm font-medium mb-1">Amount Paid:</div>
            </div>
            <div class="col-span-1">
              <x-input wire:model="amountPaid" type="number" placeholder="0.00" class="text-right pr-0" />
            </div>

            <div class="col-span-1 my-auto" x-show="excess" x-collapse>
              <div class="text-sm font-medium mb-1">Excess Amount:</div>
            </div>
            <div class="col-span-1" x-show="excess" x-collapse>
              <x-input wire:model="excess_amount" disabled type="number" class="text-right" />
              <x-checkbox id="right-label" label="Save excess as deposit" wire:model.defer="save_excess"
                class="mx-2 mt-2" />
            </div>


          </div>
        </div>
        <x-slot name="footer">
          <div class="flex justify-end gap-x-4">
            <x-button flat label="Cancel" x-on:click="close" />
            <x-button positive wire:click="storeGuest" spinner="storeGuest" label="Check-In Guest" />
          </div>
        </x-slot>
      </x-card>
    </x-modal>

    <x-modal wire:model.defer="food_beverages_modal" align="center">
        <x-card>
          <div>
            <div class="header flex space-x-1 border-b items-end justify-between py-0.5">
              <h2 class="text-lg uppercase text-gray-600 font-bold">Food and Beverages</h2>
              <x-button.circle icon="plus" xs positive />
            </div>
            <div class="mt-3">
              <div class="space-y-4">
                <x-native-select label="Item" wire:model="food_id">
                  <option>Select Item</option>
                  @forelse($foods as $food)
                    <option value="{{ $food->id }}">{{ $food->name }}</option>
                  @empty
                    <option>No Items Yet</option>
                  @endforelse
                </x-native-select>
                <x-input label="Price" disabled type="number" min="0" placeholder=""
                  wire:model="food_price" />
                <x-input label="Quantity" type="number" min="1" value="1" placeholder=""
                  wire:model="food_quantity" />

                <dl class="mt-8 bg-gray-300 rounded-md p-2 divide-y divide-gray-400 text-sm lg:col-span-5 lg:mt-0">
                  <div class="flex items-center justify-between pb-4">
                    <dt class="text-gray-600">Subtotal</dt>
                    <dd class="font-medium text-gray-800">₱ {{ number_format($food_price, 2, '.', ',') }}</dd>
                  </div>
                  <div class="flex items-center justify-between pt-4">
                    <dt class="font-medium text-lg text-gray-800">Total Payable Amount</dt>
                    <dd class="font-medium text-lg text-gray-900">₱ {{ number_format($food_total_amount, 2, '.', ',') }}
                    </dd>
                  </div>
                </dl>
              </div>
            </div>
          </div>

          <x-slot name="footer">
            <div class="flex justify-end gap-x-2">
              <x-button flat negative label="Cancel" wire:click="closeModal" />
              <x-button positive label="Save" wire:click="addFood" right-icon="arrow-narrow-right" />
            </div>
          </x-slot>
        </x-card>
      </x-modal>

      {{-- Kiosk Batch viewer modal --}}
      <x-modal.card title="Kiosk Batch Status" blur wire:model.defer="kioskBatchModal" max-width="6xl">
        <div class="space-y-6">
          @if (empty($kioskBatchData))
            <p class="text-sm text-gray-500">No kiosk batch data.</p>
          @else
            {{-- Branch capacity — single bar with stacked progress indicator --}}
            @if (! empty($kioskBatchTotals))
              @php
                $tot = max(1, (int) $kioskBatchTotals['total']);
                $avail = (int) $kioskBatchTotals['available'];
                $occ = (int) $kioskBatchTotals['occupied'];
                $other = max(0, $tot - $avail - $occ);
                $availPct = round($avail / $tot * 100);
                $occPct = round($occ / $tot * 100);
                $otherPct = max(0, 100 - $availPct - $occPct);
              @endphp
              <div class="flex items-center gap-x-4">
                <div class="text-xs text-gray-500 uppercase tracking-wider whitespace-nowrap">Branch capacity</div>
                <div class="flex-1 flex h-2 overflow-hidden rounded-full bg-gray-100">
                  <div class="bg-emerald-500" style="width: {{ $availPct }}%" title="Available: {{ $avail }}"></div>
                  <div class="bg-rose-500" style="width: {{ $occPct }}%" title="Occupied: {{ $occ }}"></div>
                  <div class="bg-gray-300" style="width: {{ $otherPct }}%" title="Other (cleaning/maintenance/reserved): {{ $other }}"></div>
                </div>
                <div class="flex items-baseline gap-x-3 text-sm whitespace-nowrap">
                  <span><span class="inline-block w-2 h-2 rounded-full bg-emerald-500 align-middle mr-1"></span><span class="font-semibold text-gray-900">{{ $avail }}</span> <span class="text-gray-500">ready</span></span>
                  <span><span class="inline-block w-2 h-2 rounded-full bg-rose-500 align-middle mr-1"></span><span class="font-semibold text-gray-900">{{ $occ }}</span> <span class="text-gray-500">occupied</span></span>
                  @if ($other > 0)
                    <span><span class="inline-block w-2 h-2 rounded-full bg-gray-300 align-middle mr-1"></span><span class="font-semibold text-gray-900">{{ $other }}</span> <span class="text-gray-500">other</span></span>
                  @endif
                  <span class="text-gray-400">·</span>
                  <span class="text-gray-500">{{ $tot }} total</span>
                </div>
              </div>
            @endif

            <div class="rounded-md bg-gray-50 border border-gray-200 p-3 text-xs text-gray-700 space-y-1">
              <p><span class="font-semibold text-gray-900">How to read:</span> each row is one batch (set of rooms displayed together). Top row "NOW" is live on the kiosk. "NEXT" / "AFTER" preview what comes when guests pick all the current rooms.</p>
              <p class="flex flex-wrap gap-x-4 gap-y-1">
                <span><span class="inline-block rounded bg-emerald-600 text-white text-[10px] font-semibold px-1.5 py-0.5 align-middle">99</span> = guest can pick now</span>
                <span><span class="inline-block rounded bg-amber-500 text-white text-[10px] font-semibold px-1.5 py-0.5 align-middle line-through">99</span> = picked, waiting frontdesk</span>
                <span><span class="text-gray-400">—</span> = no room available on that floor</span>
              </p>
            </div>

            @foreach ($kioskBatchData as $typeBlock)
              @php
                $activeCount = collect($typeBlock['current'])->where('slot_status', 'active')->count();
                $pickedCount = collect($typeBlock['current'])->where('slot_status', 'picked')->count();
                $totalAvailable = $typeBlock['total_available'] ?? 0;
                $waitingCount = $typeBlock['waiting_count'] ?? 0;
              @endphp
              <div class="border rounded-lg p-4">
                <div class="flex items-center justify-between mb-3">
                  <h3 class="font-semibold text-base text-gray-800">
                    {{ strtoupper($typeBlock['type_name']) }}
                  </h3>
                  <div class="text-xs text-gray-600 space-x-3">
                    <span><span class="font-semibold text-emerald-700">{{ $totalAvailable }}</span> total available</span>
                    <span class="text-gray-400">·</span>
                    <span><span class="font-semibold">{{ $activeCount }}</span> on kiosk</span>
                    <span class="text-gray-400">·</span>
                    <span><span class="font-semibold">{{ $waitingCount }}</span> in stack</span>
                    @if ($pickedCount > 0)
                      <span class="text-gray-400">·</span>
                      <span><span class="font-semibold text-amber-700">{{ $pickedCount }}</span> waiting frontdesk</span>
                    @endif
                  </div>
                </div>

                @php
                  // Build a sorted list of all floor numbers that appear in
                  // any of the three batches so columns line up consistently.
                  $allFloors = collect();
                  foreach ($typeBlock['current'] as $s) $allFloors->push(['id' => null, 'number' => $s['floor_number']]);
                  foreach ($typeBlock['upcoming'] as $batch) {
                      foreach ($batch as $s) $allFloors->push(['id' => $s['floor_id'], 'number' => $s['floor_number']]);
                  }
                  $floorNumbers = $allFloors->pluck('number')->unique()->sort()->values();

                  // Index the current batch by floor number for quick lookup.
                  $currentByFloor = collect($typeBlock['current'])->keyBy('floor_number');
                @endphp

                <div class="overflow-x-auto">
                  <table class="w-full text-xs border-collapse">
                    <thead>
                      <tr class="border-b border-gray-200">
                        <th class="text-left uppercase tracking-wide text-gray-400 py-1 pr-2 w-16 font-medium">Batch</th>
                        @foreach ($floorNumbers as $fn)
                          <th class="text-center uppercase tracking-wide text-gray-400 py-1 px-1 font-medium">F{{ $fn }}</th>
                        @endforeach
                      </tr>
                    </thead>
                    <tbody>
                      {{-- Current batch row — highlighted so frontdesk sees what's live at a glance --}}
                      <tr class="bg-emerald-50/60 border-b-2 border-emerald-300">
                        <td class="py-1.5 pr-2">
                          <span class="inline-flex items-center rounded bg-emerald-600 px-2 py-0.5 text-[10px] font-bold text-white">NOW</span>
                        </td>
                        @foreach ($floorNumbers as $fn)
                          @php $slot = $currentByFloor[$fn] ?? null; @endphp
                          <td class="py-1 px-1 text-center">
                            @if (! $slot)
                              <span class="text-gray-300" title="No room available on this floor for this batch">—</span>
                            @elseif ($slot['slot_status'] === 'active')
                              <span class="inline-block rounded bg-emerald-600 text-white font-semibold px-2 py-0.5">
                                {{ $slot['room_number'] }}
                              </span>
                            @else
                              <span class="inline-block rounded bg-amber-500 text-white font-semibold px-2 py-0.5 line-through" title="picked — waiting frontdesk confirmation">
                                {{ $slot['room_number'] }}
                              </span>
                            @endif
                          </td>
                        @endforeach
                      </tr>

                      {{-- Upcoming batches --}}
                      @foreach ($typeBlock['upcoming'] as $i => $batch)
                        @php
                          $byFloor = collect($batch)->keyBy('floor_number');
                          $label = $i === 0 ? 'NEXT' : 'AFTER';
                        @endphp
                        <tr class="@if (! $loop->last) border-b border-gray-100 @endif">
                          <td class="py-1 pr-2">
                            <span class="inline-flex items-center rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600">{{ $label }}</span>
                          </td>
                          @foreach ($floorNumbers as $fn)
                            @php $slot = $byFloor[$fn] ?? null; @endphp
                            <td class="py-1 px-1 text-center">
                              @if ($slot && $slot['room_number'])
                                <span class="inline-block text-slate-700 px-2 py-0.5">{{ $slot['room_number'] }}</span>
                              @else
                                <span class="text-gray-300" title="No room available on this floor for this batch">—</span>
                              @endif
                            </td>
                          @endforeach
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
              </div>
            @endforeach
          @endif
        </div>

        <x-slot name="footer">
          <div class="flex justify-end gap-x-2">
            <x-button flat label="Close" wire:click="closeKioskBatchModal" />
            <x-button primary icon="refresh" label="Refresh" wire:click="showKioskBatch" spinner="showKioskBatch" />
          </div>
        </x-slot>
      </x-modal.card>

  </div>
