<div x-data="{ open: @entangle('reminders_modal') }">
  <div class=" lg:grid  lg:grid-cols-12 lg:gap-8 w-full ">
    <div class="hidden lg:col-span-3 lg:block xl:col-span-2">
      <nav aria-label="Sidebar" class=" divide-y divide-gray-300">
        <!-- <nav class="flex flex-col space-y-2">
          <x-button label="Transfer Room" md slate right-icon="external-link" wire:click=" $set('transfer_modal', true)" />
          <x-button label="Extend" md slate right-icon="external-link" wire:click=" $set('extend_modal', true)" />
          <x-button label="Damage Charges" md slate right-icon="external-link"
            wire:click=" $set('damage_modal', true)" />
          <x-button label="Amenities" md slate right-icon="external-link" wire:click=" $set('amenities_modal', true)" />
          <x-button label="Food and Beverages" md slate right-icon="external-link"
            wire:click=" $set('food_beverages_modal', true)" />
          <x-button label="Deposits" md slate right-icon="external-link" wire:click=" $set('deposit_modal', true)" />
        </nav> -->
        <div>
          <div class="border shadow-md rounded-lg">
            <li class="flex py-2 px-2">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-10 w-10 fill-green-600">
                <path fill="none" d="M0 0h24v24H0z" />
                <path d="M5 20h14v2H5v-2zm7-2a8 8 0 1 1 0-16 8 8 0 0 1 0 16z" />
              </svg>
              <div class="ml-2">
                <div class="flex space-x-1">
                  <p class="text-sm font-medium text-gray-900 uppercase">{{ $guest->name }}</p>
                  <p class="text-sm text-gray-900 ">{{ $guest->contact != 'N/A' ? '(09' . $guest->contact . ')' : '' }}
                  </p>
                </div>
                <div class="flex space-x-1 items-center fill-gray-600 text-gray-600">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-5 w-5">
                    <path fill="none" d="M0 0h24v24H0z" />
                    <path
                      d="M16 17v-1h-3v-3h3v2h2v2h-1v2h-2v2h-2v-3h2v-1h1zm5 4h-4v-2h2v-2h2v4zM3 3h8v8H3V3zm2 2v4h4V5H5zm8-2h8v8h-8V3zm2 2v4h4V5h-4zM3 13h8v8H3v-8zm2 2v4h4v-4H5zm13-2h3v2h-3v-2zM6 6h2v2H6V6zm0 10h2v2H6v-2zM16 6h2v2h-2V6z" />
                  </svg>
                  <span class="text-sm">{{ $guest->qr_code }}</span>
                </div>
              </div>
            </li>
          </div>
          <div class="bg-gradient-to-br shadow-md from-gray-300 via-gray-200 to-gray-100 mt-2 rounded-lg p-3">
            <div class="header">
              <h3 class="text-lg font-bold text-gray-700 uppercase">Check-In Details</h3>

              <div class="mt-2 border-b border-gray-300">
                <h1 class="text-xs text-gray-500">Room Number</h1>
                <h1 class="font-bold text-gray-700">ROOM #{{ $guest->room->number }}</h1>
              </div>
              <div class="mt-2 border-b border-gray-300">
                <h1 class="text-xs text-gray-500">Initial Check In Hour</h1>
                <h1 class="font-bold text-gray-700">{{ $guest->rates->stayingHour->number }} Hours</h1>
              </div>
              <div class="mt-2 border-b border-gray-300">
                <h1 class="text-xs text-gray-500">Time Remaining</h1>
                <h1 class="font-bold text-gray-700">
                  @php
                    $check_out_date = Carbon\Carbon::parse($guest->checkinDetail->check_out_at ?? null);

                  @endphp
                  <x-countdown :expires="$check_out_date" class="text-red-600">
                    <span x-text="timer.days">{{ $component->days() }}</span>d :
                    <span x-text="timer.hours">{{ $component->hours() }}</span>h :
                    <span x-text="timer.minutes">{{ $component->minutes() }}</span>m :
                    <span x-text="timer.seconds">{{ $component->seconds() }}</span>s
                  </x-countdown>

                </h1>
              </div>
              <div class="mt-2 border-b border-gray-300">
                <h1 class="text-xs text-gray-500">Check In Date</h1>
                <h1 class="font-medium text-gray-700">
                  {{ Carbon\Carbon::parse($guest->checkinDetail->check_in_at)->format('F d, Y h:i A') }}</h1>
              </div>
              <div class="mt-2 border-b border-gray-300">
                <h1 class="text-xs text-gray-500">Expected Check Out Date</h1>
                <h1 class="font-medium text-gray-700">
                  {{ Carbon\Carbon::parse($guest->checkinDetail->check_out_at)->format('F d, Y h:i A') }}</h1>
              </div>
            </div>
          </div>
        </div>
      </nav>
    </div>
    <main class="lg:col-span-9 xl:col-span-7">
      <div class="grid grid-cols-6 gap-x-2 border rounded-lg p-4 mb-4">
        <x-button label="Transfer Room" sm slate right-icon="external-link"
          wire:click=" $set('transfer_modal', true)" />
        <x-button label="Extend" sm slate right-icon="external-link" wire:click=" $set('extend_modal', true)" />
        <x-button label="Damage Charges" sm slate right-icon="external-link" wire:click=" $set('damage_modal', true)" />
        <x-button label="Amenities" sm slate right-icon="external-link" wire:click=" $set('amenities_modal', true)" />
        <x-button label="Food and Beverages" sm slate right-icon="external-link"
          wire:click=" $set('food_beverages_modal', true)" />
        <x-button label="Deposits" sm slate right-icon="external-link" wire:click=" $set('deposit_modal', true)" />
      </div>
      <div class="border rounded-lg p-4">
        <div class="lg:flex lg:items-center lg:justify-between border-b">
          <div class="min-w-0 flex-1 ">
            <div class="flex justify-between">
              <div>
                <h2 class="text-2xl font-bold leading-7 text-gray-900 s sm:truncate sm:text-3xl sm:tracking-tight">GUEST
                  TRANSACTIONS</h2>
              </div>
              <div class="flex space-x-2">
                <x-button label="Back" icon="reply" negative href="{{ route('frontdesk.room-monitoring') }}" />
                <x-button label="Check Out" right-icon="arrow-right" positive @click="open = true" />
              </div>
            </div>
            <div class="my-1 mt-3 flex flex-col  sm:flex-row sm:flex-wrap sm:space-x-6">
              <div class="mt-2 flex items-center text-sm text-gray-500">
                @if ($transaction->where('description', 'Room Transfer')->count() > 0)
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-5 fill-green-600">
                    <path fill="none" d="M0 0h24v24H0z" />
                    <path
                      d="M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10zm-.997-6l7.07-7.071-1.414-1.414-5.656 5.657-2.829-2.829-1.414 1.414L11.003 16z" />
                  </svg>
                  <span class="ml-1">Transfer Room</span>
                @else
                  <!-- Heroicon name: mini/briefcase -->
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-5 fill-gray-500">
                    <path fill="none" d="M0 0h24v24H0z" />
                    <path
                      d="M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10zm0-2a8 8 0 1 0 0-16 8 8 0 0 0 0 16z" />
                  </svg>
                  <span class="ml-1">Transfer Room</span>
                @endif
              </div>
              <div class="mt-2 flex items-center text-sm text-gray-500">
                @if ($transaction->where('description', 'Extension')->count() > 0)
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-5 fill-green-600">
                    <path fill="none" d="M0 0h24v24H0z" />
                    <path
                      d="M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10zm-.997-6l7.07-7.071-1.414-1.414-5.656 5.657-2.829-2.829-1.414 1.414L11.003 16z" />
                  </svg>
                  <span class="ml-1">Extension</span>
                @else
                  <!-- Heroicon name: mini/briefcase -->
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-5 fill-gray-500">
                    <path fill="none" d="M0 0h24v24H0z" />
                    <path
                      d="M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10zm0-2a8 8 0 1 0 0-16 8 8 0 0 0 0 16z" />
                  </svg>
                  <span class="ml-1">Extension</span>
                @endif
              </div>
              <div class="mt-2 flex items-center text-sm text-gray-500">
                @if ($transaction->where('description', 'Damage Charges')->count() > 0)
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-5 fill-green-600">
                    <path fill="none" d="M0 0h24v24H0z" />
                    <path
                      d="M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10zm-.997-6l7.07-7.071-1.414-1.414-5.656 5.657-2.829-2.829-1.414 1.414L11.003 16z" />
                  </svg>
                  <span class="ml-1">Damage Charges</span>
                @else
                  <!-- Heroicon name: mini/briefcase -->
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-5 fill-gray-500">
                    <path fill="none" d="M0 0h24v24H0z" />
                    <path
                      d="M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10zm0-2a8 8 0 1 0 0-16 8 8 0 0 0 0 16z" />
                  </svg>
                  <span class="ml-1">Damage Charges</span>
                @endif
              </div>
              <div class="mt-2 flex items-center text-sm text-gray-500">
                @if ($transaction->where('description', 'Amenities')->count() > 0)
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-5 fill-green-600">
                    <path fill="none" d="M0 0h24v24H0z" />
                    <path
                      d="M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10zm-.997-6l7.07-7.071-1.414-1.414-5.656 5.657-2.829-2.829-1.414 1.414L11.003 16z" />
                  </svg>
                  <span class="ml-1">Amenities</span>
                @else
                  <!-- Heroicon name: mini/briefcase -->
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-5 fill-gray-500">
                    <path fill="none" d="M0 0h24v24H0z" />
                    <path
                      d="M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10zm0-2a8 8 0 1 0 0-16 8 8 0 0 0 0 16z" />
                  </svg>
                  <span class="ml-1">Amenities</span>
                @endif
              </div>
              <div class="mt-2 flex items-center text-sm text-gray-500">
                @if ($transaction->where('description', 'Food and Beverages')->count() > 0)
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-5 fill-green-600">
                    <path fill="none" d="M0 0h24v24H0z" />
                    <path
                      d="M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10zm-.997-6l7.07-7.071-1.414-1.414-5.656 5.657-2.829-2.829-1.414 1.414L11.003 16z" />
                  </svg>
                  <span class="ml-1">Food and Beverages</span>
                @else
                  <!-- Heroicon name: mini/briefcase -->
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-5 fill-gray-500">
                    <path fill="none" d="M0 0h24v24H0z" />
                    <path
                      d="M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10zm0-2a8 8 0 1 0 0-16 8 8 0 0 0 0 16z" />
                  </svg>
                  <span class="ml-1">Food and Beverages</span>
                @endif
              </div>
              <div class="mt-2 flex items-center text-sm text-gray-500">
                @if ($transaction->where('description', 'Deposit')->count() > 0)
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-5 fill-green-600">
                    <path fill="none" d="M0 0h24v24H0z" />
                    <path
                      d="M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10zm-.997-6l7.07-7.071-1.414-1.414-5.656 5.657-2.829-2.829-1.414 1.414L11.003 16z" />
                  </svg>
                  <span class="ml-1">Deposits</span>
                @else
                  <!-- Heroicon name: mini/briefcase -->
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-5 fill-gray-500">
                    <path fill="none" d="M0 0h24v24H0z" />
                    <path
                      d="M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10zm0-2a8 8 0 1 0 0-16 8 8 0 0 0 0 16z" />
                  </svg>
                  <span class="ml-1">Deposits</span>
                @endif
              </div>
            </div>
          </div>
        </div>
        <div class="mt-5 xl:h-[28rem] lg:h-96 overflow-y-auto ">
          <div class="px-4 sm:px-6 lg:px-8">

            <div class="mt flex flex-col">
              <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-6 lg:-mx-8">
                <div class="inline-block min-w-full py-2 align-middle ">
                  <div class="overflow-hidden shadow ">
                    <table class="min-w-full">
                      <thead class="bg-gray-500">
                        <tr>
                          <th scope="col"
                            class="py-3.5 pl-4 pr-3 text-left w-32 text-sm font-semibold text-white sm:pl-6">
                          </th>

                          <th scope="col" class="px-3 py-3.5 w-80 text-left text-sm font-semibold text-white">
                            DETAILS
                          </th>
                          <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-white">
                            AMOUNT
                          </th>
                          <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-white">
                            TRANSACTION DATE
                          </th>
                          <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-white">
                            PAID AT
                          </th>
                        </tr>
                      </thead>
                      <tbody class="bg-white">
                        @foreach ($transactions as $description => $transactionGroup)
                          <tr class="border-t border-gray-200">
                            <th colspan="5" scope="colgroup"
                              class="bg-gray-100 px-4 py-2 text-left text-sm font-semibold text-green-600 uppercase sm:px-6">
                              {{ $description }}
                            </th>
                          </tr>
                          @foreach ($transactionGroup as $transaction)
                            <tr class="border-t border-gray-300">
                              <td class="whitespace-nowrap py-2 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6">
                              </td>
                              <td class=" px-3 py-2 text-sm text-gray-600 ">
                                <p> {{ $transaction->remarks }}</p>
                              </td>
                              <td class="whitespace-nowrap px-3 py-2 text-sm text-gray-600 ">
                                ₱{{ number_format($transaction->payable_amount, 2) }}
                              </td>
                              <td class="whitespace-nowrap px-3 py-2 text-sm text-gray-600 ">
                                {{ Carbon\Carbon::parse($transaction->created_at)->format('F d, Y h:i A') }}
                              </td>
                              <td class="whitespace-nowrap px-3 py-2 text-sm text-gray-600 ">
                                @if ($transaction->paid_at == null)
                                  <div class="flex gap-1">
                                    <x-button xs positive label="Pay"
                                      wire:click="payTransaction({{ $transaction->id }})"
                                      spinner="payTransaction({{ $transaction->id }})" />
                                      @if ($deposit_except_remote_and_key - $check_in_details->total_deduction >= $transaction->payable_amount)
                                    <x-button xs amber wire:click="payWithDeposit({{ $transaction->id }})"
                                      spinner="payWithDeposit({{ $transaction->id }})" label="Pay with deposit" />
                                      @endif
                                    <x-button xs negative wire:click="override({{ $transaction->id }})"
                                      spinner="override({{ $transaction->id }})" label="Override" />
                                  </div>
                                @else
                                  {{ Carbon\Carbon::parse($transaction->paid_at)->format('F d, Y h:i A') }}
                                @endif
                          @endforeach
                          <tr class="border-t border-gray-200">
                            <td class="whitespace-nowrap py-2 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6">
                            </td>
                            <td class=" py-1 text-right pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6">
                              SUBTOTAL:
                            </td>
                            <td class="text-left py-2 pl-4 pr-3 text-sm font-bold text-gray-700 sm:pl-6">
                              &#8369;{{ number_format($transactionGroup->sum('payable_amount'), 2) }}
                            </td>
                          </tr>
                          </td>
                        @endforeach
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>

        </div>

      </div>

    </main>
    <aside class="hidden xl:col-span-3 xl:block">
      <div class=" space-y-4">
        <div class="border rounded-lg p-5">
          <div class="flex space-x-1 items-center">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-5 w-5 fill-gray-600">
              <path fill="none" d="M0 0h24v24H0z" />
              <path
                d="M19 22H5a3 3 0 0 1-3-3V3a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v12h4v4a3 3 0 0 1-3 3zm-1-5v2a1 1 0 0 0 2 0v-2h-2zM6 7v2h8V7H6zm0 4v2h8v-2H6zm0 4v2h5v-2H6z" />
            </svg>
            <span class="font-bold text-gray-600  text-lg">BILL SUMMARY REPORT</span>
          </div>
          <div class="mt-3">
            <span class="font-bold text-gray-600  text-lg mb-4">Paid Bills</span>
            <dl class="mt-8 divide-y divide-gray-200 text-sm mb-4 border-b-2 border-dashed lg:col-span-5 lg:mt-0">
              @foreach ($transaction_bills_paid as $bill)
                @if ($bill->transaction_type->name != 'Deposit' && $bill->transaction_type->name != 'Cashout')
                  <div class="flex items-center justify-between py-2">
                    <dt class="text-gray-600">{{ $bill->transaction_type->name }}</dt>
                    <dd class="font-medium text-gray-900">₱ {{ number_format($bill->total_payable_amount, 2) }}</dd>
                  </div>
                @endif
              @endforeach
              @php
                $total_paid = $transaction_bills_paid
                    ->filter(function ($bill) {
                        return $bill->transaction_type->name != 'Deposit' && $bill->transaction_type->name != 'Cashout';
                    })
                    ->sum('total_payable_amount');
              @endphp
              <div class="flex items-center justify-between py-2">
                <dt class="font-medium text-gray-900 text-md">Total Amount</dt>
                <dd class="font-medium text-indigo-600 text-md">₱ {{ number_format($total_paid, 2) }}</dd>
              </div>
            </dl>

            <span class="font-bold text-gray-600  text-lg mb-4">Unpaid Bills</span>
            <dl class="mt-8 divide-y divide-gray-200 text-sm mb-4 border-b-2 border-dashed lg:col-span-5 lg:mt-0">
              @foreach ($transaction_bills_unpaid as $bill)
                @if ($bill->transaction_type->name != 'Deposit')
                  <div class="flex items-center justify-between py-2">
                    <dt class="text-gray-600">{{ $bill->transaction_type->name }}</dt>
                    <dd class="font-medium text-gray-900">₱ {{ number_format($bill->total_payable_amount, 2) }}</dd>
                  </div>
                @endif
              @endforeach
              @php
                $total_payable = $transaction_bills_unpaid
                    ->filter(function ($bill) {
                        return $bill->transaction_type->name != 'Deposit';
                    })
                    ->sum('total_payable_amount');
              @endphp
              <div class="flex items-center justify-between py-2">
                <dt class="font-medium text-gray-900 text-md">Payable Amount</dt>
                <dd class="font-medium text-indigo-600 text-md">₱ {{ number_format($total_payable, 2) }}</dd>
              </div>
              @if ($total_payable > 0)
                @if ($deposit_except_remote_and_key - $check_in_details->total_deduction >= $total_payable)
                  <div class="flex justify-around">
                    <div class="py-3">
                      <x-button full label="Pay all unpaid balances" negative wire:click="payAll" />
                    </div>
                    <div class="py-3">
                      <x-button full label="Pay all with deposit" amber
                        wire:click="payAllWithDeposit({{ $total_payable }})" />
                    </div>
                  </div>
                @else
                  <div class="py-3">
                    <x-button full label="Pay all unpaid balances" negative wire:click="payAll" />
                  </div>
                @endif
              @endif
            </dl>

            <span class="font-bold text-gray-600  text-lg mb-4">Deposits</span>
            <dl class="mt-8 divide-y divide-gray-200 text-sm mb-4 border-b-2 border-dashed lg:col-span-5 lg:mt-0">
              <div class="flex items-center justify-between py-2">
                <dt class="text-gray-600">Room Key & TV Remote</dt>
                <dd class="font-medium text-gray-900">₱{{ number_format($deposit_remote_and_key, 2) }}</dd>
              </div>
              <div class="flex items-center justify-between py-2">
                <dt class="text-gray-600">Other Deposits</dt>
                <dd class="font-medium text-gray-900">₱{{ number_format($deposit_except_remote_and_key, 2) }}</dd>
              </div>
              <div class="flex items-center justify-between py-2">
                <dt class="text-gray-600">Total Deposit</dt>
                <dd class="font-medium text-gray-900">₱{{ number_format($check_in_details->total_deposit, 2) }}</dd>
              </div>

              <div class="flex items-center justify-between py-2">
                <dt class="text-red-500">Total Deduction</dt>
                <dd class="font-medium text-red-500">₱{{ number_format($check_in_details->total_deduction, 2) }}</dd>
              </div>

              <div class="flex items-center justify-between py-2">
                <dt class="font-medium text-gray-900">Balance</dt>
                <dd class="font-medium text-indigo-600">
                  ₱{{ number_format($check_in_details->total_deposit - $check_in_details->total_deduction, 2) }}</dd>
              </div>
              {{-- @if ($check_in_details->total_deposit - $check_in_details->total_deduction != 0)
                <div class="p-3">
                  <x-button full label="Claim all deposits" positive wire:click="claimAll" />
                </div>
              @endif --}}
            </dl>

            <dl class="mt-8 divide-y divide-gray-200 text-sm lg:col-span-5 lg:mt-0">
              <div class="flex items-center justify-between py-2">
                <dt class="font-medium text-gray-900 text-xl">Total</dt>
                <dd class="font-medium text-indigo-600 text-xl">₱{{ number_format($total_payable, 2) }}</dd>
              </div>
            </dl>
          </div>
        </div>

      </div>
    </aside>
  </div>

  <x-modal wire:model.defer="transfer_modal" align="center">
    <x-card>
      <div>
        <div class="header flex space-x-1 border-b items-end justify-between py-0.5">
          <h2 class="text-lg uppercase text-gray-600 font-bold">Transfer Room</h2>
          <x-button.circle icon="plus" xs positive />
        </div>
        <div class="mt-3">
          <div class="grid grid-cols-2 gap-3" x-animate>
            <x-native-select label="Type" wire:model="type_id">
              <option selected hidden>Select Type</option>
              @foreach ($types as $type)
                <option value="{{ $type->id }}">{{ $type->name }}</option>
              @endforeach
            </x-native-select>
            <x-native-select label="Floor" wire:model="floor_id">
              <option selected hidden>Select Floor</option>
              @foreach ($floors as $floor)
                <option value="{{ $floor->id }}">{{ $floor->numberWithFormat() }}</option>
              @endforeach
            </x-native-select>
            @php
              $rooms_count = \App\Models\Room::where('branch_id', auth()->user()->branch_id)
                  ->where('status', 'Available')
                  ->where('type_id', $type_id)
                  ->count();
            @endphp

            @if ($rooms_count > 0)
              <x-native-select label="Room" wire:model="room_id">
                <option selected hidden>Select Room</option>
                @foreach ($rooms as $room)
                  <option value="{{ $room->id }}">{{ $room->numberWithFormat() }}</option>
                @endforeach
              </x-native-select>
              <x-native-select label="Old Room Status" wire:model.defer="old_status">
                <option selected hidden>Select Status</option>
                <option value="Uncleaned">Uncleaned</option>
                <option value="Cleaned">Cleaned</option>

              </x-native-select>
              <div class="col-span-2">
                <x-textarea label="Reason" wire:model.defer="reason" placeholder="write reason of transfer" />
              </div>
              <div class="col-span-2 bg-gray-200 rounded-lg p-3">
                <dl class=" space-y-3 text-sm font-medium text-gray-500">
                  <div class="flex border-b items-center justify-between">
                    <dt>Previous Room Amount</dt>
                    <dd class="text-gray-700 ">&#8369;{{ number_format($guest->static_amount, 2) }}
                    </dd>
                  </div>
                  @php
                    $hours = $guest->checkInDetail->hours_stayed;
                    $new_room = \App\Models\Rate::where('branch_id', auth()->user()->branch_id)
                        ->where('type_id', $type_id)
                        ->where('is_available', true)
                        ->whereHas('stayingHour', function ($query) use ($hours) {
                            $query->where('branch_id', auth()->user()->branch_id)->where('number', '=', $hours);
                        })
                        ->first();
                  @endphp
                  <div class="flex items-center justify-between">
                    <dt>New Room Amount</dt>
                    <dd class="text-gray-900 front-bold text-lg">&#8369;{{ number_format($new_room->amount, 2) }}</dd>
                  </div>
                  <div class="flex justify-between border-t border-gray-200  text-gray-500">
                    <dt class="text-sm">Excess Amount</dt>
                    <dd class="text-sm">&#8369;

                      @if ($guest->static_amount > $new_room->amount)
                        {{ number_format($guest->static_amount - $new_room->amount, 2) }}
                      @else
                        0.00
                      @endif
                    </dd>
                  </div>
                </dl>
              </div>
              <div class="mt-1 border-t w-full col-span-2">
                <div class="flex flex-col">
                  <dt class="text-sm text-gray-500 uppercase">Total Payable Amount</dt>
                  <dd class="text-red-600 font-bold text-3xl">

                    &#8369;{{ number_format($total, 2) }}
                  </dd>

                </div>
              </div>
            @else
              <div class="rounded-md bg-red-50 p-4 col-span-2">
                <div class="flex">
                  <div class="flex-shrink-0">
                    <!-- Heroicon name: mini/information-circle -->
                    <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                      fill="currentColor" aria-hidden="true">
                      <path fill-rule="evenodd"
                        d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z"
                        clip-rule="evenodd" />
                    </svg>
                  </div>
                  <div class="ml-3 flex-1 md:flex md:justify-between">
                    <p class="text-sm font-medium text-red-700">No Available Room
                    </p>
                  </div>
                </div>
              </div>
            @endif
          </div>
        </div>
      </div>

      <x-slot name="footer">
        <div class="flex justify-end gap-x-2">
          <x-button flat negative label="Cancel" wire:click="closeModal" />
          <x-button positive label="Save" wire:click="saveTransfer" right-icon="arrow-narrow-right" />
        </div>
      </x-slot>
    </x-card>
  </x-modal>

  <x-modal wire:model.defer="deposit_modal" align="center">
    <x-card>
      <div>
        <div class="header flex space-x-1 border-b items-end justify-between py-0.5">
          <h2 class="text-lg uppercase text-gray-600 font-bold">Add Deposit</h2>
          <x-button.circle icon="plus" xs positive />
        </div>
        <div class="mt-3">
          <div class="flex justify-between p-2 mb-4 bg-gray-300 rounded-md items-center">
            <div class="flex space-x-5">
              <dt class="text-gray-600">Total Deposit <small>(Except Room Key and TV Remote) : </small></dt>
            </div>

            @if (($deposit_except_remote_and_key - $check_in_details->total_deduction) > 0)
            <div class="flex items-center space-x-2">
                <dd class="font-medium text-gray-800">₱ {{ number_format($deposit_except_remote_and_key - $check_in_details->total_deduction, 2, '.', ',') }}</dd>
                <x-button wire:click="$set('deposit_deduct_modal', true)" amber label="Deduct" />
            </div>
            @else
            <div class="flex items-center space-x-2">
                <dd class="font-medium text-gray-800">₱ {{ number_format($deposit_except_remote_and_key - $check_in_details->total_deduction, 2, '.', ',') }}</dd>
            </div>
            @endif
          </div>
          <div class="space-y-4">
            <x-input label="Deposit Amount" type="number" min="0" wire:model="deposit_amount" />
            <x-textarea label="Description/Remarks" wire:model="deposit_remarks" />
          </div>
        </div>
      </div>

      <x-slot name="footer">
        <div class="flex justify-end gap-x-2">
          <x-button flat negative label="Cancel" wire:click="closeModal" />
          <x-button positive label="Save" wire:click="addNewDeposit" right-icon="arrow-narrow-right" />
        </div>
      </x-slot>
    </x-card>
  </x-modal>

  <x-modal wire:model.defer="deposit_deduct_modal" align="center">
    <x-card>
      <div>
        <div class="header flex space-x-1 border-b items-end justify-between py-0.5">
          <h2 class="text-lg uppercase text-gray-600 font-bold">Deposit Deduction</h2>
          <x-button.circle icon="plus" xs positive />
        </div>
        <div class="mt-3">
          <div class="space-y-4">
            <x-input label="Amount" type="number" min="0" wire:model="deduction_amount" />
          </div>
        </div>
      </div>

      <x-slot name="footer">
        <div class="flex justify-end gap-x-2">
          <x-button flat negative label="Cancel" x-on:click="close" />
          <x-button positive label="Save" wire:click="deductDeposit" right-icon="arrow-narrow-right" />
        </div>
      </x-slot>
    </x-card>
  </x-modal>

  <x-modal wire:model.defer="extend_modal" align="center" max-width="xl">
    <x-card>
      <div>
        <div class="header flex space-x-1 border-b items-end justify-between py-0.5">
          <h2 class="text-lg uppercase text-gray-600 font-bold">Extend</h2>
          <x-button.circle icon="plus" xs positive />
        </div>
        <div class="mt-3">
          <div>
            <x-native-select label="Hour" wire:model="extend_rate">
              <option selected hidden>Select Hour</option>
              @forelse ($extension_rates as $rate)
                <option class="uppercase" value="{{ $rate->id }}">{{ $rate->hour }} hours</option>
              @empty
                <option>No extension rate</option>
              @endforelse
            </x-native-select>
            <div class="mt-5 border-t">

              <h1 class="text-sm text-gray-500">TOTAL PAYABLE AMOUNT</h1>
              <h1 class="text-3xl font-bold text-red-600">&#8369;{{ number_format($total_get_rate, 2) }}</h1>
            </div>
          </div>
        </div>
      </div>

      <x-slot name="footer">
        <div class="flex justify-end s gap-x-2">
          <x-button flat negative label="Cancel" wire:click="closeModal" />
          <x-button positive label="Save" wire:click="addExtend" spinner="addExtend"
            right-icon="arrow-narrow-right" />
        </div>
      </x-slot>
    </x-card>
  </x-modal>

  <x-modal wire:model.defer="damage_modal" align="center">
    <x-card>
      <div>
        <div class="header flex space-x-1 border-b items-end justify-between py-0.5">
          <h2 class="text-lg uppercase text-gray-600 font-bold">Damage Charges</h2>
          <x-button.circle icon="plus" xs positive />
        </div>
        <div class="mt-3">
          <div class="space-y-4">
            <x-native-select label="Item" wire:model="item_id_damage">
              <option>Select Item</option>
              @forelse($items as $item)
                <option value="{{ $item->id }}">{{ $item->name }}</option>
              @empty
                <option>No Items Yet</option>
              @endforelse
            </x-native-select>
            <x-input label="Additional Amount" type="number" min="0" placeholder=""
              wire:model="additional_amount_damage" />

            <dl class="mt-8 bg-gray-300 rounded-md p-2 divide-y divide-gray-400 text-sm lg:col-span-5 lg:mt-0">
              <div class="flex items-center justify-between pb-4">
                <dt class="text-gray-600">Item Amount</dt>
                <dd class="font-medium text-gray-800">₱ {{ number_format($item_price_damage, 2, '.', ',') }}</dd>
              </div>
              <div class="flex items-center justify-between py-4">
                <dt class="text-gray-600">Additional Amount</dt>
                <dd class="font-medium text-gray-800">₱
                  {{ $additional_amount_damage == '' ? '0.00' : number_format($additional_amount_damage, 2, '.', ',') }}
                </dd>
              </div>
              <div class="flex items-center justify-between pt-4">
                <dt class="font-medium text-lg text-gray-800">Total Payable Amount</dt>
                <dd class="font-medium text-lg text-gray-900">₱ {{ number_format($total_amount_damage, 2, '.', ',') }}
                </dd>
              </div>
            </dl>

          </div>
        </div>
      </div>

      <x-slot name="footer">
        <div class="flex justify-end gap-x-2">
          <x-button flat negative label="Cancel" wire:click="closeModal" />
          <x-button positive label="Save" wire:click="addDamageCharges" right-icon="arrow-narrow-right" />
        </div>
      </x-slot>
    </x-card>
  </x-modal>

  <x-modal wire:model.defer="amenities_modal" align="center">
    <x-card>
      <div>
        <div class="header flex space-x-1 border-b items-end justify-between py-0.5">
          <h2 class="text-lg uppercase text-gray-600 font-bold">Amenities</h2>
          <x-button.circle icon="plus" xs positive />
        </div>
        <div class="mt-3">
          <div class="space-y-4">
            <x-native-select label="Item" wire:model="item_id">
              <option>Select Item</option>
              @forelse($amenities as $amenity)
                <option value="{{ $amenity->id }}">{{ $amenity->name }}</option>
              @empty
                <option>No Items Yet</option>
              @endforelse
            </x-native-select>
            <x-input label="Quantity" type="number" min="1" value="1" placeholder=""
              wire:model="item_quantity" />
            <x-input label="Additional Amount" type="number" min="0" placeholder=""
              wire:model="additional_amount" />

            <dl class="mt-8 bg-gray-300 rounded-md p-2 divide-y divide-gray-400 text-sm lg:col-span-5 lg:mt-0">
              <div class="flex items-center justify-between pb-4">
                <dt class="text-gray-600">Subtotal</dt>
                <dd class="font-medium text-gray-800">₱ {{ number_format($subtotal, 2, '.', ',') }}</dd>
              </div>
              <div class="flex items-center justify-between py-4">
                <dt class="text-gray-600">Additional Amount</dt>
                <dd class="font-medium text-gray-800">₱
                  {{ $additional_amount == '' ? '0.00' : number_format($additional_amount, 2, '.', ',') }}</dd>
              </div>
              <div class="flex items-center justify-between pt-4">
                <dt class="font-medium text-lg text-gray-800">Total Payable Amount</dt>
                <dd class="font-medium text-lg text-gray-900">₱ {{ number_format($total_amount, 2, '.', ',') }}</dd>
              </div>
            </dl>
          </div>
        </div>
      </div>

      <x-slot name="footer">
        <div class="flex justify-end gap-x-2">
          <x-button flat negative label="Cancel" wire:click="closeModal" />
          <x-button positive label="Save" wire:click="addAmenities" right-icon="arrow-narrow-right" />
        </div>
      </x-slot>
    </x-card>
  </x-modal>

  <x-modal wire:model.defer="food_beverages_modal" align="center">
    <x-card>
      <div>
        <div class="header flex space-x-1 border-b items-end justify-between py-0.5">
          <h2 class="text-lg uppercase text-gray-600 font-bold">Food and Beverages</h2>
          <x-button.circle icon="plus" xs positive />
        </div>
        <div class="mt-3">
          <div class="space-y-4">
            <x-native-select label="Item" wire:model="food_id">
              <option>Select Item</option>
              @forelse($foods as $food)
                <option value="{{ $food->id }}">{{ $food->name }}</option>
              @empty
                <option>No Items Yet</option>
              @endforelse
            </x-native-select>
            <x-input label="Price" disabled type="number" min="0" placeholder=""
              wire:model="food_price" />
            <x-input label="Quantity" type="number" min="1" value="1" placeholder=""
              wire:model="food_quantity" />

            <dl class="mt-8 bg-gray-300 rounded-md p-2 divide-y divide-gray-400 text-sm lg:col-span-5 lg:mt-0">
              <div class="flex items-center justify-between pb-4">
                <dt class="text-gray-600">Subtotal</dt>
                <dd class="font-medium text-gray-800">₱ {{ number_format($food_price, 2, '.', ',') }}</dd>
              </div>
              <div class="flex items-center justify-between pt-4">
                <dt class="font-medium text-lg text-gray-800">Total Payable Amount</dt>
                <dd class="font-medium text-lg text-gray-900">₱ {{ number_format($food_total_amount, 2, '.', ',') }}
                </dd>
              </div>
            </dl>
          </div>
        </div>
      </div>

      <x-slot name="footer">
        <div class="flex justify-end gap-x-2">
          <x-button flat negative label="Cancel" wire:click="closeModal" />
          <x-button positive label="Save" wire:click="addFood" right-icon="arrow-narrow-right" />
        </div>
      </x-slot>
    </x-card>
  </x-modal>

  <x-modal wire:model.defer="autorization_modal" align="center" max-width="md">
    <x-card>
      <div class="flex space-x-1">
        <h1 class=" text-xl font-bold text-gray-600">AUTHORIZATION CODE</h1>
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-5 h-5 fill-green-600">
          <path fill="none" d="M0 0h24v24H0z" />
          <path d="M17 14h-4.341a6 6 0 1 1 0-4H23v4h-2v4h-4v-4zM7 14a2 2 0 1 0 0-4 2 2 0 0 0 0 4z" />
        </svg>
      </div>
      <div class="mt-7">
        <input type="password" wire:model="code"
          class="w-full text-lg
      @error('code')
          border-red-500
      @enderror
        rounded-lg">
      </div>
      @error('code')
        <span class="text-sm text-red-500 mt-1">{{ $message }}</span>
      @enderror
      <div class="mt-5 flex justify-end items-center space-x-2">
        <x-button x-on:click="close" label="CANCEL" sm negative />
        <x-button label="PROCEED" sm positive wire:click="proceedTransfer" spinner="proceedTransfer"
          right-icon="arrow-right" />
      </div>

    </x-card>
  </x-modal>

  <x-modal wire:model.defer="pay_modal" max-width="lg" align="center">
    <x-card>
      <div>
        <div class="header flex space-x-1 border-b items-end justify-between py-0.5">
          <h2 class="text-lg uppercase text-gray-600 font-bold">Pay Transaction</h2>
          <x-button.circle icon="cash" xs positive />
        </div>
        <div class="mt-3">
          <div class="p-3 bg-gray-100 rounded-lg">
            <h1 class=" text-sm text-gray-500">Total Payable Amount</h1>
            <h1 class="text-3xl font-bold text-red-600">&#8369;{{ number_format($pay_transaction_amount, 2) }}</h1>
          </div>

          <div class="mt-4 flex flex-col space-y-3" x-animate>
            <x-input label="Enter Amount" wire:model="pay_amount" placeholder="" suffix="₱" />
            <x-input label="Excess Amount" wire:model="pay_excess" placeholder="" />
            @if ($pay_amount > $pay_transaction_amount)
              <div>
                <x-checkbox id="right-label" label="Save excess amount as deposit" wire:model.defer="saveAsExcess" />
              </div>
            @endif
          </div>
        </div>
      </div>

      <x-slot name="footer">
        <div class="flex justify-end gap-x-2">
          <x-button flat negative label="Cancel" wire:click="closeModal" />
          <x-button positive wire:click="addPayment" spinner="addPayment" label="Save"
            right-icon="arrow-narrow-right" />

        </div>
      </x-slot>
    </x-card>
  </x-modal>


  <x-modal  max-width="lg" align="center">
    <x-card>
      <div>
        <div class="header flex space-x-1 border-b items-end justify-between py-0.5">
          <h2 class="text-lg uppercase text-gray-600 font-bold">Check Out Reminders</h2>
        </div>
        <div class="mt-3">
          <div class="p-3 flex justify-center items-center bg-gray-100 rounded-lg align-middle">
            <span>{{ $reminders[$reminderIndex] }}</span>
          </div>
          @if ($reminderIndex == 0)
          <div class="flex justify-center space-x-5 p-4">
            <x-button positive wire:click="incrementReminderIndex" label="Yes" icon="check" />
            <x-button negative wire:click="decrementReminderIndex" label="No" icon="x" />
          </div>
          @elseif($reminderIndex == 1)
          <div class="flex justify-center space-x-5 p-4">
            <x-button negative wire:click="chargeForDamages" label="Charge for damages" icon="calculator" />
            </div>
          @endif
        </div>
      </div>

      <x-slot name="footer">
        <div class="flex justify-between">
          <div>
            @if ($reminderIndex != 0)
              <x-button slate wire:click="decrementReminderIndex" label="Back" icon="arrow-narrow-left" />
            @endif
          </div>
          <div>
            @if ($reminderIndex > 0 && $reminderIndex != array_key_last($reminders))
              <x-button slate wire:click="incrementReminderIndex" label="Next" right-icon="arrow-narrow-right" />
            @elseif($reminderIndex == 3)
            <x-button positive label="Claim all deposit" icon="calculator" />
            @elseif($reminderIndex == array_key_last($reminders))
              <x-button positive wire:click="proceedCheckout" label="Proceed" right-icon="arrow-narrow-right" />
            @endif
          </div>
        </div>
      </x-slot>
    </x-card>
  </x-modal>




  <x-modal wire:model.defer="payWithDeposit_modal" max-width="lg" align="center">
    <x-card>
      <div>
        <div class="header flex space-x-1 border-b items-end justify-between py-0.5">
          <h2 class="text-lg uppercase text-gray-600 font-bold">Pay with Deposit Transaction</h2>
          <x-button.circle icon="cash" xs positive />
        </div>
        <div class="mt-3">
          <div class="p-3 bg-gray-100 rounded-lg">
            <div>
              <h1 class=" text-sm text-gray-500">Total Balance Deposit</h1>
              <h1 class="text-3xl font-bold text-gray-600">&#8369;{{ number_format($render_deposit, 2) }}</h1>
              @if ($pay_transaction_amount > $render_deposit)
                <span class="text-sm text-red-500 mt-1">Insufficient Deposit</span>
              @endif
            </div>
            <div class="pt-5 border-t">
              <h1 class=" text-sm text-gray-500">Total Payable Amount</h1>
              <h1 class="text-3xl font-bold text-red-600">&#8369;{{ number_format($pay_transaction_amount, 2) }}</h1>
            </div>
          </div>
        </div>
      </div>

      <x-slot name="footer">
        <div class="flex justify-end gap-x-2">
          <x-button flat negative label="Cancel" wire:click="closeModal" />
          @if ($pay_transaction_id == null)
            <x-button positive wire:click="addAllPaymentWithDeposit" spinner="addPaymenWithDeposit"
              label="Pay with Deposit" right-icon="arrow-narrow-right" />
          @else
            <x-button positive wire:click="addPaymentWithDeposit" spinner="addPaymenWithDeposit"
              label="Pay with Deposit" right-icon="arrow-narrow-right" />
          @endif

        </div>
      </x-slot>
    </x-card>
  </x-modal>

  <div x-cloak x-show="open" class="fixed top-0 left-0 w-full h-full bg-gray-500 bg-opacity-50 z-50">
    <div class="mx-auto max-w-3xl p-8 bg-white rounded-lg shadow-xl absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2">
        <div class="text-center">
            <h3 class="text-lg font-semibold mb-4">Modal Title</h3>
        </div>
        <div>
            //content

        </div>
        <div class="flex justify-end mt-4">
            <button class="text-gray-600 hover:text-gray-800 font-semibold text-sm mr-4" wire:click="closeModal">Cancel</button>
            <button class="bg-blue-500 hover:bg-blue-700 text-white font-semibold text-sm py-2 px-4 rounded" @click="submit()">Submit</button>
        </div>
    </div>
</div>


  <x-modal wire:model.defer="override_modal" max-width="lg" align="center">
    <x-card>
      <div>
        <div class="header flex space-x-1 border-b items-end justify-between py-0.5">
          <h2 class="text-lg uppercase text-gray-600 font-bold">Override Transaction</h2>
          <x-button.circle icon="cash" xs positive />
        </div>
        <div class="mt-3">
          <div class="p-3 bg-gray-100 rounded-lg">
            <h1 class=" text-sm text-gray-500">Total Payable Amount</h1>
            <h1 class="text-3xl font-bold text-red-600">&#8369;{{ number_format($pay_transaction_amount, 2) }}</h1>
          </div>

          <div class="mt-4 flex flex-col space-y-3" x-animate>
            <x-input label="Enter Amount" wire:model.defer="override_amount" placeholder="" suffix="₱" />
          </div>
        </div>
      </div>

      <x-slot name="footer">
        <div class="flex justify-end gap-x-2">
          <x-button flat negative label="Cancel" wire:click="closeModal" />
          <x-button negative wire:click="addOverride" spinner="addOverride" label="Override"
            right-icon="arrow-narrow-right" />

        </div>
      </x-slot>
    </x-card>
  </x-modal>

</div>
