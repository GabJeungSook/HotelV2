<div x-data="{ step: @entangle('steps') }">
  <div x-cloak x-show="step == 1">
    @include('kiosk.partials-checkout.input-qr-code')
  </div>
  <div x-cloak x-show="step == 2">
    @include('kiosk.partials-checkout.bill-summary')
  </div>
</div>
