<div>
      <div class="max-w-4xl mx-auto py-10 px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="mb-6">
            <h1 class="text-2xl font-semibold text-gray-900">{{ auth()->user()->cash_drawer->name ?? 'No Drawer' }}</h1>
            {{-- <p class="text-sm text-gray-500">Review summary before proceeding.</p> --}}
        </div>

        {{-- Card --}}
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200">
            {{-- Table-like header --}}
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <div class="text-sm font-medium text-gray-700">Summary</div>
                <div class="text-sm font-medium text-gray-700">Amount</div>
            </div>

            {{-- Rows --}}
            <div class="divide-y divide-gray-200">
                {{-- Transactions --}}
                <div class="px-6 py-5 flex items-center justify-between">
                    <div class="flex flex-col">
                        <span class="text-sm font-medium text-gray-900">Expenses</span>
                        {{-- <span class="text-xs text-gray-500">Extensions, Food & Beverages, Etc.</span> --}}
                    </div>
                    <div class="w-40 text-right">
                        <span class="inline-block h-5 w-24 rounded font-mono">₱ {{ number_format($total_expenses, 2) }}</span>
                    </div>
                </div>

                 <div class="px-6 py-5 flex items-center justify-between">
                    <div class="flex flex-col">
                        <span class="text-sm font-medium text-gray-900">Remittance</span>
                    </div>
                    <div class="w-40 text-right">
                        <span class="inline-block h-5 w-24 rounded font-mono">₱ {{ number_format($total_remittances, 2) }}</span>
                    </div>
                </div>

                <div class="px-6 py-5 flex items-center justify-between">
                    <div class="flex flex-col">
                        <span class="text-sm font-medium text-gray-900">POS</span>
                    </div>
                    <div class="w-40 text-right">
                        <span class="inline-block h-5 w-24 rounded font-mono">₱ {{ number_format($total_pos, 2) }}</span>
                    </div>
                </div>

               {{-- Cash on Hand --}}
                <div class="px-6 py-5 flex items-center justify-between">
                    <div class="flex flex-col">
                        <span class="text-sm font-medium text-gray-900">Cash on Hand</span>
                        {{-- <span class="text-xs text-gray-500">Cash remitted to vault or management</span> --}}
                    </div>

                    <div class="w-48">
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500 font-semibold">
                                ₱
                            </span>

                            <input
                                type="number"
                                step="0.01"
                                wire:model="remittance"
                                class="w-full pl-7 pr-3 py-1.5 text-right border rounded-md font-mono text-lg font-semibold focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="0.00"
                            >
                        </div>
                            @error('remittance') <span class="text-sm text-red-500 mt-1">{{ $message }}</span> @enderror

                    </div>
                </div>

                {{-- Description --}}
                {{-- <div class="px-6 py-5">
                    <div class="flex flex-col mb-2">
                        <span class="text-sm font-semibold text-gray-900">Description</span>
                        <span class="text-xs text-gray-500">Add notes or explanation for this report</span>
                    </div>

                    <textarea
                        wire:model="description"
                        rows="3"
                        class="w-full border rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="Enter description or remarks..."
                    ></textarea>
                    @error('description') <span class="text-sm text-red-500 mt-1">{{ $message }}</span> @enderror
                </div> --}}
            </div>

            {{-- Footer actions (bottom-right logout) --}}
            <div class="px-6 py-4 space-x-4 border-t border-gray-200 flex items-center justify-end">
                {{-- <button
                        type="button"
                        wire:click="$set('withdraw_modal', true)"
                        class="inline-flex items-center rounded-lg bg-yellow-600 px-4 py-2 text-sm font-medium text-white hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2">
                        Withdraw Cash
                </button> --}}
                <button type="button" wire:click="confirmLogout" class="inline-flex items-center rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                    End Shift
                </button>
                {{-- <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button
                        type="submit"
                        class="inline-flex items-center rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                        End Shift
                    </button>
                </form> --}}
            </div>
        </div>
    </div>

    {{-- Logout Modal --}}
     <x-modal wire:model.defer="logout_modal" align="center">
      <x-card>
        <div>
          <div class="header flex space-x-1 border-b items-end justify-between py-0.5">
            <h2 class="text-lg uppercase text-gray-600 font-bold">Confirm End Shift</h2>
          </div>
          <div class="mt-3">
            <div class="space-y-4">
              <dl class="mt-8 p-2 divide-y divide-gray-400 text-sm lg:col-span-5 lg:mt-0">
                <div class="flex justify-between py-2">
                    <div class="w-full divide-y divide-gray-400 ">
                        <span class="text-xl font-semibold mb-8">Are you sure you want to end your shift?</span>
                        {{-- <dl class="mt-2 text-sm lg:col-span-5 lg:mt-0">
                            <div class="flex justify-between py-2">
                                <dt class="text-gray-600 text-lg font-bold">Total Cash on Drawer:</dt>
                                <dd class="text-gray-800 text-lg font-bold">₱ {{ number_format($total_transactions + $total_deposits, 2) }}</dd>
                            </div> --}}
                    </div>
                </div>
              </dl>
            </div>
          </div>
        </div>
        <x-slot name="footer">
          <div class="flex justify-end space-x-2">
              <x-button red label="No" x-on:click="close" />
              <x-button emerald label="Yes" wire:click="enterPasscode" />
              {{-- <form method="POST" action="{{ route('logout') }}">
                    @csrf
                </form> --}}
          </div>
        </x-slot>
      </x-card>
    </x-modal>

        {{-- Withdraw Modal --}}
     <x-modal wire:model.defer="withdraw_modal" align="center">
      <x-card>
        <div>
          <div class="header flex space-x-1 border-b items-end justify-between py-0.5">
            <h2 class="text-lg uppercase text-gray-600 font-bold">Withdraw Cash</h2>
          </div>
          <div class="mt-3">
            <div class="space-y-4">
              <dl class="mt-8 p-2 divide-y divide-gray-400 text-sm lg:col-span-5 lg:mt-0">
                <div class="flex justify-between py-2">
                    <div class="w-full divide-y divide-gray-400 ">
                        <span class="text-xl font-semibold mb-8">Are you sure you want to withdraw cash?</span>
                        <dl class="mt-2 text-sm lg:col-span-5 lg:mt-0">
                            <div class="flex justify-between py-2">
                                <dt class="text-gray-600 text-lg font-bold">Total Cash on Drawer:</dt>
                                <dd class="text-gray-800 text-lg font-bold">₱ {{ number_format($total_transactions + $total_deposits, 2) }}</dd>
                            </div>
                    </div>
                </div>
              </dl>
            </div>
          </div>
        </div>
        <x-slot name="footer">
          <div class="flex justify-end space-x-2">
              <x-button red label="Close" x-on:click="close" />
              <x-button emerald label="Confirm" wire:click="enterPasscode" />

              {{-- <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-button emerald label="Confirm" type="submit" />
                </form> --}}
          </div>
        </x-slot>
      </x-card>
    </x-modal>

     {{-- moadal for authorization code --}}
        <x-modal wire:model.defer="authorization_modal" align="center" max-width="md">
            <x-card>

                <div class="flex">
                    <h1 class="text-xl font-bold text-gray-600">ENTER PASSCODE</h1>
                </div>

                {{-- Disclaimer --}}
                <div class="mt-4 p-3 rounded-md bg-gray-50 border text-sm text-gray-600 leading-relaxed">
                    By entering your passcode, you confirm that the declared cash on hand is accurate and final. 
                    Any discrepancy resulting from negligence, falsification, or misappropriation may result in 
                    liability under the policies of <span class="font-semibold">ALMA RESIDENCES</span>.
                </div>

                {{-- Passcode --}}
                <div class="mt-6">
                    <input 
                        type="password" 
                        wire:model="code"
                        class="w-full text-lg rounded-lg border px-3 py-2
                        @error('code') border-red-500 @enderror"
                        placeholder="Enter passcode"
                    >
                </div>

                @error('code')
                    <span class="text-sm text-red-500 mt-1">{{ $message }}</span>
                @enderror

                {{-- Buttons --}}
                <div class="mt-6 flex flex-col space-y-2">

                    <x-button 
                        x-on:click="close"
                        label="CANCEL"
                        class="w-full bg-white border border-gray-300 text-gray-700"
                    />

                    <x-button 
                        label="END SHIFT"
                        class="w-full bg-red-600 text-white"
                        wire:click="endShiftConfirm"
                        spinner="endShiftConfirm"
                    />

                </div>

            </x-card>
        </x-modal>

</div>
