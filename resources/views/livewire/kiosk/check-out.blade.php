<div x-data="{ step: @entangle('steps') }">
  <div x-cloak x-show="step == 1" x-effect="if (step == 1) $nextTick(() => document.getElementById('room_number')?.focus())">
    @include('kiosk.partials-checkout.input-room-number')
  </div>
  <div x-cloak x-show="step == 2" x-effect="if (step == 2) $nextTick(() => document.getElementById('qr_code')?.focus())">
    @include('kiosk.partials-checkout.input-qr-code')
  </div>
  <div x-cloak x-show="step == 3">
    @include('kiosk.partials-checkout.bill-summary')
  </div>
</div>
