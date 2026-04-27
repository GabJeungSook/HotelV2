<div class="p-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">Ghost Rooms</h2>
            <p class="text-sm text-gray-500 mt-1">Rooms showing as "Occupied" but have no active guest</p>
        </div>
        @if($this->ghostRooms->count() > 0)
        <button wire:click="fixAllRooms"
            class="bg-purple-600 hover:bg-purple-700 text-white font-semibold px-4 py-2 rounded-lg shadow-sm flex items-center space-x-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <span>Fix All ({{ $this->ghostRooms->count() }})</span>
        </button>
        @endif
    </div>

    @if($this->ghostRooms->count() > 0)
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-purple-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Room</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Floor</th>
                    @if(auth()->user()->hasRole('superadmin'))
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Branch</th>
                    @endif
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Issue</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @foreach($this->ghostRooms as $room)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="text-sm font-semibold text-gray-900">Room #{{ $room->number }}</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                        {{ $room->type->name ?? 'N/A' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                        {{ $room->floor->number ?? 'N/A' }}{{ $room->floor ? ($room->floor->number == 1 ? 'st' : ($room->floor->number == 2 ? 'nd' : ($room->floor->number == 3 ? 'rd' : 'th'))) : '' }} Floor
                    </td>
                    @if(auth()->user()->hasRole('superadmin'))
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                        {{ $room->branch->name ?? 'N/A' }}
                    </td>
                    @endif
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                            {{ $room->status }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="text-sm text-red-600 font-medium">No active guest</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <button wire:click="fixRoom({{ $room->id }})"
                            class="bg-purple-500 hover:bg-purple-600 text-white text-xs font-bold px-4 py-2 rounded">
                            Fix Room
                        </button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div class="bg-white rounded-lg shadow-sm p-12 text-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-16 w-16 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <h3 class="mt-4 text-xl font-semibold text-gray-900">No Ghost Rooms</h3>
        <p class="mt-2 text-gray-500">All occupied rooms have active guests. Everything is in order!</p>
        <a href="{{ route('admin.room') }}" class="mt-6 inline-block text-purple-600 hover:text-purple-800 font-medium">
            &larr; Back to Room Management
        </a>
    </div>
    @endif
</div>
