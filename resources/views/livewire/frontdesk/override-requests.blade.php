<div class="p-6" wire:poll.5s>
    <h2 class="text-xl font-semibold text-gray-800 mb-6">Transfer Room Requests</h2>

    {{-- Pending Requests --}}
    @if($this->pendingRequests->count() > 0)
    <div class="mb-8">
        <h3 class="text-lg font-medium text-gray-700 mb-4 flex items-center">
            <span class="bg-gray-200 p-1 rounded-full mr-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </span>
            Pending Approval ({{ $this->pendingRequests->count() }})
        </h3>
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Guest</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">From Room</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">To Room</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reason</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Supervisor</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Requested</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($this->pendingRequests as $request)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $request->guest->name ?? 'N/A' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $request->fromRoom->number ?? 'N/A' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $request->toRoom->number ?? 'N/A' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $request->transferReason->reason ?? 'N/A' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $request->supervisor->name ?? 'N/A' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $request->created_at->diffForHumans() }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <button wire:click="confirmCancelRequest({{ $request->id }})" class="text-red-600 hover:text-red-800 text-sm font-medium">
                                Cancel
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Approved Requests (Ready to Complete) --}}
    @if($this->approvedRequests->count() > 0)
    <div class="mb-8">
        <h3 class="text-lg font-medium text-green-600 mb-4 flex items-center">
            <span class="bg-green-100 p-1 rounded-full mr-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </span>
            Approved - Ready to Complete ({{ $this->approvedRequests->count() }})
        </h3>
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-green-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Guest</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">From Room</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">To Room</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reason</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Approved By</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Approved At</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($this->approvedRequests as $request)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $request->guest->name ?? 'N/A' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $request->fromRoom->number ?? 'N/A' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $request->toRoom->number ?? 'N/A' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $request->transferReason->reason ?? 'N/A' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $request->supervisor->name ?? 'N/A' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $request->responded_at ? $request->responded_at->diffForHumans() : 'N/A' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <button wire:click="openConfirmModal({{ $request->id }})" class="bg-green-500 hover:bg-green-600 text-white text-sm font-bold px-4 py-2 rounded">
                                COMPLETE TRANSFER
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Declined Requests --}}
    @if($this->declinedRequests->count() > 0)
    <div class="mb-8">
        <h3 class="text-lg font-medium text-red-600 mb-4 flex items-center">
            <span class="bg-red-100 p-1 rounded-full mr-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </span>
            Declined Today ({{ $this->declinedRequests->count() }})
        </h3>
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-red-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Guest</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">From Room</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">To Room</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reason</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Decline Reason</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($this->declinedRequests as $request)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $request->guest->name ?? 'N/A' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $request->fromRoom->number ?? 'N/A' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $request->toRoom->number ?? 'N/A' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $request->transferReason->reason ?? 'N/A' }}</td>
                        <td class="px-6 py-4 text-sm text-red-600 max-w-xs">{{ $request->decline_reason ?? 'N/A' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <button wire:click="retryRequest({{ $request->id }})" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                Retry Transfer
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Empty State --}}
    @if($this->pendingRequests->count() == 0 && $this->approvedRequests->count() == 0 && $this->declinedRequests->count() == 0)
    <div class="bg-white rounded-lg shadow-sm p-12 text-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-16 w-16 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <h3 class="mt-4 text-lg font-medium text-gray-900">No Override Requests</h3>
        <p class="mt-2 text-sm text-gray-500">You don't have any pending, approved, or declined override requests.</p>
    </div>
    @endif

    {{-- Confirm Transfer Modal --}}
    <x-modal wire:model.defer="confirmModal" align="center" max-width="md">
        <x-card>
            @if($selectedRequest)
            <div class="text-center py-4">
                <div class="bg-green-100 rounded-full p-4 w-16 h-16 mx-auto mb-4 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-gray-700 mb-4">Complete Transfer</h3>

                <div class="bg-gray-50 rounded-lg p-4 text-left mb-4">
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-gray-500">Guest:</span>
                            <p class="font-medium">{{ $selectedRequest->guest->name ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <span class="text-gray-500">From Room:</span>
                            <p class="font-medium">{{ $selectedRequest->fromRoom->number ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <span class="text-gray-500">To Room:</span>
                            <p class="font-medium text-green-600">{{ $selectedRequest->toRoom->number ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <span class="text-gray-500">Reason:</span>
                            <p class="font-medium">{{ $selectedRequest->transferReason->reason ?? 'N/A' }}</p>
                        </div>
                    </div>
                </div>

                <p class="text-sm text-gray-500">Are you sure you want to complete this transfer?</p>
            </div>
            @endif

            <x-slot name="footer">
                <div class="flex justify-center space-x-2">
                    <x-button flat label="Cancel" x-on:click="close" />
                    <x-button positive label="Complete Transfer" wire:click="completeTransfer" spinner="completeTransfer" />
                </div>
            </x-slot>
        </x-card>
    </x-modal>
</div>
