<div>
  {{-- <div class="mt-5">
    <div class="flex items-end">
      <div class="sm:flex-auto">
        <div class="search flex items-center rounded-lg  px-3 py-1 w-72 border border-gray-200 shadow-sm">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="fill-gray-500" width="24" height="24">
            <path fill="none" d="M0 0h24v24H0z" />
            <path
              d="M11 2c4.968 0 9 4.032 9 9s-4.032 9-9 9-9-4.032-9-9 4.032-9 9-9zm0 16c3.867 0 7-3.133 7-7 0-3.868-3.133-7-7-7-3.868 0-7 3.132-7 7 0 3.867 3.132 7 7 7zm8.485.071l2.829 2.828-1.415 1.415-2.828-2.829 1.414-1.414z" />
          </svg>
          <input type="text" wire:model="search"
            class="outline:none  h-8 focus:ring-0 flex-1 border-0 focus:border-0" placeholder="Search">
        </div>
      </div>
      <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
        <x-button wire:click="$set('add_modal', true)" icon="plus" slate label="Add New" />
      </div>
    </div>

    <div class="mt-3 flex flex-col">
      <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-6 lg:-mx-8">
        <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
          <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
            <table class="min-w-full divide-y divide-gray-300">
              <thead class="bg-gray-500">
                <tr>
                  <th scope="col"
                    class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold uppercase text-white sm:pl-6">
                    Name</th>
                  <th scope="col"
                    class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold uppercase text-white sm:pl-6">
                    EMAIL</th>
                  <th scope="col"
                    class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold uppercase text-white sm:pl-6">
                    ROLE</th>
                  <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                    <span class="sr-only">Edit</span>
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 bg-white">
                @foreach ($users as $user)
                  <tr>
                    <td class="whitespace-nowrap py-3 pl-4 pr-3 text-sm   text-gray-700 sm:pl-6">
                      {{ $user->name }}</td>
                    <td class="whitespace-nowrap py-3 pl-4 pr-3 text-sm   text-gray-700 sm:pl-6">
                      {{ $user->email }}</td>
                    <td class="whitespace-nowrap py-3 pl-4 pr-3 text-sm uppercase font-medium text-gray-700 sm:pl-6">
                      {{ $user->roles->first()->name }}</td>
                    <td class="relative whitespace-nowrap py-3 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                      <div class="flex space-x-2 justify-end">
                        <x-button icon="pencil-alt" wire:click="editUser({{ $user->id }})"
                          spinner="editUser({{ $user->id }})" label="Edit" xs />
                        <x-button wire:click="deleteUser({{ $user->id }})" spinner="deleteUser({{ $user->id }})"
                          icon="trash" label="Delete" xs />
                      </div>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div> --}}
  {{--
  <x-modal wire:model.defer="add_modal" max-width="lg">
    <x-card title="Add New">
      <div class="flex flex-col space-y-3">
        <x-input wire:model.defer="name" label="Name" placeholder="" />
        <x-input wire:model.defer="email" label="Email" placeholder="" />
        <x-inputs.password wire:model.defer="password" label="Password" placeholder="" />
        <x-native-select label="Role" wire:model="role">
          <option selected hidden>Select Role</option>
          <option value="admin">Admin</option>
          {{-- SUPERVISOR MODULE ON HOLD — option hidden from admin user create/edit forms. Uncomment to restore. --}}
          {{-- <option value="supervisor">Supervisor</option> --}}
          <option value="frontdesk">Frontdesk</option>
          <option value="kiosk">Kiosk</option>
          <option value="kitchen">Kitchen</option>
          <option value="roomboy">Roomboy</option>
          <option value="back_office">Back_Office</option>
        </x-native-select>
      </div>
      <x-slot name="footer">
        <div class="flex justify-end gap-x-2">
          <x-button flat label="Cancel" x-on:click="close" />
          <x-button wire:click="saveUser" spinner="saveUser" positive label="Save" />
        </div>
      </x-slot>
    </x-card>
  </x-modal>

  <x-modal wire:model.defer="edit_modal" max-width="lg">
    <x-card title="Update User">
      <div class="flex flex-col space-y-3">
        <x-input wire:model.defer="name" label="Name" placeholder="" />
        <x-input wire:model.defer="email" label="Email" placeholder="" />
        <x-inputs.password wire:model.defer="password" label="Password" placeholder="" />
        <x-native-select label="Role" wire:model="role">
          <option selected hidden>Select Role</option>
          <option value="admin">Admin</option>
          {{-- SUPERVISOR MODULE ON HOLD — option hidden from admin user create/edit forms. Uncomment to restore. --}}
          {{-- <option value="supervisor">Supervisor</option> --}}
          <option value="frontdesk">Frontdesk</option>
          <option value="kiosk">Kiosk</option>
          <option value="kitchen">Kitchen</option>
          <option value="roomboy">Roomboy</option>
          <option value="back_office">Back_Office</option>
        </x-native-select>
      </div>
      <x-slot name="footer">
        <div class="flex justify-end gap-x-2">
          <x-button flat label="Cancel" x-on:click="close" />
          <x-button wire:click="updateUser" spinner="updateUser" positive label="Update" />
        </div>
      </x-slot>
    </x-card>
  </x-modal> --}}
  <div class="bg-white p-4 rounded-xl">
    {{-- <div class="flex mb-5">
      <x-button wire:click="$set('add_modal', true)" icon="plus" slate label="Add New User" />
    </div>
    {{ $this->table }} --}}
    <div>

      <div class="hidden sm:block" x-data="{ type: 1 }" x-animate>
        <div class="border-b border-gray-200">
          <nav class="-mb-px flex space-x-8" aria-label="Tabs">
            <!-- Current: "border-indigo-500 text-indigo-600", Default: "border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-500" -->
            <button x-on:click="type=1" wire:click="$set('type',1)"
              :class="type == 1 ? 'border-gray-600' : 'border-transparent'"
              class="  text-gray-500  hover:text-gray-700 hover:border-gray-500 whitespace-nowrap flex py-3  border-b-2 font-semibold text-md">
              Users

              <!-- Current: "bg-indigo-100 text-indigo-600", Default: "bg-gray-100 text-gray-900" -->

            </button>
            <button x-on:click="type=2" wire:click="$set('type',2)"
              :class="type == 2 ? 'border-gray-600' : 'border-transparent'"
              class=" text-gray-500 hover:text-gray-700 hover:border-gray-500 whitespace-nowrap flex py-3 px-1 border-b-2 font-semibold text-md">
              Manage Frontdesk

              <!-- Current: "bg-indigo-100 text-indigo-600", Default: "bg-gray-100 text-gray-900" -->

            </button>
            <button x-on:click="type=3" wire:click="$set('type',3)"
              :class="type == 3 ? 'border-gray-600' : 'border-transparent'"
              class=" text-gray-500 hover:text-gray-700 hover:border-gray-500 whitespace-nowrap flex py-3 px-1 border-b-2 font-semibold text-md">
              Roomboy Designation

              <!-- Current: "bg-indigo-100 text-indigo-600", Default: "bg-gray-100 text-gray-900" -->

            </button>


          </nav>
        </div>
        <div class="p-4">
          @if ($type == 1)
            <div>
              <div class="flex mb-5">
                <x-button wire:click="$set('add_modal', true)" icon="plus" blue label="Add New User" />
              </div>
              {{ $this->table }}
            </div>
          @elseif($type == 2)
            <livewire:admin.manage-frondesk />
          @elseif($type == 3)
            <livewire:admin.roomboy-designation />
          @endif



        </div>
      </div>
    </div>
  </div>
  {{-- <x-modal wire:model.defer="add_modal" max-width="lg">
    <x-card title="Add New">
      <div class="flex flex-col space-y-3">
        <x-input wire:model.defer="name" label="Name" placeholder="" />
        <x-input wire:model.defer="email" label="Email" placeholder="" />
        <x-inputs.password wire:model.defer="password" label="Password" placeholder="" />
        <x-native-select label="Role" wire:model="role">
          <option selected hidden>Select Role</option>
          <option value="admin">Admin</option>
          {{-- SUPERVISOR MODULE ON HOLD — option hidden from admin user create/edit forms. Uncomment to restore. --}}
          {{-- <option value="supervisor">Supervisor</option> --}}
          <option value="frontdesk">Frontdesk</option>
          <option value="kiosk">Kiosk</option>
          <option value="kitchen">Kitchen</option>
          <option value="roomboy">Roomboy</option>
          <option value="back_office">Back_Office</option>
        </x-native-select>
      </div>
      <x-slot name="footer">
        <div class="flex justify-end gap-x-2">
          <x-button flat label="Cancel" x-on:click="close" />
          <x-button wire:click="saveUser" spinner="saveUser" positive label="Save" />
        </div>
      </x-slot>
    </x-card>
  </x-modal> --}}

  <x-modal wire:model.defer="add_modal" align="center" max-width="xl">
    <x-card>
      <div class="header flex space-x-2 items-center">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" class="fill-gray-600">
          <path fill="none" d="M0 0h24v24H0z" />
          <path
            d="M11 11V7h2v4h4v2h-4v4h-2v-4H7v-2h4zm1 11C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10zm0-2a8 8 0 1 0 0-16 8 8 0 0 0 0 16z" />
        </svg>
        <h1 class="text-lg font-semibold uppercase text-gray-600 ">Add New User</h1>
      </div>
      <div class="mt-5 px-2 grid grid-cols-2 gap-4 ">
        @if(auth()->user()->hasRole('superadmin'))
        <div class="col-span-2">
          <x-native-select label="Branch" wire:model="branch_id">
              <option selected hidden>Select Branch</option>
                @foreach ($branches as $item)
                    <option value="{{ $item->id }}">{{ $item->name }}</option>
                @endforeach
          </x-native-select>
        </div>
        @endif
        <x-input label="Name" wire:model.defer="name" />
        <x-input label="Email" wire:model.defer="email" />
        <x-inputs.password label="Password" wire:model.defer="password" />
        <x-native-select label="Role" wire:model="role">
          <option selected hidden>Select Role</option>
          <option value="admin">Admin</option>
          {{-- SUPERVISOR MODULE ON HOLD — option hidden from admin user create/edit forms. Uncomment to restore. --}}
          {{-- <option value="supervisor">Supervisor</option> --}}
          <option value="frontdesk">Frontdesk</option>
          <option value="kiosk">Kiosk</option>
          <option value="kitchen">Kitchen</option>
          <option value="roomboy">Roomboy</option>
          <option value="back_office">Back_Office</option>
        </x-native-select>


      </div>
      <x-slot name="footer">
        <div class="flex justify-end gap-x-4">
          <x-button flat label="Cancel" x-on:click="close" />

          <x-button positive right-icon="save-as" wire:click="saveUser" spinner="saveUser" label="Save" />
        </div>
      </x-slot>
    </x-card>
  </x-modal>
</div>
