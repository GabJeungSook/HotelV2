<div class="max-w-full mx-auto py-8 px-4 sm:px-6 lg:px-8">

    {{-- Filters --}}
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">

            @if (!$weekStart)
                {{-- Date From --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                    <input type="date"
                           wire:model.defer="date_from"
                           class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                {{-- Date To --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                    <input type="date"
                           wire:model.defer="date_to"
                           class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            @endif

            {{-- Supervisor --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Supervisor</label>
                <select wire:model.defer="supervisor_id"
                        class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All Supervisors</option>
                    @foreach ($supervisors as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
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
                SUPERVISOR APPROVALS REPORT
            </div>
            <div class="text-sm font-semibold text-gray-700">
                Total Handled: {{ $totals['total'] }}
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse">
                <thead>
                    <tr>
                        <th class="border border-gray-300 px-3 py-2 text-left text-sm font-semibold text-gray-800">SUPERVISOR</th>
                        <th class="border border-gray-300 px-3 py-2 text-center text-sm font-semibold text-gray-800">TOTAL HANDLED</th>
                        <th class="border border-gray-300 px-3 py-2 text-center text-sm font-semibold text-gray-800">APPROVED</th>
                        <th class="border border-gray-300 px-3 py-2 text-center text-sm font-semibold text-gray-800">DECLINED</th>
                        <th class="border border-gray-300 px-3 py-2 text-center text-sm font-semibold text-gray-800">APPROVAL RATE</th>
                        <th class="border border-gray-300 px-3 py-2 text-center text-sm font-semibold text-gray-800">AVG RESPONSE TIME</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($supervisorStats as $stat)
                        <tr>
                            <td class="border border-gray-300 px-3 py-3 text-sm text-gray-900 font-medium">
                                {{ $stat['supervisor']?->name ?? 'Unknown' }}
                            </td>
                            <td class="border border-gray-300 px-3 py-3 text-sm text-gray-900 text-center">
                                {{ $stat['total'] }}
                            </td>
                            <td class="border border-gray-300 px-3 py-3 text-sm text-center">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    {{ $stat['approved'] }}
                                </span>
                            </td>
                            <td class="border border-gray-300 px-3 py-3 text-sm text-center">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                    {{ $stat['declined'] }}
                                </span>
                            </td>
                            <td class="border border-gray-300 px-3 py-3 text-sm text-center">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                    {{ $stat['approval_rate'] >= 80 ? 'bg-green-100 text-green-800' : ($stat['approval_rate'] >= 50 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                    {{ $stat['approval_rate'] }}%
                                </span>
                            </td>
                            <td class="border border-gray-300 px-3 py-3 text-sm text-gray-900 text-center">
                                {{ $this->formatResponseTime($stat['avg_response_time']) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="border border-gray-300 px-3 py-6 text-sm text-center text-gray-500">
                                No supervisor approval data found for the selected filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                @if ($supervisorStats->count() > 0)
                    <tfoot>
                        <tr class="bg-gray-50 font-semibold">
                            <td class="border border-gray-300 px-3 py-3 text-sm text-gray-900">
                                TOTALS
                            </td>
                            <td class="border border-gray-300 px-3 py-3 text-sm text-gray-900 text-center">
                                {{ $totals['total'] }}
                            </td>
                            <td class="border border-gray-300 px-3 py-3 text-sm text-center">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    {{ $totals['approved'] }}
                                </span>
                            </td>
                            <td class="border border-gray-300 px-3 py-3 text-sm text-center">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                    {{ $totals['declined'] }}
                                </span>
                            </td>
                            <td class="border border-gray-300 px-3 py-3 text-sm text-center">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                    {{ $totals['approval_rate'] >= 80 ? 'bg-green-100 text-green-800' : ($totals['approval_rate'] >= 50 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                    {{ $totals['approval_rate'] }}%
                                </span>
                            </td>
                            <td class="border border-gray-300 px-3 py-3 text-sm text-gray-900 text-center">
                                —
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

    </div>
</div>
