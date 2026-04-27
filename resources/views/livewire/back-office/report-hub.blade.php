<div class="p-1 sm:p-1 space-y-6">

    {{-- TOP: Header + Dropdown --}}
    <div class="rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="p-4 sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                {{-- <div>
                    <h1 class="text-2xl font-semibold text-gray-900">Reports</h1>
                    <p class="text-sm text-gray-500">
                        Select a report to display it below.
                    </p>
                </div> --}}

                <div class="w-full sm:w-[380px]">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Report Type
                    </label>

                    <select
                        wire:model="report"
                        class="w-full rounded-xl border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        @foreach($this->reports as $key => $r)
                            <option value="{{ $key }}">{{ $r['label'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    {{-- BOTTOM: Selected report --}}
    <div class="rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="p-4 border-b border-gray-100">
            <div class="text-sm font-semibold text-gray-900">
                {{ $this->reports[$report]['label'] ?? 'Report' }}
            </div>
            <div class="text-xs text-gray-500">
                Use filters inside the report.
            </div>
        </div>

        <div class="p-4 sm:p-6">
            @if($this->activeComponent)
                {{-- resets state per report switch --}}
                @livewire($this->activeComponent, [], key($report))
            @else
                <div class="text-sm text-gray-500">No report selected.</div>
            @endif
        </div>
    </div>

</div>
