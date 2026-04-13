<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <title>HOMI - Frontdesk</title>

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;600;700;800;900&display=swap"
    rel="stylesheet">

  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
  <style>
    /* width */
    ::-webkit-scrollbar {
      width: 5px;
    }

    /* Track */
    ::-webkit-scrollbar-track {
      background: #f1f1f1;
    }

    /* Handle */
    ::-webkit-scrollbar-thumb {
      background: #888;
    }

    /* Handle on hover */
    ::-webkit-scrollbar-thumb:hover {
      background: #555;
    }
  </style>
  @wireUiScripts
  <!-- Scripts -->
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  <!-- Styles -->
  @livewireStyles
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.2.1/dist/chart.umd.min.js "></script>
  <script src="https://fastly.jsdelivr.net/npm/echarts@5.4.1/dist/echarts.min.js"></script>
</head>
@if(app()->environment('staging'))
   <div class="fixed top-0 left-0 w-full bg-red-600 text-white text-center py-1 text-sm font-semibold z-50 animate-pulse">
        STAGING ENVIRONMENT
    </div>

    <div style="height: 20px;"></div> {{-- spacer to avoid overlap --}}
@endif
<body class="font-sans antialiased" x-data="{ logout: false }">

  {{-- <div>
    <div class="relative sticky top-0 z-10 bg-gradient-to-b from-gray-700 via-gray-600 to-gray-500">
      <div class="flex items-center justify-between px-16 py-4 md:justify-start md:space-x-10">
        <div class="flex justify-start lg:w-0 lg:flex-1">
          <div class="flex space-x-2 items-center">
            <x-svg.hotel class="w-10 h-10 text-gray-100" />
            <div class="border-l-2 border-gray-400 pl-2">
              <div class="text-gray-200 text-2xl font-bold">HIMS</div>
              <div class="text-gray-300 font-rubik font-medium  leading-3">{{ auth()->user()->branch_name }}</div>
            </div>
          </div>
        </div>
        <div class="-my-2 -mr-2 md:hidden">
          <button type="button"
            class="inline-flex items-center justify-center rounded-md bg-white p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500"
            aria-expanded="false">
            <span class="sr-only">Open menu</span>
            <!-- Heroicon name: outline/bars-3 -->
            <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
              stroke-width="1.5" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
            </svg>
          </button>
        </div>
        <nav class="hidden space-x-6 md:flex">


          <a href="{{ route('frontdesk.dashboard') }}"
            class="{{ Request::routeIs('frontdesk.dashboard') ? 'bg-white text-white fill-white bg-opacity-30' : 'text-white fill-white' }} text-md font-medium hover:bg-white hover:bg-opacity-30 p-2 items-center rounded-lg flex space-x-2 ">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24">
              <path fill="none" d="M0 0H24V24H0z" />
              <path
                d="M12 2c5.523 0 10 4.477 10 10s-4.477 10-10 10S2 17.523 2 12 6.477 2 12 2zm0 2c-4.418 0-8 3.582-8 8s3.582 8 8 8 8-3.582 8-8-3.582-8-8-8zm3.833 3.337c.237-.166.559-.138.763.067.204.204.23.526.063.76-2.18 3.046-3.38 4.678-3.598 4.897-.586.585-1.536.585-2.122 0-.585-.586-.585-1.536 0-2.122.374-.373 2.005-1.574 4.894-3.602zM17.5 11c.552 0 1 .448 1 1s-.448 1-1 1-1-.448-1-1 .448-1 1-1zm-11 0c.552 0 1 .448 1 1s-.448 1-1 1-1-.448-1-1 .448-1 1-1zm2.318-3.596c.39.39.39 1.023 0 1.414-.39.39-1.024.39-1.414 0-.39-.39-.39-1.024 0-1.414.39-.39 1.023-.39 1.414 0zM12 5.5c.552 0 1 .448 1 1s-.448 1-1 1-1-.448-1-1 .448-1 1-1z" />
            </svg>
            <span>DASHBOARD</span>
          </a>
          <a href="{{ route('frontdesk.room-monitoring') }}"
            class="{{ Request::routeIs('frontdesk.room-monitoring') ? 'bg-white text-white fill-white bg-opacity-30' : 'text-white fill-white' }} text-md font-medium hover:bg-white hover:bg-opacity-30 p-2 items-center rounded-lg flex space-x-2 ">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24">
              <path fill="none" d="M0 0h24v24H0z" />
              <path
                d="M6 19h12V9.157l-6-5.454-6 5.454V19zm13 2H5a1 1 0 0 1-1-1v-9H1l10.327-9.388a1 1 0 0 1 1.346 0L23 11h-3v9a1 1 0 0 1-1 1zM7.5 13h2a2.5 2.5 0 1 0 5 0h2a4.5 4.5 0 1 1-9 0z" />
            </svg>
            <span>ROOM MONITORING</span>
          </a>
          <a href="{{ route('frontdesk.priority-room') }}"
            class="{{ Request::routeIs('frontdesk.priority-room') ? 'bg-white text-white fill-white bg-opacity-30' : 'text-white fill-white' }} text-md font-medium hover:bg-white hover:bg-opacity-30 p-2 items-center rounded-lg flex space-x-2 ">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24">
              <path fill="none" d="M0 0h24v24H0z" />
              <path
                d="M6 19h12V9.157l-6-5.454-6 5.454V19zm13 2H5a1 1 0 0 1-1-1v-9H1l10.327-9.388a1 1 0 0 1 1.346 0L23 11h-3v9a1 1 0 0 1-1 1zM7.5 13h2a2.5 2.5 0 1 0 5 0h2a4.5 4.5 0 1 1-9 0z" />
            </svg>
            <span>PRIORITY ROOM</span>
          </a>
          <a href="#"
            class=" text-md font-medium text-white fill-white  p-2 items-center rounded-lg flex space-x-2 hover:text-gray-200">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24">
              <path fill="none" d="M0 0h24v24H0z" />
              <path
                d="M2.213 14.06a9.945 9.945 0 0 1 0-4.12c1.11.13 2.08-.237 2.396-1.001.317-.765-.108-1.71-.986-2.403a9.945 9.945 0 0 1 2.913-2.913c.692.877 1.638 1.303 2.403.986.765-.317 1.132-1.286 1.001-2.396a9.945 9.945 0 0 1 4.12 0c-.13 1.11.237 2.08 1.001 2.396.765.317 1.71-.108 2.403-.986a9.945 9.945 0 0 1 2.913 2.913c-.877.692-1.303 1.638-.986 2.403.317.765 1.286 1.132 2.396 1.001a9.945 9.945 0 0 1 0 4.12c-1.11-.13-2.08.237-2.396 1.001-.317.765.108 1.71.986 2.403a9.945 9.945 0 0 1-2.913 2.913c-.692-.877-1.638-1.303-2.403-.986-.765.317-1.132 1.286-1.001 2.396a9.945 9.945 0 0 1-4.12 0c.13-1.11-.237-2.08-1.001-2.396-.765-.317-1.71.108-2.403.986a9.945 9.945 0 0 1-2.913-2.913c.877-.692 1.303-1.638.986-2.403-.317-.765-1.286-1.132-2.396-1.001zM4 12.21c1.1.305 2.007 1.002 2.457 2.086.449 1.085.3 2.22-.262 3.212.096.102.195.201.297.297.993-.562 2.127-.71 3.212-.262 1.084.45 1.781 1.357 2.086 2.457.14.004.28.004.42 0 .305-1.1 1.002-2.007 2.086-2.457 1.085-.449 2.22-.3 3.212.262.102-.096.201-.195.297-.297-.562-.993-.71-2.127-.262-3.212.45-1.084 1.357-1.781 2.457-2.086.004-.14.004-.28 0-.42-1.1-.305-2.007-1.002-2.457-2.086-.449-1.085-.3-2.22.262-3.212a7.935 7.935 0 0 0-.297-.297c-.993.562-2.127.71-3.212.262C13.212 6.007 12.515 5.1 12.21 4a7.935 7.935 0 0 0-.42 0c-.305 1.1-1.002 2.007-2.086 2.457-1.085.449-2.22.3-3.212-.262-.102.096-.201.195-.297.297.562.993.71 2.127.262 3.212C6.007 10.788 5.1 11.485 4 11.79c-.004.14-.004.28 0 .42zM12 15a3 3 0 1 1 0-6 3 3 0 0 1 0 6zm0-2a1 1 0 1 0 0-2 1 1 0 0 0 0 2z" />
            </svg>
            <span>SETTINGS</span>
          </a>

        </nav>
        <div class="hidden items-center justify-end md:flex md:flex-1 lg:w-0">
          <livewire:frontdesk.switch-frontdesk />
          <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-button negative label="Logout" class="ml-2"
              onclick="event.preventDefault();
              this.closest('form').submit();" icon="logout" />
          </form>


        </div>
      </div>
    </div>
    <div class="mt-14 px-16">
      {{ $slot }}
    </div>
  </div> --}}



  <div class="relative bg-white">
    <div class="pointer-events-none absolute inset-0 z-30 " aria-hidden="true"></div>
    <div class="relative z-20">
      <div class="mx-auto flex  items-center space-x-12  justify-between py-5 px-16">
        <div>
          <div class="flex justify-center items-center w-full">
            <img src="{{ asset('images/homiLogo.png') }}" class="h-10" alt="">
          </div>
        </div>
        <div class="-my-2 -mr-2 md:hidden">
          <button type="button"
            class="inline-flex items-center justify-center rounded-md bg-white p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500"
            aria-expanded="false">
            <span class="sr-only">Open menu</span>
            <!-- Heroicon name: outline/bars-3 -->
            <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
              stroke-width="1.5" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
            </svg>
          </button>
        </div>
        <div class="hidden md:flex md:flex-1 md:items-center md:justify-between">
          <nav class="flex space-x-10">
            <div class="flex space-x-10">
              @php
                  $isBeginningCash = request()->routeIs('frontdesk.beginning-cash');
              @endphp
              <!-- Item active: "text-gray-900", Item inactive: "text-gray-500" -->
              <a href="{{ $isBeginningCash ? '#' : route('frontdesk.dashboard') }}"
                class="text-gray-500 fill-gray-500 group inline-flex items-center rounded-md bg-white text-base font-medium hover:text-gray-900 "
                aria-expanded="false">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" class="mr-2"
                  height="24">
                  <path fill="none" d="M0 0H24V24H0z" />
                  <path
                    d="M12 2c5.523 0 10 4.477 10 10s-4.477 10-10 10S2 17.523 2 12 6.477 2 12 2zm0 2c-4.418 0-8 3.582-8 8s3.582 8 8 8 8-3.582 8-8-3.582-8-8-8zm0 1c1.018 0 1.985.217 2.858.608L13.295 7.17C12.882 7.06 12.448 7 12 7c-2.761 0-5 2.239-5 5 0 1.38.56 2.63 1.464 3.536L7.05 16.95l-.156-.161C5.72 15.537 5 13.852 5 12c0-3.866 3.134-7 7-7zm6.392 4.143c.39.872.608 1.84.608 2.857 0 1.933-.784 3.683-2.05 4.95l-1.414-1.414C16.44 14.63 17 13.38 17 12c0-.448-.059-.882-.17-1.295l1.562-1.562zm-2.15-2.8l1.415 1.414-3.724 3.726c.044.165.067.338.067.517 0 1.105-.895 2-2 2s-2-.895-2-2 .895-2 2-2c.179 0 .352.023.517.067l3.726-3.724z" />
                </svg>
                <span>Dashboard</span>
              </a>
              <a href="{{ $isBeginningCash ? '#' : route('frontdesk.room-monitoring') }}"
                class="text-gray-500 fill-gray-500 group inline-flex items-center rounded-md bg-white text-base font-medium hover:text-gray-900 "
                aria-expanded="false">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"
                  class="mr-2">
                  <path fill="none" d="M0 0h24v24H0z" />
                  <path
                    d="M6 19h12V9.157l-6-5.454-6 5.454V19zm13 2H5a1 1 0 0 1-1-1v-9H1l10.327-9.388a1 1 0 0 1 1.346 0L23 11h-3v9a1 1 0 0 1-1 1zM7.5 13h2a2.5 2.5 0 1 0 5 0h2a4.5 4.5 0 1 1-9 0z" />
                </svg>
                <span>Room Monitoring</span>
              </a>
              <a href="{{ $isBeginningCash ? '#' : route('frontdesk.frontdesk-extension') }}"
                class="text-gray-500 fill-gray-500 group inline-flex items-center rounded-md bg-white text-base font-medium hover:text-gray-900 "
                aria-expanded="false">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="24" height="24" class="mr-2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>

                <span>Extension Report</span>
              </a>
               <a href="{{ $isBeginningCash ? '#' : route('frontdesk.expenses') }}"
                class="text-gray-500 fill-gray-500 group inline-flex items-center rounded-md bg-white text-base font-medium hover:text-gray-900 "
                aria-expanded="false">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="24" height="24" class="mr-2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>


                <span>Expenses</span>
              </a>
               <a href="{{ $isBeginningCash ? '#' : route('frontdesk.remittance') }}"
                class="text-gray-500 fill-gray-500 group inline-flex items-center rounded-md bg-white text-base font-medium hover:text-gray-900 "
                aria-expanded="false">
                {{-- <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" >
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg> --}}
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="24" height="24" class="mr-2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="m5.25 4.5 7.5 7.5-7.5 7.5m6-15 7.5 7.5-7.5 7.5" />
                </svg>



                <span>Remittance</span>
              </a>
              <a href="{{ $isBeginningCash ? '#' : route('frontdesk.point-of-sale') }}"
                class="text-gray-500 fill-gray-500 group inline-flex items-center rounded-md bg-white text-base font-medium hover:text-gray-900 "
                aria-expanded="false">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="24" height="24" class="mr-2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                </svg>

                <span>POS</span>
              </a>

              {{-- <a href="{{ route('frontdesk.food-inventory') }}"
              class="text-gray-500 fill-gray-400 group inline-flex items-center rounded-md bg-white text-base font-medium hover:text-gray-800 "
              aria-expanded="false">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="24" height="24" class="mr-2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
              </svg>
              <span>Food Inventory</span>
            </a> --}}


            </div>
          </nav>
          <div class="flex items-center md:ml-12">
            <a href="{{ $isBeginningCash ? '#' : route('frontdesk.cash-on-hand') }}"
                class="text-gray-500 fill-gray-500 group inline-flex items-center rounded-md bg-white text-base font-medium hover:text-gray-900"
                aria-expanded="false">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="24" height="24" class="mr-2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15M12 9l3 3m0 0-3 3m3-3H2.25" />
                </svg>


                <span>End Shift</span>
              </a>
            {{-- <form method="POST" action="{{ route('logout') }}">
              @csrf
              <x-button negative label="Logout" class="ml-2"
                onclick="event.preventDefault();
              this.closest('form').submit();" icon="logout" />
            </form> --}}
          </div>
        </div>
      </div>
    </div>



  </div>
  <!--
  This example requires updating your template:

  ```
  <html class="h-full bg-gray-100">
  <body class="h-full">
  ```
