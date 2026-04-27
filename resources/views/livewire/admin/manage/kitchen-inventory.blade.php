<div>
    <div class="flex justify-end mb-4">
        @if(auth()->user()->hasRole('superadmin'))
            <x-native-select label="Branch" wire:model="branch_id">
                <option selected hidden>Select Branch</option>
                    @foreach ($branches as $item)
                        <option value="{{ $item->id }}">{{ $item->name }}</option>
                    @endforeach
            </x-native-select>
        @endif
    </div>

    <div class="flex justify-between items-center mb-4">
        <div class="flex items-center space-x-10">
             @forelse ($categories as $category)
                <button wire:click="selectCategory({{ $category->id }})"
                    class="{{ $selectedCategoryId === $category->id ? 'tracking-wider text-[#009ff4] font-semibold border-b-2 border-[#009ff4]' : 'tracking-wider text-black' }}">
                    {{ $category->name }}
                </button>
            @empty
                <span class="text-sm font-medium text-gray-500 uppercase"></span>
            @endforelse
        </div>
         <div>
            <a href="{{ route('frontdesk.food-category') }}" class="inline-flex items-center px-3 py-2 rounded-md" style="background-color: #009ff4; color: #fff;">
                <svg class="mr-1 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                <span class="text-sm  font-medium">
                    Manage Categories
                </span>
            </a>
        </div>
    </div>
    {{-- content --}}
    <div class="grid grid-cols-5 gap-6 mt-6">
    {{-- @forelse ($menu as $item)
        <!-- Menu Card -->
        <div class="relative rounded-xl overflow-hidden shadow-xl group">
            <img src="{{ asset('images/category.jpg') }}" alt="Category" class="w-full h-48 object-cover group-hover:scale-105 transition-transform duration-300">
            <!-- Discount badge -->
            <div class="absolute top-0 right-2 bg-[#009ff4] text-white text-xs font-normal px-2 pb-1 pt-3 rounded-b tracking-wider">
                ₱ {{ number_format($item->price, 2) }}
            </div>

            <!-- Bottom text overlay -->
            <div class="absolute bottom-0 left-0 p-4 bg-gradient-to-t from-black/100 via-black/50 to-transparent w-full">
                <h3 class="text-white font-bold text-base">{{$item->name}}</h3>
                <p class="text-sm text-[#009ff4] mb-1 font-semibold shadow-xl">Restaurant</p>
            </div>
        </div>
    @empty
        <div class="col-span-5 flex flex-col items-center justify-center">
            <div class="flex flex-col items-center justify-center border-2 border-dashed border-[#009ff4] rounded-xl cursor-pointer hover:bg-[#f0f8ff] transition-colors duration-200"
                wire:click="openAddMenuModal" style="width: 200px; height: 200px;">
                <svg class="h-12 w-12 text-[#009ff4] mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                   <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                <span class="text-[#009ff4] font-semibold">Add Menu</span>
            </div>
        </div>
    @endforelse --}}
</div>
<div>
    <div class="mt-10">
        @if($selectedCategory)
        <div class="flex flex-col items-center justify-center space-y-3 mb-4">
            <h2 class="text-2xl font-semibold text-[#009ff4] uppercase">{{$selectedCategory->name}}</h2>
            <a href="{{ route('frontdesk.food-inventories', $selectedCategory->id) }}" class="inline-flex items-center px-2 py-1 rounded-md" style="background-color: #009ff4; color: #fff;">
                <svg class="mr-1 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                <span class="text-sm font-medium">
                    Manage Inventory
                </span>
            </a>
        </div>
        @endif


    <div class="mt-12 flex space-x-4 overflow-x-auto pb-2">
        <!-- Salads -->
        @foreach ($menus as $item)
            <button wire:click="editMenu({{ $item->id }})" class="group min-w-[180px] bg-white rounded-lg shadow-lg relative overflow-hidden">
                <div class="relative w-full h-36 bg-gray-100 rounded-t-md mb-2 ">
                    @if ($item->image)
                        <img src="{{ asset('storage/' . $item->image) }}" alt="Menu Image" class="w-full h-full object-cover rounded-t-md group-hover:scale-[101%] transition-transform duration-300">
                    @else
                        <img src="{{ asset('images/default.jpeg') }}" alt="Default Image" class="w-full h-full object-cover rounded-t-md group-hover:scale-[101%] transition-transform duration-300">
                    @endif
                    <!-- Discount badge -->
                    <div class="absolute top-0 right-2 bg-[#009ff4] text-white text-xs font-normal px-2 pb-1 pt-3 rounded-b tracking-wider">
                        ₱ {{ number_format($item->price, 2) }}
                    </div>
                    <!-- Hover overlay -->
                    <div class="absolute inset-0 flex items-center justify-center bg-black bg-opacity-75 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                        <span class="text-white text-base font-semibold">Update Menu</span>
                    </div>
                </div>
                <div class="px-4 py-2">
                    <h3 class="text-sm font-semibold text-gray-800 text-left leading-tight truncate" title="{{ $item->name }}">{{ $item->name }}</h3>
                    @php $stock = (float)($item->frontdeskInventory?->number_of_serving ?? 0); @endphp
                    <p class="text-xs {{ $stock <= 0 ? 'text-red-500' : ($stock < 5 ? 'text-amber-600' : 'text-[#009ff4]') }} mt-1 text-left">
                        @if($stock <= 0)
                            out of stock
                        @else
                            {{ rtrim(rtrim(number_format($stock, 2), '0'), '.') }} left
                        @endif
                    </p>
                </div>
            </button>
        @endforeach
        <!-- Add Menu Button -->
        @if($selectedCategory)
        <button wire:click="addMenu" class="min-w-[140px] bg-gray-100 rounded-lg flex flex-col justify-center items-center h-[140px]">
            <div class="text-3xl text-gray-500 mb-1">+</div>
            <span class="text-sm text-gray-500 font-medium">Add Menu</span>
        </button>
        @elseif(auth()->user()->hasRole('superadmin') && $branch_id == null)
            <div class="flex mt-10 justify-center w-full">
                <span class="text-center text-gray-500 text-xl italic">Select a branch</span>
            </div>
        @else
            <div class="flex mt-10 justify-center w-full">
                <span class="text-center text-gray-500 text-xl italic">Add a category first</span>
            </div>
        @endif
    </div>
</div>

</div>

    <x-modal wire:model.defer="add_modal" max-width="xl">
        <x-card title="Add New Menu ({{$selectedCategory?->name}})">
          <div class="grid grid-cols-1">
            <x-input label="Item Code" wire:model="item_code" />
          </div>
          <div class="grid grid-cols-2 gap-4 mt-5">
            <x-input label="Name" wire:model="name" />
            @error('name')@enderror
            <x-input label="Price" wire:model="price" />
            @error('price')@enderror
          </div>
          <div class="grid grid-cols-1 mt-5">
             @if ($image)
                    <div class="mt-3 mb-5">
                        <img src="{{ $image->temporaryUrl() }}" alt="Preview" class="h-32 rounded-md object-cover">
                    </div>
                @endif
            <x-input label="Image" type="file" wire:model="image" />
            @error('image') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror

            {{-- @if ($image)
                <div class="mt-2">
                    <img src="{{ $image->temporaryUrl() }}" alt="Preview" class="h-32 rounded-md object-cover">
                </div>
            @endif --}}
          </div>
          <x-slot name="footer">
            <div class="flex justify-end gap-x-4">
              <x-button flat label="Cancel" x-on:click="close" />
              <x-button positive label="Save" wire:click="saveMenu" spinner="saveMenu"/>
            </div>
          </x-slot>
        </x-card>
      </x-modal>

      <x-modal wire:model.defer="edit_modal" max-width="xl">
        <x-card title="Update Menu">
          <div class="grid grid-cols-1">
            <x-input label="Item Code" wire:model="item_code" />
          </div>
          <div class="grid grid-cols-2 gap-4">
            <x-input label="Name" wire:model="name" />
            @error('name')@enderror
            <x-input label="Price" wire:model="price" />
            @error('price')@enderror
          </div>
            <div class="grid grid-cols-1 mt-5">
                {{-- Current image preview --}}
                @if ($selectedMenu && $selectedMenu->image && !$image)
                    <div class="mb-3">
                        <p class="text-sm text-gray-600 mb-2">Current Image:</p>
                        <div class="flex items-center gap-3">
                            <img src="{{ asset('storage/' . $selectedMenu->image) }}" alt="Current" class="h-24 rounded-md object-cover">
                            <button type="button" wire:click="removeImage" class="text-red-500 hover:text-red-700 text-sm underline">
                                Remove Image
                            </button>
                        </div>
                    </div>
                @endif
                {{-- New image preview --}}
                @if ($image)
                    <div class="mb-3">
                        <p class="text-sm text-gray-600 mb-2">New Image:</p>
                        <img src="{{ $image->temporaryUrl() }}" alt="Preview" class="h-24 rounded-md object-cover">
                    </div>
                @endif
                <x-input label="{{ $selectedMenu && $selectedMenu->image ? 'Change Image' : 'Add Image' }}" type="file" wire:model="image" />
                @error('image') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
          <x-slot name="footer">
            <div class="flex justify-end gap-x-4">
              <x-button flat label="Cancel" x-on:click="close" />
              <x-button positive label="Update" wire:click="updateMenu" spinner="updateMenu" />
            </div>
          </x-slot>
        </x-card>
      </x-modal>

</div>
