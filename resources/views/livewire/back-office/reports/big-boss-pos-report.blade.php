<div class="max-w-full mx-auto py-8 px-4 sm:px-6 lg:px-8">

    <style>
        @media print {
            .no-print { display: none !important; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>

    {{-- Shift Selector --}}
    <div class="no-print bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Select Shift</label>
                <select wire:model="selectedShiftLogId"
                        class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">-- Select a completed shift --</option>
                    @foreach($availableShiftSessions as $session)
                        <option value="{{ $session['id'] }}">{{ $session['label'] }}</option>
                    @endforeach
                </select>
                @if(empty($availableShiftSessions))
                    <p class="text-xs text-gray-500 mt-1">No completed shifts found.</p>
                @endif
            </div>
            <div class="flex items-end gap-2">
                <button onclick="window.print()" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium">
                    Print Report
                </button>
                <button wire:click="exportHtml"
                        wire:loading.attr="disabled"
                        class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm font-medium disabled:opacity-50">
                    <span wire:loading.remove wire:target="exportHtml">Export HTML</span>
                    <span wire:loading wire:target="exportHtml">Exporting...</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Report Content --}}
    <div id="report-content" class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden p-8">

        {{-- Header --}}
        <div class="text-center mb-6">
            <h1 class="text-xl font-bold">{{ \App\Models\Branch::find(auth()->user()->branch_id)?->name ?? 'Branch' }}</h1>
            <h2 class="text-lg font-bold mt-2">POS — DAILY SHIFT REPORT</h2>
            @if($selectedSession)
                <p class="text-sm text-gray-700 mt-2">
                    {{ $selectedSession['shift_type'] }} Shift &middot;
                    {{ $selectedSession['date_formatted'] }} &middot;
                    {{ $selectedSession['time_in_formatted'] }} &mdash; {{ $selectedSession['time_out_formatted'] }}
                </p>
                <p class="text-xs text-gray-500">Frontdesk: {{ $selectedSession['frontdesks'] }}</p>
            @endif
        </div>

        @if(!$selectedSession)
            <div class="text-center text-gray-500 py-12">
                Select a completed shift above to see its POS sales and inventory movements.
            </div>
        @else

            {{-- ==================== POS SALES SECTION ==================== --}}
            <section class="mb-10">
                <h3 class="text-base font-bold bg-gray-100 px-3 py-2 rounded">POS SALES</h3>

                @if(empty($posSales['orders']))
                    <p class="text-sm text-gray-500 px-3 py-4">No POS sales rung in this shift.</p>
                @else
                    <table class="w-full text-sm border-collapse mt-3">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="border border-gray-300 px-2 py-1 text-left">TIME</th>
                                <th class="border border-gray-300 px-2 py-1 text-left">ORDER</th>
                                <th class="border border-gray-300 px-2 py-1 text-left">CASHIER</th>
                                <th class="border border-gray-300 px-2 py-1 text-left">PAYMENT</th>
                                <th class="border border-gray-300 px-2 py-1 text-left">ITEMS</th>
                                <th class="border border-gray-300 px-2 py-1 text-right">SUBTOTAL</th>
                                <th class="border border-gray-300 px-2 py-1 text-right">TOTAL</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($posSales['orders'] as $order)
                                <tr class="{{ $order['voided'] ? 'text-gray-400 line-through' : '' }}">
                                    <td class="border border-gray-300 px-2 py-1">{{ $order['time'] }}</td>
                                    <td class="border border-gray-300 px-2 py-1 font-mono">{{ sprintf('OR-%05d', $order['id']) }}</td>
                                    <td class="border border-gray-300 px-2 py-1">{{ $order['cashier'] }}</td>
                                    <td class="border border-gray-300 px-2 py-1 align-top">
                                        @if($order['type'] === 'CASH')
                                            <span class="inline-block px-2 py-0.5 rounded bg-gray-100 text-gray-800 text-xs font-bold uppercase tracking-wide">CASH</span>
                                        @elseif($order['type'] === 'ROOM')
                                            <span class="inline-block px-2 py-0.5 rounded bg-blue-100 text-blue-800 text-xs font-bold uppercase tracking-wide">ROOM</span>
                                            <div class="mt-0.5 text-[11px]">
                                                <span class="font-semibold">RM {{ $order['room_number'] ?? '—' }}</span>
                                                @if($order['guest']) &middot; {{ $order['guest'] }} @endif
                                            </div>
                                        @else
                                            {{ $order['type'] }}
                                        @endif
                                        @if($order['voided'])
                                            <div class="mt-0.5">
                                                <span class="inline-block px-1.5 py-0.5 rounded bg-red-100 text-red-700 text-[10px] font-bold uppercase">Voided</span>
                                            </div>
                                        @endif
                                    </td>
                                    <td class="border border-gray-300 px-2 py-1">{{ $order['items'] }}</td>
                                    <td class="border border-gray-300 px-2 py-1 text-right">&#8369;{{ number_format($order['subtotal'], 2) }}</td>
                                    <td class="border border-gray-300 px-2 py-1 text-right font-semibold">&#8369;{{ number_format($order['total'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="bg-gray-50 font-semibold">
                                <td class="border border-gray-300 px-2 py-1" colspan="6">CASH SALES (non-voided)</td>
                                <td class="border border-gray-300 px-2 py-1 text-right">&#8369;{{ number_format($posSales['totals']['cash'], 2) }}</td>
                            </tr>
                            <tr class="bg-gray-50 font-semibold">
                                <td class="border border-gray-300 px-2 py-1" colspan="6">ROOM-CHARGE SALES (non-voided)</td>
                                <td class="border border-gray-300 px-2 py-1 text-right">&#8369;{{ number_format($posSales['totals']['room'], 2) }}</td>
                            </tr>
                            <tr class="bg-gray-100 font-bold text-base">
                                <td class="border border-gray-300 px-2 py-1" colspan="6">GROSS POS</td>
                                <td class="border border-gray-300 px-2 py-1 text-right">&#8369;{{ number_format($posSales['totals']['gross'], 2) }}</td>
                            </tr>
                            @if($posSales['totals']['voided_count'] > 0)
                                <tr class="bg-red-50 text-red-700 text-sm">
                                    <td class="border border-gray-300 px-2 py-1" colspan="7">
                                        {{ $posSales['totals']['voided_count'] }} order(s) voided this shift &mdash; not counted in totals above.
                                    </td>
                                </tr>
                            @endif
                        </tfoot>
                    </table>
                @endif
            </section>

            {{-- ==================== STOCKS INVENTORY SECTION ==================== --}}
            <section>
                <div class="text-center my-6">
                    <h3 class="text-xl font-bold text-gray-500">STOCKS INVENTORY</h3>
                </div>

                @if(empty($stocksInventory))
                    <p class="text-sm text-gray-500 px-3 py-4">No inventory movement in this shift.</p>
                @else
                    @foreach($stocksInventory as $parentName => $group)
                        <table class="w-full text-xs border-collapse mt-4" style="font-family: 'Courier New', monospace;">
                            {{-- Parent category header --}}
                            <tr>
                                <td colspan="14" class="bg-gray-400 text-black font-bold px-2 py-1 border border-gray-500">{{ $parentName }}</td>
                            </tr>
                            {{-- Column headers row 1 --}}
                            <tr>
                                <td rowspan="3" class="border border-gray-400 px-1 py-1 font-bold">ITEM NAME</td>
                                <td rowspan="3" class="border border-gray-400 px-1 py-1 font-bold text-center">PRICE</td>
                                <td colspan="8" class="border border-gray-400 px-1 py-1 font-bold text-center bg-gray-300">STOCKS FLOW</td>
                                <td rowspan="3" class="border border-gray-400 px-1 py-1 font-bold text-center bg-green-100">TOTAL<br>STOCKS<br>REM.</td>
                                <td rowspan="3" class="border border-gray-400 px-1 py-1 font-bold text-center bg-yellow-100">COMBO<br>DED.</td>
                                <td rowspan="3" class="border border-gray-400 px-1 py-1 font-bold text-center bg-cyan-100">STOCK<br>SOLD</td>
                                <td rowspan="3" class="border border-gray-400 px-1 py-1 font-bold text-center">TOTAL</td>
                            </tr>
                            {{-- Column headers row 2: Kitchen / Frontdesk --}}
                            <tr>
                                <td colspan="4" class="border border-gray-400 px-1 py-1 font-bold text-center bg-yellow-100">KITCHEN</td>
                                <td colspan="4" class="border border-gray-400 px-1 py-1 font-bold text-center bg-gray-200">FRONT DESK</td>
                            </tr>
                            {{-- Column headers row 3: sub-headers --}}
                            <tr>
                                <td class="border border-gray-400 px-1 py-0.5 font-bold text-center bg-yellow-100">BEGIN.</td>
                                <td class="border border-gray-400 px-1 py-0.5 font-bold text-center bg-yellow-100">IN</td>
                                <td class="border border-gray-400 px-1 py-0.5 font-bold text-center bg-yellow-100">OUT</td>
                                <td class="border border-gray-400 px-1 py-0.5 font-bold text-center bg-yellow-100">END</td>
                                <td class="border border-gray-400 px-1 py-0.5 font-bold text-center bg-gray-200">BEGIN.</td>
                                <td class="border border-gray-400 px-1 py-0.5 font-bold text-center bg-gray-200">IN</td>
                                <td class="border border-gray-400 px-1 py-0.5 font-bold text-center bg-gray-200">OUT</td>
                                <td class="border border-gray-400 px-1 py-0.5 font-bold text-center bg-gray-200">END</td>
                            </tr>

                            {{-- Items grouped by sub-category --}}
                            @foreach($group['categories'] as $categoryName => $category)
                                <tr>
                                    <td class="border border-gray-400 px-1 py-1 font-bold text-left" colspan="14">{{ $categoryName }}</td>
                                </tr>
                                @foreach($category['items'] as $item)
                                    <tr>
                                        <td class="border border-gray-400 px-1 py-0.5 text-left">&nbsp;&nbsp;&raquo; {{ strtolower($item['name']) }}</td>
                                        <td class="border border-gray-400 px-1 py-0.5 text-right">{{ number_format($item['price'], 2) }}</td>
                                        <td class="border border-gray-400 px-1 py-0.5 text-center bg-yellow-50">{{ $item['kitchen']['opening'] ?: '' }}</td>
                                        <td class="border border-gray-400 px-1 py-0.5 text-center bg-yellow-50">{{ $item['kitchen']['in'] ?: '' }}</td>
                                        <td class="border border-gray-400 px-1 py-0.5 text-center bg-yellow-50">{{ $item['kitchen']['out'] ?: '' }}</td>
                                        <td class="border border-gray-400 px-1 py-0.5 text-center bg-yellow-50">{{ $item['kitchen']['closing'] ?: '' }}</td>
                                        <td class="border border-gray-400 px-1 py-0.5 text-center bg-gray-50">{{ $item['frontdesk']['opening'] ?: '' }}</td>
                                        <td class="border border-gray-400 px-1 py-0.5 text-center bg-gray-50">{{ $item['frontdesk']['in'] ?: '' }}</td>
                                        <td class="border border-gray-400 px-1 py-0.5 text-center bg-gray-50">{{ $item['frontdesk']['out'] ?: '' }}</td>
                                        <td class="border border-gray-400 px-1 py-0.5 text-center bg-gray-50">{{ $item['frontdesk']['closing'] ?: '' }}</td>
                                        <td class="border border-gray-400 px-1 py-0.5 text-center bg-green-50 font-semibold">{{ $item['total_remaining'] ?: '' }}</td>
                                        <td class="border border-gray-400 px-1 py-0.5 text-center bg-yellow-50">{{ $item['combo_ded'] ?: '' }}</td>
                                        <td class="border border-gray-400 px-1 py-0.5 text-center bg-cyan-50">{{ $item['stock_sold'] ?: '' }}</td>
                                        <td class="border border-gray-400 px-1 py-0.5 text-right font-bold">{{ $item['sales_total'] > 0 ? number_format($item['sales_total'], 2) : '' }}</td>
                                    </tr>
                                @endforeach
                            @endforeach

                            {{-- Total sales for this parent category --}}
                            <tr>
                                <td colspan="12" class="border border-gray-500 px-2 py-1 text-right font-bold bg-black text-white">TOTAL {{ $parentName }} SALES:</td>
                                <td class="border border-gray-500 px-1 py-1 text-right bg-black text-white"></td>
                                <td class="border border-gray-500 px-1 py-1 text-right font-bold bg-black text-white">{{ number_format($group['total_sales'], 2) }}</td>
                            </tr>
                        </table>
                    @endforeach

                    {{-- Summary --}}
                    <table class="w-full text-sm border-collapse mt-6" style="font-family: 'Courier New', monospace;">
                        <tr>
                            <td colspan="14" class="bg-gray-300 font-bold px-2 py-1 border border-gray-500 text-center">SUMMARY</td>
                        </tr>
                        @foreach($stocksSummary as $line)
                            <tr>
                                <td colspan="13" class="border border-gray-400 px-2 py-2 text-right font-bold {{ $line['label'] === 'TOTAL SALES' ? 'text-lg' : '' }}">{{ $line['label'] }}</td>
                                <td class="border border-gray-400 px-2 py-2 text-right font-bold {{ $line['label'] === 'TOTAL SALES' ? 'text-xl' : 'text-lg' }}">{{ number_format($line['amount'], 2) }}</td>
                            </tr>
                        @endforeach
                    </table>
                @endif
            </section>

        @endif
    </div>
</div>
