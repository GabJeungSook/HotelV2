<div wire:poll.5s>
    {{-- HOMI Logo outside dark header --}}
    <div class="flex justify-start px-4 sm:px-8 py-4">
        <img src="{{ asset('images/homiLogo.png') }}" class="h-8 sm:h-10" alt="HOMI">
    </div>

    {{-- Dark Header Section with Stats --}}
    <div class="mx-3 sm:mx-6 bg-[#1f2937] rounded-xl pb-8 sm:pb-16">
        <div class="px-4 sm:px-6 py-4 sm:py-6">
            {{-- Main row with two groups - stacks on mobile --}}
            <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4 lg:gap-8">
                {{-- Left Group: ACTIVE + User Box --}}
                <div class="flex flex-col space-y-2">
                    <div class="flex items-center space-x-2">
                        <span class="text-white text-xl sm:text-2xl font-bold">ACTIVE</span>
                        <span class="relative flex h-3 w-3">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                        </span>
                    </div>
                    <div class="border border-gray-600 rounded-lg px-4 sm:px-6 py-3 flex flex-col justify-center">
                        <div class="flex items-center space-x-3">
                            <div class="bg-white rounded-full w-10 h-10 sm:w-12 sm:h-12 flex items-center justify-center flex-shrink-0">
                                <span class="text-gray-700 font-bold text-base sm:text-lg">SV</span>
                            </div>
                            <div class="min-w-0">
                                <p class="text-white font-bold text-sm sm:text-base truncate">{{ strtoupper(auth()->user()->name) }}</p>
                                <p class="text-gray-400 text-xs">SUPERVISOR</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Right Group: DASHBOARD + Stats --}}
                <div class="flex flex-col items-start space-y-2 w-full lg:w-auto">
                    <h1 class="text-white text-xl sm:text-2xl font-bold">DASHBOARD</h1>
                    {{-- Stats grid - responsive grid layout --}}
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2 w-full lg:w-auto">
                        {{-- Override Summary - White (Clickable) --}}
                        <button wire:click="openSummaryModal" class="bg-white rounded-lg px-3 sm:px-6 py-3 flex flex-col justify-center hover:bg-gray-100 transition cursor-pointer text-left">
                            <span class="text-gray-600 text-xs sm:text-sm">Override Summary</span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 sm:h-6 sm:w-6 text-gray-500 mt-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>

                        {{-- Override Requests - Dark Gray --}}
                        <div class="bg-gray-600 rounded-lg px-3 sm:px-5 py-3">
                            <p class="text-xs text-red-400 font-medium">Override Request(s)</p>
                            <p class="text-2xl sm:text-3xl font-bold text-white">{{ $this->pendingCount }}</p>
                        </div>

                        {{-- Approved Requests - Green --}}
                        <div class="bg-green-500 rounded-lg px-3 sm:px-5 py-3">
                            <p class="text-xs text-green-100 font-medium">Approved Request(s)</p>
                            <p class="text-2xl sm:text-3xl font-bold text-white">{{ $this->approvedCount }}</p>
                        </div>

                        {{-- Auto-Approve Requests - Yellow --}}
                        <div class="bg-yellow-500 rounded-lg px-3 sm:px-5 py-3">
                            <p class="text-xs text-yellow-100 font-medium">Auto-Approve(s)</p>
                            <p class="text-2xl sm:text-3xl font-bold text-white">{{ $this->autoApprovedCount }}</p>
                        </div>

                        {{-- Declined Requests - Red --}}
                        <div class="bg-red-500 rounded-lg px-3 sm:px-5 py-3">
                            <p class="text-xs text-red-100 font-medium">Declined Request(s)</p>
                            <p class="text-2xl sm:text-3xl font-bold text-white">{{ $this->declinedCount }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Floating White Card with Table --}}
    <main class="-mt-8 sm:-mt-16 mx-3 sm:mx-6">
        <div class="pb-8 sm:pb-12">
            <div class="rounded-xl bg-white px-4 sm:px-6 py-4 sm:py-6 shadow-sm mx-0 sm:mx-4">
                {{-- Override Request Queue --}}
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                    <h2 class="text-base sm:text-lg font-semibold text-gray-700">Override Request Queue</h2>
                    {{-- Search --}}
                    <div class="w-full sm:w-64">
                        <input type="text" wire:model.debounce.300ms="search" placeholder="Search requests..."
                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-gray-400 focus:border-transparent">
                    </div>
                </div>

                @if($this->overrideRequests->count() > 0)
                {{-- Desktop Table View --}}
                <div class="hidden md:block overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="w-1"></th>
                                <th scope="col" class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Room #</th>
                                <th scope="col" class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Guest</th>
                                <th scope="col" class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transaction Type</th>
                                <th scope="col" class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transfer To</th>
                                <th scope="col" class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reason</th>
                                <th scope="col" class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Frontdesk</th>
                                <th scope="col" class="px-4 lg:px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($this->overrideRequests as $request)
                            <tr class="hover:bg-gray-50">
                                {{-- Left green border --}}
                                <td class="w-1 bg-green-500"></td>
                                <td class="px-4 lg:px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm font-medium text-gray-900">{{ $request->fromRoom->number ?? 'N/A' }}</span>
                                </td>
                                <td class="px-4 lg:px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-900">{{ $request->guest->name ?? 'N/A' }}</span>
                                </td>
                                <td class="px-4 lg:px-6 py-4 whitespace-nowrap">
                                    @if($request->transaction_type === 'cancel')
                                        <span class="text-sm font-medium text-red-600">Cancel Transaction</span>
                                    @else
                                        <span class="text-sm text-gray-900">Transfer Room</span>
                                    @endif
                                </td>
                                <td class="px-4 lg:px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-900">{{ $request->toRoom->number ?? 'N/A' }}</span>
                                </td>
                                <td class="px-4 lg:px-6 py-4 whitespace-nowrap">
                                    @if($request->transaction_type === 'cancel')
                                        <span class="text-sm text-gray-600">{{ $request->cancel_reason }}</span>
                                    @else
                                        <span class="text-sm text-gray-600">{{ $request->transferReason->reason ?? 'N/A' }}</span>
                                    @endif
                                </td>
                                <td class="px-4 lg:px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-900">{{ $request->requester->name ?? 'N/A' }}</span>
                                </td>
                                <td class="px-4 lg:px-6 py-4 whitespace-nowrap">
                                    <div class="flex flex-col space-y-1">
                                        <button wire:click="confirmApprove({{ $request->id }})"
                                            class="bg-green-500 hover:bg-green-600 text-white text-xs font-bold px-4 lg:px-6 py-1.5 rounded">
                                            APPROVE
                                        </button>
                                        <button wire:click="openDeclineModal({{ $request->id }})"
                                            class="bg-red-500 hover:bg-red-600 text-white text-xs font-bold px-4 lg:px-6 py-1.5 rounded">
                                            DECLINE
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Mobile Card View --}}
                <div class="md:hidden space-y-3">
                    @foreach($this->overrideRequests as $request)
                    <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
                        {{-- Card Header with Green Left Border --}}
                        <div class="flex">
                            <div class="w-1 bg-green-500 flex-shrink-0"></div>
                            <div class="flex-1 p-4">
                                {{-- Top Row: Room & Type --}}
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center space-x-2">
                                        <span class="text-lg font-bold text-gray-900">Room {{ $request->fromRoom->number ?? 'N/A' }}</span>
                                        @if($request->transaction_type === 'cancel')
                                            <span class="bg-red-100 text-red-600 text-xs font-medium px-2 py-0.5 rounded">Cancel</span>
                                        @else
                                            <span class="bg-blue-100 text-blue-600 text-xs font-medium px-2 py-0.5 rounded">Transfer</span>
                                        @endif
                                    </div>
                                </div>

                                {{-- Details Grid --}}
                                <div class="grid grid-cols-2 gap-2 text-sm mb-3">
                                    <div>
                                        <p class="text-gray-500 text-xs">Guest</p>
                                        <p class="text-gray-900 font-medium truncate">{{ $request->guest->name ?? 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500 text-xs">Transfer To</p>
                                        <p class="text-gray-900 font-medium">{{ $request->toRoom->number ?? 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500 text-xs">Frontdesk</p>
                                        <p class="text-gray-900 font-medium truncate">{{ $request->requester->name ?? 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500 text-xs">Reason</p>
                                        <p class="text-gray-600 truncate">
                                            @if($request->transaction_type === 'cancel')
                                                {{ $request->cancel_reason }}
                                            @else
                                                {{ $request->transferReason->reason ?? 'N/A' }}
                                            @endif
                                        </p>
                                    </div>
                                </div>

                                {{-- Action Buttons --}}
                                <div class="flex space-x-2">
                                    <button wire:click="confirmApprove({{ $request->id }})"
                                        class="flex-1 bg-green-500 hover:bg-green-600 text-white text-sm font-bold py-2 rounded">
                                        APPROVE
                                    </button>
                                    <button wire:click="openDeclineModal({{ $request->id }})"
                                        class="flex-1 bg-red-500 hover:bg-red-600 text-white text-sm font-bold py-2 rounded">
                                        DECLINE
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="px-4 sm:px-6 py-8 sm:py-12 text-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-10 w-10 sm:h-12 sm:w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No pending requests</h3>
                    <p class="mt-1 text-sm text-gray-500">All override requests have been processed.</p>
                </div>
                @endif
            </div>
        </div>
    </main>

    {{-- Decline Modal --}}
    <x-modal wire:model.defer="declineModal" align="center" max-width="md">
        <x-card>
            <div class="flex items-center space-x-2 mb-4">
                <div class="bg-red-100 p-2 rounded-full">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-700">Decline Override Request</h3>
            </div>

            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Reason for declining <span class="text-red-500">*</span></label>
                <textarea wire:model.defer="declineReason"
                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-red-500 focus:border-red-500"
                    rows="4"
                    placeholder="Please provide a reason for declining this override request..."></textarea>
                @error('declineReason')
                    <span class="text-sm text-red-500 mt-1">{{ $message }}</span>
                @enderror
            </div>

            <x-slot name="footer">
                <div class="flex justify-end space-x-2">
                    <x-button flat label="Cancel" x-on:click="close" />
                    <x-button negative label="Decline Request" wire:click="declineRequest" spinner="declineRequest" />
                </div>
            </x-slot>
        </x-card>
    </x-modal>

    {{-- Override Summary Modal --}}
    <x-modal wire:model.defer="summaryModal" align="center" max-width="6xl">
        <x-card>
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg sm:text-xl font-bold text-gray-700">Override Summary</h2>
                <button wire:click="$set('summaryModal', false)" class="text-red-500 hover:text-red-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Filters - Responsive --}}
            <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4 mb-4">
                <div class="flex items-center space-x-2">
                    <span class="text-sm text-gray-600 whitespace-nowrap">DATE FILTER:</span>
                    <input type="date" wire:model="summaryDate" class="border-gray-300 rounded-lg text-sm flex-1 sm:flex-none">
                </div>
                <div class="flex flex-wrap items-center gap-3 sm:gap-4">
                    <label class="flex items-center space-x-2">
                        <input type="checkbox" wire:model="showOverride" class="rounded border-gray-300 text-green-500 focus:ring-green-500">
                        <span class="text-sm text-gray-600">Override</span>
                    </label>
                    <label class="flex items-center space-x-2">
                        <input type="checkbox" wire:model="showAutoApprove" class="rounded border-gray-300 text-yellow-500 focus:ring-yellow-500">
                        <span class="text-sm text-gray-600">Auto-Approve</span>
                    </label>
                    <label class="flex items-center space-x-2">
                        <input type="checkbox" wire:model="showDeclined" class="rounded border-gray-300 text-red-500 focus:ring-red-500">
                        <span class="text-sm text-gray-600">Declined</span>
                    </label>
                </div>
            </div>

            {{-- Summary Table - Desktop --}}
            <div class="hidden sm:block overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-800 text-white">
                        <tr>
                            <th class="px-3 lg:px-4 py-2 text-left text-xs font-medium uppercase">#</th>
                            <th class="px-3 lg:px-4 py-2 text-left text-xs font-medium uppercase">Date/Time</th>
                            <th class="px-3 lg:px-4 py-2 text-left text-xs font-medium uppercase">Room #</th>
                            <th class="px-3 lg:px-4 py-2 text-left text-xs font-medium uppercase">Guest</th>
                            <th class="px-3 lg:px-4 py-2 text-left text-xs font-medium uppercase">Transaction Type</th>
                            <th class="px-3 lg:px-4 py-2 text-left text-xs font-medium uppercase">Reason</th>
                            <th class="px-3 lg:px-4 py-2 text-left text-xs font-medium uppercase">Frontdesk</th>
                            <th class="px-3 lg:px-4 py-2 text-left text-xs font-medium uppercase">Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($this->summaryRequests as $index => $request)
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 lg:px-4 py-3 text-sm text-gray-900">{{ $index + 1 }}.</td>
                            <td class="px-3 lg:px-4 py-3 text-sm text-gray-600">{{ $request->created_at->format('m-d-Y') }}<br>{{ $request->created_at->format('h:i A') }}</td>
                            <td class="px-3 lg:px-4 py-3 text-sm text-gray-900">{{ $request->from_room_number }}</td>
                            <td class="px-3 lg:px-4 py-3 text-sm text-gray-900">{{ $request->guest_name }}</td>
                            <td class="px-3 lg:px-4 py-3 text-sm">
                                @if($request->transaction_type === 'cancel')
                                    <span class="text-red-600 font-medium">Cancel Transaction</span>
                                @else
                                    <span class="text-gray-900">Transfer Room</span>
                                @endif
                            </td>
                            <td class="px-3 lg:px-4 py-3 text-sm text-gray-600">
                                @if($request->transaction_type === 'cancel')
                                    {{ $request->cancel_reason }}
                                @else
                                    {{ $request->transferReason->reason ?? 'N/A' }}
                                @endif
                            </td>
                            <td class="px-3 lg:px-4 py-3 text-sm text-gray-900">{{ $request->requester_name }}</td>
                            <td class="px-3 lg:px-4 py-3 text-sm">
                                @if($request->status === 'declined')
                                    <span class="text-red-600 font-medium">Declined</span>
                                @elseif($request->status === 'auto_approved')
                                    <span class="text-yellow-600 font-medium">Auto-Approve</span>
                                @else
                                    <span class="text-green-600 font-medium">Override</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-gray-500">No records found for this date.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Summary Cards - Mobile --}}
            <div class="sm:hidden space-y-3">
                @forelse($this->summaryRequests as $index => $request)
                <div class="bg-gray-50 rounded-lg p-3 border border-gray-200">
                    <div class="flex items-start justify-between mb-2">
                        <div>
                            <span class="text-sm font-bold text-gray-900">Room {{ $request->from_room_number }}</span>
                            <p class="text-xs text-gray-500">{{ $request->created_at->format('m-d-Y h:i A') }}</p>
                        </div>
                        @if($request->status === 'declined')
                            <span class="bg-red-100 text-red-600 text-xs font-medium px-2 py-0.5 rounded">Declined</span>
                        @elseif($request->status === 'auto_approved')
                            <span class="bg-yellow-100 text-yellow-600 text-xs font-medium px-2 py-0.5 rounded">Auto-Approve</span>
                        @else
                            <span class="bg-green-100 text-green-600 text-xs font-medium px-2 py-0.5 rounded">Override</span>
                        @endif
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div>
                            <p class="text-gray-500 text-xs">Guest</p>
                            <p class="text-gray-900 truncate">{{ $request->guest_name }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500 text-xs">Type</p>
                            <p class="text-gray-900">
                                @if($request->transaction_type === 'cancel')
                                    <span class="text-red-600">Cancel</span>
                                @else
                                    Transfer
                                @endif
                            </p>
                        </div>
                        <div>
                            <p class="text-gray-500 text-xs">Frontdesk</p>
                            <p class="text-gray-900 truncate">{{ $request->requester_name }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500 text-xs">Reason</p>
                            <p class="text-gray-600 truncate">
                                @if($request->transaction_type === 'cancel')
                                    {{ $request->cancel_reason }}
                                @else
                                    {{ $request->transferReason->reason ?? 'N/A' }}
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
                @empty
                <div class="py-8 text-center text-gray-500">No records found for this date.</div>
                @endforelse
            </div>
        </x-card>
    </x-modal>
</div>
