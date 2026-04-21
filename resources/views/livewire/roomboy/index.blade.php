<div>
  <div x-animate x-data class="mx-5">
    <div
      class="max-w-full px-4 py-2 mx-auto bg-white rounded-t-3xl sm:px-4 md:flex md:items-center md:justify-between md:space-x-5 lg:max-w-full lg:px-4 xl:rounded-md shadow-lg">
      <div class="flex items-center space-x-5">
        <div class="flex-shrink-0">
          <div class="relative">
            <img class="w-16 h-16 rounded-full" src="https://ui-avatars.com/api/?name={{ auth()->user()->name }}"
              alt="">
            <span class="absolute inset-0 rounded-full shadow-inner" aria-hidden="true"></span>
          </div>
        </div>
        <div>
          <h1 class="text-2xl font-bold text-gray-700">{{ auth()->user()->name }}</h1>
          @php
              $cleaning_rooms_count = App\Models\Room::beingCleanedBy(auth()->id())->count();
          @endphp
          <p wire:poll.1s class="text-sm font-medium text-gray-400 flex  items-center mt-2">
             <span class="text-sm uppercase">status: </span>
                @if ($cleaning_rooms_count == 0)
                    <span
                    class="ml-2 inline-flex items-center rounded-full bg-red-100 px-3 py-0.5 text-xs font-medium text-red-800 uppercase">Not
                    Cleaning</span>
                @else
                    <span
                    class="ml-2 inline-flex items-center rounded-full bg-green-100 px-3 py-0.5 text-xs font-medium text-green-800 uppercase">
                    Cleaning {{ $cleaning_rooms_count }} {{ Str::plural('room', $cleaning_rooms_count) }}</span>
                @endif
          </p>
        </div>
      </div>
      <div
        class="flex flex-col-reverse mt-6 space-y-4 space-y-reverse justify-stretch sm:flex-row-reverse sm:justify-end sm:space-y-0 sm:space-x-3 sm:space-x-reverse md:mt-0 md:flex-row md:space-x-3">
        <div class="flex items-center justify-center space-x-1">
            <a href="{{ route('roomboy.cleaning-history') }}"
                      class="px-4 py-2 flex items-center text-gray-50 bg-[#009ff4] rounded-full hover:text-white focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-gray-50">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12s-3.75 6.75-9.75 6.75S2.25 12 2.25 12z" />
                        <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.5" fill="none"/>
                    </svg>
                      <span class="ml-1 hidden xl:block">
                        View Cleaning History
                      </span>
                    </a>
        </div>
      </div>
    </div>

    {{-- modified functionality --}}
    <div wire:ignore>
        @if (request()->routeIs('roomboy.cleaning-history'))
            <livewire:roomboy.cleaning-history />
        @else
            <livewire:roomboy.main />
        @endif
    </div>


    {{-- <div
      class="grid max-w-3xl grid-cols-1 gap-6 mx-auto mt-5 sm:px-6 lg:max-w-7xl lg:grid-flow-col-dense lg:grid-cols-3">
      <div class="space-y-6 lg:col-span-2 lg:col-start-1">
        <!-- Description list-->
        <section aria-labelledby="applicant-information-title">
          <div class="bg-white border-b border-x">
            <div class="flex px-4 py-5 space-x-2 sm:px-6">
              <h2 id="applicant-information-title" class="text-sm font-medium leading-6 text-gray-700">Legend: </h2>
              <span
                class="inline-flex items-center rounded-full bg-red-600 px-3 py-0.5 text-sm font-medium text-white">Priority</span>
              <span
                class="inline-flex items-center rounded-full bg-green-600 px-3 py-0.5 text-sm font-medium text-white">Onqueue</span>
              <span
                class="inline-flex items-center rounded-full bg-yellow-600 px-3 py-0.5 text-sm font-medium text-white">Cleaning</span>
            </div>
          </div>

          <div class="mt-3">
            <div class="">
              @if (auth()->user()->roomboy_cleaning_room_id != null)
                <div class="xl:px-2 px-4">
                  <h1 class="font-semibold">
                    Currently Cleaning
                  </h1>

                  <div class="grid grid-cols-1">
                    <div
                      class="bg-gradient-to-br from-transparent via-yellow-100 p-4 to-yellow-200 overflow-hidden relative h-36 border-yellow-600 border-2 rounded-3xl">

                      <svg class="w-40 h-40 absolute -right-5 text-gray-500 opacity-10  top-5"
                        xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M0 0h24v24H0V0z" fill="none"></path>
                        <path d="M7 19h6V5H7v14zm3-8h2v2h-2v-2z" opacity=".3"></path>
                        <path d="M19 19V4h-4V3H5v16H3v2h12V6h2v15h4v-2h-2zm-6 0H7V5h6v14zm-3-8h2v2h-2z"></path>
                      </svg>
                      <div class="px-7">
                        <h1 class="font-bold text-3xl text-gray-600">
                          {{ App\Models\Room::where('id', auth()->user()->roomboy_cleaning_room_id)->first()->numberWithFormat() }}
                        </h1>

                        <div class="flex space-x-3 mt-2">
                          <span>Time Started:</span>
                          @php
                            $start = App\Models\Room::where('id', auth()->user()->roomboy_cleaning_room_id)->first()->started_cleaning_at;
                          @endphp
                          <span>{{ \Carbon\Carbon::parse($start)->diffForHumans() }}</span>
                        </div>
                        <div class="mt-1">
                          <x-button label="Finish Cleaning" dark right-icon="arrow-narrow-right"
                            x-on:confirm="{
                            title: 'Are you sure? you want to finish cleaning this room?',
                            icon: 'question',
                            method: 'finishCleaning',
                        }" />

                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              @endif
              <div class="px-4 mt-4 xl:px-2">
                <h1 class="font-semibold">
                  Assigned Rooms
                </h1>
                <div class="grid xl:grid-cols-2 grid-cols-1 py-1 gap-3">
                  @forelse ($assignedRooms as $room)
                    <div
                      class=" overflow-hidden relative h-36  {{ $loop->first ? 'bg-gradient-to-br from-transparent via-red-100 p-4 to-red-200 border-red-600' : 'bg-gradient-to-br from-transparent via-green-100 p-4 to-green-200 border-green-600' }} border-2 rounded-3xl">

                      <svg class="w-40 h-40 absolute -right-5 text-gray-500 opacity-10  top-5"
                        xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M0 0h24v24H0V0z" fill="none"></path>
                        <path d="M7 19h6V5H7v14zm3-8h2v2h-2v-2z" opacity=".3"></path>
                        <path d="M19 19V4h-4V3H5v16H3v2h12V6h2v15h4v-2h-2zm-6 0H7V5h6v14zm-3-8h2v2h-2z"></path>
                      </svg>
                      <div class="px-7">
                        <h1 class="font-bold text-3xl text-gray-600">
                          {{ $room->numberWithFormat() }}
                        </h1>
                        @php
                          $expires = \Carbon\Carbon::parse($room->time_to_clean);
                        @endphp
                        <div class="flex space-x-3 mt-2">
                          <span>Time to clean:</span>
                          <x-countdown :$expires>
                            <span class="font-medium text-red-600" x-text="timer.days">{{ $component->days() }}</span>
                            :
                            <span class="font-medium text-red-600" x-text="timer.hours">{{ $component->hours() }}</span>
                            :
                            <span class="font-medium text-red-600"
                              x-text="timer.minutes">{{ $component->minutes() }}</span> :
                            <span class="font-medium text-red-600"
                              x-text="timer.seconds">{{ $component->seconds() }}</span>
                          </x-countdown>
                        </div>
                        <div class="mt-1">
                          @if ($loop->first)
                            <x-button sm dark label="Start Cleaning" right-icon="arrow-narrow-right"
                              x-on:confirm="{
                            title: 'Are you sure? you want to start cleaning this room?',
                            icon: 'question',
                            method: 'startCleaning',
                            params: {{ $room->id }},
                        }" />
                          @endif
                        </div>
                      </div>
                    </div>
                  @empty
                    <div class="flex col-span-2 py-1 bg-gray-300 justify-center items-center">
                      <span>No Unclean room.</span>
                    </div>
                  @endforelse
                </div>
              </div>
              <div class="mt-4 px-4 xl:px-2">
                <h1 class="font-semibold">
                  Unassigned Rooms
                </h1>

                <div class="grid xl:grid-cols-2 grid-cols-1  gap-3 py-1">
                  @forelse ($unassignedRooms as $unassignedRoom)
                    <div
                      class="bg-gradient-to-br from-transparent via-red-100 p-4 to-red-200 overflow-hidden relative h-36 border-red-600 border-2 rounded-3xl">

                      <svg class="w-40 h-40 absolute -right-5 text-gray-500 opacity-10  top-5"
                        xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M0 0h24v24H0V0z" fill="none"></path>
                        <path d="M7 19h6V5H7v14zm3-8h2v2h-2v-2z" opacity=".3"></path>
                        <path d="M19 19V4h-4V3H5v16H3v2h12V6h2v15h4v-2h-2zm-6 0H7V5h6v14zm-3-8h2v2h-2z"></path>
                      </svg>
                      <div class="px-7">
                        <h1 class="font-bold text-3xl text-gray-600"> {{ $unassignedRoom->numberWithFormat() }}
                        </h1>
                        @php
                          $expires = \Carbon\Carbon::parse($unassignedRoom->time_to_clean);
                        @endphp
                        <div class="flex space-x-3 mt-2">
                          <span>Time to clean:</span>
                          <x-countdown :$expires>
                            <span class="font-medium text-red-600"
                              x-text="timer.days">{{ $component->days() }}</span>
                            :
                            <span class="font-medium text-red-600"
                              x-text="timer.hours">{{ $component->hours() }}</span> :
                            <span class="font-medium text-red-600"
                              x-text="timer.minutes">{{ $component->minutes() }}</span> :
                            <span class="font-medium text-red-600"
                              x-text="timer.seconds">{{ $component->seconds() }}</span>
                          </x-countdown>
                        </div>
                        <div class="mt-1">
                          @if ($loop->first)
                            <x-button sm dark label="Start Cleaning" right-icon="arrow-narrow-right"
                              x-on:confirm="{
                            title: 'Are you sure? you want to start cleaning this room?',
                            icon: 'question',
                            method: 'startCleaning',
                            params: {{ $unassignedRoom->id }},
                        }" />
                          @endif
                        </div>
                      </div>
                    </div>
                  @empty
                    <div class="flex col-span-2 py-1 bg-gray-300 justify-center items-center">
                      <span>No Unclean room.</span>
                    </div>
                  @endforelse
                </div>
              </div>
            </div>
          </div>
        </section>

      </div>

      <section aria-labelledby="timeline-title" class="lg:col-span-1 lg:col-start-3">
        <div class="px-4 py-5 bg-white shadow sm:rounded-lg sm:px-6">
          <h2 id="timeline-title" class="text-lg font-medium text-gray-900">Timeline</h2>

          <!-- Activity Feed -->
          <div class="flow-root mt-6">
            <ul role="list" class="">
              @forelse (auth()->user()->cleaningHistories as $history)
                <li>
                  <div class="relative pb-3 ">

                    <div class="relative flex space-x-3">
                      <div>
                        <span
                          class="flex items-center justify-center w-8 h-8

                        @if ($history->delayed_cleaning == 0)
                            bg-green-500
                        @else
                            bg-red-500
                        @endif
                        rounded-full ring-8 ring-white">
                          <!-- Heroicon name: mini/user -->
                          <svg class="w-5 h-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v5">
                            </path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"
                              stroke-width="1.5" d="M9 2h6"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M5 8a8.696 8.696 0 00-1.75 5.25C3.25 18.08 7.17 22 12 22s8.75-3.92 8.75-8.75S16.83 4.5 12 4.5c-1.26 0-2.45.26-3.53.74">
                            </path>
                          </svg>
                        </span>
                      </div>
                      <div class="flex min-w-0 flex-1 justify-between space-x-4 pt-1.5">
                        <div>
                          <p class="text-sm font-bold text-gray-500">{{ $history->room->numberWithFormat() }}</p>
                        </div>
                        <div class="text-sm text-right text-gray-500 whitespace-nowrap">
                          <time
                            datetime="2020-09-20">{{ \Carbon\Carbon::parse($history->end_date)->format('M. d, Y') }}</time>
                        </div>
                      </div>
                    </div>
                  </div>
                </li>
              @empty
              @endforelse


            </ul>
          </div>
        </div>
      </section>
    </div> --}}
  </div>

</div>
