<div class="p-1 sm:p-1 space-y-6">

    {{-- TOP: Header + Dropdowns --}}
    <div class="rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="p-4 sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end">

                <div class="w-full sm:w-[380px]">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Week
                    </label>
                    <select
                        wire:model.defer="selectedWeek"
                        class="w-full rounded-xl border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        @foreach($availableWeeks as $week)
                            <option value="{{ $week['value'] }}">{{ $week['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="w-full sm:w-[380px]">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Report Type
                    </label>
                    <select
                        wire:model.defer="report"
                        class="w-full rounded-xl border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        @foreach($this->reports as $key => $r)
                            <option value="{{ $key }}">{{ $r['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-end">
                    <button
                        wire:click="loadReport"
                        wire:loading.attr="disabled"
                        class="inline-flex justify-center rounded-lg bg-gray-900 px-6 py-2 text-sm font-medium text-white hover:bg-gray-800 disabled:bg-gray-400"
                    >
                        <span wire:loading.remove wire:target="loadReport">Load Report</span>
                        <span wire:loading wire:target="loadReport">Loading...</span>
                    </button>
                </div>

            </div>
        </div>
    </div>

    {{-- BOTTOM: Selected report --}}
    @if($loaded && $selectedWeek)
    <div class="rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="p-4 border-b border-gray-100">
            <div class="text-sm font-semibold text-gray-900">
                {{ $this->reports[$report]['label'] ?? 'Report' }}
            </div>
            <div class="text-xs text-gray-500">
                Archived data for the selected week.
            </div>
        </div>

        <div class="p-4 sm:p-6">
            @if($this->activeComponent)
                @livewire($this->activeComponent, ['weekStart' => $selectedWeek], key($report . '_' . $selectedWeek))
            @else
                <div class="text-sm text-gray-500">No report selected.</div>
            @endif
        </div>
    </div>
    @else
    <div class="rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="p-8 text-center text-sm text-gray-500">
            Select a week and report type, then click "Load Report" to view archived data.
        </div>
    </div>
    @endif

</div>
