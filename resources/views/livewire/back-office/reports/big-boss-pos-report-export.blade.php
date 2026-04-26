<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $branchName }} POS — {{ $selectedSession['shift_date'] ?? '' }} {{ $selectedSession['shift_type'] ?? '' }} SHIFT</title>
    <style>
        body { font-family: Arial, sans-serif; color: #000; padding: 24px; }
        h1 { font-size: 18px; text-align: center; margin: 0 0 4px; }
        h2 { font-size: 14px; text-align: center; margin: 0 0 12px; }
        h3 { font-size: 13px; background: #f3f4f6; padding: 6px 10px; margin: 16px 0 8px; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        th, td { border: 1px solid #000; padding: 4px 6px; vertical-align: top; }
        thead { background: #f3f4f6; }
        .right { text-align: right; }
        .center { text-align: center; }
        .strong { font-weight: 700; }
        .small { font-size: 10px; }
        .voided { color: #888; text-decoration: line-through; }
        .badge-voided { background: #fee2e2; color: #b91c1c; padding: 1px 4px; font-size: 9px; font-weight: 700; }
        .meta { font-size: 12px; text-align: center; margin-bottom: 12px; }
        .meta div { margin: 2px 0; }
        @page { margin: 12mm; }
    </style>
</head>
<body>

    <h1>{{ $branchName }}</h1>
    <h2>POS — DAILY SHIFT REPORT</h2>

    <div class="meta">
        <div>{{ $selectedSession['shift_type'] }} Shift &middot; {{ $selectedSession['date_formatted'] }}</div>
        <div>{{ $selectedSession['time_in_formatted'] }} &mdash; {{ $selectedSession['time_out_formatted'] }}</div>
        <div>Frontdesk: {{ $selectedSession['frontdesks'] }}</div>
    </div>

    {{-- POS SALES --}}
    <h3>POS SALES</h3>
    @if(empty($posSales['orders']))
        <p>No POS sales rung in this shift.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>TIME</th>
                    <th>ORDER</th>
                    <th>CASHIER</th>
                    <th>TYPE</th>
                    <th>GUEST / ROOM</th>
                    <th>ITEMS</th>
                    <th class="right">SUBTOTAL</th>
                    <th class="right">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                @foreach($posSales['orders'] as $order)
                    <tr class="{{ $order['voided'] ? 'voided' : '' }}">
                        <td>{{ $order['time'] }}</td>
                        <td>{{ sprintf('OR-%05d', $order['id']) }}</td>
                        <td>{{ $order['cashier'] }}</td>
                        <td>
                            {{ $order['type'] }}
                            @if($order['voided']) <span class="badge-voided">VOID</span> @endif
                        </td>
                        <td>
                            @if($order['type'] === 'ROOM')
                                RM {{ $order['room_number'] ?? '—' }}
                                @if($order['guest']) &middot; {{ $order['guest'] }} @endif
                            @else
                                &mdash;
                            @endif
                        </td>
                        <td>{{ $order['items'] }}</td>
                        <td class="right">&#8369;{{ number_format($order['subtotal'], 2) }}</td>
                        <td class="right strong">&#8369;{{ number_format($order['total'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="strong">
                    <td colspan="7">CASH SALES (non-voided)</td>
                    <td class="right">&#8369;{{ number_format($posSales['totals']['cash'], 2) }}</td>
                </tr>
                <tr class="strong">
                    <td colspan="7">ROOM-CHARGE SALES (non-voided)</td>
                    <td class="right">&#8369;{{ number_format($posSales['totals']['room'], 2) }}</td>
                </tr>
                <tr class="strong" style="background:#f3f4f6;">
                    <td colspan="7">GROSS POS</td>
                    <td class="right">&#8369;{{ number_format($posSales['totals']['gross'], 2) }}</td>
                </tr>
                @if($posSales['totals']['voided_count'] > 0)
                    <tr style="background:#fee2e2;color:#b91c1c;">
                        <td colspan="8">{{ $posSales['totals']['voided_count'] }} order(s) voided this shift &mdash; not counted in totals above.</td>
                    </tr>
                @endif
            </tfoot>
        </table>
    @endif

    {{-- INVENTORY --}}
    <h3>INVENTORY MOVEMENT</h3>
    <p class="small">Only items with movement during the shift's time window are listed.</p>

    @if(empty($inventoryRows))
        <p>No inventory movement in this shift.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>SOURCE</th>
                    <th>ITEM</th>
                    <th class="right">OPENING</th>
                    <th class="right">IN</th>
                    <th class="right">OUT</th>
                    <th class="right">CLOSING</th>
                </tr>
            </thead>
            <tbody>
                @foreach($inventoryRows as $row)
                    <tr>
                        <td style="text-transform:uppercase;font-size:10px;">{{ $row['source_type'] }}</td>
                        <td>{{ $row['item_name'] }}</td>
                        <td class="right">{{ rtrim(rtrim(number_format($row['opening'], 2), '0'), '.') }}</td>
                        <td class="right">@if($row['in'] > 0) +{{ rtrim(rtrim(number_format($row['in'], 2), '0'), '.') }} @else &mdash; @endif</td>
                        <td class="right">@if($row['out'] > 0) -{{ rtrim(rtrim(number_format($row['out'], 2), '0'), '.') }} @else &mdash; @endif</td>
                        <td class="right strong">{{ rtrim(rtrim(number_format($row['closing'], 2), '0'), '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

</body>
</html>