-->
  <div class="min-h-full">
    <div class="bg-gray-800 pb-32">
      <header class="py-5">
        <div class="px-20">
          <div class="">
            <div class="md:flex md:items-center md:justify-between md:space-x-5">
              <div class="flex items-start space-x-5">
                <div class="flex-col flex lg:flex-row items-start lg:items-center">
                  <div class="flex items-center">
                    <x-avatar md label="AB" class="mr-3" />
                    <div>
                      <p class="text-sm text-white leading-4 mb-1">{{ auth()->user()->name }} - ({{ auth()->user()->shift }} Shift)</p>
                      <p class="text-xs text-gray-400 uppercase leading-4">{{ auth()->user()->roles->first()->name }} - ({{ auth()->user()->cash_drawer?->name }})
                      </p>
                    </div>
                  </div>
                  <div class="ml-0 lg:ml-20 my-6 lg:my-0">
                    <h4 class="text-2xl font-bold leading-tight text-white mb-2">
                      @if (request()->routeIs('frontdesk.dashboard'))
                        Dashboard
                      @elseif(request()->routeIs('frontdesk.room-monitoring'))
                        Room Monitoring
                      @elseif(request()->routeIs('frontdesk.check-in-from-kiosk'))
                        Check-in Information
                      @elseif(request()->routeIs('frontdesk.extend-guest'))
                        Extend Guest Stay
                      @elseif(request()->routeIs('frontdesk.transfer-room'))
                        Transfer Room
                      @else
                        {{-- Transactions --}}
                      @endif
                    </h4>
                    <p class="flex items-center text-gray-300 text-xs">

                      @if (request()->routeIs('frontdesk.manage-guest'))
                        <span class="cursor-pointer">
                          Room Monitoring
                        </span>
                        <span class="mx-2">&gt;</span>
                        <span class="cursor-pointer">Manage Guest</span>
                      @else
                      @endif
                    </p>
                  </div>
                </div>
              </div>
              <div
                class="justify-stretch mt-6 flex flex-col-reverse space-y-4 space-y-reverse sm:flex-row-reverse sm:justify-end sm:space-y-0 sm:space-x-3 sm:space-x-reverse md:mt-0 md:flex-row md:space-x-3">
                {{-- <livewire:frontdesk.switch-frontdesk /> --}}
              </div>
            </div>

          </div>
        </div>
      </header>
    </div>

    <main class="-mt-32">
      <div class="px-20 pb-12 ">
        <!-- Replace with your content -->
        <div class="rounded-lg bg-white px-5 py-6 shadow sm:px-6">
          <div class="rounded-xl ">
            {{ $slot }}
          </div>
        </div>
        <!-- /End replace -->
      </div>
    </main>
  </div>




  <x-notifications z-index="z-50" />
  <x-dialog z-index="z-50" blur="md" align="center" />
  @livewireScripts
  @yield('scripts')

</body>

</html>
