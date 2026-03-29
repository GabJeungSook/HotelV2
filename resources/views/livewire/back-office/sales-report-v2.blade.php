<div class="max-w-full mx-auto py-8 px-4 sm:px-6 lg:px-8" x-data="{
    printSection(refName, title) {
        const el = this.$refs[refName];
        if (!el) return;
        const printContent = el.innerHTML;
        const printWindow = window.open('', '_blank');
        const doc = printWindow.document;
        doc.open();
        const html = `<!DOCTYPE html>
            <html>
                <head>
                    <title>${title}</title>
                    <style>
                        body { font-family: Arial, sans-serif; font-size: 11px; margin: 20px; }
                        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; }
                        th { background: #f5f5f5; font-weight: 600; }
                        .text-right { text-align: right; }
                        .font-semibold { font-weight: 600; }
                        .font-bold { font-weight: 700; }
                        .text-center { text-align: center; }
                        .header { margin-bottom: 16px; }
                        .header div { margin-bottom: 2px; }
                        .section-title { font-size: 14px; font-weight: 700; margin: 16px 0 8px; }
                        .bg-gray { background: #f9f9f9; }
                    </style>
                </head>
                <body>${printContent}</body>
            </html>`;
        doc.write(html);
        doc.close();
        printWindow.focus();
        printWindow.print();
        printWindow.close();
    }
}">

    {{-- Info Banner --}}
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4">
        <div class="flex items-start gap-2">
            <svg class="w-5 h-5 text-blue-500 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
            </svg>
            <div class="text-sm text-blue-800">
                <span class="font-semibold">Occupancy-Based Report:</span>
                This report shows ALL transactions for guests who were <strong>occupying rooms</strong> during the selected date range, including guests who checked in before the date range but were still staying.
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-4 mb-6">
        {{-- Filter Mode Toggle --}}
        <div class="flex gap-6 mb-4 pb-4 border-b border-gray-200">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="radio" wire:model.live="filterMode" value="date_range"
                       class="text-indigo-600 focus:ring-indigo-500">
                <span class="text-sm font-medium text-gray-700">Date Range</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="radio" wire:model.live="filterMode" value="shift"
                       class="text-indigo-600 focus:ring-indigo-500">
                <span class="text-sm font-medium text-gray-700">Shift</span>
            </label>
        </div>

        {{-- Date Range Mode Filters --}}
        @if($filterMode === 'date_range')
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            {{-- Frontdesk Filter --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Processed By (Frontdesk)</label>
                <select wire:model.defer="frontdesk"
                        class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All</option>
                    @foreach($frontdesks as $fd)
                        <option value="{{ $fd->user_id }}">{{ $fd->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Date From --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                <input type="date" wire:model.defer="date_from"
                       class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
            </div>

            {{-- Date To --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                <input type="date" wire:model.defer="date_to"
                       class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
            </div>

            {{-- Buttons --}}
            <div class="flex items-end gap-2">
                <button wire:click="generateReport" type="button"
                        class="flex-1 inline-flex justify-center rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">
                    Apply
                </button>
                <button wire:click="resetFilters" type="button"
                        class="flex-1 inline-flex justify-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Reset
                </button>
            </div>
        </div>
        @else
        {{-- Shift Mode Filters --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- Shift Session Selection --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Select Shift</label>
                <select wire:model.defer="selectedShiftLogId"
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

            {{-- Buttons --}}
            <div class="flex items-end gap-2">
                <button wire:click="generateReport" type="button"
                        class="flex-1 inline-flex justify-center rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">
                    Apply
                </button>
                <button wire:click="resetFilters" type="button"
                        class="flex-1 inline-flex justify-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Reset
                </button>
            </div>
        </div>
        @endif
    </div>

    {{-- Shift Statistics Badges --}}
    <div class="flex flex-wrap gap-3 mb-4">
        {{-- Check-ins --}}
        <div class="bg-green-50 border border-green-200 rounded-lg px-4 py-2">
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-green-100 text-green-800">
                    {{ $shiftCheckins }} CHECK-INS
                </span>
            </div>
        </div>

        {{-- Check-outs --}}
        <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-2">
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-800">
                    {{ $shiftCheckouts }} CHECK-OUTS
                </span>
            </div>
        </div>

        {{-- Forwarded --}}
        @if($forwardedCount > 0)
        <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-2">
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800">
                    {{ $forwardedCount }} FORWARDED
                </span>
                <span class="text-xs text-amber-700">
                    check-in from previous shift
                </span>
            </div>
        </div>
        @endif

        {{-- Unclaimed --}}
        @if($unclaimedCount > 0)
        <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-2">
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-800">
                    {{ $unclaimedCount }} UNCLAIMED
                </span>
                <span class="text-xs text-red-700">
                    guest deposit from checked-out guests
                </span>
            </div>
        </div>
        @endif
    </div>

    {{-- Summary Cards (clickable) --}}
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-3 mb-4">
        <div wire:click="openCardModal('room_charges')" class="bg-white rounded-lg shadow-sm ring-1 ring-gray-200 p-4 cursor-pointer hover:ring-2 hover:ring-indigo-300 transition">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Room Charges</div>
            <div class="text-lg font-semibold text-gray-900 mt-1">P {{ number_format($summaryByType['room_charges'] ?? 0, 2) }}</div>
        </div>
        <div wire:click="openCardModal('extensions')" class="bg-white rounded-lg shadow-sm ring-1 ring-gray-200 p-4 cursor-pointer hover:ring-2 hover:ring-indigo-300 transition">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Extensions</div>
            <div class="text-lg font-semibold text-gray-900 mt-1">P {{ number_format($summaryByType['extensions'] ?? 0, 2) }}</div>
        </div>
        <div wire:click="openCardModal('amenities')" class="bg-white rounded-lg shadow-sm ring-1 ring-gray-200 p-4 cursor-pointer hover:ring-2 hover:ring-indigo-300 transition">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Amenities</div>
            <div class="text-lg font-semibold text-gray-900 mt-1">P {{ number_format($summaryByType['amenities'] ?? 0, 2) }}</div>
        </div>
        <div wire:click="openCardModal('food')" class="bg-white rounded-lg shadow-sm ring-1 ring-gray-200 p-4 cursor-pointer hover:ring-2 hover:ring-indigo-300 transition">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Food</div>
            <div class="text-lg font-semibold text-gray-900 mt-1">P {{ number_format($summaryByType['food'] ?? 0, 2) }}</div>
        </div>
        <div wire:click="openCardModal('damages')" class="bg-white rounded-lg shadow-sm ring-1 ring-gray-200 p-4 cursor-pointer hover:ring-2 hover:ring-indigo-300 transition">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Damages</div>
            <div class="text-lg font-semibold text-gray-900 mt-1">P {{ number_format($summaryByType['damages'] ?? 0, 2) }}</div>
        </div>
        <div wire:click="openCardModal('transfers')" class="bg-white rounded-lg shadow-sm ring-1 ring-gray-200 p-4 cursor-pointer hover:ring-2 hover:ring-indigo-300 transition">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Transfers</div>
            <div class="text-lg font-semibold text-gray-900 mt-1">P {{ number_format($summaryByType['transfers'] ?? 0, 2) }}</div>
        </div>
        {{-- Room Deposit with checkout/remaining breakdown --}}
        <div class="bg-white rounded-lg shadow-sm ring-1 ring-gray-200 p-4">
            <div wire:click="openCardModal('room_deposits')" class="cursor-pointer hover:text-indigo-600 transition">
                <div class="text-xs text-gray-500 uppercase tracking-wide">Room Deposit</div>
                <div class="text-lg font-semibold text-gray-900 mt-1">P {{ number_format(($shiftCheckins + $forwardedCount) * 200, 2) }}</div>
            </div>
            <div class="text-xs text-gray-500 mt-1 space-y-0.5">
                <div wire:click="openCardModal('checkout_room_deposit')" class="flex justify-between cursor-pointer hover:text-indigo-600 transition">
                    <span>Checkout:</span>
                    <span class="font-medium">P {{ number_format($checkoutRoomDeposit ?? 0, 2) }}</span>
                </div>
                <div wire:click="openCardModal('remaining_room_deposit')" class="flex justify-between cursor-pointer hover:text-indigo-600 transition">
                    <span>Remaining:</span>
                    <span class="font-medium">P {{ number_format($remainingRoomDeposit ?? 0, 2) }}</span>
                </div>
            </div>
        </div>
        <div wire:click="openCardModal('guest_deposits')" class="bg-white rounded-lg shadow-sm ring-1 ring-gray-200 p-4 cursor-pointer hover:ring-2 hover:ring-indigo-300 transition">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Guest Deposit</div>
            <div class="text-lg font-semibold text-gray-900 mt-1">P {{ number_format($summaryByType['guest_deposits'] ?? 0, 2) }}</div>
        </div>
        {{-- Cashout --}}
        <div wire:click="openCardModal('cashouts')" class="bg-white rounded-lg shadow-sm ring-1 ring-gray-200 p-4 cursor-pointer hover:ring-2 hover:ring-indigo-300 transition">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Cashout</div>
            <div class="text-lg font-semibold text-gray-900 mt-1">P {{ number_format($totalCashouts ?? 0, 2) }}</div>
        </div>
        {{-- Checkout Room Amount --}}
        <div wire:click="openCardModal('checkout_room')" class="bg-white rounded-lg shadow-sm ring-1 ring-gray-200 p-4 cursor-pointer hover:ring-2 hover:ring-indigo-300 transition">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Checkout Room Amount</div>
            <div class="text-lg font-semibold text-gray-900 mt-1">P {{ number_format($checkoutRoomAmount ?? 0, 2) }}</div>
        </div>
        {{-- Forwarded Room --}}
        <div wire:click="openCardModal('fwd_room')" class="bg-amber-50 rounded-lg shadow-sm ring-1 ring-amber-200 p-4 cursor-pointer hover:ring-2 hover:ring-indigo-300 transition">
            <div class="text-xs text-amber-700 uppercase tracking-wide">Forwarded Room</div>
            <div class="text-lg font-semibold text-amber-900 mt-1">P {{ number_format($forwardedRoom ?? 0, 2) }}</div>
        </div>
        {{-- Forwarded Deposits --}}
        <div class="bg-amber-50 rounded-lg shadow-sm ring-1 ring-amber-200 p-4">
            <div class="text-xs text-amber-700 uppercase tracking-wide">Forwarded Deposit</div>
            <div class="text-sm text-amber-800 mt-1">
                <div wire:click="openCardModal('fwd_room_deposit')" class="flex justify-between cursor-pointer hover:text-indigo-600 transition">
                    <span>Room Deposit:</span>
                    <span class="font-semibold">P {{ number_format($forwardedRoomDeposit ?? 0, 2) }}</span>
                </div>
                <div wire:click="openCardModal('fwd_guest_deposit')" class="flex justify-between cursor-pointer hover:text-indigo-600 transition">
                    <span>Guest Deposit:</span>
                    <span class="font-semibold">P {{ number_format($forwardedGuestDeposit ?? 0, 2) }}</span>
                </div>
            </div>
        </div>
        {{-- Unclaimed Deposits --}}
        <div wire:click="openCardModal('unclaimed_deposits')" class="bg-red-50 rounded-lg shadow-sm ring-1 ring-red-200 p-4 cursor-pointer hover:ring-2 hover:ring-indigo-300 transition">
            <div class="text-xs text-red-700 uppercase tracking-wide">Unclaimed Guest Deposit</div>
            <div class="text-lg font-semibold text-red-900 mt-1">P {{ number_format($unclaimedDepositTotal ?? 0, 2) }}</div>
            <div class="text-xs text-red-600 mt-0.5">{{ $unclaimedCount }} checked-out guest{{ $unclaimedCount !== 1 ? 's' : '' }}</div>
        </div>
    </div>

    {{-- Grand Total Card --}}
    <div class="bg-indigo-600 rounded-lg shadow-sm p-4 mb-6">
        <div class="flex justify-between items-center">
            <div>
                <div class="text-xs text-indigo-200 uppercase tracking-wide">Gross Sales</div>
                <div class="text-xl font-semibold text-white">P {{ number_format($summaryByType['grand_total'] ?? 0, 2) }}</div>
            </div>
            <div class="text-indigo-200 text-2xl">-</div>
            <div>
                <div class="text-xs text-indigo-200 uppercase tracking-wide">Expenses</div>
                <div class="text-xl font-semibold text-white">P {{ number_format($expensesTotal ?? 0, 2) }}</div>
            </div>
            <div class="text-indigo-200 text-2xl">=</div>
            <div>
                <div class="text-xs text-indigo-200 uppercase tracking-wide">Net Sales</div>
                <div class="text-xl font-bold text-white">P {{ number_format($netSales ?? 0, 2) }}</div>
            </div>
        </div>
    </div>

    {{-- Sales Table --}}
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden mb-6" x-data="{ search: '' }">
        <div class="px-4 py-3 border-b border-gray-200 flex justify-between items-center">
            <div class="text-sm font-semibold text-gray-900">SALES REPORT (OCCUPANCY-BASED)</div>
            <div class="flex items-center gap-4">
                <input type="text" x-model="search" placeholder="Search guest or room..."
                       class="rounded-lg border-gray-300 text-sm px-3 py-1.5 w-56 focus:border-indigo-500 focus:ring-indigo-500" />
                <button @click="printSection('salesPrintContent', 'Sales Report')" type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-gray-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-gray-800">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    Print
                </button>
                <div class="text-xs text-gray-500">
                    @if($filterMode === 'shift' && $selectedShiftLogId)
                        @php
                            $selectedSession = collect($availableShiftSessions)->firstWhere('id', $selectedShiftLogId);
                        @endphp
                        Shift: {{ $selectedSession['label'] ?? 'N/A' }}
                    @else
                        {{ $date_from }} to {{ $date_to }}
                        @if($frontdesk_name)
                            | Frontdesk: {{ $frontdesk_name }}
                        @endif
                    @endif
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="border border-gray-300 px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase">Room #</th>
                        <th class="border border-gray-300 px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase">Room Type</th>
                        <th class="border border-gray-300 px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase">Guest Name</th>
                        <th class="border border-gray-300 px-3 py-2 text-center text-xs font-semibold text-gray-700 uppercase">Status</th>
                        <th class="border border-gray-300 px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase">Type</th>
                        <th class="border border-gray-300 px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase">Check-In</th>
                        <th class="border border-gray-300 px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase">Check-Out</th>
                        <th class="border border-gray-300 px-3 py-2 text-right text-xs font-semibold text-gray-700 uppercase">Amount</th>
                        <th class="border border-gray-300 px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase">Processed By</th>
                        <th class="border border-gray-300 px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase">Shift</th>
                        <th class="border border-gray-300 px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase">Transaction Date</th>
                        <th class="border border-gray-300 px-3 py-2 text-right text-xs font-semibold text-gray-700 uppercase">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($salesRows as $row)
                        <tr :class="search && !{{ Js::from(strtolower($row['guest_name'] . ' ' . $row['room_number'])) }}.includes(search.toLowerCase()) ? 'hidden' : ''" data-total="{{ $row['total'] }}" class="{{ ($row['is_forwarded_guest_row'] ?? false) ? 'bg-amber-50 hover:bg-amber-100' : 'hover:bg-gray-50' }}">
                            <td class="border border-gray-300 px-3 py-2 text-sm font-medium text-gray-900">{{ $row['room_number'] }}</td>
                            <td class="border border-gray-300 px-3 py-2 text-sm text-gray-700">{{ $row['room_type'] }}</td>
                            <td class="border border-gray-300 px-3 py-2 text-sm text-gray-700">{{ $row['guest_name'] }}</td>
                            <td class="border border-gray-300 px-3 py-2 text-sm text-center">
                                @if($row['is_forwarded_guest_row'] ?? false)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-amber-500 text-white">
                                        FORWARDED
                                    </span>
                                @elseif($row['is_forwarded'] ?? false)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-amber-100 text-amber-800">
                                        FORWARDED
                                    </span>
                                @endif
                            </td>
                            <td class="border border-gray-300 px-3 py-2 text-sm text-gray-700">
                                @if($row['is_forwarded_guest_row'] ?? false)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800">
                                        {{ $row['transaction_type'] }}@if(str_contains($row['remarks'] ?? '', 'Unclaimed')) (Unclaimed)@endif
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                        @switch($row['transaction_type_id'])
                                            @case(1) bg-green-100 text-green-800 @break
                                            @case(2) bg-yellow-100 text-yellow-800 @break
                                            @case(4) bg-red-100 text-red-800 @break
                                            @case(6) bg-blue-100 text-blue-800 @break
                                            @case(7) bg-purple-100 text-purple-800 @break
                                            @case(8) bg-pink-100 text-pink-800 @break
                                            @case(9) bg-orange-100 text-orange-800 @break
                                            @default bg-gray-100 text-gray-800
                                        @endswitch
                                    ">
                                        {{ $row['transaction_type'] }}
                                    </span>
                                @endif
                            </td>
                            <td class="border border-gray-300 px-3 py-2 text-sm text-gray-700">{{ $row['check_in'] }}</td>
                            <td class="border border-gray-300 px-3 py-2 text-sm text-gray-700">{{ $row['check_out'] }}</td>
                            <td class="border border-gray-300 px-3 py-2 text-sm text-gray-700 text-right">
                                @if($row['is_forwarded_guest_row'] ?? false)
                                    <span class="text-amber-700">P {{ number_format($row['amount'], 2) }}</span>
                                    <div class="text-xs text-amber-600">(prev shift)</div>
                                @else
                                    P {{ number_format($row['amount'], 2) }}
                                @endif
                                @if($row['is_override'] ?? false)
                                    <div class="text-xs text-red-600 font-semibold">(Override)</div>
                                @endif
                            </td>
                            <td class="border border-gray-300 px-3 py-2 text-sm text-gray-700">{{ $row['processed_by'] }}</td>
                            <td class="border border-gray-300 px-3 py-2 text-sm text-gray-700">{{ $row['shift'] }}</td>
                            <td class="border border-gray-300 px-3 py-2 text-sm text-gray-700">{{ $row['transaction_date'] }}</td>
                            <td class="border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-900 text-right">
                                @if($row['is_forwarded_guest_row'] ?? false)
                                    <span class="text-amber-600">—</span>
                                @else
                                    P {{ number_format($row['total'], 2) }}
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="border border-gray-300 px-3 py-8 text-center text-sm text-gray-500">
                                No transactions found for guests occupying rooms in the selected date range.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if(count($salesRows) > 0)
                    <tfoot>
                        <tr class="bg-gray-100">
                            <td colspan="11" class="border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-900 text-right">
                                TOTAL SALES:
                            </td>
                            <td class="border border-gray-300 px-3 py-2 text-sm font-bold text-gray-900 text-right">
                                P {{ number_format($totalSales, 2) }}
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- Room Summary --}}
    @if(!empty($roomSummary['rows']))
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden mb-6">
        <div class="px-4 py-3 border-b border-gray-200">
            <div class="text-sm font-semibold text-gray-900">ROOM SUMMARY BY FLOOR</div>
        </div>

        <div class="overflow-x-auto p-4">
            <table class="min-w-full border-collapse">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="border border-gray-300 px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase">Description</th>
                        @foreach(($roomSummary['floors'] ?? []) as $f)
                            <th class="border border-gray-300 px-3 py-2 text-right text-xs font-semibold text-gray-700 uppercase">
                                {{ $f['number'] }}{{ $f['number'] == 1 ? 'st' : ($f['number'] == 2 ? 'nd' : ($f['number'] == 3 ? 'rd' : 'th')) }} Floor
                            </th>
                        @endforeach
                        <th class="border border-gray-300 px-3 py-2 text-right text-xs font-semibold text-gray-700 uppercase">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(($roomSummary['rows'] ?? []) as $r)
                        <tr>
                            <td class="border border-gray-300 px-3 py-2 text-sm font-medium text-gray-900">{{ $r['description'] }}</td>
                            @foreach(($roomSummary['floors'] ?? []) as $f)
                                <td class="border border-gray-300 px-3 py-2 text-sm text-gray-700 text-right">
                                    P {{ number_format($r['floors'][$f['id']] ?? 0, 2) }}
                                </td>
                            @endforeach
                            <td class="border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-900 text-right">
                                P {{ number_format($r['row_total'] ?? 0, 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="bg-gray-100">
                        <td class="border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-900">TOTAL</td>
                        @foreach(($roomSummary['floors'] ?? []) as $f)
                            <td class="border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-900 text-right">
                                P {{ number_format($roomSummary['totals'][$f['id']] ?? 0, 2) }}
                            </td>
                        @endforeach
                        <td class="border border-gray-300 px-3 py-2 text-sm font-bold text-gray-900 text-right">
                            P {{ number_format(collect($roomSummary['totals'] ?? [])->sum(), 2) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    @endif

    {{-- Expense Summary --}}
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden mb-6">
        <div class="px-4 py-3 border-b border-gray-200">
            <div class="text-sm font-semibold text-gray-900">EXPENSE SUMMARY</div>
        </div>

        <div class="overflow-x-auto p-4">
            <table class="min-w-full border-collapse">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="border border-gray-300 px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase">Expense Type</th>
                        <th class="border border-gray-300 px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase">Description</th>
                        <th class="border border-gray-300 px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase">Shift</th>
                        <th class="border border-gray-300 px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase">Frontdesk</th>
                        <th class="border border-gray-300 px-3 py-2 text-right text-xs font-semibold text-gray-700 uppercase">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($expensesRows ?? [] as $e)
                        <tr>
                            <td class="border border-gray-300 px-3 py-2 text-sm text-gray-700">{{ $e['expense_type'] }}</td>
                            <td class="border border-gray-300 px-3 py-2 text-sm text-gray-700">{{ $e['description'] }}</td>
                            <td class="border border-gray-300 px-3 py-2 text-sm text-gray-700">{{ $e['shift'] }}</td>
                            <td class="border border-gray-300 px-3 py-2 text-sm text-gray-700">{{ $e['frontdesk'] }}</td>
                            <td class="border border-gray-300 px-3 py-2 text-sm text-gray-700 text-right">P {{ number_format($e['amount'], 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="border border-gray-300 px-3 py-6 text-center text-sm text-gray-500">
                                No expenses found for the selected date range.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if(count($expensesRows ?? []) > 0)
                    <tfoot>
                        <tr class="bg-gray-100">
                            <td colspan="4" class="border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-900 text-right">
                                TOTAL EXPENSES:
                            </td>
                            <td class="border border-gray-300 px-3 py-2 text-sm font-bold text-gray-900 text-right">
                                P {{ number_format($expensesTotal, 2) }}
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- Remittance Summary --}}
    <div class="bg-white rounded-lg shadow-sm ring-1 ring-gray-200 mb-6">
        <div class="px-4 py-3 border-b border-gray-200">
            <div class="text-sm font-semibold text-gray-900">REMITTANCE SUMMARY</div>
        </div>

        <div class="overflow-x-auto p-4">
            <table class="min-w-full border-collapse">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="border border-gray-300 px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase">Description</th>
                        <th class="border border-gray-300 px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase">Frontdesk</th>
                        <th class="border border-gray-300 px-3 py-2 text-right text-xs font-semibold text-gray-700 uppercase">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($remittanceRows ?? [] as $r)
                        <tr>
                            <td class="border border-gray-300 px-3 py-2 text-sm text-gray-700">{{ $r['description'] }}</td>
                            <td class="border border-gray-300 px-3 py-2 text-sm text-gray-700">{{ $r['frontdesk'] }}</td>
                            <td class="border border-gray-300 px-3 py-2 text-sm text-gray-700 text-right">P {{ number_format($r['amount'], 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="border border-gray-300 px-3 py-6 text-center text-sm text-gray-500">
                                No remittances found for the selected date range.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if(count($remittanceRows ?? []) > 0)
                    <tfoot>
                        <tr class="bg-gray-100">
                            <td colspan="2" class="border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-900 text-right">
                                TOTAL REMITTANCE:
                            </td>
                            <td class="border border-gray-300 px-3 py-2 text-sm font-bold text-gray-900 text-right">
                                P {{ number_format($remittanceTotal, 2) }}
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- Hidden Print Container: Sales Report --}}
    <div x-ref="salesPrintContent" class="hidden">
        {{-- Header --}}
        @if($filterMode === 'shift' && $selectedShiftLogId)
            @php $printSession = collect($availableShiftSessions)->firstWhere('id', $selectedShiftLogId); @endphp
            @if($printSession)
            <div class="header">
                <div style="font-size:14px;font-weight:700;">SALES REPORT</div>
                <div><strong>Frontdesk:</strong> {{ $printSession['frontdesks'] ?? '—' }}</div>
                <div><strong>Opening:</strong> {{ $printSession['time_in_formatted'] ?? '—' }}</div>
                <div><strong>Closing:</strong> {{ $printSession['time_out_formatted'] ?? '—' }}</div>
            </div>
            @endif
        @else
            <div class="header">
                <div style="font-size:14px;font-weight:700;">SALES REPORT</div>
                <div><strong>Date:</strong> {{ $date_from }} to {{ $date_to }}</div>
                @if($frontdesk_name)<div><strong>Frontdesk:</strong> {{ $frontdesk_name }}</div>@endif
            </div>
        @endif

        {{-- Sales Table --}}
        <table>
            <thead>
                <tr>
                    <th>Room #</th><th>Room Type</th><th>Guest Name</th><th>Status</th><th>Type</th>
                    <th>Check-In</th><th>Check-Out</th><th>Hours</th><th class="text-right">Amount</th>
                    <th>Processed By</th><th>Shift</th><th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($salesRows as $row)
                <tr>
                    <td>{{ $row['room_number'] }}</td>
                    <td>{{ $row['room_type'] }}</td>
                    <td>{{ $row['guest_name'] }}</td>
                    <td>@if($row['is_forwarded_guest_row'] ?? false)FORWARDED @elseif($row['is_forwarded'] ?? false)FORWARDED @endif</td>
                    <td>{{ $row['transaction_type'] }}@if(str_contains($row['remarks'] ?? '', 'Unclaimed')) (Unclaimed)@endif</td>
                    <td>{{ $row['check_in'] }}</td>
                    <td>{{ $row['check_out'] }}</td>
                    <td>{{ $row['hours_stayed'] ?? '—' }}</td>
                    <td class="text-right">P {{ number_format($row['amount'], 2) }}</td>
                    <td>{{ $row['processed_by'] }}</td>
                    <td>{{ $row['shift'] }}</td>
                    <td class="text-right">@if($row['is_forwarded_guest_row'] ?? false)— @else P {{ number_format($row['total'], 2) }}@endif</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="bg-gray">
                    <td colspan="11" class="text-right font-bold">TOTAL SALES:</td>
                    <td class="text-right font-bold">P {{ number_format($totalSales, 2) }}</td>
                </tr>
            </tfoot>
        </table>

        {{-- Room Summary --}}
        @if(!empty($roomSummary['rows']))
        <div class="section-title">ROOM SUMMARY BY FLOOR</div>
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    @foreach(($roomSummary['floors'] ?? []) as $f)
                        <th class="text-right">{{ $f['number'] }}{{ $f['number'] == 1 ? 'st' : ($f['number'] == 2 ? 'nd' : ($f['number'] == 3 ? 'rd' : 'th')) }} Floor</th>
                    @endforeach
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach(($roomSummary['rows'] ?? []) as $r)
                <tr>
                    <td>{{ $r['description'] }}</td>
                    @foreach(($roomSummary['floors'] ?? []) as $f)
                        <td class="text-right">P {{ number_format($r['floors'][$f['id']] ?? 0, 2) }}</td>
                    @endforeach
                    <td class="text-right font-semibold">P {{ number_format(collect($r['floors'] ?? [])->sum(), 2) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="bg-gray">
                    <td class="font-bold">TOTAL</td>
                    @foreach(($roomSummary['floors'] ?? []) as $f)
                        <td class="text-right font-bold">P {{ number_format($roomSummary['totals'][$f['id']] ?? 0, 2) }}</td>
                    @endforeach
                    <td class="text-right font-bold">P {{ number_format(collect($roomSummary['totals'] ?? [])->sum(), 2) }}</td>
                </tr>
            </tfoot>
        </table>
        @endif

        {{-- Expense Summary --}}
        @if(count($expensesRows ?? []) > 0)
        <div class="section-title">EXPENSE SUMMARY</div>
        <table>
            <thead>
                <tr><th>Expense Type</th><th>Description</th><th>Shift</th><th>Frontdesk</th><th class="text-right">Amount</th></tr>
            </thead>
            <tbody>
                @foreach($expensesRows as $e)
                <tr>
                    <td>{{ $e['expense_type'] }}</td><td>{{ $e['description'] }}</td><td>{{ $e['shift'] }}</td>
                    <td>{{ $e['frontdesk'] }}</td><td class="text-right">P {{ number_format($e['amount'], 2) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="bg-gray"><td colspan="4" class="text-right font-bold">TOTAL EXPENSES:</td><td class="text-right font-bold">P {{ number_format($expensesTotal, 2) }}</td></tr>
            </tfoot>
        </table>
        @endif

        {{-- Remittance Summary --}}
        @if(count($remittanceRows ?? []) > 0)
        <div class="section-title">REMITTANCE SUMMARY</div>
        <table>
            <thead>
                <tr><th>Description</th><th>Frontdesk</th><th class="text-right">Amount</th></tr>
            </thead>
            <tbody>
                @foreach($remittanceRows as $r)
                <tr>
                    <td>{{ $r['description'] }}</td><td>{{ $r['frontdesk'] }}</td><td class="text-right">P {{ number_format($r['amount'], 2) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="bg-gray"><td colspan="2" class="text-right font-bold">TOTAL REMITTANCE:</td><td class="text-right font-bold">P {{ number_format($remittanceTotal, 2) }}</td></tr>
            </tfoot>
        </table>
        @endif
    </div>

    {{-- Card Detail Modal --}}
    <x-modal wire:model.defer="showCardModal" max-width="5xl">
        <x-card :title="$cardModalTitle">
            @if($filterMode === 'shift' && $selectedShiftLogId)
                @php
                    $modalSession = collect($availableShiftSessions)->firstWhere('id', $selectedShiftLogId);
                @endphp
                @if($modalSession)
                <div class="mb-4 bg-gray-50 rounded-lg p-3 text-sm">
                    <div class="font-semibold text-gray-900">{{ $modalSession['frontdesks'] ?? '—' }}</div>
                    <div class="text-gray-600">Opening: {{ $modalSession['time_in_formatted'] ?? '—' }}</div>
                    <div class="text-gray-600">Closing: {{ $modalSession['time_out_formatted'] ?? '—' }}</div>
                </div>
                @endif
            @endif

            <div class="max-h-[70vh] overflow-y-auto">
                <table class="w-full border-collapse text-sm">
                    <thead class="sticky top-0 bg-gray-50">
                        <tr>
                            <th class="border border-gray-300 px-3 py-2 text-left font-semibold text-gray-700">Room #</th>
                            <th class="border border-gray-300 px-3 py-2 text-left font-semibold text-gray-700">Guest Name</th>
                            <th class="border border-gray-300 px-3 py-2 text-left font-semibold text-gray-700">Type</th>
                            <th class="border border-gray-300 px-3 py-2 text-left font-semibold text-gray-700">Remarks</th>
                            <th class="border border-gray-300 px-3 py-2 text-right font-semibold text-gray-700">Amount</th>
                            <th class="border border-gray-300 px-3 py-2 text-left font-semibold text-gray-700">Transaction Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cardModalRows as $row)
                        <tr class="hover:bg-gray-50">
                            <td class="border border-gray-300 px-3 py-2 text-gray-900">{{ $row['room_number'] ?? '—' }}</td>
                            <td class="border border-gray-300 px-3 py-2 text-gray-700">{{ $row['guest_name'] ?? '—' }}</td>
                            <td class="border border-gray-300 px-3 py-2 text-gray-700">{{ $row['transaction_type'] ?? '—' }}</td>
                            <td class="border border-gray-300 px-3 py-2 text-gray-700">{{ $row['remarks'] ?? '—' }}</td>
                            <td class="border border-gray-300 px-3 py-2 text-right text-gray-900">P {{ number_format($row['amount'] ?? 0, 2) }}</td>
                            <td class="border border-gray-300 px-3 py-2 text-gray-700">{{ $row['transaction_date'] ?? '—' }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="border border-gray-300 px-3 py-4 text-center text-gray-500">No records found</td>
                        </tr>
                        @endforelse
                    </tbody>
                    @if(count($cardModalRows) > 0)
                    <tfoot class="bg-gray-100">
                        <tr>
                            <td colspan="4" class="border border-gray-300 px-3 py-2 text-right font-bold text-gray-900">TOTAL ({{ count($cardModalRows) }} records)</td>
                            <td class="border border-gray-300 px-3 py-2 text-right font-bold text-gray-900">P {{ number_format($cardModalTotal, 2) }}</td>
                            <td class="border border-gray-300 px-3 py-2"></td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
            {{-- Hidden Modal Print Container --}}
            <div x-ref="modalPrintContent" class="hidden">
                @if($filterMode === 'shift' && $selectedShiftLogId)
                    @php $modalPrintSession = collect($availableShiftSessions)->firstWhere('id', $selectedShiftLogId); @endphp
                    @if($modalPrintSession)
                    <div class="header">
                        <div style="font-size:14px;font-weight:700;">{{ $cardModalTitle }}</div>
                        <div><strong>Frontdesk:</strong> {{ $modalPrintSession['frontdesks'] ?? '—' }}</div>
                        <div><strong>Opening:</strong> {{ $modalPrintSession['time_in_formatted'] ?? '—' }}</div>
                        <div><strong>Closing:</strong> {{ $modalPrintSession['time_out_formatted'] ?? '—' }}</div>
                    </div>
                    @endif
                @else
                    <div class="header">
                        <div style="font-size:14px;font-weight:700;">{{ $cardModalTitle }}</div>
                        <div><strong>Date:</strong> {{ $date_from }} to {{ $date_to }}</div>
                        @if($frontdesk_name)<div><strong>Frontdesk:</strong> {{ $frontdesk_name }}</div>@endif
                    </div>
                @endif
                <table>
                    <thead>
                        <tr><th>Room #</th><th>Guest Name</th><th>Type</th><th>Remarks</th><th class="text-right">Amount</th><th>Transaction Date</th></tr>
                    </thead>
                    <tbody>
                        @foreach($cardModalRows as $row)
                        <tr>
                            <td>{{ $row['room_number'] ?? '—' }}</td>
                            <td>{{ $row['guest_name'] ?? '—' }}</td>
                            <td>{{ $row['transaction_type'] ?? '—' }}</td>
                            <td>{{ $row['remarks'] ?? '—' }}</td>
                            <td class="text-right">P {{ number_format($row['amount'] ?? 0, 2) }}</td>
                            <td>{{ $row['transaction_date'] ?? '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    @if(count($cardModalRows) > 0)
                    <tfoot>
                        <tr class="bg-gray"><td colspan="4" class="text-right font-bold">TOTAL ({{ count($cardModalRows) }} records)</td><td class="text-right font-bold">P {{ number_format($cardModalTotal, 2) }}</td><td></td></tr>
                    </tfoot>
                    @endif
                </table>
            </div>

            <x-slot name="footer">
                <div class="flex justify-between">
                    <button @click="printSection('modalPrintContent', '{{ $cardModalTitle }}')" type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-gray-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-gray-800">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                        </svg>
                        Print
                    </button>
                    <x-button flat label="Close" x-on:click="close" />
                </div>
            </x-slot>
        </x-card>
    </x-modal>

</div>
