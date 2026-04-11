<div>
    {{-- Branch selector (superadmin) --}}
    @if($branches->isNotEmpty())
        <div class="mb-4 flex justify-end">
            <select wire:model="branch_id"
                class="rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500">
                <option selected hidden>Select Branch</option>
                @foreach($branches as $branch)
                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
    @endif

    {{-- Global settings card --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 mb-5">
        <h3 class="text-sm font-bold text-gray-800 mb-3">Branch Discount Settings</h3>
        <div class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1">Discount Amount</label>
                <div class="flex items-center">
                    <span class="inline-flex items-center rounded-l-lg border border-r-0 border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-500">&#8369;</span>
                    <input type="number" wire:model.defer="discountAmount" min="0" step="1"
                        class="w-32 rounded-r-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 focus:outline-none">
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1">Global Switch</label>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" wire:model="discountEnabled" class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:ring-2 peer-focus:ring-blue-500/20 rounded-full peer peer-checked:bg-blue-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                    <span class="ml-2 text-sm font-medium {{ $discountEnabled ? 'text-blue-600' : 'text-gray-400' }}">
                        {{ $discountEnabled ? 'Enabled' : 'Disabled' }}
                    </span>
                </label>
            </div>
            <x-button wire:click="saveGlobalSettings" spinner="saveGlobalSettings" positive label="Save Settings" />
        </div>
    </div>

    {{-- Filter --}}
    <div class="mb-4">
        <label class="block text-xs font-medium text-gray-500 mb-1">Filter by Room Type</label>
        <select wire:model="filter_type_id"
            class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm w-48 focus:ring-blue-500 focus:border-blue-500">
            <option value="">All Types</option>
            @foreach($types as $type)
                <option value="{{ $type->id }}">{{ $type->name }}</option>
            @endforeach
        </select>
    </div>

    {{-- Discount configurations table --}}
    <div class="overflow-x-auto border border-gray-200 rounded-lg">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-blue-500">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-white uppercase">Room Type</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-white uppercase">Hours</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-white uppercase">Discount Enabled</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($configurations as $config)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $config->type->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $config->stayingHour->number ?? '—' }} Hours</td>
                    <td class="px-4 py-3 text-center">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox"
                                wire:click="toggleConfiguration({{ $config->id }})"
                                {{ $config->is_enabled ? 'checked' : '' }}
                                class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:ring-2 peer-focus:ring-blue-500/20 rounded-full peer peer-checked:bg-green-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                        </label>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="3" class="px-4 py-8 text-center text-sm text-gray-400 italic">
                        @if(!$branch_id)
                            Select a branch to view configurations.
                        @else
                            No configurations found. Add room types and staying hours first.
                        @endif
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
