<div>
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-semibold text-gray-800">Shift Log Correction</h2>
        <div class="flex items-center gap-3">
            <x-input type="date" wire:model="date" label="Filter by date" />
        </div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Frontdesk</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Shift</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time In</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time Out</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Transactions</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($shiftLogs as $log)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $log->id }}</td>
                        <td class="px-4 py-3 text-sm text-gray-900 font-medium">{{ $log->frontdesk?->name ?? 'Unknown' }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $log->shift === 'AM' ? 'bg-blue-100 text-blue-800' : 'bg-indigo-100 text-indigo-800' }}">
                                {{ $log->shift }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $log->time_in?->format('M d, g:i A') }}</td>
                        <td class="px-4 py-3 text-sm">
                            @if ($log->time_out)
                                <span class="text-gray-600">{{ $log->time_out->format('M d, g:i A') }}</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600 text-center">{{ $log->transactions_count }}</td>
                        <td class="px-4 py-3 text-center">
                            <x-button xs warning label="Change Shift" wire:click="openEdit({{ $log->id }})" spinner="openEdit" />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">
                            No shift logs found for this date.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Edit Shift Modal --}}
    @if ($editModal)
    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-60 transition-opacity"></div>
        <div class="w-full min-h-full flex items-center justify-center p-4">
            <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Change Shift</h3>

                @php $currentLog = $shiftLogs->firstWhere('id', $editingShiftLogId); @endphp

                @if ($currentLog)
                <div class="mb-4 text-sm text-gray-600">
                    <p><span class="font-medium">Frontdesk:</span> {{ $currentLog->frontdesk?->name }}</p>
                    <p><span class="font-medium">Current Shift:</span>
                        <span class="font-bold {{ $currentLog->shift === 'AM' ? 'text-blue-600' : 'text-indigo-600' }}">{{ $currentLog->shift }}</span>
                    </p>
                    <p><span class="font-medium">Time:</span> {{ $currentLog->time_in?->format('M d, g:i A') }} - {{ $currentLog->time_out?->format('g:i A') }}</p>
                </div>
                @endif

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Change to:</label>
                    <div class="py-2 px-4 rounded-lg text-sm font-semibold border-2 text-center {{ $newShift === 'AM' ? 'border-blue-600 bg-blue-50 text-blue-700' : 'border-indigo-600 bg-indigo-50 text-indigo-700' }}">
                        {{ $newShift }}
                    </div>
                </div>

                <div class="mb-4">
                    <x-textarea label="Reason for correction *" placeholder="Explain why the shift needs to be changed..." wire:model.defer="reason" />
                    @error('reason') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                </div>

                <div class="flex justify-end gap-x-3">
                    <x-button flat label="Cancel" wire:click="closeEdit" spinner="closeEdit" />
                    <x-button warning label="Save Correction" wire:click="updateShift" spinner="updateShift" />
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
