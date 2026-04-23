<div x-data="{ open: @entangle('reminders_modal'), close: @entangle('reminders_modal') }">
  <div class="lg:grid  lg:grid-cols-12 lg:gap-8 ">
    <div class="hidden lg:col-span-3 lg:block xl:col-span-2">
      <nav aria-label="Sidebar" class="sticky top-6 divide-y divide-gray-300">
        <div>
          <li class="col-span-1 flex flex-col divide-y divide-gray-200 rounded-lg bg-white text-center border">
            <div class="flex flex-1 flex-col py-3">
              <div class="flex justify-center items-center">
                <x-avatar xl border="border border-gray-800 bg-gray-600" label="G"/>
                {{-- <x-avatar xl border="border border-gray-800 bg-gray-600" src="{{ asset('images/user-gray.png') }}" /> --}}
              </div>
              <h3 class="mt-3 text-sm font-bold uppercase text-gray-700">{{ $guest->name }}</h3>
              <dl class="mt-1 flex flex-grow flex-col justify-between">
                <dt class="sr-only">Title</dt>
                <dd class="text-sm text-gray-500 flex space-x-1 justify-center">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-5 w-5 fill-gray-600">
                    <path fill="none" d="M0 0h24v24H0z" />
                    <path
                      d="M16 17v-1h-3v-3h3v2h2v2h-1v2h-2v2h-2v-3h2v-1h1zm5 4h-4v-2h2v-2h2v4zM3 3h8v8H3V3zm2 2v4h4V5H5zm8-2h8v8h-8V3zm2 2v4h4V5h-4zM3 13h8v8H3v-8zm2 2v4h4v-4H5zm13-2h3v2h-3v-2zM6 6h2v2H6V6zm0 10h2v2H6v-2zM16 6h2v2h-2V6z" />
                  </svg>
                  <span class="font-medium">{{ $guest->qr_code }}</span>
                </dd>
              </dl>
            </div>
          </li>
          <div class="">
            <div class="bg-gradient-to-br shadow-md from-gray-300 via-gray-200 to-gray-100 mt-2 rounded-lg p-3">
              <div class="header">
                <h3 class="text-lg font-bold text-gray-700 uppercase">Check-In Details</h3>

                <div class="mt-2 border-b border-gray-300">
                  <h1 class="text-xs text-gray-500">Room Number</h1>
                  <h1 class="font-bold text-gray-700">ROOM #{{ $guest->room->number }}</h1>
                </div>
                <div class="mt-2 border-b border-gray-300">
                    <h1 class="text-xs text-gray-500">Room Type</h1>
                    <h1 class="font-bold text-gray-700">{{ $guest->room->type->name }}</h1>
                  </div>
                <div class="mt-2 border-b border-gray-300">
                  <h1 class="text-xs text-gray-500">Initial Check In Hours</h1>
                  <h1 class="font-bold text-gray-700">{{ $guest->is_long_stay ? ($guest->number_of_days * $guest->rates->stayingHour->number) : $guest->rates->stayingHour->number }} Hours</h1>
                </div>
                @if ($guest->stayExtensions->count() > 0)
                <div class="mt-2 border-b border-gray-300">
                  <h1 class="text-xs text-gray-500">Total Extension Hours</h1>
                  <h1 class="font-bold text-gray-700">{{ $guest->stayExtensions->sum('hours') }} Hours</h1>
                </div>
                @endif
                <div class="mt-2 border-b border-gray-300">
                  <h1 class="text-xs text-gray-500">Total Staying Hours</h1>
                  <h1 class="font-bold text-gray-700">
                    {{ $guest->is_long_stay ? ($guest->number_of_days * $guest->rates->stayingHour->number + $guest->stayExtensions->sum('hours')) : ($guest->rates->stayingHour->number + $guest->stayExtensions->sum('hours')) }} Hours</h1>
                </div>
                <div class="mt-2 border-b border-gray-300">
                  <h1 class="text-xs text-gray-500">Time Remaining</h1>
                  <h1 class="font-bold text-gray-700">
                  @php
                      $check_out_date = Carbon\Carbon::parse($guest->checkinDetail->check_out_at ?? null);
                      $now = Carbon\Carbon::now();
                      $grace_end = $check_out_date?->copy()->addMinutes(15);
                  @endphp

                  @if ($check_out_date)
                      @if ($now->gt($grace_end))
                          <span class="inline-flex items-center rounded-md py-1 text-sm font-medium text-red-700">
                              Time Expired!
                          </span>

                      @elseif ($now->gt($check_out_date))
                          <div class="text-yellow-600">
                              <span class="inline-flex items-center rounded-md py-1 text-sm font-medium text-yellow-700">
                                  Grace Period:
                              </span>
                              <x-countdown :expires="$grace_end" class="text-yellow-600">
                                  <span x-text="timer.days">{{ $component->days() }}</span>d :
                                  <span x-text="timer.hours">{{ $component->hours() }}</span>h :
                                  <span x-text="timer.minutes">{{ $component->minutes() }}</span>m :
                                  <span x-text="timer.seconds">{{ $component->seconds() }}</span>s
                              </x-countdown>
                          </div>

                      @else
                          <x-countdown :expires="$check_out_date" class="text-red-600">
                              <span x-text="timer.days">{{ $component->days() }}</span>d :
                              <span x-text="timer.hours">{{ $component->hours() }}</span>h :
                              <span x-text="timer.minutes">{{ $component->minutes() }}</span>m :
                              <span x-text="timer.seconds">{{ $component->seconds() }}</span>s
                          </x-countdown>
                      @endif
                  @endif
                  </h1>
                </div>
                <div class="mt-2 border-b border-gray-300">
                  <h1 class="text-xs text-gray-500">Check In Date</h1>
                  <h1 class="font-medium text-gray-700">
                    {{ Carbon\Carbon::parse($guest->checkinDetail->check_in_at)->format('F d, Y h:i A') }}</h1>
                </div>
                <div class="mt-2 border-gray-300">
                  <h1 class="text-xs text-gray-500">Expected Check Out Date</h1>
                  <h1 class="font-medium text-gray-700">
                    {{ Carbon\Carbon::parse($guest->checkinDetail->check_out_at)->format('F d, Y h:i A') }}</h1>
                </div>
              </div>
            </div>
            <div class="mt-4">
                @if (!$guest->transactions->whereNotIn('transaction_type_id', [1, 2])->count() > 0)
                <x-button wire:ignore wire:click="cancelTransaction" class="w-full" label="Cancel Transaction" icon="x" negative />
                @endif
            </div>
          </div>
        </div>
      </nav>
    </div>
    <main class="lg:col-span-9 xl:col-span-7">
      <div class="grid grid-cols-6 gap-x-2 border rounded-lg p-4 mb-4">
        @php
          $grace_end = $check_out_date?->copy()->addMinutes(15);
        @endphp

        <x-button :disabled="$grace_end < Carbon\Carbon::now()"  label="Transfer Room" sm blue right-icon="external-link" wire:click="redirectToTransferRoom" />
        {{-- <x-button :disabled="$check_out_date < Carbon\Carbon::now()" label="Transfer Room" sm blue right-icon="external-link" wire:click="$set('transfer_modal', true)" /> --}}

        {{-- <x-button :disabled="$check_out_date < Carbon\Carbon::now()" label="Extend" sm blue right-icon="external-link" wire:click=" $set('extend_modal', true)" /> --}}
        {{-- <x-button label="Extend" sm blue right-icon="external-link" wire:click=" $set('extend_modal', true)" /> --}}
        <x-button label="Extend" sm blue right-icon="external-link" wire:click="redirectToExtendGuest" />
        <x-button :disabled="$grace_end < Carbon\Carbon::now()" label="Damage Charges" sm blue right-icon="external-link" wire:click=" $set('damage_modal', true)" />
        <x-button :disabled="$grace_end < Carbon\Carbon::now()" label="Amenities" sm blue right-icon="external-link" wire:click=" $set('amenities_modal', true)" />
        <x-button :disabled="$grace_end < Carbon\Carbon::now()" label="Food and Beverages" sm blue right-icon="external-link"
          wire:click=" $set('food_beverages_modal', true)" />
        <x-button :disabled="$grace_end < Carbon\Carbon::now()" label="Deposits" sm blue right-icon="external-link" wire:click=" $set('deposit_modal', true)" />
      </div>

      <div class="border  p-4 rounded-xl">
        <div class="lg:flex lg:items-center lg:justify-between border-b">
          <div class="min-w-0 flex-1 ">
            <div class="flex justify-between mb-1">
              <div>
                <h2 class="text-2xl font-bold leading-7 text-gray-900 s sm:truncate sm:text-3xl sm:tracking-tight">GUEST
                  TRANSACTIONS</h2>
              </div>
              <div class="flex space-x-2">
                <x-button label="Back" icon="reply" negative href="{{ route('frontdesk.room-monitoring') }}" />
                <x-button label="Check Out" right-icon="arrow-right" positive wire:click="checkOut" />
              </div>
            </div>
            {{-- <div class="my-1 mt-3 flex flex-col  sm:flex-row sm:flex-wrap sm:space-x-6">
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
            </div> --}}
          </div>
        </div>
        {{-- wire poll problem here wire:poll.2s--}}
        <div class="px-4 mt-5 sm:px-6 lg:px-8 h-[30rem] overflow-y-auto" wire:poll.2s>
          <div class="mt flex flex-col">
            <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-6 lg:-mx-8">
              <div class="inline-block min-w-full py-2 align-middle ">
                <div class="overflow-hidden shadow ">
                  <table class="min-w-full">
                    <thead class="bg-blue-500">
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
                      @forelse ($transactions as $type)
                        <tr class="border-t border-gray-200">
                          <th colspan="5" scope="colgroup"
                            class="bg-gray-100 px-4 py-2 text-left text-sm font-semibold text-green-600 uppercase sm:px-6">
                            {{ $type->name }}
                          </th>
                        </tr>
                        @foreach ($type->transactions->where('guest_id', $guest_id) as $transaction)
                          <tr class="border-t border-gray-300">
                            <td class="whitespace-nowrap py-2 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6">
                            </td>
                            <td class=" px-3 py-2 text-sm text-gray-600 ">
                              <p> {{ $transaction->remarks }}</p>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 text-sm text-gray-600 ">
                              @if($guest->has_discount && $transaction->transaction_type_id == 1)
                              ₱{{ number_format(($transaction->payable_amount - $guest->discount_amount), 2) }}
                              @else
                              ₱{{ number_format($transaction->payable_amount, 2) }}
                              @endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 text-sm text-gray-600 ">
                              {{ Carbon\Carbon::parse($transaction->updated_at)->format('F d, Y h:i A') }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 text-sm text-gray-600 ">
                              @if ($transaction->paid_at == null && $transaction->payable_amount > 0)
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
                              @elseif($transaction->transaction_type_id == 7 && $transaction->paid_at == null)
                                {{ Carbon\Carbon::parse($transaction->updated_at)->format('F d, Y h:i A') }}
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
                          <td class="whitespace-nowrap px-3 py-2 text-sm font-bold text-gray-600 ">
                            &#8369;{{
                                number_format(
                                    $type->transactions
                                        ->where('guest_id', $guest_id)
                                        ->map(function ($transaction) use ($guest) {
                                            if ($guest->has_discount && $transaction->transaction_type_id == 1) {
                                                return $transaction->payable_amount - $guest->discount_amount; // example: 20% discount
                                            }
                                            return $transaction->payable_amount;
                                        })
                                        ->sum(),
                                2)
                                }}
                          </td>
                          <td class="whitespace-nowrap py-2 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6">
                          </td>
                        </tr>
                      @empty
                      @endforelse



                      </td>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>


    </main>
    <aside  wire:ignore class="hidden xl:col-span-3 xl:block">
      <div class="sticky top-6 space-y-4">
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
                  <div wire:key="{{ $loop->index }}" class="flex items-center justify-between py-2">
                    <dt class="text-gray-600">{{ $bill->transaction_type->name }}</dt>
                    @if($guest->has_discount && $bill->transaction_type_id == 1)
                    <dd class="font-medium text-gray-900">₱ {{ number_format($bill->total_payable_amount - $guest->discount_amount, 2) }}</dd>
                    @else
                    <dd class="font-medium text-gray-900">₱ {{ number_format(($bill->total_payable_amount), 2) }}</dd>
                    @endif
                  </div>
                @endif
              @endforeach
              @php
                // $total_paid = $transaction_bills_paid
                //     ->filter(function ($bill) {
                //         return $bill->transaction_type->name != 'Deposit' && $bill->transaction_type->name != 'Cashout';
                //     })
                //     ->sum('total_payable_amount');
                $total_paid = $transaction_bills_paid
    ->filter(function ($bill) {
        return $bill->transaction_type->name != 'Deposit' 
            && $bill->transaction_type->name != 'Cashout';
    })
    ->map(function ($bill) use ($guest) {
        $amount = $bill->total_payable_amount;

        if ($guest->has_discount && $bill->transaction_type_id == 1) {
            $amount = $amount - $guest->discount_amount; // example 20% discount
        }

        return $amount;
    })
    ->sum();
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
                <dt class="font-medium text-gray-900 text-md">Total Payable Amount</dt>
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
                <dt class="text-gray-600">Client Deposits</dt>
                <dd class="font-medium text-gray-900">₱{{ number_format($deposit_except_remote_and_key, 2) }}</dd>
              </div>
              <div class="flex items-center justify-between py-2">
                <dt class="text-gray-600">Total Deposit</dt>
                <dd class="font-medium text-gray-900">₱{{ number_format(($deposit_remote_and_key + $deposit_except_remote_and_key), 2) }}</dd>
              </div>

              <div class="flex items-center justify-between py-2">
                <dt class="text-red-500">Total Deduction</dt>
                <dd class="font-medium text-red-500">₱{{ number_format($check_in_details->total_deduction, 2) }}</dd>
              </div>

              <div class="flex items-center justify-between py-2">
                <dt class="font-medium text-gray-900">Available Deposit</dt>
                <dd class="font-medium text-indigo-600">
                  ₱{{ number_format(($deposit_remote_and_key + $deposit_except_remote_and_key) - $check_in_details->total_deduction, 2) }}</dd>
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

            @if ($deposit_except_remote_and_key - ($check_in_details->total_deduction ?? 0) > 0)
              <div class="flex items-center space-x-2">
                <dd class="font-medium text-gray-800">₱
                  {{ number_format($deposit_except_remote_and_key - $check_in_details->total_deduction, 2, '.', ',') }}
                </dd>
                <x-button wire:click="$set('deposit_deduct_modal', true)" amber icon="cash" label="Cash Out" />
              </div>
            @else
              <div class="flex items-center space-x-2">
                <dd class="font-medium text-gray-800">₱
                  {{ number_format($deposit_except_remote_and_key - ($check_in_details->total_deduction ?? 0), 2, '.', ',') }}
                </dd>
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
        <div class="flex justify-between gap-x-2">
          <x-button negative label="Cancel" wire:click="$set('deposit_modal', false)" />
          <div class="flex space-x-3">
              <x-button positive label="Save" wire:click="addNewDeposit" spinner="addNewDeposit"/>
          </div>
        </div>
      </x-slot>
    </x-card>
  </x-modal>

  <x-modal wire:model.defer="deposit_deduct_modal" align="center" max-width="lg">
    <x-card>
      <div>
        <div class="header flex space-x-1 border-b items-end justify-between py-0.5">
          <h2 class="text-lg uppercase text-gray-600 font-bold">Cash Out</h2>
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
          <x-button positive label="Save" wire:click="deductDeposit"   />
        </div>
      </x-slot>
    </x-card>
  </x-modal>

  <x-modal wire:model.defer="extend_modal" align="center" max-width="lg">
    <x-card>
      <div>
        <div class="header flex space-x-1 border-b items-end justify-between py-0.5">
          <h2 class="text-lg uppercase text-gray-600 font-bold">Extend</h2>
          <x-button.circle icon="plus" xs positive />
        </div>
        <div class="mt-3">
          <div>
            <x-native-select label="Add Time" wire:model="extend_rate">
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
        <div class="flex justify-between s gap-x-2">
          <x-button negative label="Cancel" x-on:click="close" />
            <div class="flex space-x-3">
                <x-button cyan label="Save & Pay" wire:click="savePayExtend" spinner="savePayExtend"/>
                @if ($deposit_except_remote_and_key - $check_in_details->total_deduction >= $transaction->payable_amount)
                <x-button amber label="Save & Pay With Deposit" wire:click="savePayDepositExtend" spinner="savePayDepositExtend"/>
                @endif
                <x-button positive label="Save" wire:click="saveExtend" spinner="saveExtend"/>
            </div>
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
                {{-- <option>No Items Yet</option> --}}
              @endforelse
            </x-native-select>
            <x-input label="Price" disabled type="number" min="0" placeholder=""
              wire:model="food_price" />
              <x-input label="Number of stock / serving left" disabled type="number" min="0" placeholder=""
              wire:model="food_number_of_stock" />
            <x-input label="Quantity" type="number" min="1" max="{{$food_number_of_stock}}" value="1" placeholder=""
              wire:model="food_quantity" />

            <dl class="mt-8 bg-gray-300 rounded-md p-2 divide-y divide-gray-400 text-sm lg:col-span-5 lg:mt-0">
              <div class="flex items-center justify-between pb-4">
                <dt class="text-gray-600">Subtotal</dt>
                <dd class="font-medium text-gray-800">₱{{ number_format($food_subtotal, 2, '.', ',') }}</dd>
              </div>
              <div class="flex items-center justify-between pt-4">
                <dt class="font-bold text-lg text-gray-800">Total Payable Amount</dt>
                <dd class="font-bold text-lg text-red-600">₱{{ number_format($food_total_amount, 2, '.', ',') }}
                </dd>
              </div>
            </dl>
          </div>
        </div>
      </div>

      <x-slot name="footer">
        <div class="flex justify-between gap-x-2">
          <x-button negative label="Cancel" x-on:click="close" />
           <div class="flex space-x-3">
               <x-button cyan label="Save & Pay" wire:click="confirmFoodPay" spinner="confirmFoodPay"/>
                @if ($deposit_except_remote_and_key - $check_in_details->total_deduction >= $transaction->payable_amount)
                <x-button amber label="Save & Pay With Deposit" wire:click="confirmFoodPayDeposit" spinner="confirmFoodPayDeposit"/>
                @endif
               <x-button positive label="Save" wire:click="confirmFood" spinner="confirmFood"/>
           </div>
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
              <option selected hidden>Select Item</option>
              @forelse($amenities as $amenity)
                <option value="{{ $amenity->id }}">{{ $amenity->name }}</option>
              @empty
                <option>No Items Yet</option>
              @endforelse
            </x-native-select>
            <x-input label="Quantity" type="number" min="1" value="1" placeholder=""
              wire:model="item_quantity" />
            <x-input label="Additional Amount" disabled type="number" min="0" placeholder=""
              wire:model="additional_amount" />

            <dl class="mt-8 bg-gray-300 rounded-md p-3 divide-y divide-gray-400 text-sm lg:col-span-5 lg:mt-0">
              <div class="flex items-center justify-between pb-4">
                <dt class="text-gray-600">Subtotal</dt>
                <dd class="font-medium text-gray-800">₱ {{ number_format($subtotal, 2, '.', ',') }}</dd>
              </div>
              <div class="flex items-center justify-between py-4">
                <dt class="text-gray-600">Additional Amount</dt>
                <dd class="font-medium text-gray-800">₱
                  {{ $additional_amount == '' ? '0.00' : number_format($additional_amount, 2, '.', ',') }}</dd>
                  {{-- {{ number_format($additional_amount, 2, '.', ',') }}</dd> --}}
              </div>
              <div class="flex items-center justify-between pt-4">
                <dt class="font-bold text-lg text-gray-800">Total Payable Amount</dt>
                <dd class="font-bold text-lg text-red-600">₱ {{ number_format($total_amount, 2, '.', ',') }}</dd>
              </div>
            </dl>
          </div>
        </div>
      </div>

      <x-slot name="footer">
        <div class="flex justify-between gap-x-2">
          <x-button negative label="Cancel" wire:click="$set('amenities_modal', false)" />
          <div class="flex space-x-3">
            <x-button cyan label="Save & Pay" wire:click="confirmAmenitiesPay" spinner="confirmAmenitiesPay"/>
            @if ($deposit_except_remote_and_key - $check_in_details->total_deduction >= $transaction->payable_amount)
            <x-button amber label="Save & Pay With Deposit" wire:click="confirmAmenitiesPayDeposit" spinner="confirmAmenitiesPayDeposit"/>
            @endif
          <x-button positive label="Save" wire:click="confirmAmenities" spinner="confirmAmenities"/>
          </div>
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
              <option selected hidden>Select Item</option>
              @forelse($items as $item)
                <option value="{{ $item->id }}">{{ $item->name }}</option>
              @empty
                <option>No Items Yet</option>
              @endforelse
            </x-native-select>
            <x-input label="Additional Amount" type="number" min="0" placeholder=""
              wire:model="additional_amount_damage" disabled />

            <dl class="mt-8 bg-gray-300 rounded-md p-3 divide-y divide-gray-400 text-sm lg:col-span-5 lg:mt-0">
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
                <dt class="font-bold text-lg text-gray-800">Total Payable Amount</dt>
                <dd class="font-bold text-lg text-red-600">₱ {{ number_format($total_amount_damage, 2, '.', ',') }}
                </dd>
              </div>
            </dl>

          </div>
        </div>
      </div>

      <x-slot name="footer">
        <div class="flex justify-between gap-x-2">
          <x-button negative label="Cancel" x-on:click="close" />
          <div class="flex space-x-3">
              <x-button cyan label="Save & Pay" wire:click="confirmDamageChargesPay" spinner="confirmDamageChargesPay"/>
                @if ($deposit_except_remote_and_key - $check_in_details->total_deduction >= $transaction->payable_amount)
                <x-button amber label="Save & Pay With Deposit" wire:click="confirmDamageChargesPayDeposit" spinner="confirmDamageChargesPayDeposit"/>
                @endif
              <x-button positive label="Save" wire:click="confirmDamageCharges" spinner="confirmDamageCharges"/>
          </div>
        </div>
      </x-slot>
    </x-card>
  </x-modal>

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

            @if ($rooms_count > 0 && $has_rate == true)
                @if (count($rooms) === 0)
                <x-native-select disabled label="Room">
                    <option selected>No Room Available</option>
                  </x-native-select>
                @else
                <x-native-select label="Room" wire:model="room_id">
                    <option selected hidden>Select Room</option>
                    @forelse ($rooms as $room)
                      <option value="{{ $room->id }}">{{ $room->numberWithFormat() }}</option>
                    @empty
                      <option>No Room Available</option>
                    @endforelse
                  </x-native-select>
                  <x-native-select label="Old Room Status" wire:model.defer="old_status">
                    <option selected hidden>Select Status</option>
                    <option value="Uncleaned">Uncleaned</option>
                    <option value="Cleaned">Cleaned</option>

                  </x-native-select>
                @endif


              <div class="col-span-2">
                <x-native-select label="Reason" wire:model="reason_id">
                <option selected hidden>Select Reason</option>
                @foreach ($transfer_reason as $reason)
                    <option value="{{ $reason->id }}">{{ $reason->reason }}</option>
                @endforeach
                </x-native-select>
                {{-- <x-textarea label="Reason" wire:model.defer="reason" placeholder="write reason of transfer" /> --}}
              </div>
              <div class="col-span-2 bg-gray-200 rounded-lg p-3">
                <dl class=" space-y-3 text-sm font-medium text-gray-500">
                  <div class="flex border-b items-center justify-between">
                    <dt>Previous Room Amounts</dt>
                    <dd class="text-gray-700 ">&#8369;{{ number_format($check_in_details->static_room_amount, 2) }}
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

                      @if ($check_in_details->static_room_amount > $new_room->amount)
                        {{ number_format($check_in_details->static_room_amount - $new_room->amount, 2) }}
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
            @elseif ($rooms_count > 0 && $has_rate == false)
            @php
                 $guestss = App\Models\Guest::where('id', $this->guest_id)->first();
                 $hours = $guestss->checkInDetail->hours_stayed;
            @endphp
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
                    <p class="text-sm font-medium text-red-700">No Available {{$hours}} hour rate for this type.
                    </p>
                  </div>
                </div>
              </div>
            @elseif($rooms_count <= 0)
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
        <div class="flex justify-between gap-x-2">
          <x-button negative label="Cancel" x-on:click="close" />
          @if ($guest->transactions->where('transaction_type_id', 6)->count() > 0)
            <div class="rounded-md bg-red-50 p-4">
              <div class="flex">
                <div class="flex-shrink-0">
                  <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd"
                      d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z"
                      clip-rule="evenodd" />
                  </svg>
                </div>
                <div class="ml-3">
                  <h3 class="text-sm font-medium text-red-800">Transfer Room is Not Allowed because the guest already
                    extend his/her stay.</h3>

                </div>
              </div>
            </div>
          @else
          <div>
            <x-button cyan label="Save & Pay" wire:click="savePayTransfer" spinner="savePayTransfer"/>
            @if ($deposit_except_remote_and_key - $check_in_details->total_deduction >= $transaction->payable_amount)
            <x-button amber label="Save & Pay With Deposit" wire:click="savePayDepositTransfer" spinner="savePayDepositTransfer"/>
            @endif
            <x-button positive label="Save" wire:click="saveTransfer" spinner="saveTransfer"/>
          </div>
          @endif
        </div>
      </x-slot>
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
            <div>
                <x-input label="Enter Amount" wire:model="pay_amount" placeholder="" suffix="₱" />
                @error('pay_amount')@enderror
            </div>
            <x-input disabled label="Excess Amount" wire:model="pay_excess" placeholder="" />
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
          <x-button flat negative label="Cancel" x-on:click="close" />
          <x-button positive wire:click="addPayment" spinner="addPayment" label="Save"
              />

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
              <h1 class=" text-sm text-gray-500">Total Balance Deposit (Excluding TV Remote & Room Key)</h1>
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
          <x-button flat negative label="Cancel" x-on:click="close" />
          @if ($pay_transaction_id == null)
            <x-button positive wire:click="addAllPaymentWithDeposit" spinner="addPaymenWithDeposit"
              label="Pay with Deposit"   />
          @else
            <x-button positive wire:click="addPaymentWithDeposit" spinner="addPaymenWithDeposit"
              label="Pay with Deposit"   />
          @endif

        </div>
      </x-slot>
    </x-card>
  </x-modal>
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
          <x-button flat negative label="Cancel" x-on:click="close" />
          <x-button negative wire:click="addOverride" spinner="addOverride" label="Override"
              />

        </div>
      </x-slot>
    </x-card>
  </x-modal>

  <div x-cloak x-show="{{ $reminders_modal ? 'open' : 'close' }}" class="relative z-10"
    aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <!--
      Background backdrop, show/hide based on modal state.

      Entering: "ease-out duration-300"
        From: "opacity-0"
        To: "opacity-100"
      Leaving: "ease-in duration-200"
        From: "opacity-100"
        To: "opacity-0"
    -->
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>

    <div class="fixed inset-0 z-10 overflow-y-auto">
      <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
        <!--
          Modal panel, show/hide based on modal state.

          Entering: "ease-out duration-300"
            From: "opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            To: "opacity-100 translate-y-0 sm:scale-100"
          Leaving: "ease-in duration-200"
            From: "opacity-100 translate-y-0 sm:scale-100"
            To: "opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        -->
        <div
          class="relative transform overflow-hidden rounded-lg bg-white px-4 pt-5 pb-4 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6">
          <div>
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-green-100">
              <!-- Heroicon name: outline/check -->
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                stroke="currentColor" class="w-6 h-6">
                <path stroke-linecap="round" stroke-linejoin="round"
                  d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 01-.825-.242m9.345-8.334a2.126 2.126 0 00-.476-.095 48.64 48.64 0 00-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0011.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155" />
              </svg>
            </div>
            <div class="mt-3 text-center sm:mt-5">
              <h3 class="text-lg font-medium leading-6 text-gray-900" id="modal-title">Checkout Reminders</h3>
              <div class="mt-2">
                <p class="text-sm text-gray-500">
                  @if ($reminderIndex == 3)
                    @if ($is_checkout && $deposit_except_remote_and_key != 0)
                      <span>Claimable Deposit (Including TV Remote & Room Key ) :
                        &#8369;{{ number_format($deposit_remote_and_key + ($deposit_except_remote_and_key - $check_in_details->total_deduction), 2) }}</span>
                    @else
                      <span>No Claimable Deposit</span>
                        {{-- &#8369;{{ number_format($deposit_except_remote_and_key - $check_in_details->total_deduction, 2) }}</span> --}}
                    @endif
                  @else
                    <span>{{ $reminders[$reminderIndex] }}</span>
                  @endif
                </p>
              </div>
              <div class="mt-3">
                @if ($reminderIndex == 0)
                  <div class="flex justify-center space-x-5 p-4">
                    <x-button spinner="room_and_key_available" positive wire:click="room_and_key_available"
                      label="Yes" />
                    <x-button spinner="room_and_key_unavailable" negative wire:click="room_and_key_unavailable"
                      label="No" />
                  </div>
                @elseif($reminderIndex == 1)
                  <div class="flex justify-center space-x-5 p-4">
                    <x-button negative wire:click="chargeForDamages" label="Charge for damages" />
                  </div>
                @endif
              </div>
              <div class="flex justify-between">
                <div>
                  @if ($reminderIndex != 0)
                    <x-button slate wire:click="decrementReminderIndex" label="Back"/>
                  @endif
                </div>
                <div>
                  @if ($reminderIndex > 0 && $reminderIndex < 3)
                    <x-button slate wire:click="incrementReminderIndex" label="Next"
                        />
                  @elseif($reminderIndex == 3)
                    @if ($is_checkout || ($deposit_except_remote_and_key - $check_in_details->total_deduction) > 0)
                      <x-button wire:click="claimAll" positive label="Claim all deposit" />
                    @else
                      <x-button slate wire:click="incrementReminderIndex" label="Next"
                          />
                    @endif
                  @elseif($reminderIndex == 4)
                    <x-button positive wire:click="proceedCheckout" label="Proceed"
                        />
                  @endif
                </div>
              </div>
            </div>
          </div>
          <div class="mt-5 sm:mt-6 sm:grid sm:grid-flow-row-dense sm:grid-cols-2 sm:gap-3">
            <x-button label="Cancel" spinner="closeModal" wire:click="closeModal"
              class="mt-3 inline-flex w-full justify-center rounded-md border col-span-2 border-gray-300 bg-white px-4 py-2 text-base font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:col-start-1 sm:mt-0 sm:text-sm" />
          </div>
        </div>
      </div>
    </div>
  </div>
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
        @if ($authorization_type == 'override')
          <x-button wire:click="proceedOverride" label="Proceed Override" sm positive />
        @else
          <x-button label="PROCEED" sm positive wire:click="proceedTransfer" spinner="proceedTransfer" />
        @endif
      </div>

    </x-card>
  </x-modal>

  {{-- Deposit Summary Modal --}}

    <x-modal wire:model.defer="deposit_summary_modal" align="center" max-width="md">
        <x-card>
        <div class="flex space-x-1">
            <h1 class=" text-xl font-bold text-gray-600">DEPOSIT SUMMARY</h1>
        </div>
        <div class="mt-7">
            <div class="space-y-4">
                <div class="flex justify-between items-center">
                    <span class="text-gray-600 text-sm">Deposit From Check-In (Room Key & TV Remote)</span>
                    <span class="text-gray-800 font-bold">₱{{ number_format($deposit_remote_and_key, 2, '.', ',') }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-600 text-sm">Excess Amount</span>
                    <span class="text-gray-800 font-bold">₱{{ number_format($deposit_except_remote_and_key, 2, '.', ',') }}</span>
                </div>
                <hr class="my-2 border-gray-300">
                <div class="flex justify-between items-center">
                    <span class="text-gray-600 text-sm">Total Claimable Deposit</span>
                    <span class="text-gray-800 font-bold">₱{{ number_format($deposit_remote_and_key + $deposit_except_remote_and_key, 2) }}</span>
                </div>
            </div>
        </div>
        <div class="mt-5 flex justify-end items-center space-x-2">
            <x-button x-on:click="close" label="CLOSE" sm negative />
            <x-button label="PROCEED" sm positive wire:click="proceedAuthorization" spinner="proceedAuthorization" />
        </div>
        </x-card>
    </x-modal>

  {{-- Cancel Override Request Modal --}}
  <x-modal wire:model.defer="cancel_override_modal" align="center" max-width="md">
    <x-card>
      <div class="bg-red-600 -mx-4 -mt-4 px-4 py-3 rounded-t-lg mb-4">
        <h1 class="text-lg font-bold text-white">REVIEW OVERRIDE</h1>
        <p class="text-red-100 text-sm">CANCEL TRANSACTION</p>
      </div>

      <div class="space-y-4">
        {{-- Guest and Room --}}
        <div class="flex space-x-4">
          <div class="flex-1">
            <label class="text-sm text-gray-500">Guest:</label>
            <input type="text" value="{{ $check_in_details->guest->name ?? 'N/A' }}" disabled
              class="w-full bg-gray-100 border-gray-300 rounded-lg text-gray-700">
          </div>
          <div class="w-32">
            <label class="text-sm text-gray-500">Room #:</label>
            <div class="bg-gray-100 border border-gray-300 rounded-lg px-3 py-2 text-center">
              <span class="text-2xl font-bold text-gray-700">{{ $check_in_details->room->number ?? 'N/A' }}</span>
            </div>
          </div>
        </div>

        {{-- Frontdesk --}}
        <div>
          <label class="text-sm text-gray-500">Frontdesk:</label>
          <input type="text" value="{{ auth()->user()->name }}" disabled
            class="w-full bg-gray-100 border-gray-300 rounded-lg text-gray-700">
        </div>

        {{-- Transaction Type --}}
        <div>
          <label class="text-sm text-gray-500">Transaction Type:</label>
          <input type="text" value="Cancel Transaction" disabled
            class="w-full bg-gray-100 border-gray-300 rounded-lg text-gray-700">
        </div>

        {{-- Reason --}}
        <div>
          <label class="text-sm text-gray-500">Reason/s:</label>
          <textarea wire:model.defer="cancel_reason" rows="3"
            class="w-full border-gray-300 rounded-lg @error('cancel_reason') border-red-500 @enderror"
            placeholder="Please provide a reason for cancellation..."></textarea>
          @error('cancel_reason')
            <span class="text-sm text-red-500">{{ $message }}</span>
          @enderror
        </div>

        {{-- Supervisor Selection --}}
        <div>
          <label class="text-sm text-gray-500">Supervisor: <span class="text-red-500">*</span></label>
          <select wire:model.defer="selected_cancel_supervisor_id"
            class="w-full border-gray-300 rounded-lg @error('selected_cancel_supervisor_id') border-red-500 @enderror">
            <option value="">Select Supervisor</option>
            @foreach($this->supervisors as $supervisor)
              <option value="{{ $supervisor->id }}">{{ strtoupper($supervisor->name) }}</option>
            @endforeach
          </select>
          @error('selected_cancel_supervisor_id')
            <span class="text-sm text-red-500">{{ $message }}</span>
          @enderror
        </div>
      </div>

      <div class="mt-6 flex justify-end items-center space-x-2">
        <x-button wire:click="submitCancelOverrideRequest" class="bg-red-600 hover:bg-red-700 text-white font-bold px-6"
          spinner="submitCancelOverrideRequest">
          REQUEST OVERRIDE
        </x-button>
        <x-button x-on:click="close" label="CANCEL" flat />
      </div>
    </x-card>
  </x-modal>

  {{-- Cancel Request Submitted Modal --}}
  <x-modal wire:model.defer="cancel_request_submitted_modal" align="center" max-width="sm">
    <x-card>
      <div class="text-center py-6">
        <div class="bg-yellow-100 rounded-full p-4 w-16 h-16 mx-auto mb-4 flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <h3 class="text-lg font-bold text-gray-700 mb-2">Request Submitted</h3>
        <p class="text-sm text-gray-500 mb-4">Your cancel override request has been sent to the supervisor for approval.</p>
        <p class="text-xs text-gray-400">You will be notified once the supervisor responds.</p>
      </div>

      <x-slot name="footer">
        <div class="flex justify-center">
          <x-button wire:click="closeCancelRequestModal" label="OK" primary />
        </div>
      </x-slot>
    </x-card>
  </x-modal>
</div>
