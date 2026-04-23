<div class="p-6" wire:poll.5s>
    <h2 class="text-xl font-semibold text-gray-800 mb-6">Override Requests</h2>

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
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Guest</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Room</th>
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
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            @if($request->transaction_type === 'cancel')
                                <span class="text-red-600 font-medium">Cancel</span>
                            @else
                                <span class="text-gray-900">Transfer</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $request->guest->name ?? 'N/A' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $request->fromRoom->number ?? 'N/A' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $request->toRoom->number ?? 'N/A' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            @if($request->transaction_type === 'cancel')
                                {{ $request->request_data['cancel_reason'] ?? 'N/A' }}
                            @else
                                {{ $request->transferReason->reason ?? 'N/A' }}
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $request->supervisor->name ?? 'N/A' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $request->created_at->format('M d, Y h:i A') }}</td>
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


    {{-- Declined Requests --}}
    @if($this->declinedRequests->count() > 0)
    <div class="mb-8">
        <h3 class="text-lg font-medium text-red-600 mb-4 flex items-center">
            <span class="bg-red-100 p-1 rounded-full mr-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </span>
            Declined History ({{ $this->declinedRequests->count() }})
        </h3>
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-red-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Guest</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Room</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reason</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Requested At</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Declined At</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Decline Reason</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($this->declinedRequests as $request)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            @if($request->transaction_type === 'cancel')
                                <span class="text-red-600 font-medium">Cancel</span>
                            @else
                                <span class="text-gray-900">Transfer</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $request->guest->name ?? 'N/A' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $request->fromRoom->number ?? 'N/A' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            @if($request->transaction_type === 'cancel')
                                {{ $request->request_data['cancel_reason'] ?? 'N/A' }}
                            @else
                                {{ $request->transferReason->reason ?? 'N/A' }}
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $request->created_at->format('M d, Y h:i A') }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $request->responded_at ? $request->responded_at->format('M d, Y h:i A') : 'N/A' }}</td>
                        <td class="px-6 py-4 text-sm text-red-600 max-w-xs">{{ $request->decline_reason ?? 'N/A' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($request->transaction_type === 'transfer')
                                @if(!$this->guestHasActiveRequest($request->guest_id))
                                    <button wire:click="retryRequest({{ $request->id }})" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                        Retry Transfer
                                    </button>
                                @else
                                    <span class="text-gray-400 text-sm">Has Active Request</span>
                                @endif
                            @else
                                <span class="text-gray-400 text-sm">-</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Empty State --}}
    @if($this->pendingRequests->count() == 0 && $this->declinedRequests->count() == 0)
    <div class="bg-white rounded-lg shadow-sm p-12 text-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-16 w-16 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <h3 class="mt-4 text-lg font-medium text-gray-900">No Override Requests</h3>
        <p class="mt-2 text-sm text-gray-500">You don't have any pending, approved, or declined override requests.</p>
    </div>
    @endif

</div>
