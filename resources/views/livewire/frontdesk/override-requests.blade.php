<div class="p-6" wire:poll.5s>
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-semibold text-gray-800">Override Requests</h2>

        {{-- Search and Date Filter --}}
        <div class="flex items-center space-x-4">
            <div class="flex items-center space-x-2">
                <label class="text-sm text-gray-600">Date:</label>
                <input type="date" wire:model="dateFilter"
                    class="border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-gray-400 focus:border-transparent">
            </div>
            <div class="relative">
                <input type="text" wire:model.debounce.300ms="search" placeholder="Search guest, room..."
                    class="w-64 pl-10 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-gray-400 focus:border-transparent">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400 absolute left-3 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="border-b border-gray-200 mb-6">
        <nav class="-mb-px flex space-x-8">
            {{-- Pending Tab --}}
            <button wire:click="$set('activeTab', 'pending')"
                class="py-3 px-1 border-b-2 font-medium text-sm flex items-center space-x-2
                    {{ $activeTab === 'pending' ? 'border-gray-700 text-gray-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Pending</span>
                @if($this->pendingRequests->count() > 0)
                    <span class="bg-yellow-500 text-white text-xs font-bold px-2 py-0.5 rounded-full">{{ $this->pendingRequests->count() }}</span>
                @endif
            </button>

            {{-- Approved Tab --}}
            <button wire:click="$set('activeTab', 'approved')"
                class="py-3 px-1 border-b-2 font-medium text-sm flex items-center space-x-2
                    {{ $activeTab === 'approved' ? 'border-green-600 text-green-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Approved</span>
                @if($this->approvedRequests->count() > 0)
                    <span class="bg-green-500 text-white text-xs font-bold px-2 py-0.5 rounded-full">{{ $this->approvedRequests->count() }}</span>
                @endif
            </button>

            {{-- Declined Tab --}}
            <button wire:click="$set('activeTab', 'declined')"
                class="py-3 px-1 border-b-2 font-medium text-sm flex items-center space-x-2
                    {{ $activeTab === 'declined' ? 'border-red-600 text-red-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Declined</span>
                @if($this->declinedRequests->count() > 0)
                    <span class="bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full">{{ $this->declinedRequests->count() }}</span>
                @endif
            </button>
        </nav>
    </div>

    {{-- Pending Tab Content --}}
    @if($activeTab === 'pending')
        @if($this->pendingRequests->count() > 0)
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Transaction Type</th>
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
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $request->guest->name ?? $request->request_data['guest_name'] ?? 'N/A' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $request->fromRoom->number ?? $request->request_data['from_room_number'] ?? 'N/A' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $request->toRoom->number ?? $request->request_data['to_room_number'] ?? 'N/A' }}</td>
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
                            <button wire:click="confirmCancelRequest({{ $request->id }})" class="bg-red-500 hover:bg-red-600 text-white text-xs font-bold px-4 py-1.5 rounded">
                                CANCEL
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="bg-white rounded-lg shadow-sm p-12 text-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <h3 class="mt-4 text-lg font-medium text-gray-900">No Pending Requests</h3>
            <p class="mt-2 text-sm text-gray-500">You don't have any pending override requests.</p>
        </div>
        @endif
    @endif

    {{-- Approved Tab Content --}}
    @if($activeTab === 'approved')
        @if($this->approvedRequests->count() > 0)
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-green-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Transaction Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Guest</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Room</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">To Room</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reason</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Requested At</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Approved At</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Approved By</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($this->approvedRequests as $request)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            @if($request->transaction_type === 'cancel')
                                <span class="text-red-600 font-medium">Cancel</span>
                            @else
                                <span class="text-gray-900">Transfer</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $request->guest->name ?? $request->request_data['guest_name'] ?? 'N/A' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $request->fromRoom->number ?? $request->request_data['from_room_number'] ?? 'N/A' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $request->toRoom->number ?? $request->request_data['to_room_number'] ?? 'N/A' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            @if($request->transaction_type === 'cancel')
                                {{ $request->request_data['cancel_reason'] ?? 'N/A' }}
                            @else
                                {{ $request->transferReason->reason ?? 'N/A' }}
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $request->created_at->format('M d, Y h:i A') }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $request->responded_at ? $request->responded_at->format('M d, Y h:i A') : 'N/A' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $request->supervisor->name ?? 'Auto' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            @if($request->status === 'auto_approved')
                                <span class="text-yellow-600 font-medium">Auto Override</span>
                            @else
                                <span class="text-green-600 font-medium">Approved</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="bg-white rounded-lg shadow-sm p-12 text-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-12 w-12 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <h3 class="mt-4 text-lg font-medium text-gray-900">No Approved Requests</h3>
            <p class="mt-2 text-sm text-gray-500">No approved override requests in the last 30 days.</p>
        </div>
        @endif
    @endif

    {{-- Declined Tab Content --}}
    @if($activeTab === 'declined')
        @if($this->declinedRequests->count() > 0)
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-red-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Transaction Type</th>
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
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $request->guest->name ?? $request->request_data['guest_name'] ?? 'N/A' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $request->fromRoom->number ?? $request->request_data['from_room_number'] ?? 'N/A' }}</td>
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
                                    <button wire:click="retryRequest({{ $request->id }})" class="bg-blue-500 hover:bg-blue-600 text-white text-xs font-bold px-4 py-1.5 rounded">
                                        RETRY
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
        @else
        <div class="bg-white rounded-lg shadow-sm p-12 text-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-12 w-12 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <h3 class="mt-4 text-lg font-medium text-gray-900">No Declined Requests</h3>
            <p class="mt-2 text-sm text-gray-500">No declined override requests in the last 30 days.</p>
        </div>
        @endif
    @endif

</div>
