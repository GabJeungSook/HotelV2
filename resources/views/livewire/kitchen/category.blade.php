<div>
  <div class="mt-5">
    <div class="flex items-end">
      <div class="sm:flex-auto">
      </div>
      <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
        <x-button wire:click="$set('add_modal', true)" icon="plus" slate label="Add New" />
      </div>
    </div>

    <div class="mt-3 flex flex-col">
      {{ $this->table }}
    </div>
  </div>
  <x-modal wire:model.defer="add_modal" max-width="lg">
    <x-card title="Add New Category">
      <div class="space-y-4">
        <x-native-select label="Parent Category" wire:model="parent_category_id">
            <option selected hidden>Select Parent Category</option>
            @foreach ($parentCategories as $item)
                <option value="{{ $item->id }}">{{ $item->name }}</option>
            @endforeach
        </x-native-select>
        <x-input label="Name" wire:model="name" />
      </div>
      <x-slot name="footer">
        <div class="flex justify-end gap-x-4">
          <x-button flat label="Cancel" x-on:click="close" />
          <x-button positive label="Save" wire:click="saveCategory" spinner="saveCategory"/>
        </div>
      </x-slot>
    </x-card>
  </x-modal>

  <x-modal wire:model.defer="edit_modal" max-width="lg">
    <x-card title="Update Category">
      <div class="space-y-4">
        <x-native-select label="Parent Category" wire:model="parent_category_id">
            <option selected hidden>Select Parent Category</option>
            @foreach ($parentCategories as $item)
                <option value="{{ $item->id }}">{{ $item->name }}</option>
            @endforeach
        </x-native-select>
        <x-input label="Name" wire:model="name" />
      </div>
      <x-slot name="footer">
        <div class="flex justify-end gap-x-4">
          <x-button flat label="Cancel" x-on:click="close" />
          <x-button positive label="Update" wire:click="updateCategory" spinner="updateCategory"
            right-icon="arrow-narrow-right" />
        </div>
      </x-slot>
    </x-card>
  </x-modal>
</div>
