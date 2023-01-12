<div>
  <div class="flex items-end justify-between">
    <div>
      <h1 class="font-bold text-green-400">CHECK-IN</h1>
      <h1 class="text-4xl uppercase font-extrabold text-gray-50">Fill-Up Information </h1>
    </div>
    <div>
      @if ($steps == 1)
        <a href="{{ route('kiosk.dashboard') }}"
          class="bg-gradient-to-r from-red-500 via-red-500 to-transparent p-2 px-4 flex space-x-1 rounded-full">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
            class="w-6 text-white h-6">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 15.75L3 12m0 0l3.75-3.75M3 12h18" />
          </svg>
          <span class="font-semibold text-gray-100 uppercase">Back</span>
        </a>
      @else
        <button x-on:click="step--"
          class="bg-gradient-to-r from-red-500 via-red-500 to-transparent p-2 px-4 flex space-x-1 rounded-full">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
            stroke="currentColor" class="w-6 text-white h-6">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 15.75L3 12m0 0l3.75-3.75M3 12h18" />
          </svg>
          <span class="font-semibold text-gray-100 uppercase">Back</span>
        </button>
      @endif
    </div>
  </div>
  <div class="px-40 mt-5 ">
    <div class="w-full flex space-x-5 bg-gray-50 bg-opacity-75 rounded-2xl ">
      <div class="w-96 border-r-2 border-gray-600  p-5">
        <h1 class="font-medium text-xl text-gray-700">Personal Information</h1>

        <div class="mt-4 bg-white p-2 py-3 rounded-lg flex flex-col space-y-3">
          <x-input label="Complete Name" wire:model="name" />
          <div>
            <label for="company-website" class="block text-sm font-medium text-gray-700">Contact
              Number(Optional)</label>
            <div class="mt-1 flex rounded-md shadow-sm">
              <span
                class="inline-flex items-center rounded-l-md border border-r-0 border-gray-300 bg-gray-50 px-3 text-gray-500 sm:text-sm">09</span>
              <input type="number" wire:model="contact"
                class="block w-full min-w-0 flex-1 rounded-none rounded-r-md border-gray-300 px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            </div>
            @error('contact')
              <span class="text-sm text-red-600">{{ $message }}</span>
            @enderror
          </div>

        </div>

        <div class=" w-full mt-24">
          <div class="flex flex-col space-y-3">
            <div
              class="relative flex h-40 flex-col justify-between overflow-hidden rounded-lg bg-white before:absolute before:bottom-[2.5rem] before:-left-2 before:h-5 before:w-5 before:rounded-full before:bg-gray-300 after:absolute after:bottom-[2.5rem] after:-right-2 after:h-5 after:w-5 after:rounded-full after:bg-gray-300">
              <div class="flex flex-col justify-between flex-1">
                <section class="p-3">
                  <h1 class="font-bold text-gray-600 uppercase">Overall Summary</h1>
                  <div class="flex justify-between mt-4 text-gray-600">
                    <dt>Subtotal</dt>
                    <dd class="">&#8369;<span>{{ number_format($room_pay + 200, 2) }}</span>
                    </dd>
                  </div>
                </section>
                <section class="border border-gray-400 border-dashed"></section>
              </div>
              <section class="p-3">
                <div class="flex justify-between text-green-600">
                  <dt class="font-bold text-xl">Total</dt>
                  <dd class="font-semibold text-lg">
                    &#8369;<span>{{ number_format($room_pay + 200, 2) }}</span>
                  </dd>
                </div>
              </section>
            </div>
          </div>
        </div>

      </div>
      <div class="flex-1 p-5 px-10 ">
        <h1 class="font-medium text-xl uppercase text-gray-700">Check-In Details</h1>
        <div class="bg-white rounded-xl ">
          <div class="w-full">
            <div class="mt-5  border-gray-200">
              <div class="flex space-x-6 px-2  border-gray-200 py-5">
                <svg class="w-64 h-64" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 28 24" fill="currentColor">
                  <path
                    d="m5.638 22.405v.001c0 .881-.714 1.595-1.595 1.595-.002 0-.003 0-.005 0h-.001c-.881 0-1.594-.714-1.594-1.594 0-.001 0-.001 0-.002v-20.809-.001c0-.881.714-1.594 1.594-1.594h.001.005c.881 0 1.595.714 1.595 1.595z">
                  </path>
                  <path d="m2.014 2.686h-1.115c-.487.015-.876.413-.876.902s.389.887.874.902h.001 1.115z"></path>
                  <path
                    d="m25.604 5.95h-16c-1.465.002-2.652 1.189-2.654 2.654v9.246.002c0 1.465 1.187 2.652 2.652 2.652h.002 16c1.465-.002 2.652-1.189 2.654-2.654v-9.246c0-.001 0-.002 0-.002 0-1.465-1.187-2.652-2.652-2.652-.001 0-.001 0-.002 0zm1.28 11.9c-.003.703-.572 1.272-1.275 1.275h-16s-.001 0-.002 0c-.703 0-1.273-.57-1.273-1.273 0-.001 0-.002 0-.003v-9.246c.003-.703.572-1.272 1.274-1.275h16c.703.003 1.272.572 1.275 1.275z">
                  </path>
                  <path
                    d="m12.101 5.437c.514 0 .93-.417.93-.93v-1.86c0-.008 0-.018 0-.027 0-.514-.417-.93-.93-.93s-.93.417-.93.93v.029-.001 1.86c0 .513.416.929.93.93z">
                  </path>
                  <path
                    d="m23.11 5.437c.514 0 .93-.417.93-.93v-1.86c0-.514-.417-.93-.93-.93s-.93.417-.93.93v1.86c0 .514.417.93.93.93z">
                  </path>
                  <path d="m13.487 2.686h8.235v1.804h-8.235z"></path>
                  <path d="m27.577 3.577c-.001-.491-.399-.889-.89-.89h-2.061v1.804h2.06c.491-.002.889-.399.89-.89z">
                  </path>
                  <path d="m12.236 15.011h-.97v-.898h-.89v2.574h.89v-1.04h.97v1.04h.89v-2.574h-.89z"></path>
                  <path
                    d="m16.198 14.41c-.304-.216-.684-.345-1.093-.345-.414 0-.797.132-1.11.357l.006-.004c-.243.226-.395.548-.395.905 0 .026.001.051.002.077v-.003c-.001.017-.001.037-.001.057 0 .258.075.499.205.702l-.003-.005c.125.193.299.344.505.438l.007.003c.213.087.461.138.72.138.028 0 .056-.001.084-.002h-.004c.015 0 .033.001.051.001.271 0 .529-.059.761-.165l-.011.005c.207-.099.375-.253.487-.444l.003-.005c.106-.198.169-.433.169-.682 0-.019 0-.039-.001-.058v.003c.001-.022.002-.048.002-.074 0-.352-.147-.67-.383-.895zm-.657 1.556c-.103.104-.247.169-.405.169-.013 0-.026 0-.038-.001h.002c-.008 0-.018.001-.027.001-.161 0-.307-.064-.414-.169-.1-.159-.16-.353-.16-.56s.059-.401.162-.564l-.003.004c.115-.105.268-.168.437-.168s.322.064.437.169h-.001c.102.123.164.283.164.457 0 .026-.001.051-.004.076v-.003c.003.028.005.061.005.094 0 .186-.059.358-.158.5l.002-.003z">
                  </path>
                  <path d="m16.751 14.746h.906v1.941h.89v-1.941h.906v-.634h-2.702z"></path>
                  <path d="m20.745 15.597h1.38v-.53h-1.38v-.409h1.484v-.546h-2.374v2.574h2.422v-.586h-1.532z"></path>
                  <path d="m23.639 14.113h-.89v2.574h2.278v-.634h-1.387z"></path>
                  <path d="m10.609 12.028.834-.442.842.442-.16-.93.674-.658-.93-.136-.425-.85-.417.85-.93.136.674.658z">
                  </path>
                  <path
                    d="m13.696 12.028.834-.442.834.442-.16-.93.673-.658-.93-.136-.417-.85-.417.85-.938.136.674.658z">
                  </path>
                  <path
                    d="m16.775 12.028.834-.442.834.442-.16-.93.674-.658-.93-.136-.417-.85-.417.85-.938.136.682.658z">
                  </path>
                  <path
                    d="m19.854 12.028.834-.442.834.442-.16-.93.674-.658-.93-.136-.417-.85-.417.85-.938.136.682.658z">
                  </path>
                  <path d="m23.766 9.454-.417.85-.93.136.673.658-.16.93.834-.442.834.442-.16-.93.682-.658-.938-.136z">
                  </path>
                  <path d="m6.142 2.686h4.45v1.804h-4.45z"></path>
                </svg>
                <div class="w-full border-l-2 relative">
                  <div class="flex px-4 justify-between items-end">
                    <div>
                      <h1 class="font-bold text-green-700 uppercase">{{ $room_type }}</h1>
                      <h1 class="text-gray-600">RM #{{ $room_number }} | {{ $room_floor }}</h1>
                      <h1 class="text-gray-600">{{ $room_rate }} Hour</h1>
                    </div>
                    <div class="font-bold text-gray-700 text-xl">&#8369;{{ number_format($room_pay, 2) }}</div>
                  </div>
                  <div class="flex mt-4 px-4 justify-between items-end">
                    <div>
                      <h1 class="font-bold text-green-700 underline uppercase">CHECK-IN DEPOSIT</h1>
                      <h1 class="text-gray-600">Room key & TV Remote</h1>
                    </div>
                    <div class="font-bold text-xl text-gray-700">&#8369;200.00</div>
                  </div>

                  <div class="absolute bottom-0 right-0 left-0 px-4">
                    <div class="flex border-t py-1 justify-between">
                      <div class="font-bold text-gray-600">TOTAL CHARGE</div>
                      <div class="font-bold text-gray-700 text-xl">&#8369;{{ number_format($room_pay + 200, 2) }}</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="mt-5 flex justify-center w-full">
          @if ($name)
            <x-button rounded positive label="CONFIRM TRANSACTION" wire:click="confirmTransaction" class="font-bold" lg
              right-icon="chevron-double-right" />
          @endif
        </div>
      </div>
    </div>
  </div>
</div>
