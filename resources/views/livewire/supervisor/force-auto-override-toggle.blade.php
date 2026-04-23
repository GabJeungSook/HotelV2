<div>
    <button wire:click="toggleAutoOverride"
        class="px-3 py-1 text-xs font-bold rounded {{ $enabled ? 'bg-green-500 hover:bg-green-600 text-white' : 'bg-gray-500 hover:bg-gray-600 text-white' }}">
        {{ $enabled ? 'ON' : 'OFF' }}
    </button>
</div>
