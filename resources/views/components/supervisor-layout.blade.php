<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-gray-100">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <title>HIMS - Supervisor</title>

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;600;700;800;900&display=swap"
    rel="stylesheet">

  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">

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
    <div style="height: 20px;"></div>
@endif
<body class="h-full font-sans antialiased" x-data="{ logout: false }">
  @php
    $pendingCount = \App\Models\OverrideRequest::where('branch_id', auth()->user()->branch_id)
        ->where('status', 'pending')
        ->count();
  @endphp

  <div class="min-h-full">
    <!-- Static sidebar for desktop - Dark Blue Design -->
    <div class="hidden md:fixed md:inset-y-0 md:flex md:w-56 md:flex-col z-10">
      <div class="flex min-h-0 flex-1 flex-col relative bg-[#0f172a]">
        <div class="flex flex-1 flex-col overflow-y-auto pt-5 pb-4">
          {{-- Logo Section --}}
          <div class="flex flex-shrink-0 items-center px-4 mb-6">
            <div class="flex items-center space-x-2">
              <div class="bg-white p-2 rounded">
                <x-svg.hotel class="w-6 h-6 text-gray-800" />
              </div>
              <div>
                <div class="text-white text-lg font-bold">HIMS</div>
                <div class="text-gray-400 text-xs leading-tight">
                  {{ auth()->user()->branch_name }}
                </div>
              </div>
            </div>
          </div>

          <nav class="flex-1 space-y-1 px-2">
            {{-- Reports --}}
            <a href="{{ route('supervisor.report-hub') }}"
              class="{{ request()->routeIs('supervisor.report-hub') ? 'bg-[#1e293b] text-white' : 'text-gray-300 hover:bg-[#1e293b] hover:text-white' }} group flex items-center px-3 py-2 text-sm font-medium rounded-md">
              REPORTS
            </a>

            {{-- Archives --}}
            <a href="{{ route('supervisor.archives') }}"
              class="{{ request()->routeIs('supervisor.archives') ? 'bg-[#1e293b] text-white' : 'text-gray-300 hover:bg-[#1e293b] hover:text-white' }} group flex items-center px-3 py-2 text-sm font-medium rounded-md">
              ARCHIVES
            </a>

            {{-- Override with Badge --}}
            <a href="{{ route('supervisor.dashboard') }}"
              class="{{ request()->routeIs('supervisor.dashboard') ? 'bg-[#1e293b] text-white' : 'text-gray-300 hover:bg-[#1e293b] hover:text-white' }} group flex items-center justify-between px-3 py-2 text-sm font-medium rounded-md">
              <span>OVERRIDE</span>
              @if($pendingCount > 0)
              <span class="bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full">
                {{ $pendingCount }}
              </span>
              @endif
            </a>
          </nav>
        </div>

        {{-- Force Auto-Override Toggle --}}
        <div class="px-4 py-4 border-t border-gray-700">
          <div class="flex items-center justify-between">
            <span class="text-gray-300 text-xs font-medium">Force Auto-override</span>
            <livewire:supervisor.force-auto-override-toggle />
          </div>
        </div>

        {{-- Logout Button --}}
        <div class="px-4 py-4 border-t border-gray-700">
          <button x-on:click="logout = true" class="flex items-center space-x-2 text-gray-300 hover:text-white w-full">
            <span class="text-sm font-medium">Logout</span>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
          </button>
        </div>
      </div>
    </div>

    {{-- Main Content Area --}}
    <div class="md:pl-56 min-h-screen bg-gray-100">
      {{ $slot }}
    </div>
  </div>

  <!-- Logout Modal -->
  <div x-show="logout" x-cloak class="relative z-50" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div x-show="logout" x-cloak x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0"
      x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200"
      x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
      class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity">
    </div>

    <div class="fixed inset-0 z-10 overflow-y-auto">
      <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
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
                <svg class="h-6 w-6 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none"
                  viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round"
                    d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
              </div>
              <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                <h3 class="text-lg font-medium leading-6 text-gray-900" id="modal-title">Logout Account</h3>
                <div class="mt-2">
                  <p class="text-sm text-gray-500">Are you sure you want to logout your account?</p>
                </div>
              </div>
            </div>
          </div>
          <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
            <form method="POST" action="{{ route('logout') }}" class="flex space-x-2">
              @csrf
              <x-button @click="logout=false" label="Cancel" sm icon="x" />
              <x-button href="{{ route('logout') }}"
                onclick="event.preventDefault(); this.closest('form').submit();" label="Logout"
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
