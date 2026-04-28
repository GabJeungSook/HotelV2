<div class="max-w-full mx-auto py-8 px-4 sm:px-6 lg:px-8">

    {{-- Filters --}}
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">

            {{-- Frontdesk --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Frontdesk</label>
                <select wire:model.defer="frontdesk_id"
                        class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All</option>
                    @foreach ($frontdesks as $fd)
                        <option value="{{ $fd->id }}">{{ $fd->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Shift --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Shift</label>
                <select wire:model.defer="shift"
                        class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All</option>
                    <option value="AM">AM</option>
                    <option value="PM">PM</option>
                </select>
            </div>

            {{-- Date --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                <input type="date"
                       wire:model.defer="date"
                       class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            {{-- Time --}}
            {{-- <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Time (Optional)</label>
                <input type="time"
                       wire:model.defer="time"
                       class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
            </div> --}}

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
                CHECK-OUT GUEST REPORT
            </div>
            <div class="text-sm font-semibold text-gray-700">
                No. of Check-outs: {{ $total_guest }}
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse">
                <thead>
                    <tr>
                        <th class="border border-gray-300 px-3 py-2 text-left text-sm font-semibold text-gray-800">ROOM NUMBER</th>
                        <th class="border border-gray-300 px-3 py-2 text-left text-sm font-semibold text-gray-800">GUEST NAME</th>
                        <th class="border border-gray-300 px-3 py-2 text-left text-sm font-semibold text-gray-800">CHECK-IN DATE/TIME</th>
                        <th class="border border-gray-300 px-3 py-2 text-left text-sm font-semibold text-gray-800">CHECK-OUT DATE/TIME</th>
                        <th class="border border-gray-300 px-3 py-2 text-left text-sm font-semibold text-gray-800">INITIAL HRS</th>
                        <th class="border border-gray-300 px-3 py-2 text-left text-sm font-semibold text-gray-800">TOTAL EXTENSION HRS</th>
                        <th class="border border-gray-300 px-3 py-2 text-left text-sm font-semibold text-gray-800">TOTAL HRS</th>
                        <th class="border border-gray-300 px-3 py-2 text-left text-sm font-semibold text-gray-800">SHIFT</th>
                        <th class="border border-gray-300 px-3 py-2 text-left text-sm font-semibold text-gray-800">FRONTDESK</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($reports as $item)
                        <tr>
                            <td class="border border-gray-300 px-3 py-3 text-sm text-gray-900">
                                {{ $item->room?->number ?? '—' }}
                            </td>

                            <td class="border border-gray-300 px-3 py-3 text-sm text-gray-900">
                                {{ $item->checkinDetail?->guest?->name ?? '—' }}
                            </td>

                            <td class="border border-gray-300 px-3 py-3 text-sm text-gray-900">
                                {{ $item->checkinDetail?->check_in_at
                                    ? \Carbon\Carbon::parse($item->checkinDetail->check_in_at)->format('F d, Y h:i A')
                                    : '—' }}
                            </td>

                            <td class="border border-gray-300 px-3 py-3 text-sm text-gray-900">
                                {{ $item->checkinDetail?->check_out_at
                                    ? \Carbon\Carbon::parse($item->checkinDetail->check_out_at)->format('F d, Y h:i A')
                                    : '—' }}
                            </td>

                            <td class="border border-gray-300 px-3 py-3 text-sm text-gray-900">
                                {{ $item->checkinDetail ? $item->checkinDetail->hours_stayed . ' hours' : '—' }}
                            </td>

                            <td class="border border-gray-300 px-3 py-3 text-sm text-gray-900">
                                {{ $item->checkinDetail ? ($item->checkinDetail->extendedGuestReports->sum('total_hours') > 0 ? $item->checkinDetail->extendedGuestReports->sum('total_hours') . ' hours' : '-') : '—' }}
                            </td>

                            <td class="border border-gray-300 px-3 py-3 text-sm text-gray-900">
                                {{ $item->checkinDetail ? ($item->checkinDetail->hours_stayed + $item->checkinDetail->extendedGuestReports->sum('total_hours')) . ' hours' : '—' }}
                            </td>

                            <td class="border border-gray-300 px-3 py-3 text-sm text-gray-900">
                                {{ $item->shift ?? '—' }}
                            </td>

                            <td class="border border-gray-300 px-3 py-3 text-sm text-gray-900">
                                {{ $item->frontdesk?->name ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="border border-gray-300 px-3 py-6 text-sm text-center text-gray-500">
                                No check-out guest records found for the selected filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($reports->hasPages())
            <div class="px-4 py-3 border-t border-gray-200">
                {{ $reports->links() }}
            </div>
        @endif

        {{-- Footer --}}
        <div class="p-6 border-t border-gray-200">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-10">
                <div class="text-gray-700">
                    <div class="text-sm font-semibold">Prepared By:</div>
                    <div class="mt-10 w-64 border-b border-gray-400"></div>
                </div>

                <div class="text-gray-700">
                    <div class="text-sm font-semibold">Verified By:</div>
                    <div class="mt-10 w-64 border-b border-gray-400"></div>
                </div>
            </div>
        </div>

    </div>
</div>
