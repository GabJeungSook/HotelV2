<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="bg-gray-100">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <title>HIMS - Back Office</title>

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;600;700;800;900&display=swap"
    rel="stylesheet">

  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
  <link href="print.css" rel="stylesheet" type="text/css" media="print" />

  <style type="text/css" media="print">
    @media print {
      .hide-div {
        display: hidden !important;
      }
    }
  </style>
  @wireUiScripts
  <!-- Scripts -->
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  <!-- Styles -->
  @livewireStyles
</head>
@if(app()->environment('staging'))
   <div class="fixed top-0 left-0 w-full bg-red-600 text-white text-center py-1 text-sm font-semibold z-50 animate-pulse">
        STAGING ENVIRONMENT
    </div>

    <div style="height: 20px;"></div> {{-- spacer to avoid overlap --}}
@endif
<body class="font-sans antialiased " x-data="{ logout: false }">
  <!--
  This example requires updating your template:

  ```
  <html class="h-full bg-gray-100">
  <body class="h-full">
  ```
-->
  <div>
    <!-- Off-canvas menu for mobile, show/hide based on off-canvas menu state. -->
    <div class="relative z-40 md:hidden" role="dialog" aria-modal="true">
      <!--
      Off-canvas menu backdrop, show/hide based on off-canvas menu state.

      Entering: "transition-opacity ease-linear duration-300"
        From: "opacity-0"
        To: "opacity-100"
      Leaving: "transition-opacity ease-linear duration-300"
        From: "opacity-100"
        To: "opacity-0"
    -->
      <div class="fixed inset-0 bg-gray-600 bg-opacity-75"></div>

      <div class="fixed inset-0 z-40 flex">
        <!--
        Off-canvas menu, show/hide based on off-canvas menu state.

        Entering: "transition ease-in-out duration-300 transform"
          From: "-translate-x-full"
          To: "translate-x-0"
        Leaving: "transition ease-in-out duration-300 transform"
          From: "translate-x-0"
          To: "-translate-x-full"
      -->
        <div class="relative flex w-full max-w-xs flex-1 flex-col bg-indigo-700">
          <!--
          Close button, show/hide based on off-canvas menu state.

          Entering: "ease-in-out duration-300"
            From: "opacity-0"
            To: "opacity-100"
          Leaving: "ease-in-out duration-300"
            From: "opacity-100"
            To: "opacity-0"
        -->
          <div class="absolute top-0 right-0 -mr-12 pt-2">
            <button type="button"
              class="ml-1 flex h-10 w-10 items-center justify-center rounded-full focus:outline-none focus:ring-2 focus:ring-inset focus:ring-white">
              <span class="sr-only">Close sidebar</span>
              <!-- Heroicon name: outline/x-mark -->
              <svg class="h-6 w-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <div class="h-0 flex-1 overflow-y-auto pt-5 pb-4">
            <div class="flex flex-shrink-0 items-center px-4">
              <img class="h-8 w-auto" src="https://tailwindui.com/img/logos/mark.svg?color=indigo&shade=300"
                alt="Your Company">
            </div>
            <nav class="mt-5 space-y-1 px-2">
              <!-- Current: "bg-indigo-800 text-white", Default: "text-gray-700 hover:bg-gray-100 hover:bg-opacity-75" -->
              <a href="#"
                class="bg-indigo-800 text-gray-700 group flex items-center px-4 py-2 text-base font-mediumd">
                <!-- Heroicon name: outline/home -->
                <svg class="mr-4 h-6 w-6 flex-shrink-0 text-gray-700" xmlns="http://www.w3.org/2000/svg" fill="none"
                  viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round"
                    d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                </svg>
                Dashboard
              </a>

              <a href="#"
                class="text-gray-700 hover:bg-gray-100 hover:bg-opacity-75 group flex items-center px-4 py-2 text-base font-mediumd">
                <!-- Heroicon name: outline/users -->
                <svg class="mr-4 h-6 w-6 flex-shrink-0 text-gray-700" xmlns="http://www.w3.org/2000/svg" fill="none"
                  viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round"
                    d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                </svg>
                Team
              </a>

              <a href="#"
                class="text-gray-700 hover:bg-gray-100 hover:bg-opacity-75 group flex items-center px-4 py-2 text-base font-mediumd">
                <!-- Heroicon name: outline/folder -->
                <svg class="mr-4 h-6 w-6 flex-shrink-0 text-gray-700" xmlns="http://www.w3.org/2000/svg" fill="none"
                  viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round"
                    d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                </svg>
                Projects
              </a>

              <a href="#"
                class="text-gray-700 hover:bg-gray-100 hover:bg-opacity-75 group flex items-center px-4 py-2 text-base font-mediumd">
                <!-- Heroicon name: outline/calendar -->
                <svg class="mr-4 h-6 w-6 flex-shrink-0 text-gray-700" xmlns="http://www.w3.org/2000/svg" fill="none"
                  viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round"
                    d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                </svg>
                Calendar
              </a>

              <a href="#"
                class="text-gray-700 hover:bg-gray-100 hover:bg-opacity-75 group flex items-center px-4 py-2 text-base font-mediumd">
                <!-- Heroicon name: outline/inbox -->
                <svg class="mr-4 h-6 w-6 flex-shrink-0 text-gray-700" xmlns="http://www.w3.org/2000/svg"
                  fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round"
                    d="M2.25 13.5h3.86a2.25 2.25 0 012.012 1.244l.256.512a2.25 2.25 0 002.013 1.244h3.218a2.25 2.25 0 002.013-1.244l.256-.512a2.25 2.25 0 012.013-1.244h3.859m-19.5.338V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 00-2.15-1.588H6.911a2.25 2.25 0 00-2.15 1.588L2.35 13.177a2.25 2.25 0 00-.1.661z" />
                </svg>
                Documents
              </a>

              <a href="#"
                class="text-gray-700 hover:bg-gray-100 hover:bg-opacity-75 group flex items-center px-4 py-2 text-base font-mediumd">
                <!-- Heroicon name: outline/chart-bar -->
                <svg class="mr-4 h-6 w-6 flex-shrink-0 text-gray-700" xmlns="http://www.w3.org/2000/svg"
                  fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round"
                    d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                </svg>
                Reports
              </a>
            </nav>
          </div>
          <div class="flex flex-shrink-0 border-t border-indigo-800 p-4">
            <a href="#" class="group block flex-shrink-0">
              <div class="flex items-center">
                <div>
                  <img class="inline-block h-10 w-10 rounded-full"
                    src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?ixlib=rb-1.2.1&ixid=eyJhcHBfaWQiOjEyMDd9&auto=format&fit=facearea&facepad=2&w=256&h=256&q=80"
                    alt="">
                </div>
                <div class="ml-3">
                  <p class="text-base font-medium text-white">Tom Cook</p>
                  <p class="text-sm text-indigo-200 group-hover:text-white">View profile</p>
                </div>
              </div>
            </a>
          </div>
        </div>

        <div class="w-14 flex-shrink-0" aria-hidden="true">
          <!-- Force sidebar to shrink to fit close icon -->
        </div>
      </div>
    </div>

    <!-- Static sidebar for desktop -->
    <div class="hidden md:fixed md:inset-y-0 md:flex md:w-64 md:flex-col">
      <!-- Sidebar component, swap this element with another sidebar if you like -->
      <div class="flex min-h-0 flex-1 flex-col relative bg-white">
        <div class="flex flex-1 flex-col overflow-y-auto pt-5 pb-4">
          <div class="flex flex-shrink-0 items-center px-4">
            <div class="flex space-x-2 items-center justify-center">
              <x-svg.hotel class="w-10 h-10 text-gray-600" />
              <div class="border-l-2 border-gray-500 pl-2">
                <div class="text-gray-500 text-xl font-bold">HIMS</div>
                <div class="text-gray-600 font-rubik border-t text-sm  leading-4">
                  {{ auth()->user()->branch_name }}
                </div>
              </div>
            </div>
          </div>
          <nav class="mt-5  space-y-0.5 ">
            <!-- Current: "bg-indigo-800 text-white", Default: "text-gray-700 hover:bg-gray-100 hover:bg-opacity-75" -->
            <a href="{{ route('back-office.dashboard') }}"
              class=" {{ request()->routeIs('back-office.dashboard') ? 'bg-gray-100 before:h-full before:w-1 relative before:bg-gray-500 before:rounded-r before:absolute before:left-0 ' : '' }}  text-gray-600 group flex items-center px-4 py-2 text-sm  ">

              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                class="mr-3 h-6 w-6 flex-shrink-0 fill-gray-600">
                <path fill="none" d="M0 0H24V24H0z" />
                <path
                  d="M12 2c5.523 0 10 4.477 10 10s-4.477 10-10 10S2 17.523 2 12 6.477 2 12 2zm0 2c-4.418 0-8 3.582-8 8s3.582 8 8 8 8-3.582 8-8-3.582-8-8-8zm0 1c1.018 0 1.985.217 2.858.608L13.295 7.17C12.882 7.06 12.448 7 12 7c-2.761 0-5 2.239-5 5 0 1.38.56 2.63 1.464 3.536L7.05 16.95l-.156-.161C5.72 15.537 5 13.852 5 12c0-3.866 3.134-7 7-7zm6.392 4.143c.39.872.608 1.84.608 2.857 0 1.933-.784 3.683-2.05 4.95l-1.414-1.414C16.44 14.63 17 13.38 17 12c0-.448-.059-.882-.17-1.295l1.562-1.562zm-2.15-2.8l1.415 1.414-3.724 3.726c.044.165.067.338.067.517 0 1.105-.895 2-2 2s-2-.895-2-2 .895-2 2-2c.179 0 .352.023.517.067l3.726-3.724z" />
              </svg>
              Dashboard
            </a>
            <nav class="mt-2  space-y-0.5 ">
              <a href="{{ route('back-office.report-hub') }}"
                class="{{ request()->routeIs('back-office.report-hub') ? 'bg-gray-100 before:h-full before:w-1 relative before:bg-gray-500 before:rounded-r before:absolute before:left-0 ' : '' }} text-gray-600 group flex items-center px-4 py-2 text-sm hover:bg-gray-100 ">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                  class="mr-3 h-6 w-6 flex-shrink-0 fill-gray-600">
                  <path fill="none" d="M0 0h24v24H0z" />
                  <path
                    d="M11 7h2v10h-2V7zm4 4h2v6h-2v-6zm-8 2h2v4H7v-4zm8-9H5v16h14V8h-4V4zM3 2.992C3 2.444 3.447 2 3.999 2H16l5 5v13.993A1 1 0 0 1 20.007 22H3.993A1 1 0 0 1 3 21.008V2.992z" />
                </svg>
                Reports
              </a>
              <a href="{{ route('back-office.archives') }}"
                class="{{ request()->routeIs('back-office.archives') ? 'bg-gray-100 before:h-full before:w-1 relative before:bg-gray-500 before:rounded-r before:absolute before:left-0 ' : '' }} text-gray-600 group flex items-center px-4 py-2 text-sm hover:bg-gray-100 ">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                  class="mr-3 h-6 w-6 flex-shrink-0 text-gray-600">
                  <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                </svg>
                Archives
              </a>
            </nav>

            {{-- <a href="{{ route('back-office.expenses') }}"
              class="{{ request()->routeIs('back-office.expenses') ? 'bg-gray-100 before:h-full before:w-1 relative before:bg-gray-500 before:rounded-r before:absolute before:left-0 ' : '' }} text-gray-700 hover:bg-gray-100 hover:bg-opacity-75 group flex items-center px-4 py-2 text-sm ">
              <!-- Heroicon name: outline/folder -->
              <svg class="w-6 h-6 mr-3  flex-shrink-0 text-gray-600" stroke-width="1.5" viewBox="0 0 24 24"
                fill="none" xmlns="http://www.w3.org/2000/svg">
                <path
                  d="M4.62323 5.24841C2.99408 7.02743 2 9.39765 2 12C2 17.5229 6.47715 22 12 22C14.5361 22 16.8517 21.0559 18.6146 19.5"
                  stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"></path>
                <path
                  d="M21.3021 15.6775C21.7525 14.5392 22 13.2985 22 12C22 6.47715 17.5228 2 12 2C10.7687 2 9.58934 2.22255 8.5 2.62961"
                  stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"></path>
                <path
                  d="M9 15C9.64448 15.8593 10.8428 16.3494 12 16.391C13.1141 16.431 14.1901 16.0554 14.6973 15.1933"
                  stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"></path>
                <path d="M12 16.391V18.5" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                </path>
                <path d="M9.5 9.5C9.5 10.6811 10.3525 11.1647 11.3862 11.5" stroke="currentColor"
                  stroke-linecap="round" stroke-linejoin="round"></path>
                <path d="M15 8.5C14.315 7.81501 13.1087 7.33855 12 7.30872V5.5" stroke="currentColor"
                  stroke-linecap="round" stroke-linejoin="round"></path>
                <path d="M3 3L21 21" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"></path>
              </svg>
              Expenses
            </a> --}}
          </nav>
          {{-- <div class="border-t mt-5 py-1">
            <div class="px-1 text-xs font-medium">REPORTS</div>
            <nav class="mt-2  space-y-0.5 ">
              <a href="{{ route('back-office.reports') }}"
                class="{{ request()->routeIs('back-office.reports') ? 'bg-gray-100 before:h-full before:w-1 relative before:bg-gray-500 before:rounded-r before:absolute before:left-0 ' : '' }} text-gray-600 group flex items-center px-4 py-2 text-sm hover:bg-gray-100 ">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                  class="mr-3 h-6 w-6 flex-shrink-0 fill-gray-600">
                  <path fill="none" d="M0 0h24v24H0z" />
                  <path
                    d="M11 7h2v10h-2V7zm4 4h2v6h-2v-6zm-8 2h2v4H7v-4zm8-9H5v16h14V8h-4V4zM3 2.992C3 2.444 3.447 2 3.999 2H16l5 5v13.993A1 1 0 0 1 20.007 22H3.993A1 1 0 0 1 3 21.008V2.992z" />
                </svg>
                Reports
              </a>
            </nav>
          </div> --}}
        </div>
        <div class="flex flex-shrink-0 border-t-2 border-gray-200 py-4 px-2">
          <a href="#" class="group block w-full flex-shrink-0">
            <div class="flex justify-between">
              <div class="flex items-center">
                <div>
                  <x-avatar sm label="AB" />
                </div>
                <div class="ml-3">

                  <p class="text-xs font-medium text-gray-500 uppercase  ">{{ auth()->user()->roles->first()->name }}
                  </p>
                </div>
              </div>
              <x-button icon="logout" sm negative x-on:click="logout = true" />
            </div>
          </a>
        </div>
      </div>
    </div>
    <div class="flex flex-1 flex-col md:pl-64">
      <div class="sticky top-0 z-10 bg-gray-100 pl-1 pt-1 sm:pl-3 sm:pt-3 md:hidden">
        <button type="button"
          class="-ml-0.5 -mt-0.5 inline-flex h-12 w-12 items-center justify-center rounded-md text-gray-500 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500">
          <span class="sr-only">Open sidebar</span>
          <!-- Heroicon name: outline/bars-3 -->
          <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
            stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
          </svg>
        </button>
      </div>
      <main class="flex-1">
        <div class="py-6">
          <div class="mx-auto max-w-full px-4 sm:px-6 md:px-8">
            <div
              class="md:flex bg-white p-2 px-5 shadow-sm rounded-lg md:items-center md:justify-between md:space-x-5">
              <div class="flex items-start space-x-5">
                <div class="pt-1.5">
                  <h1 class="text-2xl font-semibold text-gray-600 uppercase">@yield('breadcrumbs')</h1>
                </div>
              </div>
              <div
                class="justify-stretch mt-6 flex flex-col-reverse space-y-4 space-y-reverse sm:flex-row-reverse sm:justify-end sm:space-y-0 sm:space-x-3 sm:space-x-reverse md:mt-0 md:flex-row md:space-x-3">
                <nav class="flex" aria-label="Breadcrumb">
                  <ol role="list" class="flex items-center space-x-2">
                    <li>
                      <div>
                        <a href="#" class="text-gray-400 hover:text-gray-500">
                          <!-- Heroicon name: mini/home -->
                          <svg class="h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                            fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd"
                              d="M9.293 2.293a1 1 0 011.414 0l7 7A1 1 0 0117 11h-1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-3a1 1 0 00-1-1H9a1 1 0 00-1 1v3a1 1 0 01-1 1H5a1 1 0 01-1-1v-6H3a1 1 0 01-.707-1.707l7-7z"
                              clip-rule="evenodd" />
                          </svg>
                          <span class="sr-only">Home</span>
                        </a>
                      </div>
                    </li>

                    <li>
                      <div class="flex items-center">
                        <!-- Heroicon name: mini/chevron-right -->
                        <svg class="h-5 w-5 flex-shrink-0 text-gray-400" xmlns="http://www.w3.org/2000/svg"
                          viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                          <path fill-rule="evenodd"
                            d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z"
                            clip-rule="evenodd" />
                        </svg>
                        <a href="{{ route('back-office.frontdesk-shift-table') }}"
                          class="ml-4 text-sm  text-gray-500 hover:text-gray-700">@yield('breadcrumbs')</a>
                      </div>
                    </li>
                  </ol>
                </nav>

              </div>
            </div>

          </div>
          <div class="mx-auto max-w-full">
            <!-- Replace with your content -->
            <div class="py-8">
              <div class="h-96">
                {{ $slot }}
              </div>
            </div>
            <!-- /End replace -->
          </div>
        </div>
      </main>
    </div>
  </div>

  <div x-show="logout" x-cloak class="relative z-10" aria-labelledby="modal-title" role="dialog"
    aria-modal="true">
    <!--
    Background backdrop, show/hide based on modal state.

    Entering: "ease-out duration-300"
      From: "opacity-0"
      To: "opacity-100"
    Leaving: "ease-in duration-200"
      From: "opacity-100"
      To: "opacity-0"
  -->
    <div x-show="logout" x-cloak x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0"
      x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200"
      x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
      class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity">
    </div>

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
        <div x-show="logout" x-cloak x-transition:enter="ease-out duration-300"
          x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
          x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200"
          x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
          x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
          class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-sm">
          <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
            <div class="sm:flex sm:items-start">
              <div
                class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                <!-- Heroicon name: outline/exclamation-triangle -->
                <svg class="h-6 w-6 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none"
                  viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round"
                    d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
              </div>
              <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                <h3 class="text-lg font-medium leading-6 text-gray-900" id="modal-title">Logout Account</h3>
                <div class="mt-2">
                  <p class="text-sm text-gray-500">Are you sure you want to logout your account? </p>
                </div>
              </div>
            </div>
          </div>
          <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse  sm:px-6">
            <form method="POST" action="{{ route('logout') }}" class="flex space-x-2">
              @csrf
              <x-button @click="logout=false" label="Cancel" sm icon="x" />
              <x-button href="{{ route('logout') }}"
                onclick="event.preventDefault();
              this.closest('form').submit();" label="Logout"
                icon="logout" sm negative />
            </form>

          </div>
        </div>
      </div>
    </div>
  </div>


  @stack('scripts')

  @livewireScripts
  <x-dialog z-index="z-50" blur="md" align="center" />
</body>

</html>
