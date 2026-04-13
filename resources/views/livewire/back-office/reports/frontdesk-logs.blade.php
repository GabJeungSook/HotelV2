<div class="max-w-full mx-auto py-8 px-4 sm:px-6 lg:px-8">

    {{-- Filters --}}
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

            {{-- Date --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                <input type="date"
                       wire:model.defer="date"
                       class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            {{-- Buttons --}}
            <div class="flex items-end gap-2">
                <button wire:click="$refresh"
                        class="w-full md:w-auto inline-flex justify-center rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">
                    Apply
                </button>

                <button wire:click="resetFilters"
                        class="w-full md:w-auto inline-flex justify-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Reset
                </button>
            </div>

        </div>
    </div>

    {{-- Report --}}
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">

        <div class="px-4 py-3 border-b border-gray-200 flex justify-between items-center">
            <div class="text-sm font-semibold text-gray-900">
                FRONTDESK LOGS
            </div>
            <div class="text-sm font-semibold text-gray-700">
                Total Entries: {{ $logs->count() }}
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse">
                <thead>
                    <tr>
                        <th class="border border-gray-300 px-3 py-2 text-left text-sm font-semibold text-gray-800">SHIFT</th>
                        <th class="border border-gray-300 px-3 py-2 text-left text-sm font-semibold text-gray-800">FRONTDESK</th>
                        <th class="border border-gray-300 px-3 py-2 text-left text-sm font-semibold text-gray-800">TIME-IN</th>
                        <th class="border border-gray-300 px-3 py-2 text-left text-sm font-semibold text-gray-800">TIME-OUT</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="border border-gray-300 px-3 py-3 text-sm text-gray-900">
                                {{ $log['shift'] }}
                            </td>
                            <td class="border border-gray-300 px-3 py-3 text-sm text-gray-900">
                                {{ $log['frontdesk'] }}
                            </td>
                            <td class="border border-gray-300 px-3 py-3 text-sm text-gray-900">
                                {{ $log['time_in'] }}
                            </td>
                            <td class="border border-gray-300 px-3 py-3 text-sm text-gray-900">
                                {{ $log['time_out'] }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="border border-gray-300 px-3 py-6 text-sm text-center text-gray-500">
                                No frontdesk logs found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>
</div>
