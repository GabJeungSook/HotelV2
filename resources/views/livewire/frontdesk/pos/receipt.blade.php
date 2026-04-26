{{--
    POS receipt partial — server-rendered, printer-agnostic.

    Required vars (passed in from PointOfSale render() / @include):
      $order         App\Models\PosOrder (with lineItems eager-loaded)
      $branch        App\Models\Branch
      $cashier_name  string
      $room_number   ?string  (null for cash sales)
      $guest_name    ?string  (null for cash sales)

    Layout rules:
      - Single narrow column, monospace, left-aligned
      - No backgrounds / images so 58mm/80mm thermal printers render
      - Same HTML works on A4/Letter — the browser print dialog handles it
--}}
<div id="pos-receipt-printable" class="pos-receipt">
    <div class="r-center r-strong">{{ strtoupper($branch->name ?? 'POS RECEIPT') }}</div>
    @if(!empty($branch->address))
        <div class="r-center r-small">{{ $branch->address }}</div>
    @endif

    <div class="r-rule"></div>

    <div class="r-row"><span>Date:</span><span>{{ $order->created_at->format('M j, Y g:i A') }}</span></div>
    <div class="r-row"><span>Cashier:</span><span>{{ $cashier_name }}</span></div>
    <div class="r-row"><span>Order:</span><span>{{ sprintf('OR-%05d', $order->id) }}</span></div>

    <div class="r-rule"></div>

    {{-- Line items: name on its own line, qty x unit = total on the next --}}
    @foreach($order->lineItems as $item)
        <div class="r-item-name">{{ $item->item_name }}</div>
        <div class="r-row r-small">
            <span>{{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }} &times; &#8369;{{ number_format((int) $item->unit_price, 2) }}</span>
            <span>&#8369;{{ number_format((int) $item->payable_amount, 2) }}</span>
        </div>
    @endforeach

    <div class="r-rule"></div>

    <div class="r-row"><span>Subtotal</span><span>&#8369;{{ number_format((int) $order->subtotal, 2) }}</span></div>
    @if((int) $order->discount_amount > 0)
        <div class="r-row">
            <span>Discount{{ trim((string) $order->discount_reason) !== '' ? ' (' . $order->discount_reason . ')' : '' }}</span>
            <span>-&#8369;{{ number_format((int) $order->discount_amount, 2) }}</span>
        </div>
    @endif
    <div class="r-row r-strong">
        <span>TOTAL</span>
        <span>&#8369;{{ number_format((int) $order->total, 2) }}</span>
    </div>

    <div class="r-rule"></div>

    @if($order->payment_method === 'cash')
        <div class="r-row"><span>CASH</span><span>&#8369;{{ number_format((int) $order->paid_amount, 2) }}</span></div>
        @if((int) $order->change_amount > 0)
            <div class="r-row"><span>CHANGE</span><span>&#8369;{{ number_format((int) $order->change_amount, 2) }}</span></div>
        @endif
    @else
        <div class="r-row r-strong"><span>ROOM CHARGE</span><span>&nbsp;</span></div>
        <div class="r-row r-small">
            <span>RM {{ $room_number ?? '—' }}</span>
            <span>{{ $guest_name ?? '' }}</span>
        </div>
        <div class="r-center r-small r-mt">Will be settled at guest checkout.</div>
    @endif

    <div class="r-rule"></div>

    <div class="r-center r-small">Thank you!</div>
    @if($order->voided_at !== null)
        <div class="r-center r-strong" style="margin-top:8px; color:#b91c1c;">** VOIDED **</div>
    @endif
</div>
