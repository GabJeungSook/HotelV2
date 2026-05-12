<div x-data="{ step: @entangle('steps') }">
  <div x-cloak x-show="step == 1">
    @include('kiosk.partials.select-type')
  </div>

  <div x-cloak x-show="step == 3">
    @include('kiosk.partials.select-rate')
  </div>

  <div x-cloak x-show="step == 4">
    @include('kiosk.partials.summary')
  </div>
  <div x-cloak x-show="step == 5">
    @include('kiosk.partials.qr-code')
  </div>
</div>
