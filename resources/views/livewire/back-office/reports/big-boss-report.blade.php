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
                    Export Z-Read
                </button>
            </div>
        </div>
    </div>

    {{-- Report Content --}}
    <div id="report-content" class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden p-8">

        {{-- ==================== REMINDERS SA FRONTDESK ==================== --}}
        <div class="mb-6 border-2 border-red-500 rounded p-5">
            <h3 class="text-base font-bold text-red-600 mb-3">REMINDERS SA FRONTDESK</h3>

            <div class="mb-3">
                <p class="text-sm font-bold">DUTY TIME:</p>
                <p class="text-sm">Frontdesk : AM SHIFT: 8 AM- 8PM</p>
                <p class="text-sm">Frontdesk: PM SHIFT: 8 PM- 8AM</p>
            </div>

            <ol class="list-decimal list-inside text-sm space-y-2">
                <li>Tanan guest nga magbilin ug keys sa inyong time dapat epa-check sa inyong buddy or roomboy ang room aron malikayan ang dako nga damages. If ever naa madamage sa room or mawala kay wala ninyo gicheck ang room sa ato pa sa inyo ma-charge ang item.</li>
                <li>Inig abot ug 8 untop kinahanglan mag z-read na kay if mulapas nga wala pa ka z-read considered late na. If wala pa naabot imu ka shifting, maghatag ug 30 minutes allowance. So hangtud 8:30 kung wala pa imung ka shifting, mu log in lang ka utro para sa next shift.</li>
                <li>DILI PWEDE magdula ug magtan-aw ug youtube, mag open ug facebook, ug uban pang mga website sa computer sa frontdesk kay mao na usa sa makadaut sa system.</li>
                <li>Bawal ang mga roomboy mag standby sa frontdesk, bawal mugamit ang roomboy sa computer sa frontdesk kay usa sa hinungdan madaut ang computer. Frontdesk ang ma suspended ug masakpan.</li>
                <li>Dapat inig 7:30, close na ang food ug drinks report sa kitchen ug frontdesk arun han-ay ug hapsay ang endorsement. Inig ka 8 humana endorsement.
                    <br><span class="text-red-600 font-semibold">(NOTE: Wala nagpasabot nga dili na mudawat ug order ang kitchen, report lang ang i close.)</span>
                </li>
                <li>New guest dapat i entry dayon sa computer, Pinakaguday ma entry 5 minutes. Kada Information sheet butangan ug oras sa pag check in.</li>
            </ol>

            <div class="mt-4">
                <p class="text-sm font-bold">PENALTIES</p>
                <ol class="list-decimal list-inside text-sm mt-1 space-y-1">
                    <li>1st Offense: 1 week suspension</li>
                    <li>2nd Offense: Termination</li>
                </ol>
            </div>
        </div>

        {{-- ==================== HEADER ==================== --}}
        <div class="text-center mb-6">
            <h1 class="text-xl font-bold">ALMA Residences</h1>
            <h2 class="text-lg font-bold mt-2">DAILY SHIFT REPORT</h2>
        </div>

        <div class="mb-6">
            <table class="w-full">
                <tbody>
                    <tr>
                        <td class="py-1 font-semibold w-48">DATE</td>
                        <td class="py-1">{{ $selectedSession['date_formatted'] ?? '' }}</td>
                    </tr>
                    <tr>
                        <td class="py-1 font-semibold">FRONTDESK</td>
                        <td class="py-1">{{ $selectedSession['frontdesks'] ?? '' }}</td>
                    </tr>
                    <tr>
                        <td class="py-1 font-semibold">SHIFT</td>
                        <td class="py-1">{{ $selectedSession['shift_type'] ?? '' }}</td>
                    </tr>
                    <tr>
                        <td class="py-1 font-semibold">TIME LOGGED IN</td>
                        <td class="py-1">{{ $selectedSession['time_in_formatted'] ?? '' }}</td>
                    </tr>
                    <tr>
                        <td class="py-1 font-semibold">TIME LOGGED OUT</td>
                        <td class="py-1">{{ $selectedSession['time_out_formatted'] ?? '' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- ==================== SUMMARY TABLE ==================== --}}
        <div class="mb-6">
            <h3 class="text-base font-bold mb-2">SUMMARY</h3>
            <div class="overflow-x-auto">
                <table class="w-full border-collapse border border-gray-300 text-sm">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border border-gray-300 px-3 py-2 text-left">DETAILS</th>
                            @foreach($floors as $floor)
                                <th class="border border-gray-300 px-3 py-2 text-center">{{ strtoupper($floor->numberWithFormat()) }}</th>
                            @endforeach
                            <th class="border border-gray-300 px-3 py-2 text-center">NO ROOM</th>
                            <th class="border border-gray-300 px-3 py-2 text-center">TOTAL</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($summaryRows as $row)
                            <tr class="{{ in_array($row['label'], ['GROSS TOTAL', 'TOTAL DEPOSIT']) ? 'bg-gray-50' : '' }}">
                                <td class="border border-gray-300 px-3 py-2 {{ in_array($row['label'], ['GROSS TOTAL', 'TOTAL DEPOSIT']) ? 'font-bold' : 'font-semibold' }}">{{ $row['label'] }}</td>
                                @foreach($floors as $floor)
                                    <td class="border border-gray-300 px-3 py-2 text-center">
                                        {{ ($row['floors'][$floor->id] ?? 0) > 0 ? number_format($row['floors'][$floor->id], 2) : '' }}
                                    </td>
                                @endforeach
                                <td class="border border-gray-300 px-3 py-2 text-center">
                                    {{ ($row['no_room'] ?? 0) > 0 ? number_format($row['no_room'], 2) : '' }}
                                </td>
                                <td class="border border-gray-300 px-3 py-2 text-center {{ in_array($row['label'], ['GROSS TOTAL', 'TOTAL DEPOSIT']) ? 'font-bold' : '' }}">
                                    {{ $row['total'] > 0 ? number_format($row['total'], 2) : '' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-gray-500 mt-1 italic">Those Foods and Drinks that was not charge in individual rooms. Process through POS</p>
        </div>

        {{-- ==================== STATISTICS ==================== --}}
        <div class="mb-6">
            <table class="w-full border-collapse border border-gray-300 text-sm">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border border-gray-300 px-3 py-2 text-left">DESCRIPTION</th>
                        <th class="border border-gray-300 px-3 py-2 text-center w-32">COUNT</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="border border-gray-300 px-3 py-2">TOTAL NEW GUEST</td>
                        <td class="border border-gray-300 px-3 py-2 text-center">{{ $totalNewGuest }}</td>
                    </tr>
                    <tr>
                        <td class="border border-gray-300 px-3 py-2">TOTAL EXTENDED GUEST</td>
                        <td class="border border-gray-300 px-3 py-2 text-center">{{ $totalExtendedGuest }}</td>
                    </tr>
                    <tr>
                        <td class="border border-gray-300 px-3 py-2">TOTAL # OF UNOCCUPIED ROOM</td>
                        <td class="border border-gray-300 px-3 py-2 text-center">{{ $totalUnoccupiedRooms }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- ==================== UNOCCUPIED ROOMS ==================== --}}
        <div class="mb-4">
            <h3 class="text-sm font-bold mb-1">UNOCCUPIED ROOMS</h3>
            <p class="border-b border-gray-400 pb-1 text-sm min-h-[1.5rem]">{{ $unoccupiedRoomNumbers }}</p>
        </div>

        {{-- ==================== ROOM CLEANING ORDER ==================== --}}
        <div class="mb-4">
            <h3 class="text-sm font-bold mb-1">ROOM CLEANING ORDER</h3>
            <p class="border-b border-gray-400 pb-1 text-sm min-h-[1.5rem]">{{ $cleaningOrder }}</p>
        </div>

        {{-- ==================== ROOM UNDER REPAIR ==================== --}}
        <div class="mb-6">
            <h3 class="text-sm font-bold mb-1">ROOM UNDER REPAIR</h3>
            <p class="border-b border-gray-400 pb-1 text-sm min-h-[1.5rem]">{{ $maintenanceRooms }}</p>
        </div>

        {{-- ==================== EXPENSES ==================== --}}
        <div class="mb-6">
            <h3 class="text-base font-bold mb-2">EXPENSES</h3>
            <table class="w-full border-collapse border border-gray-300 text-sm">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border border-gray-300 px-3 py-2 text-left w-16">#</th>
                        <th class="border border-gray-300 px-3 py-2 text-left">EXPENSES TYPE</th>
                        <th class="border border-gray-300 px-3 py-2 text-left">DESCRIPTION</th>
                        <th class="border border-gray-300 px-3 py-2 text-right w-32">AMOUNT</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($expenses as $i => $expense)
                    <tr>
                        <td class="border border-gray-300 px-3 py-2">{{ $i + 1 }}.</td>
                        <td class="border border-gray-300 px-3 py-2">{{ $expense->expenseCategory?->name ?? '' }}</td>
                        <td class="border border-gray-300 px-3 py-2">{{ $expense->description ?? '' }}</td>
                        <td class="border border-gray-300 px-3 py-2 text-right">{{ number_format($expense->amount, 2) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="border border-gray-300 px-3 py-2 text-center text-gray-400 italic">No expenses</td>
                    </tr>
                    @endforelse
                    <tr class="bg-gray-50">
                        <td colspan="3" class="border border-gray-300 px-3 py-2 font-bold text-right">TOTAL EXPENSES</td>
                        <td class="border border-gray-300 px-3 py-2 text-right font-bold">{{ number_format($expensesTotal, 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- ==================== + ADDITIONALS (PAYMENT ON SHORT) ==================== --}}
        <div class="mb-6">
            <h3 class="text-base font-bold mb-2">+ ADDITIONALS</h3>
            <table class="w-full border-collapse border border-gray-300 text-sm">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border border-gray-300 px-3 py-2 text-left">DESCRIPTION</th>
                        <th class="border border-gray-300 px-3 py-2 text-right w-32">AMOUNT</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($paymentOnShorts as $payment)
                    <tr>
                        <td class="border border-gray-300 px-3 py-2 text-green-600">&raquo; PAYMENT ON SHORT: {{ $payment->shiftLog?->shift ?? '' }}: {{ $payment->name }}</td>
                        <td class="border border-gray-300 px-3 py-2 text-right">{{ number_format($payment->amount, 2) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="2" class="border border-gray-300 px-3 py-2 text-center text-gray-400 italic">No additionals</td>
                    </tr>
                    @endforelse
                    <tr class="bg-gray-50">
                        <td class="border border-gray-300 px-3 py-2 font-bold text-right">TOTAL ADDITIONALS</td>
                        <td class="border border-gray-300 px-3 py-2 text-right font-bold">{{ number_format($paymentOnShortsTotal, 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- ==================== FRONTDESK CHART ==================== --}}
        <div class="mb-6">
            <h3 class="text-base font-bold mb-2">FRONTDESK CHART</h3>

            @foreach($frontdeskChart as $floorGroup)
            <div class="mb-4">
                <h4 class="text-sm font-bold mb-1">{{ $floorGroup['floor']->numberWithFormat() }}</h4>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse border border-gray-300 text-xs">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="border border-gray-300 px-2 py-1 text-left">Room#</th>
                                <th class="border border-gray-300 px-2 py-1 text-left">Rate</th>
                                <th class="border border-gray-300 px-2 py-1 text-left">Room type</th>
                                <th class="border border-gray-300 px-2 py-1 text-left">Status</th>
                                <th class="border border-gray-300 px-2 py-1 text-left">Guest</th>
                                <th class="border border-gray-300 px-2 py-1 text-left">Check-in</th>
                                <th class="border border-gray-300 px-2 py-1 text-left">Check-out</th>
                                <th class="border border-gray-300 px-2 py-1 text-center">Initial Hours</th>
                                <th class="border border-gray-300 px-2 py-1 text-right">Room Rate</th>
                                <th class="border border-gray-300 px-2 py-1 text-right">Transfer</th>
                                <th class="border border-gray-300 px-2 py-1 text-right">Extend</th>
                                <th class="border border-gray-300 px-2 py-1 text-right">Foods</th>
                                <th class="border border-gray-300 px-2 py-1 text-right">Drinks</th>
                                <th class="border border-gray-300 px-2 py-1 text-right">Misc.</th>
                                <th class="border border-gray-300 px-2 py-1 text-right">Room Deposit</th>
                                <th class="border border-gray-300 px-2 py-1 text-right">Guest Deposit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($floorGroup['rooms'] as $room)
                            <tr class="{{ ($room['is_forwarded'] ?? false) ? 'text-red-600' : '' }}">
                                <td class="border border-gray-300 px-2 py-1 align-top">{{ $room['rowspan'] > 0 ? $room['number'] : '' }}</td>
                                <td class="border border-gray-300 px-2 py-1">{{ $room['rate'] ? number_format($room['rate'], 0) : '' }}</td>
                                <td class="border border-gray-300 px-2 py-1">{{ $room['type'] }}</td>
                                <td class="border border-gray-300 px-2 py-1">{{ $room['status'] }}</td>
                                <td class="border border-gray-300 px-2 py-1">{{ $room['guest'] }}</td>
                                <td class="border border-gray-300 px-2 py-1">{{ $room['check_in'] }}</td>
                                <td class="border border-gray-300 px-2 py-1">{{ $room['check_out'] }}</td>
                                <td class="border border-gray-300 px-2 py-1 text-center">{{ $room['initial_hours'] !== '' ? $room['initial_hours'] : '' }}</td>
                                <td class="border border-gray-300 px-2 py-1 text-right">{{ $room['room_rate'] > 0 ? number_format($room['room_rate'], 2) : '' }}</td>
                                <td class="border border-gray-300 px-2 py-1 text-right">{{ $room['transfer'] > 0 ? number_format($room['transfer'], 2) : '' }}</td>
                                <td class="border border-gray-300 px-2 py-1 text-right">{{ $room['extend'] > 0 ? number_format($room['extend'], 2) : '' }}</td>
                                <td class="border border-gray-300 px-2 py-1 text-right">{{ $room['foods'] > 0 ? number_format($room['foods'], 2) : '' }}</td>
                                <td class="border border-gray-300 px-2 py-1 text-right">{{ $room['drinks'] > 0 ? number_format($room['drinks'], 2) : '' }}</td>
                                <td class="border border-gray-300 px-2 py-1 text-right">{{ $room['misc'] > 0 ? number_format($room['misc'], 2) : '' }}</td>
                                <td class="border border-gray-300 px-2 py-1 text-right">{{ $room['room_deposit'] > 0 ? number_format($room['room_deposit'], 2) : '' }}</td>
                                <td class="border border-gray-300 px-2 py-1 text-right">{{ $room['deposit'] > 0 ? number_format($room['deposit'], 2) : '' }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="16" class="border border-gray-300 px-2 py-1 text-center text-gray-400 italic">No rooms</td>
                            </tr>
                            @endforelse
                            @if(!empty($floorGroup['subtotal']))
                            <tr class="bg-gray-200 font-bold">
                                <td colspan="8" class="border border-gray-300 px-2 py-1 text-right">SUBTOTAL</td>
                                <td class="border border-gray-300 px-2 py-1 text-right">{{ ($floorGroup['subtotal']['room_rate'] ?? 0) > 0 ? number_format($floorGroup['subtotal']['room_rate'], 2) : '' }}</td>
                                <td class="border border-gray-300 px-2 py-1 text-right">{{ ($floorGroup['subtotal']['transfer'] ?? 0) > 0 ? number_format($floorGroup['subtotal']['transfer'], 2) : '' }}</td>
                                <td class="border border-gray-300 px-2 py-1 text-right">{{ ($floorGroup['subtotal']['extend'] ?? 0) > 0 ? number_format($floorGroup['subtotal']['extend'], 2) : '' }}</td>
                                <td class="border border-gray-300 px-2 py-1 text-right">{{ ($floorGroup['subtotal']['foods'] ?? 0) > 0 ? number_format($floorGroup['subtotal']['foods'], 2) : '' }}</td>
                                <td class="border border-gray-300 px-2 py-1 text-right">{{ ($floorGroup['subtotal']['drinks'] ?? 0) > 0 ? number_format($floorGroup['subtotal']['drinks'], 2) : '' }}</td>
                                <td class="border border-gray-300 px-2 py-1 text-right">{{ ($floorGroup['subtotal']['misc'] ?? 0) > 0 ? number_format($floorGroup['subtotal']['misc'], 2) : '' }}</td>
                                <td class="border border-gray-300 px-2 py-1 text-right">{{ ($floorGroup['subtotal']['room_deposit'] ?? 0) > 0 ? number_format($floorGroup['subtotal']['room_deposit'], 2) : '' }}</td>
                                <td class="border border-gray-300 px-2 py-1 text-right">{{ ($floorGroup['subtotal']['deposit'] ?? 0) > 0 ? number_format($floorGroup['subtotal']['deposit'], 2) : '' }}</td>
                            </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
            @endforeach

        </div>

        {{-- ==================== REMINDERS SA ROOMBOY ==================== --}}
        <div class="mb-6 border-2 border-red-500 rounded p-5">
            <h3 class="text-base font-bold text-red-600 mb-3">REMINDERS SA ROOMBOY</h3>

            <ol class="list-decimal list-inside text-sm space-y-2">
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
        <div class="mb-6">
            <h3 class="text-base font-bold mb-2">ROOM CLEANING CHART</h3>
            <div class="grid grid-cols-5 gap-2">
                @foreach($roomCleaningChart as $floorGroup)
                <div>
                    <h4 class="text-xs font-bold mb-1">{{ $floorGroup['floor']->numberWithFormat() }}</h4>
                    <table class="w-full border-collapse border border-gray-300 text-xs">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="border border-gray-300 px-1 py-1 text-left">Room</th>
                                <th class="border border-gray-300 px-1 py-1 text-left">Time</th>
                                <th class="border border-gray-300 px-1 py-1 text-left">Elapse</th>
                                <th class="border border-gray-300 px-1 py-1 text-left">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($floorGroup['rooms'] as $room)
                            <tr>
                                <td class="border border-gray-300 px-1 py-1 font-bold align-top">{{ ($room['rowspan'] ?? 1) > 0 ? $room['number'] : '' }}</td>
                                <td class="border border-gray-300 px-1 py-1">{{ $room['time'] }}</td>
                                <td class="border border-gray-300 px-1 py-1">{{ $room['duration'] ?? '' }}</td>
                                <td class="border border-gray-300 px-1 py-1">{{ $room['status'] }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endforeach
            </div>
        </div>

        {{-- ==================== ROOM BOY ACTIVITY LOGS ==================== --}}
        <div class="mb-6">
            <h3 class="text-base font-bold mb-2">ROOM BOY ACTIVITY LOGS</h3>

            @forelse($roomboyLogs as $log)
            <div class="mb-4">
                <h4 class="text-sm font-bold mb-1">NAME: {{ $log['name'] }}</h4>
                <table class="max-w-3xl border-collapse border border-gray-300 text-sm">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border border-gray-300 px-2 py-1 text-left w-12">#</th>
                            <th class="border border-gray-300 px-2 py-1 text-left">Room Number</th>
                            <th class="border border-gray-300 px-2 py-1 text-left">Floor Number</th>
                            <th class="border border-gray-300 px-2 py-1 text-left">Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($log['entries'] as $entry)
                        <tr>
                            <td class="border border-gray-300 px-2 py-1">{{ $entry['number'] }}</td>
                            <td class="border border-gray-300 px-2 py-1 align-top">{{ ($entry['rowspan'] ?? 1) > 0 ? $entry['room_number'] : '' }}</td>
                            <td class="border border-gray-300 px-2 py-1">{{ ($entry['rowspan'] ?? 1) > 0 ? $entry['floor_number'] : '' }}</td>
                            <td class="border border-gray-300 px-2 py-1">{{ $entry['time'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @empty
            <p class="text-sm text-gray-400 italic">No room boy activity during this shift.</p>
            @endforelse
        </div>

        {{-- ==================== SIGNATURES ==================== --}}
        <div class="mt-12 grid grid-cols-2 gap-8">
            <div class="text-center">
                <div class="border-b border-gray-400 mb-1 w-48 mx-auto"></div>
                <p class="text-sm font-semibold">Prepared By</p>
            </div>
            <div class="text-center">
                <div class="border-b border-gray-400 mb-1 w-48 mx-auto"></div>
                <p class="text-sm font-semibold">Verified By</p>
            </div>
        </div>

    </div>

</div>
