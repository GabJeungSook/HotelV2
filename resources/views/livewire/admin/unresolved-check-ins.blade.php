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

    {{-- INFO NOTICE — 2026-04-28 --}}
    {{-- Detection query FIXED: Now uses check_out_at (correct) with 48-hour threshold. --}}
    {{-- Fix-All button disabled until per-row confirmation UI is built.                --}}
    {{-- See: docs/incidents/2026-04-28-fix-all-unresolved-incident-report.md           --}}
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
                <p class="text-blue-700 text-sm mb-2">
                    <strong>These are likely true ghosts</strong> — guests who left without checkout.
                    Verify with frontdesk before taking action.
                </p>
                <p class="text-blue-700 text-sm">
                    The "Fix All" button is temporarily disabled. To resolve ghost records,
                    process each checkout manually via frontdesk.
                </p>
            </div>
        </div>
    </div>

    {{-- Fix Button — DISABLED until per-row confirmation UI is built --}}
    {{-- Detection query is fixed. Button disabled for safety (requires manual checkout). --}}
    @if($summary['total_ghosts'] > 0)
    <div class="mb-6">
        <button disabled class="bg-gray-300 text-gray-500 px-6 py-2 rounded cursor-not-allowed font-medium">
            Fix All Records — Disabled
        </button>
        <p class="text-xs text-gray-500 mt-2">
            Bulk action disabled for safety. Process each ghost record manually via frontdesk checkout.
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
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Room</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Floor</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Status</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Guest</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Check-in</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Expected Out</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Overdue</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Deposit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($ghostRecords as $record)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $record['room_number'] }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $record['floor_number'] }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $record['room_status'] }}</td>
                        <td class="px-4 py-3 text-gray-900">{{ $record['guest_name'] }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $record['check_in_at'] }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $record['expected_out'] }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $record['days_overdue'] }} days</td>
                        <td class="px-4 py-3 text-gray-900">₱{{ number_format($record['deposit'], 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Confirmation Modal --}}
    <x-modal wire:model.defer="showConfirmModal" max-width="sm">
        <x-card title="Confirm">
            <p class="text-gray-600 mb-4">
                This will mark {{ $summary['total_ghosts'] }} records as checked out.
                Checkout time will be set to their expected checkout + 30 minutes.
            </p>
            <div class="text-sm text-gray-500 space-y-1 mb-4">
                <p>• {{ $summary['total_ghosts'] }} records fixed</p>
                <p>• ₱{{ number_format($summary['total_deposits'], 2) }} deposits resolved</p>
                <p>• {{ $summary['blocked_count'] }} rooms unblocked</p>
            </div>
            <x-slot name="footer">
                <div class="flex justify-end gap-3">
                    <x-button flat label="Cancel" wire:click="$set('showConfirmModal', false)" />
                    <x-button negative label="Fix All" wire:click="fixAllGhostRecords" />
                </div>
            </x-slot>
        </x-card>
    </x-modal>
</div>
