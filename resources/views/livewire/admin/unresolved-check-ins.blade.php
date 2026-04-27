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

    {{-- MAINTENANCE NOTICE — 2026-04-28 --}}
    {{-- The "Fix All" action and detection logic have a bug that flags ACTIVE   --}}
    {{-- guests with future check_out_at as ghosts (because the query uses       --}}
    {{-- number_of_hours, which is 0 for long-stay/extension guests, instead of --}}
    {{-- check_out_at). Clicking Fix All on 2026-04-27 23:19 force-closed 20    --}}
    {{-- active guests; recovered from backup. Button disabled until query is   --}}
    {{-- fixed. See docs/bugs/2026-04-28-fixall-unresolved-flips-active-extension-checkins.md --}}
    <div class="bg-yellow-50 border-2 border-yellow-300 rounded-lg p-4 mb-6">
        <div class="flex items-start gap-3">
            <svg class="w-6 h-6 text-yellow-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <div>
                <h2 class="font-semibold text-yellow-800 mb-1">Feature Under Maintenance — Do Not Use</h2>
                <p class="text-yellow-700 text-sm mb-2">
                    The "Fix All Records" action is <strong>temporarily disabled</strong> due to a detection bug
                    that flags ACTIVE guests (with future check-out dates) as ghost records.
                </p>
                <p class="text-yellow-700 text-sm mb-2">
                    The records listed below may include real, paying guests who are still inside their rooms.
                    <strong>Do not assume they are ghosts.</strong>
                </p>
                <p class="text-yellow-700 text-sm">
                    For each suspected ghost, verify manually with frontdesk (key card slot, physical room check)
                    before any action. A proper fix is being prepared.
                </p>
            </div>
        </div>
    </div>

    {{-- Fix Button — DISABLED 2026-04-28 due to false-positive bug. --}}
    {{-- Original button removed. Detection logic still runs (read-only). --}}
    @if($summary['total_ghosts'] > 0)
    <div class="mb-6">
        <button disabled class="bg-gray-300 text-gray-500 px-6 py-2 rounded cursor-not-allowed font-medium">
            Fix All Records — Disabled (Under Maintenance)
        </button>
        <p class="text-xs text-gray-500 mt-2">
            This action is disabled until the detection query is corrected. Listing below is read-only.
        </p>
    </div>
    @else
    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-6">
        <p class="text-gray-600 font-medium">All clear. No ghost records found.</p>
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
