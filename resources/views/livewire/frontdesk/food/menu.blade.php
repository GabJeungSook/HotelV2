<div>
    <div class="flex justify-between text-3xl text-gray-700 font-semibold">
        <div>
            Manage Menu
        </div>
        <div>
            <a href="{{route('admin.food-inventory')}}">
            <x-button icon="arrow-left" slate label="Return" />
            </a>
        </div>
    </div>
    <div class="mt-5">
        <div class="mt-4">
            <x-button wire:click="$set('add_modal', true)" icon="plus" emerald label="Add New" />
          </div>
          <div class="mt-5">
              {{$this->table}}
          </div>
    </div>
    <x-modal wire:model.defer="add_modal" max-width="xl">
        <x-card title="Add New Menu">
          <div class="grid grid-cols-2 gap-4">
            <div class="col-span-2">
              <x-native-select label="Category" wire:model="category_id">
                <option>Select Category</option>
                @foreach ($categories as $item)
                  <option value="{{ $item->id }}" class="uppercase">{{ $item->name }}</option>
                @endforeach

              </x-native-select>
              @error('category_id')@enderror
            </div>
            <x-input label="Name" wire:model="name" />
            @error('name')@enderror
            <x-input label="Price" wire:model="price" />
            @error('price')@enderror

          </div>
          @include('livewire.partials.ingredient-manager')
          <x-slot name="footer">
            <div class="flex justify-end gap-x-4">
              <x-button flat label="Cancel" x-on:click="close" />
              <x-button positive label="Save" wire:click="saveMenu" spinner="saveMenu"/>
            </div>
          </x-slot>
        </x-card>
      </x-modal>

      <x-modal wire:model.defer="edit_modal" max-width="xl">
        <x-card title="Edit Menu">
          <div class="grid grid-cols-2 gap-4">
            <x-input label="Name" wire:model="name" />
            @error('name')@enderror
            <x-input label="Price" wire:model="price" />
            @error('price')@enderror
            <div class="col-span-2">
              <x-native-select label="Category" wire:model="category_id">
                <option>Select Category</option>
                @foreach ($categories as $item)
                  <option value="{{ $item->id }}" class="uppercase">{{ $item->name }}</option>
                @endforeach

              </x-native-select>
              @error('category_id')@enderror
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
