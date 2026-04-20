<div>
  <div class="flex justify-end space-x-2 px-4">
    <x-button label="Add" icon="plus" wire:click="openAddModal" positive />
    <x-button label="View" icon="eye" wire:click="openViewModal" info />
  </div>
  <div class="table w-full px-4">
    <div class="bg-white p-4 rounded-xl mt-4">
      <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
          <p class="mt-2 text-2xl font-bold text-green-600">&#8369;{{ number_format($total, 2) }}</p>
          <h1 class="text-sm text-gray-500">Total Payment on Short</h1>
        </div>
      </div>
      <div class="-mx-4 mt-3 flex flex-col sm:-mx-6 md:mx-0">
        <table class="min-w-full border-t divide-y divide-gray-300">
          <thead>
            <tr>
              <th scope="col" class="py-3.5 w-10 pl-4 pr-3 text-left text-sm font-semibold text-gray-600 sm:pl-6 md:pl-0">#</th>
              <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-600 sm:pl-6 md:pl-0">DATE</th>
              <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-600 sm:pl-6 md:pl-0">SHIFT</th>
              <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-600 sm:pl-6 md:pl-0">NAME</th>
              <th scope="col" class="hidden py-3.5 px-3 text-right text-sm font-semibold text-gray-600 sm:table-cell">DESCRIPTION</th>
              <th scope="col" class="hidden py-3.5 px-3 text-right text-sm font-semibold text-gray-600 sm:table-cell">AMOUNT</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($payments as $index => $payment)
              <tr class="border-b border-gray-200">
                <td class="py-3 pl-4 pr-3 text-sm sm:pl-6 md:pl-0">
                  <div class="font-medium text-gray-500">{{ $index + 1 }}</div>
                </td>
                <td class="py-3 pl-4 pr-3 text-sm sm:pl-6 md:pl-0">
                  <div class="font-medium text-gray-500 uppercase">{{ Carbon\Carbon::parse($payment->created_at)->format('F d, Y h:i A') }}</div>
                </td>
                <td class="py-3 pl-4 pr-3 text-sm sm:pl-6 md:pl-0">
                  <div class="font-medium text-gray-500 uppercase">{{ $payment->shiftLog->shift ?? 'N/A' }}</div>
                </td>
                <td class="py-3 pl-4 pr-3 text-sm sm:pl-6 md:pl-0">
                  <div class="font-medium text-gray-500 uppercase">{{ $payment->name }}</div>
                </td>
                <td class="hidden py-3 px-3 text-right text-sm text-gray-500 sm:table-cell">
                  {{ $payment->description ?? '-' }}
                </td>
                <td class="hidden py-3 px-3 text-right text-sm text-gray-500 sm:table-cell">
                  &#8369; {{ number_format($payment->amount, 2) }}
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="6" class="py-3 px-3 text-center text-sm text-gray-500">No payment on short recorded for this shift.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- Add Modal --}}
  <x-modal blur wire:model.defer="add_modal" align="center">
    <x-card>
      <div class="header flex space-x-2 items-center">
        <h1 class="text-lg font-semibold uppercase text-gray-600">Add Payment on Short</h1>
      </div>
      <div class="mt-5 px-4 grid grid-cols-1 gap-4">
        <x-input label="Name" type="text" wire:model.defer="name" placeholder="Enter name" />
        <x-textarea label="Description" wire:model.defer="description" placeholder="Enter description (optional)" />
        <x-input label="Amount" type="number" wire:model.defer="amount" placeholder="Enter amount" />
      </div>
      <x-slot name="footer">
        <div class="flex justify-end gap-x-4">
          <x-button flat label="Cancel" x-on:click="close" />
          <x-button positive right-icon="save-as" wire:click="savePaymentOnShort" spinner="savePaymentOnShort" label="Save" />
        </div>
      </x-slot>
    </x-card>
  </x-modal>

  {{-- View Modal --}}
  <x-modal blur wire:model.defer="view_modal" align="center" max-width="4xl">
    <x-card>
      <div class="header flex space-x-2 items-center justify-between">
        <h1 class="text-lg font-semibold uppercase text-gray-600">Payment on Short Records</h1>
        <p class="text-xl font-bold text-green-600">Total: &#8369;{{ number_format($total, 2) }}</p>
      </div>
      <div class="mt-5 max-h-96 overflow-y-auto">
        <table class="min-w-full divide-y divide-gray-300">
          <thead class="bg-gray-50">
            <tr>
              <th scope="col" class="py-3 pl-4 pr-3 text-left text-sm font-semibold text-gray-600">#</th>
              <th scope="col" class="py-3 pl-4 pr-3 text-left text-sm font-semibold text-gray-600">NAME</th>
              <th scope="col" class="py-3 px-3 text-left text-sm font-semibold text-gray-600">DESCRIPTION</th>
              <th scope="col" class="py-3 px-3 text-right text-sm font-semibold text-gray-600">AMOUNT</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            @forelse ($payments as $index => $payment)
              <tr>
                <td class="py-3 pl-4 pr-3 text-sm text-gray-500">{{ $index + 1 }}</td>
                <td class="py-3 pl-4 pr-3 text-sm text-gray-900">{{ $payment->name }}</td>
                <td class="py-3 px-3 text-sm text-gray-500">{{ $payment->description ?? '-' }}</td>
                <td class="py-3 px-3 text-right text-sm text-gray-900">&#8369; {{ number_format($payment->amount, 2) }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="4" class="py-3 px-3 text-center text-sm text-gray-500">No records found.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
      <x-slot name="footer">
        <div class="flex justify-end">
          <x-button flat label="Close" x-on:click="close" />
        </div>
      </x-slot>
    </x-card>
  </x-modal>
</div>
