<div class="p-6">
    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Unresolved Check-Ins</h1>
        <p class="text-gray-500 text-sm">{{ $summary['total_ghosts'] }} records need attention</p>
    </div>

    {{-- Summary Bar --}}
    <div class="bg-white border border-gray-200 rounded-lg p-4 mb-6 flex flex-wrap gap-6">
        <div>
            <span class="text-gray-500 text-sm">Ghost Records</span>
            <p class="text-xl font-bold text-gray-800">{{ $summary['total_ghosts'] }}</p>
        </div>
        <div class="border-l border-gray-200 pl-6">
            <span class="text-gray-500 text-sm">Stuck Deposits</span>
            <p class="text-xl font-bold text-gray-800">₱{{ number_format($summary['total_deposits'], 2) }}</p>
        </div>
        <div class="border-l border-gray-200 pl-6">
            <span class="text-gray-500 text-sm">Rooms Affected</span>
            <p class="text-xl font-bold text-gray-800">{{ $summary['blocked_count'] }}</p>
        </div>
        <div class="border-l border-gray-200 pl-6">
            <span class="text-gray-500 text-sm">Guards</span>
            <p class="text-xl font-bold {{ $guardsEnabled ? 'text-green-600' : 'text-red-600' }}">{{ $guardsEnabled ? 'Enabled' : 'Disabled' }}</p>
        </div>
    </div>

    {{-- What is this --}}
    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-6">
        <h2 class="font-semibold text-gray-700 mb-2">What are ghost records?</h2>
        <p class="text-gray-600 text-sm">
            Ghost records occur when guests leave without checking out. The room gets cleaned and reused, but the old check-in record stays open.
            This causes wrong guest counts in reports and stuck deposits.
        </p>
    </div>

    {{-- Affected Rooms --}}
    @if($blockedRooms->count() > 0)
    <div class="bg-white border border-gray-200 rounded-lg p-4 mb-6">
        <h2 class="font-semibold text-gray-700 mb-3">Rooms with ghost records ({{ $blockedRooms->count() }})</h2>
        <p class="text-gray-500 text-sm mb-3">These rooms will be blocked from kiosk/roomboy if guards are enabled without fixing first.</p>
        <div class="flex flex-wrap gap-2">
            @foreach($blockedRooms as $room)
            <span class="bg-gray-100 border border-gray-300 px-3 py-1 rounded text-sm font-medium text-gray-700">
                {{ $room['room_number'] }} <span class="text-gray-400">(F{{ $room['floor_number'] }})</span>
            </span>
            @endforeach
        </div>
    </div>
    @endif

    {{-- INFO NOTICE — Detection query FIXED 2026-04-28 --}}
    <div class="bg-blue-50 border-2 border-blue-300 rounded-lg p-4 mb-6">
        <div class="flex items-start gap-3">
            <svg class="w-6 h-6 text-blue-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div>
                <h2 class="font-semibold text-blue-800 mb-1">Ghost Record Detection</h2>
                <p class="text-blue-700 text-sm mb-2">
                    Records shown below are guests whose <strong>expected checkout was 2+ days ago</strong>
                    and have not been formally checked out.
                </p>
                <p class="text-blue-700 text-sm">
                    <strong>These are likely true ghosts</strong> — guests who left without checkout.
                    Click "Fix All Records" to close them, or process each manually via frontdesk.
                </p>
            </div>
        </div>
    </div>

    {{-- Fix Button — ENABLED (detection query fixed 2026-04-28) --}}
    @if($summary['total_ghosts'] > 0)
    <div class="mb-6">
        <button wire:click="confirmFix" class="bg-red-500 hover:bg-red-600 text-white px-6 py-2 rounded font-medium transition-colors">
            Fix All Records ({{ $summary['total_ghosts'] }})
        </button>
        <p class="text-xs text-gray-500 mt-2">
            This will mark {{ $summary['total_ghosts'] }} ghost record(s) as checked out. A confirmation will appear.
        </p>
    </div>
    @else
    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
        <p class="text-green-700 font-medium">✓ All clear — No ghost records found (2+ days overdue).</p>
    </div>
    @endif

    {{-- Records Table --}}
    @if($summary['total_ghosts'] > 0)
    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">
            <h3 class="font-semibold text-gray-700">Ghost Records</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">ID</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Room</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Floor</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Room Status</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Guest</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Check-in</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Expected Out</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Overdue</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Deposit</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Why Ghost?</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($ghostRecords as $record)
                    <tr class="hover:bg-gray-50 {{ $record['days_overdue'] >= 7 ? 'bg-red-50' : '' }}">
                        <td class="px-4 py-3 text-gray-400 text-xs">{{ $record['id'] }}</td>
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $record['room_number'] }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $record['floor_number'] }}</td>
                        <td class="px-4 py-3">
                            @if($record['room_status'] === 'Available')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                    {{ $record['room_status'] }}
                                </span>
                                <span class="block text-xs text-gray-400 mt-0.5">Room reused</span>
                            @elseif($record['room_status'] === 'Occupied')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                    {{ $record['room_status'] }}
                                </span>
                                <span class="block text-xs text-gray-400 mt-0.5">New guest in room</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                                    {{ $record['room_status'] }}
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-900">{{ $record['guest_name'] }}</td>
                        <td class="px-4 py-3 text-gray-600 text-sm">{{ $record['check_in_at'] }}</td>
                        <td class="px-4 py-3 text-gray-600 text-sm">{{ $record['expected_out'] }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $record['days_overdue'] >= 7 ? 'bg-red-100 text-red-800' : 'bg-orange-100 text-orange-800' }}">
                                {{ $record['days_overdue'] }} days
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-900 font-medium">₱{{ number_format($record['deposit'], 2) }}</td>
                        <td class="px-4 py-3 text-xs text-gray-500">
                            <span class="block">Expected checkout passed</span>
                            <span class="block">Guest never checked out</span>
                            @if($record['room_status'] !== 'Occupied')
                                <span class="block text-orange-600">Room was reused</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <button wire:click="confirmSingleFix({{ $record['id'] }})"
                                class="inline-flex items-center px-3 py-1 border border-red-300 text-xs font-medium rounded text-red-700 bg-white hover:bg-red-50 transition-colors">
                                Fix
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Confirmation Modal --}}
    <x-modal wire:model.defer="showConfirmModal" max-width="lg">
        <x-card title="Confirm Fix All Ghost Records">
            <p class="text-gray-600 mb-4">
                This will mark <strong>{{ $summary['total_ghosts'] }} ghost record(s)</strong> as checked out.
            </p>

            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-4">
                <h4 class="font-semibold text-gray-700 mb-2">What will happen:</h4>
                <div class="text-sm text-gray-600 space-y-2">
                    <div class="flex items-start gap-2">
                        <span class="text-green-500 font-bold">✓</span>
                        <span><code class="bg-gray-100 px-1 rounded">is_check_out</code> will be set to <strong>TRUE</strong> (marks as checked out)</span>
                    </div>
                    <div class="flex items-start gap-2">
                        <span class="text-blue-500 font-bold">→</span>
                        <span><code class="bg-gray-100 px-1 rounded">check_out_at</code> will be <strong>PRESERVED</strong> (original expected time kept for audit)</span>
                    </div>
                    <div class="flex items-start gap-2">
                        <span class="text-blue-500 font-bold">→</span>
                        <span>ActivityLog entry created for each record (audit trail)</span>
                    </div>
                </div>
            </div>

            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                <h4 class="font-semibold text-yellow-700 mb-2">What will NOT happen:</h4>
                <div class="text-sm text-yellow-700 space-y-1">
                    <p>• Room status will NOT change</p>
                    <p>• Deposits will NOT be auto-refunded (handle manually if needed)</p>
                    <p>• Guest record will NOT be deleted</p>
                </div>
            </div>

            <div class="text-sm text-gray-500 space-y-1 border-t pt-3">
                <p><strong>Summary:</strong></p>
                <p>• {{ $summary['total_ghosts'] }} record(s) will be closed</p>
                <p>• ₱{{ number_format($summary['total_deposits'], 2) }} in deposits on these records</p>
            </div>

            <x-slot name="footer">
                <div class="flex justify-end gap-3">
                    <x-button flat label="Cancel" wire:click="$set('showConfirmModal', false)" />
                    <x-button negative label="Fix All ({{ $summary['total_ghosts'] }})" wire:click="fixAllGhostRecords" />
                </div>
            </x-slot>
        </x-card>
    </x-modal>

    {{-- Single Record Confirmation Modal --}}
    <x-modal wire:model.defer="showSingleConfirmModal" max-width="lg">
        <x-card title="Confirm Fix Ghost Record">
            @if($selectedRecordData)
            <div class="space-y-4">
                {{-- Record Header --}}
                <div class="bg-gradient-to-r from-gray-50 to-gray-100 border border-gray-200 rounded-lg p-5">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <p class="text-xs text-gray-500 uppercase tracking-wide">Room</p>
                            <p class="text-2xl font-bold text-gray-800">#{{ $selectedRecordData['room_number'] }}</p>
                            @if($selectedRecordData['floor_number'] !== 'N/A')
                                <p class="text-sm text-gray-500">Floor {{ $selectedRecordData['floor_number'] }}</p>
                            @endif
                        </div>
                        <div class="text-right">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                                {{ $selectedRecordData['room_status'] === 'Available' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                {{ $selectedRecordData['room_status'] }}
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 pt-4 border-t border-gray-200">
                        <div>
                            <p class="text-xs text-gray-500 uppercase tracking-wide">Guest Name</p>
                            <p class="text-base font-semibold text-gray-800">{{ $selectedRecordData['guest_name'] }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 uppercase tracking-wide">Record ID</p>
                            <p class="text-base font-mono text-gray-600">#{{ $selectedRecordData['id'] }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 uppercase tracking-wide">Check-in</p>
                            <p class="text-sm text-gray-700">{{ $selectedRecordData['check_in_at'] }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 uppercase tracking-wide">Expected Checkout</p>
                            <p class="text-sm text-gray-700">{{ $selectedRecordData['check_out_at'] }}</p>
                        </div>
                    </div>

                    @if($selectedRecordData['deposit'] > 0)
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <div class="flex items-center justify-between">
                            <p class="text-xs text-gray-500 uppercase tracking-wide">Stuck Deposit</p>
                            <p class="text-xl font-bold text-orange-600">₱{{ number_format($selectedRecordData['deposit'], 2) }}</p>
                        </div>
                    </div>
                    @endif
                </div>

                {{-- What will happen --}}
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <h4 class="font-semibold text-green-800 mb-3 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        What will happen:
                    </h4>
                    <ul class="text-sm text-green-700 space-y-2">
                        <li class="flex items-start gap-2">
                            <span class="text-green-500 mt-0.5">✓</span>
                            <span>Record marked as <strong>checked out</strong></span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-green-500 mt-0.5">✓</span>
                            <span>Guest count reports will be corrected</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-green-500 mt-0.5">✓</span>
                            <span>Activity log entry created (audit trail)</span>
                        </li>
                    </ul>
                </div>

                {{-- What will NOT happen --}}
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                    <h4 class="font-semibold text-amber-800 mb-3 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        What will NOT happen:
                    </h4>
                    <ul class="text-sm text-amber-700 space-y-2">
                        <li class="flex items-start gap-2">
                            <span class="text-amber-500 mt-0.5">•</span>
                            <span>Room status stays as <strong>{{ $selectedRecordData['room_status'] }}</strong></span>
                        </li>
                        @if($selectedRecordData['deposit'] > 0)
                        <li class="flex items-start gap-2">
                            <span class="text-amber-500 mt-0.5">•</span>
                            <span>Deposit <strong>₱{{ number_format($selectedRecordData['deposit'], 2) }}</strong> needs manual handling</span>
                        </li>
                        @endif
                        <li class="flex items-start gap-2">
                            <span class="text-amber-500 mt-0.5">•</span>
                            <span>Guest record preserved (not deleted)</span>
                        </li>
                    </ul>
                </div>
            </div>
            @endif

            <x-slot name="footer">
                <div class="flex justify-end gap-3">
                    <x-button flat label="Cancel" wire:click="$set('showSingleConfirmModal', false)" />
                    <x-button negative label="Fix This Record" wire:click="fixSingleRecord" />
                </div>
            </x-slot>
        </x-card>
    </x-modal>
</div>
