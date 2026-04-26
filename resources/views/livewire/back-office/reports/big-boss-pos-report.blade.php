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
                <button wire:click="exportHtml" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm font-medium">
                    Export HTML
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
                                <th class="border border-gray-300 px-2 py-1 text-left">TYPE</th>
                                <th class="border border-gray-300 px-2 py-1 text-left">GUEST / ROOM</th>
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
                                    <td class="border border-gray-300 px-2 py-1">
                                        @if($order['type'] === 'CASH')
                                            <span class="font-semibold text-green-700">CASH</span>
                                        @elseif($order['type'] === 'ROOM')
                                            <span class="font-semibold text-blue-700">ROOM</span>
                                        @else
                                            {{ $order['type'] }}
                                        @endif
                                        @if($order['voided'])
                                            <span class="ml-1 inline-block px-1.5 py-0.5 rounded bg-red-100 text-red-700 text-[10px] font-semibold uppercase">Voided</span>
                                        @endif
                                    </td>
                                    <td class="border border-gray-300 px-2 py-1">
                                        @if($order['type'] === 'ROOM')
                                            RM {{ $order['room_number'] ?? '—' }}
                                            @if($order['guest']) &middot; {{ $order['guest'] }} @endif
                                        @else
                                            &mdash;
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
                                <td class="border border-gray-300 px-2 py-1" colspan="7">CASH SALES (non-voided)</td>
                                <td class="border border-gray-300 px-2 py-1 text-right">&#8369;{{ number_format($posSales['totals']['cash'], 2) }}</td>
                            </tr>
                            <tr class="bg-gray-50 font-semibold">
                                <td class="border border-gray-300 px-2 py-1" colspan="7">ROOM-CHARGE SALES (non-voided)</td>
                                <td class="border border-gray-300 px-2 py-1 text-right">&#8369;{{ number_format($posSales['totals']['room'], 2) }}</td>
                            </tr>
                            <tr class="bg-gray-100 font-bold text-base">
                                <td class="border border-gray-300 px-2 py-1" colspan="7">GROSS POS</td>
                                <td class="border border-gray-300 px-2 py-1 text-right">&#8369;{{ number_format($posSales['totals']['gross'], 2) }}</td>
                            </tr>
                            @if($posSales['totals']['voided_count'] > 0)
                                <tr class="bg-red-50 text-red-700 text-sm">
                                    <td class="border border-gray-300 px-2 py-1" colspan="8">
                                        {{ $posSales['totals']['voided_count'] }} order(s) voided this shift &mdash; not counted in totals above.
                                    </td>
                                </tr>
                            @endif
                        </tfoot>
                    </table>
                @endif
            </section>

            {{-- ==================== INVENTORY SECTION ==================== --}}
            <section>
                <h3 class="text-base font-bold bg-gray-100 px-3 py-2 rounded">INVENTORY MOVEMENT</h3>
                <p class="text-xs text-gray-500 px-3 py-1">Only items with movement during the shift's time window are listed.</p>

                @if(empty($inventoryRows))
                    <p class="text-sm text-gray-500 px-3 py-4">No inventory movement in this shift.</p>
                @else
                    <table class="w-full text-sm border-collapse mt-3">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="border border-gray-300 px-2 py-1 text-left">SOURCE</th>
                                <th class="border border-gray-300 px-2 py-1 text-left">ITEM</th>
                                <th class="border border-gray-300 px-2 py-1 text-right">OPENING</th>
                                <th class="border border-gray-300 px-2 py-1 text-right">IN</th>
                                <th class="border border-gray-300 px-2 py-1 text-right">OUT</th>
                                <th class="border border-gray-300 px-2 py-1 text-right">CLOSING</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($inventoryRows as $row)
                                <tr>
                                    <td class="border border-gray-300 px-2 py-1 uppercase text-xs">{{ $row['source_type'] }}</td>
                                    <td class="border border-gray-300 px-2 py-1">{{ $row['item_name'] }}</td>
                                    <td class="border border-gray-300 px-2 py-1 text-right">{{ rtrim(rtrim(number_format($row['opening'], 2), '0'), '.') }}</td>
                                    <td class="border border-gray-300 px-2 py-1 text-right text-green-700 font-semibold">
                                        @if($row['in'] > 0) +{{ rtrim(rtrim(number_format($row['in'], 2), '0'), '.') }} @else &mdash; @endif
                                    </td>
                                    <td class="border border-gray-300 px-2 py-1 text-right text-red-700 font-semibold">
                                        @if($row['out'] > 0) -{{ rtrim(rtrim(number_format($row['out'], 2), '0'), '.') }} @else &mdash; @endif
                                    </td>
                                    <td class="border border-gray-300 px-2 py-1 text-right font-semibold">{{ rtrim(rtrim(number_format($row['closing'], 2), '0'), '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>

        @endif
    </div>
</div>
