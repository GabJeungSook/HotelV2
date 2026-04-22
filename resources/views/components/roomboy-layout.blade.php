<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <title>{{ config('app.name', 'Laravel') }}</title>

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;600;700;800;900&display=swap"
    rel="stylesheet">

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
<body class="font-sans antialiased bg-gray-100" x-data="{ logout: false }">
  <div class="min-h-full font-rubik">
    <div class="pb-32 bg-gray-800">
      <nav class="bg-white">
        <div class="max-w-full sm:px-6 lg:px-8">
          <div class="border-b border-gray-100">
            <div class="flex items-center justify-between h-16 px-4 sm:px-0">
              <div class="flex items-center">
                <div class="flex-shrink-0">
                  {{-- logo --}}
                </div>
                <div>
                  <div class="flex space-x-2 items-center">
                    <div class="flex items-center justify-center w-full">
                        <img src="{{ asset('images/homiLogo.png') }}" class="h-11" alt="">
                    </div>
                  </div>
                </div>
              </div>
              <div>
                <div class="flex items-center ml-4 md:ml-6">
                  <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="button" href="{{ route('logout') }}"
                      onclick="event.preventDefault();
                          this.closest('form').submit();"
                      class="px-4 py-2 flex items-center text-gray-600 hover:text-gray-900 rounded-full hover:bg-gray-100 focus:outline-none transition-colors">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round"
                          d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                      </svg>
                      <span class="ml-1 hidden xl:block">
                        Logout
                      </span>
                    </button>
                  </form>
                </div>
              </div>
            </div>
          </div>
        </div>
      </nav>
      <header class="py-10 font-rubik">
        <div class="px-4 max-w-full sm:px-6 lg:px-8 text-center xl:text-left">
          <h1 class="text-2xl font-bold tracking-tight xl:text-3xl text-gray-50">ROOMBOY DASHBOARD</h1>
        </div>
      </header>
    </div>
    <main class="-mt-32">
      <div class="mx-4 sm:mx-6 lg:mx-8 pb-12">
        <div class="rounded-lg bg-white px-3 py-4 shadow sm:px-4">
          {{ $slot }}
        </div>
      </div>
    </main>
  </div>
  @livewireScripts
  <x-dialog z-index="z-50" blur="md" align="center" />
</body>

</html>
