<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Z-Read Report</title>
<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f3f4f6; color: #111827; line-height: 1.5; -webkit-font-smoothing: antialiased; }

    .max-w-full { max-width: 100%; }
    .mx-auto { margin-left: auto; margin-right: auto; }
    .px-4 { padding-left: 1rem; padding-right: 1rem; }
    .py-6 { padding-top: 1.5rem; padding-bottom: 1.5rem; }
    .p-4 { padding: 1rem; }
    .p-5 { padding: 1.25rem; }
    .p-6 { padding: 1.5rem; }
    .mb-1 { margin-bottom: 0.25rem; }
    .mb-2 { margin-bottom: 0.5rem; }
    .mb-3 { margin-bottom: 0.75rem; }
    .mb-4 { margin-bottom: 1rem; }
    .mb-6 { margin-bottom: 1.5rem; }
    .mt-4 { margin-top: 1rem; }
    .mt-12 { margin-top: 3rem; }
    .mr-2 { margin-right: 0.5rem; }
    .pr-10 { padding-right: 2.5rem; }
    .pl-10 { padding-left: 2.5rem; }

    .flex { display: flex; }
    .grid { display: grid; }
    .items-center { align-items: center; }
    .justify-center { justify-content: center; }
    .grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .grid-cols-5 { grid-template-columns: repeat(5, minmax(0, 1fr)); }
    .gap-2 { gap: 0.5rem; }
    .gap-8 { gap: 2rem; }

    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .text-left { text-align: left; }
    .text-xs { font-size: 0.75rem; line-height: 1rem; }
    .text-sm { font-size: 0.875rem; line-height: 1.25rem; }
    .text-base { font-size: 1rem; line-height: 1.5rem; }
    .text-lg { font-size: 1.125rem; line-height: 1.75rem; }
    .text-xl { font-size: 1.25rem; line-height: 1.75rem; }
    .text-2xl { font-size: 1.5rem; line-height: 2rem; }
    .font-bold { font-weight: 700; }
    .font-semibold { font-weight: 600; }
    .italic { font-style: italic; }

    .text-white { color: #ffffff; }
    .text-black { color: #000000; }
    .text-gray-400 { color: #9ca3af; }
    .text-gray-500 { color: #6b7280; }
    .text-gray-700 { color: #374151; }
    .text-gray-900 { color: #111827; }
    .text-red-600 { color: #dc2626; }
    .text-green-600 { color: #16a34a; }

    .bg-white { background-color: #ffffff; }
    .bg-gray-50 { background-color: #f9fafb; }
    .bg-gray-100 { background-color: #f3f4f6; }
    .bg-green-500 { background-color: #22c55e; }
    .bg-red-50 { background-color: #fef2f2; }
    .bg-pink-50 { background-color: #fdf2f8; }
    .bg-blue-50 { background-color: #eff6ff; }
    .bg-green-50 { background-color: #f0fdf4; }

    .border { border-width: 1px; border-style: solid; }
    .border-2 { border-width: 2px; border-style: solid; }
    .border-4 { border-width: 4px; border-style: solid; }
    .border-t-2 { border-top-width: 2px; border-top-style: solid; }
    .border-gray-300 { border-color: #d1d5db; }
    .border-gray-400 { border-color: #9ca3af; }
    .border-red-400 { border-color: #f87171; }
    .border-red-500 { border-color: #ef4444; }
    .border-red-600 { border-color: #dc2626; }
    .border-pink-300 { border-color: #f9a8d4; }
    .border-blue-300 { border-color: #93c5fd; }
    .border-green-300 { border-color: #86efac; }

    .rounded { border-radius: 0.25rem; }
    .rounded-lg { border-radius: 0.5rem; }
    .rounded-xl { border-radius: 0.75rem; }
    .shadow { box-shadow: 0 1px 3px 0 rgba(0,0,0,0.1), 0 1px 2px -1px rgba(0,0,0,0.1); }
    .shadow-sm { box-shadow: 0 1px 2px 0 rgba(0,0,0,0.05); }
    .overflow-x-auto { overflow-x: auto; }
    .overflow-hidden { overflow: hidden; }
    .w-full { width: 100%; }
    .w-48 { width: 12rem; }
    .w-12 { width: 3rem; }
    .w-16 { width: 4rem; }
    .w-32 { width: 8rem; }
    .max-w-3xl { max-width: 48rem; }
    .min-h-6 { min-height: 1.5rem; }
    .list-decimal { list-style-type: decimal; }
    .list-inside { list-style-position: inside; }
    .space-y-1 > * + * { margin-top: 0.25rem; }
    .space-y-2 > * + * { margin-top: 0.5rem; }
    .align-top { vertical-align: top; }
    .border-collapse { border-collapse: collapse; }

    .report-table {
        border-collapse: collapse;
        font-size: 13px;
        width: 100%;
        border: 2px solid #333;
    }
    .report-table td, .report-table th {
        border: 1px solid #333;
        padding: 8px 10px;
    }
    .report-table th {
        background-color: #ffffff;
        font-weight: bold;
        text-align: center;
        border: 1.5px solid #333;
    }
    .report-table td {
        text-align: left;
        border: 1px solid #999;
    }
    .report-table tbody tr:nth-child(odd) { background-color: #f9fafb; }
    .report-table tbody tr:nth-child(even) { background-color: #ffffff; }
    .report-table tbody tr:hover { background-color: #f3f4f6; }
    .report-table td.text-right { text-align: right; }
    .report-table td.text-center { text-align: center; }

    .footer { margin-top: 60px; padding-top: 30px; border-top: 2px solid #e5e7eb; text-align: center; color: #6b7280; font-size: 12px; background-color: #f9fafb; padding-bottom: 30px; }
    .footer-content { max-width: 1200px; margin: 0 auto; }
    .footer-company { font-weight: 600; color: #111827; margin-bottom: 8px; }

    @media print {
        .no-print { display: none !important; }
        .footer { display: none !important; }
        body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        table { page-break-inside: auto; }
        tr { page-break-inside: avoid; page-break-after: auto; }
    }
</style>
</head>
<body>

<div class="max-w-full mx-auto px-4 py-6">

    {{-- ==================== HEADER ==================== --}}
    <div class="mb-6 text-center" style="border-bottom: 2px solid #1f2937; padding-bottom: 1.5rem;">
        <h1 style="font-size: 1.875rem; font-weight: 700; letter-spacing: 2px;" class="text-gray-900 mb-3">
            ALMA Residences
        </h1>
        <p class="text-gray-700 text-sm mb-1">{{ $branchName }}</p>
    </div>

    {{-- ==================== REMINDERS SA FRONTDESK ==================== --}}
    <div class="no-print mb-6 bg-red-50 border-4 border-red-600 rounded-lg p-6">
        <h2 class="text-2xl font-bold text-red-600 mb-4">REMINDERS SA FRONTDESK</h2>

        <div class="mb-4">
            <p class="font-bold text-gray-900 mb-1">DUTY TIME:</p>
            <p class="text-gray-900">Frontdesk: AM SHIFT: 8 AM- 8PM</p>
            <p class="text-gray-900">Frontdesk: PM SHIFT: 8 PM- 8AM</p>
        </div>

        <ol class="list-decimal list-inside space-y-2 text-gray-900">
            <li>Tanan guest nga magbilin ug keys sa inyong time dapat epa-check sa inyong buddy or roomboy ang room aron malikayan ang dako nga damages. If ever naa madamage sa room or mawala kay wala ninyo gicheck ang room sa ato pa sa inyo ma-charge ang item.</li>
            <li>Inig abot ug 8 untop kinahanglan mag z-read na kay if mulapas nga wala pa ka z-read considered late na. If wala pa naabot imu ka shifting, maghatag ug 30 minutes allowance. So hangtud 8:30 kung wala pa imung ka shifting, mu log in lang ka utro para sa next shift.</li>
            <li>DILI PWEDE magdula ug magtan-aw ug youtube, mag open ug facebook, ug uban pang mga website sa computer sa frontdesk kay mao na usa sa makadaut sa system.</li>
            <li>Bawal ang mga roomboy mag standby sa frontdesk, bawal mugamit ang roomboy sa computer sa frontdesk kay usa sa hinungdan madaut ang computer. Frontdesk ang ma suspended ug masakpan.</li>
            <li>Dapat inig 7:30, close na ang food ug drinks report sa kitchen ug frontdesk arun han-ay ug hapsay ang endorsement. Inig ka 8 humana endorsement.
                <br><span class="text-red-600 font-semibold">(NOTE: Wala nagpasabot nga dili na mudawat ug order ang kitchen, report lang ang i close.)</span>
            </li>
            <li>New guest dapat i entry dayon sa computer, Pinakaguday ma entry 5 minutes. Kada Information sheet butangan ug oras sa pag check in.</li>
        </ol>

        <div class="mt-4" style="border-top: 2px solid #f87171; padding-top: 1rem;">
            <p class="font-bold text-gray-900 mb-2">PENALTIES</p>
            <ol class="list-decimal list-inside space-y-1 text-gray-900">
                <li>1st Offense: 1 week suspension</li>
                <li>2nd Offense: Termination</li>
            </ol>
        </div>
    </div>

    {{-- ==================== TITLE ==================== --}}
    <div class="text-center mb-3">
        <b style="font-size: 35px;">DAILY SHIFT REPORT</b>
    </div>

    {{-- ==================== REPORT HEADER INFO ==================== --}}
    <div class="mb-6 bg-white rounded-lg shadow p-4">
        <table class="report-table mb-0" style="width: 100%;">
            <tr>
                <td class="font-bold" style="width: 150px;">REPORT DATE</td>
                <td><strong>{{ strtoupper($selectedSession['date_formatted'] ?? '') }}</strong></td>
            </tr>
            <tr>
                <td class="font-bold">FRONTDESK</td>
                <td><strong>{{ strtoupper($selectedSession['frontdesks'] ?? '') }}</strong></td>
            </tr>
            <tr>
                <td class="font-bold">SHIFT</td>
                <td><strong>{{ $selectedSession['shift_type'] ?? '' }}</strong></td>
            </tr>
            <tr>
                <td class="font-bold">TIME LOGGED IN</td>
                <td><strong>{{ $selectedSession['time_in_formatted'] ?? '' }}</strong></td>
            </tr>
            <tr>
                <td class="font-bold">TIME LOGGED OUT</td>
                <td><strong>{{ $selectedSession['time_out_formatted'] ?? '' }}</strong></td>
            </tr>
        </table>
    </div>

    {{-- ==================== SUMMARY OF TRANSACTIONS ==================== --}}
    <div class="bg-white rounded-lg shadow mb-6 overflow-x-auto">
        <div class="p-6">
            <h3 class="text-2xl font-bold text-gray-900 mb-4">Summary of Transactions</h3>
            <table class="report-table mb-6">
                <thead>
                    <tr>
                        <th></th>
                        @foreach($floors as $floor)
                            <th>{{ strtoupper($floor->numberWithFormat()) }}</th>
                        @endforeach
                        <th>NO ROOM</th>
                        <th>TOTAL</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($summaryRows as $row)
                        @if($row['label'] === 'GROSS TOTAL')
                            <tr style="background: #000; color: #fff; font-weight: bold;">
                                <td>{{ $row['label'] }}:</td>
                                @foreach($floors as $floor)
                                    <td style="text-align:right;">{{ number_format($row['floors'][$floor->id] ?? 0, 2) }}</td>
                                @endforeach
                                <td style="text-align:right;">{{ number_format($row['no_room'] ?? 0, 2) }}</td>
                                <td style="text-align:right;">{{ number_format($row['total'], 2) }}</td>
                            </tr>
                        @else
                            <tr>
                                <td><b>{{ $row['label'] }}:</b></td>
                                @foreach($floors as $floor)
                                    <td style="text-align:right;">{{ number_format($row['floors'][$floor->id] ?? 0, 2) }}</td>
                                @endforeach
                                <td style="text-align:right;">{{ number_format($row['no_room'] ?? 0, 2) }}</td>
                                <td style="text-align:right;">{{ number_format($row['total'], 2) }}</td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- ==================== STATISTICS ==================== --}}
    <div class="bg-white rounded-lg shadow mb-6 overflow-x-auto">
        <div class="p-6">
            <table class="report-table" style="width: 100%;">
                <tbody>
                    <tr>
                        <td style="font-weight: bold; background-color: #fbbf24;">TOTAL NEW GUEST</td>
                        <td style="text-align: right; background-color: #fbbf24; font-weight: bold;">{{ $totalNewGuest }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; background-color: #fef08a;">TOTAL EXTENDED GUEST</td>
                        <td style="text-align: right; background-color: #fef08a; font-weight: bold;">{{ $totalExtendedGuest }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; background-color: #fbcfe8;">TOTAL # OF UNOCCUPIED ROOMS</td>
                        <td style="text-align: right; background-color: #fbcfe8; font-weight: bold;">{{ $totalUnoccupiedRooms }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- ==================== UNOCCUPIED ROOMS / CLEANING ORDER / UNDER REPAIR ==================== --}}
    <div class="bg-white rounded-lg shadow mb-6 overflow-x-auto">
        <div class="p-6">
            <h3 class="text-2xl font-bold text-gray-900 mb-4">UNOCCUPIED ROOMS</h3>
            <div class="bg-pink-50 border-2 border-pink-300 rounded-lg p-4">
                <p class="text-gray-700"><strong>Room Numbers:</strong> {{ $unoccupiedRoomNumbers ?: 'None' }}</p>
            </div>
        </div>

        <div class="p-6">
            <h3 class="text-2xl font-bold text-gray-900 mb-4">ROOM CLEANING ORDER</h3>
            <div class="bg-blue-50 border-2 border-blue-300 rounded-lg p-4">
                <p class="text-gray-700"><strong>Room Numbers:</strong> {{ $cleaningOrder ?: 'None' }}</p>
            </div>
        </div>

        <div class="p-6">
            <h3 class="text-2xl font-bold text-gray-900 mb-4">ROOM UNDER REPAIR</h3>
            @if($maintenanceRooms)
                <div class="bg-pink-50 border-2 border-pink-300 rounded-lg p-4">
                    <p class="text-gray-700"><strong>Room Numbers:</strong> {{ $maintenanceRooms }}</p>
                </div>
            @else
                <div class="bg-green-50 border-2 border-green-300 rounded-lg p-4">
                    <p class="text-gray-700"><strong>Status:</strong> All rooms are operational</p>
                </div>
            @endif
        </div>
    </div>

    {{-- ==================== EXPENSES ==================== --}}
    <div class="bg-white rounded-lg shadow mb-6 overflow-x-auto">
        <div class="p-6">
            <h3 class="text-2xl text-red-600 mb-4">- LESS EXPENSE</h3>
            @if($expenses->count() > 0)
                <table class="report-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th style="width: 150px;">EXPENSE TYPE</th>
                            <th>DESCRIPTION</th>
                            <th style="width: 120px;">AMOUNT</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($expenses as $i => $expense)
                            <tr>
                                <td class="text-center">{{ $i + 1 }}</td>
                                <td>{{ $expense->expenseCategory?->name ?? '' }}</td>
                                <td>{{ $expense->description ?? '' }}</td>
                                <td class="text-right">&#8369;{{ number_format($expense->amount, 2) }}</td>
                            </tr>
                        @endforeach
                        <tr style="background-color: #fee2e2; font-weight: bold;">
                            <td colspan="3" class="text-right">TOTAL EXPENSES:</td>
                            <td class="text-right">&#8369;{{ number_format($expensesTotal, 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            @else
                <div class="bg-gray-50 rounded-lg border-2" style="border-style: dashed; border-color: #d1d5db; padding: 1.5rem;">
                    <p class="text-gray-500 text-center">No expenses recorded.</p>
                </div>
            @endif
        </div>

        {{-- ==================== + ADDITIONALS (PAYMENT ON SHORT) ==================== --}}
        <div class="p-6">
            <h3 class="text-2xl text-green-600 mb-4">+ ADDITIONALS</h3>
            @if($paymentOnShorts->count() > 0)
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>NAME</th>
                            <th>DESCRIPTION</th>
                            <th style="width: 120px;">AMOUNT</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($paymentOnShorts as $payment)
                            <tr>
                                <td style="color: #16a34a;">&raquo; {{ $payment->shiftLog?->shift ?? '' }}: {{ $payment->name }}</td>
                                <td>{{ $payment->description ?? '-' }}</td>
                                <td class="text-right">&#8369;{{ number_format($payment->amount, 2) }}</td>
                            </tr>
                        @endforeach
                        <tr style="background-color: #dcfce7; font-weight: bold;">
                            <td colspan="2" class="text-right">TOTAL ADDITIONALS:</td>
                            <td class="text-right">&#8369;{{ number_format($paymentOnShortsTotal, 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            @else
                <div class="bg-gray-50 rounded-lg border-2" style="border-style: dashed; border-color: #d1d5db; padding: 1.5rem;">
                    <p class="text-gray-500 text-center">No payment on short recorded.</p>
                </div>
            @endif
        </div>

        {{-- ==================== NET INCOME ==================== --}}
        <div class="bg-green-500 pr-10 pl-10">
            <table class="w-full text-sm">
                <tr>
                    <td style="padding: 0.5rem 0; font-weight: bold; font-size: 1.125rem;">NET INCOME:</td>
                    <td class="text-right font-bold text-black" style="font-size: 1.25rem;">&#8369;{{ number_format($grossTotal + $paymentOnShortsTotal - $expensesTotal, 2) }}</td>
                </tr>
            </table>
        </div>
    </div>

    {{-- ==================== FRONTDESK CHART ==================== --}}
    <div class="bg-white rounded-lg shadow mb-6 overflow-x-auto">
        <div class="p-6">
            <h3 class="text-2xl font-bold text-center text-gray-900 mb-4">FRONTDESK CHART</h3>

            @foreach($frontdeskChart as $floorGroup)
                <div class="mb-6">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th colspan="16" style="text-align: center; background: #000; color: #fff;">{{ strtoupper($floorGroup['floor']->numberWithFormat()) }}</th>
                            </tr>
                            <tr>
                                <th>ROOM</th>
                                <th>RATE</th>
                                <th>TYPE</th>
                                <th>STATUS</th>
                                <th>GUEST</th>
                                <th>CHECK IN</th>
                                <th>CHECK OUT</th>
                                <th>INI HOUR</th>
                                <th>ROOM</th>
                                <th>TRANSFER</th>
                                <th>EXTEND</th>
                                <th>FOOD</th>
                                <th>DRINKS</th>
                                <th>MCS.</th>
                                <th>RM DEPOSIT</th>
                                <th>DEPOSIT</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($floorGroup['rooms'] as $room)
                                @if($room['status'] === 'Available')
                                    <tr>
                                        <td class="text-center">{{ $room['number'] }}</td>
                                        <td class="text-center" style="color: #999; font-style: italic;">--</td>
                                        <td class="text-center">{{ $room['type'] }}</td>
                                        <td><span style="color: #666;">&#10003; Available</span></td>
                                        <td class="text-center" style="color: #999; font-style: italic;">--</td>
                                        <td class="text-center" style="color: #999; font-style: italic;">--</td>
                                        <td class="text-center" style="color: #999; font-style: italic;">--</td>
                                        <td class="text-center" style="color: #999; font-style: italic;">--</td>
                                        <td class="text-center" style="color: #999; font-style: italic;">--</td>
                                        <td class="text-center" style="color: #999; font-style: italic;">--</td>
                                        <td class="text-center" style="color: #999; font-style: italic;">--</td>
                                        <td class="text-center" style="color: #999; font-style: italic;">--</td>
                                        <td class="text-center" style="color: #999; font-style: italic;">--</td>
                                        <td class="text-center" style="color: #999; font-style: italic;">--</td>
                                        <td class="text-center" style="color: #999; font-style: italic;">--</td>
                                        <td class="text-center" style="color: #999; font-style: italic;">--</td>
                                    </tr>
                                @elseif($room['is_forwarded'])
                                    <tr style="background-color: #fef3c7;">
                                        <td class="text-center">{{ $room['rowspan'] > 0 ? $room['number'] : '' }}</td>
                                        <td class="text-right">{{ $room['rate'] ? number_format($room['rate'], 2) : '' }}</td>
                                        <td class="text-center">{{ $room['type'] }}</td>
                                        <td style="color: #dc2626; font-style:italic;">[ FWD: {{ $room['expected_check_out'] }} ]</td>
                                        <td>{{ $room['guest'] }}</td>
                                        <td class="text-center"><span style="color: #999;">--</span></td>
                                        <td class="text-center">@if($room['check_out'])<span style="color: #dc2626;">{{ $room['check_out'] }}</span>@endif</td>
                                        <td class="text-center">{{ $room['initial_hours'] }}</td>
                                        <td class="text-right">{{ $room['room_rate'] > 0 ? number_format($room['room_rate'], 2) : '' }}</td>
                                        <td class="text-right">{{ $room['transfer'] > 0 ? number_format($room['transfer'], 2) : '' }}</td>
                                        <td class="text-right">{{ $room['extend'] > 0 ? number_format($room['extend'], 2) : '' }}</td>
                                        <td class="text-right">{{ $room['foods'] > 0 ? number_format($room['foods'], 2) : '' }}</td>
                                        <td class="text-right">{{ $room['drinks'] > 0 ? number_format($room['drinks'], 2) : '' }}</td>
                                        <td class="text-center">{{ $room['misc'] > 0 ? number_format($room['misc'], 2) : '' }}</td>
                                        <td class="text-right">{{ $room['room_deposit'] > 0 ? number_format($room['room_deposit'], 2) : '' }}</td>
                                        <td class="text-right">{{ $room['deposit'] > 0 ? number_format($room['deposit'], 2) : '0.00' }}</td>
                                    </tr>
                                @else
                                    <tr>
                                        <td class="text-center">{{ $room['rowspan'] > 0 ? $room['number'] : '' }}</td>
                                        <td class="text-right">{{ $room['rate'] ? number_format($room['rate'], 2) : '' }}</td>
                                        <td class="text-center">{{ $room['type'] }}</td>
                                        <td>{{ $room['status'] }}</td>
                                        <td>{{ $room['guest'] }}</td>
                                        <td class="text-center">{{ $room['check_in'] }}</td>
                                        <td class="text-center">@if($room['check_out'])<span style="color: red;">{{ $room['check_out'] }}</span>@endif</td>
                                        <td class="text-center">{{ $room['initial_hours'] }}</td>
                                        <td class="text-right">{{ $room['room_rate'] > 0 ? number_format($room['room_rate'], 2) : '' }}</td>
                                        <td class="text-right">{{ $room['transfer'] > 0 ? number_format($room['transfer'], 2) : '' }}</td>
                                        <td class="text-right">{{ $room['extend'] > 0 ? number_format($room['extend'], 2) : '' }}</td>
                                        <td class="text-right">{{ $room['foods'] > 0 ? number_format($room['foods'], 2) : '' }}</td>
                                        <td class="text-right">{{ $room['drinks'] > 0 ? number_format($room['drinks'], 2) : '' }}</td>
                                        <td class="text-center">{{ $room['misc'] > 0 ? number_format($room['misc'], 2) : '' }}</td>
                                        <td class="text-right">{{ $room['room_deposit'] > 0 ? number_format($room['room_deposit'], 2) : '' }}</td>
                                        <td class="text-right">{{ $room['deposit'] > 0 ? number_format($room['deposit'], 2) : '0.00' }}</td>
                                    </tr>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="16" class="text-center" style="color: #999; font-style: italic;">No rooms</td>
                                </tr>
                            @endforelse
                            @if(!empty($floorGroup['subtotal']))
                                <tr style="background-color: #e5e7eb; font-weight: bold;">
                                    <td colspan="8" class="text-right">SUBTOTAL</td>
                                    <td class="text-right">{{ ($floorGroup['subtotal']['room_rate'] ?? 0) > 0 ? number_format($floorGroup['subtotal']['room_rate'], 2) : '' }}</td>
                                    <td class="text-right">{{ ($floorGroup['subtotal']['transfer'] ?? 0) > 0 ? number_format($floorGroup['subtotal']['transfer'], 2) : '' }}</td>
                                    <td class="text-right">{{ ($floorGroup['subtotal']['extend'] ?? 0) > 0 ? number_format($floorGroup['subtotal']['extend'], 2) : '' }}</td>
                                    <td class="text-right">{{ ($floorGroup['subtotal']['foods'] ?? 0) > 0 ? number_format($floorGroup['subtotal']['foods'], 2) : '' }}</td>
                                    <td class="text-right">{{ ($floorGroup['subtotal']['drinks'] ?? 0) > 0 ? number_format($floorGroup['subtotal']['drinks'], 2) : '' }}</td>
                                    <td class="text-right">{{ ($floorGroup['subtotal']['misc'] ?? 0) > 0 ? number_format($floorGroup['subtotal']['misc'], 2) : '' }}</td>
                                    <td class="text-right">{{ ($floorGroup['subtotal']['room_deposit'] ?? 0) > 0 ? number_format($floorGroup['subtotal']['room_deposit'], 2) : '' }}</td>
                                    <td class="text-right">{{ ($floorGroup['subtotal']['deposit'] ?? 0) > 0 ? number_format($floorGroup['subtotal']['deposit'], 2) : '' }}</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ==================== REMINDERS SA ROOMBOY ==================== --}}
    <div class="no-print mb-6 bg-red-50 border-4 border-red-600 rounded-lg p-6">
        <div class="flex items-center mb-3">
            <h2 class="text-2xl font-bold text-red-600">REMINDERS SA ROOMBOY</h2>
        </div>
        <ol class="list-decimal list-inside text-gray-900 space-y-2">
            <li>Tanan trabahante dapat mo greet sa tanan niyang nasugatan co-workers and Guest. Good morning, Good afternoon, Good evening. Ang dili mo follow mag penalty ug 100 pesos kung masakpan.</li>
            <li>Tanan roomboy dapat motubag sa radyo kung tawagan. Warningan sa maka tulo ug dili patoo advisan nga ipa re asign.</li>
            <li>Ang pagpanglimyo sa room dapat open pirmete ang purtahan. Kung sirrado nagpasabot nga nagtan-aw ka tv,natulog ug nag aircon sa ato pa willing ka modawat sa mga penalty nga 200 pesos.</li>
            <li>Ang paglimpyo sa room dapat dili dungan dunganon humano sa ang isa ka room ayha pa mobalhin sa lain nga room nga hugaw. Dapat E report sa frontdesk ang mga rooms nga limpyo na. Katulo warningan, Roomboy nga dili mo obey ug badlongon good as 1 week salary deduction.</li>
            <li>Ang mga roomboys dapat mo report sa frontdesk kung unsa damage ug kulang sa room aron mapa ayo dayon. Roomboy nga dili mo report sa problema dayon dili mapasudlan ang room automatic xa magbayad sa room.</li>
            <li>Mangutana ang roomboy sa mga rooms nga unang nag check out. Bawal ang mga unclean room nga e entry nga clean na if masakpan nga mo appear sa computer nga clean na. Penalty equivalent of 100 pesos.</li>
            <li>Tanan trabahante mag so-ot ug uniform roomboys, cook and laundry wear white uniform. If masakpan nga wala nag uniform mag penalty ug 100pesos.</li>
            <li>Tanan trabahante nga no assist or mo-entertain sa guest kinahanglan naka uniform. Bawal magtambay sa frontdesk area ug sa lobby. Trabahante nga masakpan 1 week suspension.</li>
        </ol>
    </div>

    {{-- ==================== ROOM CLEANING CHART ==================== --}}
    <div class="bg-white rounded-lg shadow mb-6 overflow-x-auto">
        <div class="p-6">
            <div class="grid grid-cols-5 gap-2">
                @foreach($roomCleaningChart as $floorGroup)
                    <div class="overflow-x-auto">
                        <table class="report-table w-full">
                            <thead>
                                <tr>
                                    <td colspan="4" style="background:#000;color:#fff;"><b>{{ strtoupper($floorGroup['floor']->numberWithFormat()) }}</b></td>
                                </tr>
                                <tr>
                                    <th>ROOM</th>
                                    <th>TIME</th>
                                    <th>ELAPSE</th>
                                    <th>STATUS</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($floorGroup['rooms'] as $room)
                                    <tr>
                                        <td class="text-center">{{ ($room['rowspan'] ?? 1) > 0 ? $room['number'] : '' }}</td>
                                        <td class="text-center">{{ $room['time'] ?: 'N/A' }}</td>
                                        <td class="text-center" style="{{ ($room['duration_seconds'] ?? 0) >= 14400 ? 'color: red; font-weight: bold;' : '' }}">{{ $room['duration'] ?: 'N/A' }}</td>
                                        <td class="text-center" style="font-weight: bold; color: {{ $room['status'] === 'Clean' ? 'green' : ($room['status'] === 'In-use' ? 'red' : ($room['status'] === 'Vacant' ? 'green' : '#b45309')) }};">{{ $room['status'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ==================== ROOM BOY CLEANING LOGS ==================== --}}
    <div class="bg-white rounded-lg shadow mb-6 overflow-x-auto">
        <div class="p-6">
            <h3 class="text-2xl font-bold text-gray-900 mb-4">Roomboy Cleaning Logs</h3>
            <div class="grid grid-cols-5 gap-2">
                @forelse($roomboyLogs as $log)
                    <div class="mb-4 bg-gray-50 border border-gray-300 rounded-lg shadow-sm p-4">
                        <h4 class="text-lg font-bold mb-3" style="color: #1e3a5f;">{{ $log['name'] }}</h4>
                        <div class="overflow-x-auto">
                            <table class="report-table w-full">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Room</th>
                                        <th>Flr</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($log['entries'] as $entry)
                                        <tr>
                                            <td class="text-center">{{ $entry['number'] }}</td>
                                            <td class="text-center">{{ ($entry['rowspan'] ?? 1) > 0 ? $entry['room_number'] : '' }}</td>
                                            <td class="text-center">{{ ($entry['rowspan'] ?? 1) > 0 ? $entry['floor_number'] : '' }}</td>
                                            <td class="text-center">{{ $entry['time'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-400 italic">No room boy activity during this shift.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ==================== SIGNATURES ==================== --}}
    <div class="mt-12 grid grid-cols-2 gap-8">
        <div class="text-center">
            <div style="border-bottom: 1px solid #9ca3af; width: 12rem; margin: 0 auto 0.25rem;"></div>
            <p class="text-sm font-semibold">Prepared By</p>
        </div>
        <div class="text-center">
            <div style="border-bottom: 1px solid #9ca3af; width: 12rem; margin: 0 auto 0.25rem;"></div>
            <p class="text-sm font-semibold">Verified By</p>
        </div>
    </div>

</div>

{{-- Footer --}}
<footer class="footer">
    <div class="footer-content">
        <div class="footer-company">ALMA Residences - Hotel Management System</div>
        <div style="margin: 12px 0;">
            <span style="color: #6b7280;">Generated on {{ now()->format('F d, Y \\a\\t h:i:s A') }}</span>
        </div>
    </div>
</footer>

</body>
</html>
