<div>
    <div class="mt-5">
      <div class="flex items-end">
        <div class="sm:flex-auto">
          {{-- <div class="search flex items-center rounded-lg  px-3 py-1 w-72 border border-gray-200 shadow-sm">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="fill-gray-500" width="24" height="24">
              <path fill="none" d="M0 0h24v24H0z" />
              <path
                d="M11 2c4.968 0 9 4.032 9 9s-4.032 9-9 9-9-4.032-9-9 4.032-9 9-9zm0 16c3.867 0 7-3.133 7-7 0-3.868-3.133-7-7-7-3.868 0-7 3.132-7 7 0 3.867 3.132 7 7 7zm8.485.071l2.829 2.828-1.415 1.415-2.828-2.829 1.414-1.414z" />
            </svg>
            <input type="text" wire:model="search"
              class="outline:none  h-8 focus:ring-0 flex-1 border-0 focus:border-0" placeholder="Search">
          </div> --}}
        </div>
        <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
          <x-button wire:click="$set('add_modal', true)" icon="plus" slate label="Add New" />
        </div>
      </div>

      <div class="mt-3 flex flex-col">
        {{ $this->table }}

      </div>
    </div>

    <x-modal wire:model.defer="add_modal" max-width="xl">
      <x-card title="Add New Menu">
        <div class="grid grid-cols-2 gap-4">
          <x-input label="Name" wire:model="name" />
          <x-input label="Price" wire:model="price" />
          <div class="col-span-2">
            <x-native-select label="Category" wire:model="category_id">
              <option>Select Category</option>
              @foreach ($categories as $item)
                <option value="{{ $item->id }}" class="uppercase">{{ $item->name }}</option>
              @endforeach

            </x-native-select>
          </div>
        </div>
        @include('livewire.partials.ingredient-manager')
        <x-slot name="footer">
          <div class="flex justify-end gap-x-4">
            <x-button flat label="Cancel" x-on:click="close" />
            <x-button positive label="Save" wire:click="saveMenu" spinner="saveMenu" />
          </div>
        </x-slot>
      </x-card>
    </x-modal>

    <x-modal wire:model.defer="edit_modal" max-width="xl">
      <x-card title="Edit Menu">
        <div class="grid grid-cols-2 gap-4">
          <x-input label="Name" wire:model="name" />
          <x-input label="Price" wire:model="price" />
          <div class="col-span-2">
            <x-native-select label="Category" wire:model="category_id">
              <option>Select Category</option>
              @foreach ($categories as $item)
                <option value="{{ $item->id }}" class="uppercase">{{ $item->name }}</option>
              @endforeach

            </x-native-select>
          </div>
        </div>
        @include('livewire.partials.ingredient-manager')
        <x-slot name="footer">
          <div class="flex justify-end gap-x-4">
            <x-button flat label="Cancel" x-on:click="close" />
            <x-button positive label="Update" wire:click="updateMenu" spinner="updateMenu" />
          </div>
        </x-slot>
      </x-card>
    </x-modal>
  </div>
