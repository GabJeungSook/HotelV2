<x-kiosk-layout-update>
  <div class="px-4 md:px-10 py-6" x-data="{ agreed: false }">
    <h1 class="text-3xl md:text-4xl text-gray-700 uppercase font-extrabold text-center">House Rules</h1>
    <p class="text-center text-gray-500 mt-2 text-sm">Please read and agree to our house rules before proceeding.</p>

    <div class="mt-6 max-w-4xl mx-auto bg-gray-50 rounded-xl border border-gray-200 p-6 md:p-8 overflow-y-auto max-h-[55vh] text-sm text-gray-700 leading-relaxed space-y-4">
      <p class="font-semibold text-gray-800">TO OUR VALUED GUESTS, WE WOULD LIKE TO ASK FOR YOUR ATTENTION AND COOPERATION IN FOLLOWING OUR HOUSE RULES.</p>

      <ol class="list-decimal list-inside space-y-3">
        <li><span class="font-semibold">TARIFF.</span> The tariff is for the room only.</li>
        <li><span class="font-semibold">SINGLE-SIZED BED ROOM.</span> Good for only 1 person.</li>
        <li><span class="font-semibold">DOUBLE SIZED BED ROOM & TWIN BED - 2 SINGLE BED.</span> Good for 2 persons only. If caught with extra person, guests will be <span class="font-bold text-red-600">"DOUBLE CHARGED FOR THE EXTRA PERSON"</span>.</li>
        <li><span class="font-semibold">SETTLEMENT OF BILLS.</span> Bills must be settled on presentation by payment in cash. Full payment for the room/s will be collected upon check-in.</li>
        <li><span class="font-semibold">SENIOR CITIZENS & PWDs.</span> Please present your Senior Citizen or PWD card upon check-in for the discount.</li>
        <li><span class="font-semibold">DEPOSIT.</span> A deposit of PHP200 per room for the key and TV remote controls will also be collected upon check-in. REFUNDABLE upon check-out.</li>
        <li><span class="font-semibold">CHECK-IN & CHECK-OUT.</span> Guest may check-in anytime. Check-in time will start at the time of arrival. Check-out time depends on the room rate availed by the guest. Guest may check-out earlier than their expected time of check-out. Unused time and time extensions are non-refundable or consumable once checked-out.</li>
        <li>Please remove your <span class="font-semibold">CAP, BONNET, HELMET, SUNGLASSES, & HOOD</span> before entering and while inside the establishment.</li>
        <li>Guests are requested to report any damaged, lost, malfunction or failure of amenities, equipment, and fixtures to the staff immediately.</li>
        <li>Guests are to use their rooms for the agreed period. Failure to check-out by the agreed period will result in an additional fee for extension. Should you wish to extend your stay, kindly inform the Front Desk immediately.</li>
        <li><span class="font-semibold">VISITORS</span> are advised to wait at the lobby only.</li>
        <li>This is a <span class="font-semibold">NON-SMOKING</span> establishment. Smoking is strictly prohibited inside the room. If caught, you will be charged <span class="font-bold text-red-600">PHP200</span>.</li>
        <li><span class="font-semibold">ALCOHOLIC BEVERAGES.</span> Any alcoholic beverages are not allowed inside the establishment. If caught, you will be charged <span class="font-bold text-red-600">PHP200</span>.</li>
        <li><span class="font-semibold">DURIAN & MARANG</span> are strictly prohibited inside the room. If caught you will be charged <span class="font-bold text-red-600">PHP200</span>.</li>
        <li><span class="font-semibold">PETS ARE NOT ALLOWED</span> INSIDE THE ESTABLISHMENT.</li>
        <li><span class="font-semibold">FIREARMS & WEAPONS, HAZARDOUS GOODS, COMBUSTIBLE ARTICLES, & TOOLS</span> ARE STRICTLY PROHIBITED.</li>
        <li><span class="font-semibold">DAMAGE & LOSS</span> of company property by the guest will be charged accordingly upon checking out. Removing fixtures (TV, TELEPHONE, AIRCON, etc.) inside the room is prohibited. If caught, the guest will be advised to vacate.</li>
        <li><span class="font-semibold">VANDALISM IS STRICTLY PROHIBITED.</span> Any form of vandalism made by the guest will be charged <span class="font-bold text-red-600">PHP500</span>.</li>
        <li><span class="font-semibold">KEEP YOUR DOOR LOCK CLOSED</span> when not in the room or when you are sleeping. The management will not in any way whatsoever, be responsible for any loss of the guest's belongings or any other property (money, jewelry, documents or other articles of value).</li>
        <li><span class="font-semibold">ROOM KEYS</span> must be deposited at the Front Desk whenever guest leaves the premise and at the time of check-out.</li>
        <li>Guests are advised to be responsible in using the facilities and to observe cleanliness all the time.</li>
      </ol>

      <div class="mt-4 pt-3 border-t border-gray-300 text-sm text-gray-600">
        <p>To contact the Front Desk please dial <span class="font-bold">"0"</span></p>
        <p>To contact the Kitchen please dial <span class="font-bold">"125"</span></p>
        <p class="mt-2 font-semibold text-gray-800">THE MANAGEMENT</p>
      </div>
    </div>

    <div class="mt-6 max-w-4xl mx-auto">
      <label class="flex items-center space-x-3 cursor-pointer select-none">
        <input type="checkbox" x-model="agreed" class="w-5 h-5 rounded border-gray-300 text-[#00a0f5] focus:ring-[#00a0f5]">
        <span class="text-gray-700 font-medium">I have read and agree to the House Rules</span>
      </label>
    </div>

    <div class="mt-6 flex justify-center">
      <a x-bind:href="agreed ? '{{ route('kiosk.dashboard') }}' : '#'"
         x-bind:class="agreed ? 'bg-[#00a0f5] hover:bg-[#0088d4] cursor-pointer' : 'bg-gray-300 cursor-not-allowed'"
         @click="if(!agreed) $event.preventDefault()"
         class="px-16 py-4 text-white font-bold text-lg rounded-xl transition duration-200 uppercase">
        Proceed
      </a>
    </div>
  </div>
</x-kiosk-layout-update>
