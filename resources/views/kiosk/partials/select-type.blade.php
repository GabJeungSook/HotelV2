<div class="pt-10 ">
  <div class="flex items-end justify-between">
    <div>
      <h1 class="font-bold text-green-600">CHECK-IN</h1>
      <h1 class="text-3xl uppercase font-extrabold text-gray-600">Select room type</h1>
    </div>
  </div>
  <div class="mt-10">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      @foreach ($types as $type)
      @php $assignedRoom = $typeRoomMap[$type->id] ?? null; @endphp
      <button wire:key="{{ $type->id }}" wire:click="selectType({{ $type->id }}, {{ $assignedRoom ? $assignedRoom->id : 'null' }})" type="button" class="transition-all duration-200 {{ $type_id == $type->id ? 'scale-105' : 'opacity-80 hover:opacity-100' }} {{ !$assignedRoom ? 'opacity-40 cursor-not-allowed' : '' }}">
        <div class="h-72 rounded-2xl overflow-hidden relative grid place-content-center border-4 transition-all duration-200 {{ $type_id == $type->id ? 'border-green-500 bg-green-50 shadow-2xl shadow-green-200 ring-4 ring-green-200' : 'border-gray-200 bg-gray-50' }}">
          @if ($type_id == $type->id)
            <div class="absolute top-3 right-3 bg-green-500 text-white rounded-full w-12 h-12 flex items-center justify-center shadow-lg">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
              </svg>
            </div>
          @endif
           @switch($type->id)
               @case($type->id == 1)
               <svg class="mx-auto mb-10" width="96" height="96" viewBox="0 0 62 62" fill="none" xmlns="http://www.w3.org/2000/svg">
                <g clip-path="url(#clip0_2_5)">
                <path d="M62 0H0V62H62V0Z" fill="white" fill-opacity="0.01"/>
                <path d="M10.3333 15.5C10.3333 13.3598 12.0682 11.625 14.2083 11.625H47.7917C49.9318 11.625 51.6667 13.3598 51.6667 15.5V29.7083H10.3333V15.5Z" stroke="black" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M7.75 45.2083V50.375" stroke="black" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M54.25 45.2083V50.375" stroke="black" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M37.4583 23.25H24.5417C22.4015 23.25 20.6667 24.9848 20.6667 27.125V29.7083H41.3333V27.125C41.3333 24.9848 39.5985 23.25 37.4583 23.25Z" fill="#2F88FF" stroke="black" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M5.16666 33.5833C5.16666 31.4432 6.90156 29.7083 9.04166 29.7083H52.9583C55.0985 29.7083 56.8333 31.4432 56.8333 33.5833V45.2083H5.16666V33.5833Z" stroke="black" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                </g>
                <defs>
                <clipPath id="clip0_2_5">
                <rect width="62" height="62" fill="white"/>
                </clipPath>
                </defs>
                </svg>
                @break
                @case($type->id == 2)
                <svg class="mx-auto mb-10" width="96" height="96" viewBox="0 0 67 67" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M67 0H0V67H67V0Z" fill="white" fill-opacity="0.01"/>
                    <path d="M8 18.0714C8 15.8228 9.8888 14 12.2188 14H48.7812C51.1113 14 53 15.8228 53 18.0714V33H8V18.0714Z" stroke="black" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M5 50V55" stroke="black" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M56 50V55" stroke="black" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M24.75 26H16.25C13.9027 26 12 27.8803 12 30.2V33H29V30.2C29 27.8803 27.0973 26 24.75 26Z" fill="#2F88FF" stroke="black" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M44.75 26H36.25C33.9027 26 32 27.8803 32 30.2V33H49V30.2C49 27.8803 47.0973 26 44.75 26Z" fill="#2F88FF" stroke="black" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M3 37.25C3 34.9027 4.84683 33 7.125 33H53.875C56.1532 33 58 34.9027 58 37.25V50H3V37.25Z" stroke="black" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                @break
                @case($type->id == 3)
                <div class="flex space-x-4">
                    <svg class="mx-auto mb-10" width="86" height="86" viewBox="0 0 62 62" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <g clip-path="url(#clip0_2_5)">
                        <path d="M62 0H0V62H62V0Z" fill="white" fill-opacity="0.01"/>
                        <path d="M10.3333 15.5C10.3333 13.3598 12.0682 11.625 14.2083 11.625H47.7917C49.9318 11.625 51.6667 13.3598 51.6667 15.5V29.7083H10.3333V15.5Z" stroke="black" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M7.75 45.2083V50.375" stroke="black" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M54.25 45.2083V50.375" stroke="black" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M37.4583 23.25H24.5417C22.4015 23.25 20.6667 24.9848 20.6667 27.125V29.7083H41.3333V27.125C41.3333 24.9848 39.5985 23.25 37.4583 23.25Z" fill="#2F88FF" stroke="black" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M5.16666 33.5833C5.16666 31.4432 6.90156 29.7083 9.04166 29.7083H52.9583C55.0985 29.7083 56.8333 31.4432 56.8333 33.5833V45.2083H5.16666V33.5833Z" stroke="black" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                        </g>
                        <defs>
                        <clipPath id="clip0_2_5">
                        <rect width="62" height="62" fill="white"/>
                        </clipPath>
                        </defs>
                        </svg>
                        <svg class="mx-auto mb-10" width="86" height="86" viewBox="0 0 62 62" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <g clip-path="url(#clip0_2_5)">
                            <path d="M62 0H0V62H62V0Z" fill="white" fill-opacity="0.01"/>
                            <path d="M10.3333 15.5C10.3333 13.3598 12.0682 11.625 14.2083 11.625H47.7917C49.9318 11.625 51.6667 13.3598 51.6667 15.5V29.7083H10.3333V15.5Z" stroke="black" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M7.75 45.2083V50.375" stroke="black" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M54.25 45.2083V50.375" stroke="black" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M37.4583 23.25H24.5417C22.4015 23.25 20.6667 24.9848 20.6667 27.125V29.7083H41.3333V27.125C41.3333 24.9848 39.5985 23.25 37.4583 23.25Z" fill="#2F88FF" stroke="black" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M5.16666 33.5833C5.16666 31.4432 6.90156 29.7083 9.04166 29.7083H52.9583C55.0985 29.7083 56.8333 31.4432 56.8333 33.5833V45.2083H5.16666V33.5833Z" stroke="black" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                            </g>
                            <defs>
                            <clipPath id="clip0_2_5">
                            <rect width="62" height="62" fill="white"/>
                            </clipPath>
                            </defs>
                            </svg>
                </div>
                @break

               @default
               <svg class="mx-auto mb-10" width="64" height="64" viewBox="0 0 62 62" fill="none" xmlns="http://www.w3.org/2000/svg">
                <g clip-path="url(#clip0_2_5)">
                <path d="M62 0H0V62H62V0Z" fill="white" fill-opacity="0.01"/>
                <path d="M10.3333 15.5C10.3333 13.3598 12.0682 11.625 14.2083 11.625H47.7917C49.9318 11.625 51.6667 13.3598 51.6667 15.5V29.7083H10.3333V15.5Z" stroke="black" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M7.75 45.2083V50.375" stroke="black" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M54.25 45.2083V50.375" stroke="black" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M37.4583 23.25H24.5417C22.4015 23.25 20.6667 24.9848 20.6667 27.125V29.7083H41.3333V27.125C41.3333 24.9848 39.5985 23.25 37.4583 23.25Z" fill="#2F88FF" stroke="black" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M5.16666 33.5833C5.16666 31.4432 6.90156 29.7083 9.04166 29.7083H52.9583C55.0985 29.7083 56.8333 31.4432 56.8333 33.5833V45.2083H5.16666V33.5833Z" stroke="black" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                </g>
                <defs>
                <clipPath id="clip0_2_5">
                <rect width="62" height="62" fill="white"/>
                </clipPath>
                </defs>
                </svg>

           @endswitch

                <h1 class="text-[2rem] font-extrabold uppercase relative {{ $type_id == $type->id ? 'text-green-700' : 'text-gray-700' }}">{{ $type->name }}</h1>
                @if ($assignedRoom)
                  <div class="mt-1 relative">
                    <span class="text-lg font-bold {{ $type_id == $type->id ? 'text-green-600' : 'text-gray-500' }}">
                        {{ $assignedRoom->numberWithFormat() }}</span>
                    <span class="text-base {{ $type_id == $type->id ? 'text-green-500' : 'text-gray-400' }}">· {{ $assignedRoom->floor->numberWithFormat() }}</span>
                  </div>
                @else
                  <div class="mt-1 relative">
                    <span class="text-lg font-semibold text-red-400">No available room</span>
                  </div>
                @endif
        </div>
        </button>
      @endforeach
    </div>
  </div>
  <div class="fixed bottom-20 right-0 left-0">
    <div class="flex justify-center">
      @if ($type_id)
        <button
          wire:click="$set('steps', 3)"
          class="font-medium px-8 py-3 text-white bg-green-600 rounded-2xl flex items-center gap-2 shadow-xl ring-4 ring-green-200 animate-pulse hover:animate-none hover:bg-green-700 transition-colors">

          NEXT

          <svg xmlns="http://www.w3.org/2000/svg"
              class="w-14 h-14"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M13 7l5 5m0 0l-5 5m5-5H6" />
          </svg>

      </button>
      @endif
    </div>
  </div>
</div>
