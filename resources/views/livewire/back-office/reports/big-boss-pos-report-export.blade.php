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
                    <th>PAYMENT</th>
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
                            <strong>{{ $order['type'] }}</strong>
                            @if($order['voided']) <span class="badge-voided">VOID</span> @endif
                            @if($order['type'] === 'ROOM')
                                <div class="small">
                                    RM {{ $order['room_number'] ?? '—' }}
                                    @if($order['guest']) &middot; {{ $order['guest'] }} @endif
                                </div>
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
                    <td colspan="6">CASH SALES (non-voided)</td>
                    <td class="right">&#8369;{{ number_format($posSales['totals']['cash'], 2) }}</td>
                </tr>
                <tr class="strong">
                    <td colspan="6">ROOM-CHARGE SALES (non-voided)</td>
                    <td class="right">&#8369;{{ number_format($posSales['totals']['room'], 2) }}</td>
                </tr>
                <tr class="strong" style="background:#f3f4f6;">
                    <td colspan="6">GROSS POS</td>
                    <td class="right">&#8369;{{ number_format($posSales['totals']['gross'], 2) }}</td>
                </tr>
                @if($posSales['totals']['voided_count'] > 0)
                    <tr style="background:#fee2e2;color:#b91c1c;">
                        <td colspan="7">{{ $posSales['totals']['voided_count'] }} order(s) voided this shift &mdash; not counted in totals above.</td>
                    </tr>
                @endif
            </tfoot>
        </table>
    @endif

    {{-- STOCKS INVENTORY --}}
    <br><br>
    <div style="background:#eee;">
        <hr style="border-top:5px solid #ddd;"><center>
        <b style="font-size:25px;color:#888;">STOCKS INVENTORY</b>
        <hr style="border-top:5px solid #ddd;"></center>

    @if(empty($stocksInventory))
        <p>No inventory movement in this shift.</p>
    @else
        @foreach($stocksInventory as $parentName => $group)
            <table width="100%" style="font:15px 'Courier New', monospace; border-collapse:collapse; text-align:center;">
                <tr style="border:0;"><td colspan="14" style="border:0;"><br></td></tr>
                {{-- Parent category header --}}
                <tr>
                    <td colspan="14" style="background:#bbb; border:1px solid #888; padding:2px 5px; text-align:left;"><b>{{ $parentName }}</b></td>
                </tr>
                {{-- Column headers row 1 --}}
                <tr>
                    <td rowspan="3" style="border:1px solid #888; padding:2px 5px;">ITEM NAME</td>
                    <td rowspan="3" style="border:1px solid #888; padding:2px 5px;"><b>PRICE</b></td>
                    <td colspan="8" style="background:#ccc; border:1px solid #888; padding:2px 5px;"><b>STOCKS FLOW</b></td>
                    <td rowspan="3" style="background:#ddffc4; border:1px solid #888; padding:2px 5px;"><b>TOTAL<br>STOCKS<br>REM.</b></td>
                    <td rowspan="3" style="background:#edf0bd; border:1px solid #888; padding:2px 5px;"><b>COMBO<br>DED.</b></td>
                    <td rowspan="3" style="background:#d7fffc; border:1px solid #888; padding:2px 5px;"><b>STOCK<br>SOLD</b></td>
                    <td rowspan="3" style="border:1px solid #888; padding:2px 5px;"><b>TOTAL</b></td>
                </tr>
                {{-- Row 2: Kitchen / Frontdesk --}}
                <tr>
                    <td colspan="4" style="background:#edf0bd; border:1px solid #888; padding:2px 5px;"><b>KITCHEN</b></td>
                    <td colspan="4" style="background:#e1e1e1; border:1px solid #888; padding:2px 5px;"><b>FRONT DESK</b></td>
                </tr>
                {{-- Row 3: sub-headers --}}
                <tr>
                    <td style="background:#edf0bd; border:1px solid #888; padding:2px 3px;"><b>BEGIN.</b></td>
                    <td style="background:#edf0bd; border:1px solid #888; padding:2px 3px;"><b>STOCK-IN</b></td>
                    <td style="background:#edf0bd; border:1px solid #888; padding:2px 3px;"><b>STOCK-OUT</b></td>
                    <td style="background:#edf0bd; border:1px solid #888; padding:2px 3px;"><b>END</b></td>
                    <td style="background:#e1e1e1; border:1px solid #888; padding:2px 3px;"><b>BEGIN.</b></td>
                    <td style="background:#e1e1e1; border:1px solid #888; padding:2px 3px;"><b>STOCK-IN</b></td>
                    <td style="background:#e1e1e1; border:1px solid #888; padding:2px 3px;"><b>STOCK-OUT</b></td>
                    <td style="background:#e1e1e1; border:1px solid #888; padding:2px 3px;"><b>END</b></td>
                </tr>

                {{-- Items by sub-category --}}
                @foreach($group['categories'] as $categoryName => $category)
                    <tr>
                        <td style="border:1px solid #888; padding:2px 5px; text-align:left;" colspan="14"><b>{{ $categoryName }}</b></td>
                    </tr>
                    @foreach($category['items'] as $item)
                        <tr>
                            <td style="border:1px solid #888; padding:2px 5px; text-align:left;">&nbsp;&nbsp;&raquo; {{ strtolower($item['name']) }}</td>
                            <td style="border:1px solid #888; padding:2px 5px; text-align:right;">{{ number_format($item['price'], 2) }}</td>
                            <td style="background:#edf0bd; border:1px solid #888; padding:2px 3px;">{{ $item['kitchen']['opening'] ?: '' }}</td>
                            <td style="background:#edf0bd; border:1px solid #888; padding:2px 3px;">{{ $item['kitchen']['in'] ?: '' }}</td>
                            <td style="background:#edf0bd; border:1px solid #888; padding:2px 3px;">{{ $item['kitchen']['out'] ?: '' }}</td>
                            <td style="background:#edf0bd; border:1px solid #888; padding:2px 3px;">{{ $item['kitchen']['closing'] ?: '' }}</td>
                            <td style="background:#e1e1e1; border:1px solid #888; padding:2px 3px;">{{ $item['frontdesk']['opening'] ?: '' }}</td>
                            <td style="background:#e1e1e1; border:1px solid #888; padding:2px 3px;">{{ $item['frontdesk']['in'] ?: '' }}</td>
                            <td style="background:#e1e1e1; border:1px solid #888; padding:2px 3px;">{{ $item['frontdesk']['out'] ?: '' }}</td>
                            <td style="background:#e1e1e1; border:1px solid #888; padding:2px 3px;">{{ $item['frontdesk']['closing'] ?: '' }}</td>
                            <td style="background:#ddffc4; border:1px solid #888; padding:2px 3px;">{{ $item['total_remaining'] ?: '' }}</td>
                            <td style="background:#edf0bd; border:1px solid #888; padding:2px 3px;">{{ $item['combo_ded'] ?: '' }}</td>
                            <td style="background:#d7fffc; border:1px solid #888; padding:2px 3px;">{{ $item['stock_sold'] ?: '' }}</td>
                            <td style="border:1px solid #888; padding:2px 5px; text-align:right;"><b>{{ $item['sales_total'] > 0 ? number_format($item['sales_total'], 2) : '' }}</b></td>
                        </tr>
                    @endforeach
                @endforeach

                {{-- Total for parent category --}}
                <tr style="background:#000;">
                    <td colspan="12" style="background:#000;color:#fff; border:1px solid #888; padding:2px 5px; text-align:right;"><b>TOTAL {{ $parentName }} SALES:</b></td>
                    <td style="background:#000;color:#fff; border:1px solid #888; padding:2px 3px; text-align:right;">combo</td>
                    <td style="background:#000;color:#fff; border:1px solid #888; padding:2px 5px; text-align:right;"><b>{{ number_format($group['total_sales'], 2) }}</b></td>
                </tr>
            </table>
        @endforeach

        {{-- Summary --}}
        <table width="100%" style="font:15px 'Courier New', monospace; border-collapse:collapse;">
            <tr style="border:0;"><td colspan="14" style="border:0;"><br></td></tr>
            <tr>
                <td colspan="14" style="background:#ccc; border:1px solid #888; padding:4px 8px; text-align:center;"><b>SUMMARY</b></td>
            </tr>
            @foreach($stocksSummary as $line)
                <tr>
                    <td colspan="13" style="border:1px solid #888; padding:8px; text-align:right; font-family:'Courier New'; {{ $line['label'] === 'TOTAL SALES' ? 'font-size:20px;' : 'font-size:16px;' }}"><b>{{ $line['label'] }}</b></td>
                    <td style="border:1px solid #888; padding:8px; text-align:right; font-family:'Courier New'; {{ $line['label'] === 'TOTAL SALES' ? 'font-size:30px;' : 'font-size:20px;' }}"><b>{{ number_format($line['amount'], 2) }}</b></td>
                </tr>
            @endforeach
        </table>
    @endif
    </div>

</body>
</html>
