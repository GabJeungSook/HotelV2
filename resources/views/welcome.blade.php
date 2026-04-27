<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <title>HOMI</title>

  <!-- Fonts -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap">
  <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@300;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
  <!-- Styles -->
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">



  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=Alkatra:wght@400;500;600;700&family=Montserrat:wght@900&display=swap"
    rel="stylesheet">
  @livewireStyles

  <!-- Scripts -->
  @vite(['resources/css/app.css', 'resources/js/app.js'])

  <style>
    /* Continuous drift on the gradient polygon — keeps the page breathing */
    .polygon-drift {
      animation: polygonDrift 22s ease-in-out infinite;
      transform-origin: center;
      will-change: transform;
    }
    @keyframes polygonDrift {
      0%, 100% { transform: translate(0, 0) rotate(0deg) scale(1); }
      33%      { transform: translate(30px, -20px) rotate(6deg) scale(1.04); }
      66%      { transform: translate(-20px, 25px) rotate(-4deg) scale(0.98); }
    }

    /* Giant HOMI watermark — replaces the faded logo image */
    .homi-watermark {
      position: absolute;
      right: -1.5rem;
      bottom: -3rem;
      font-family: 'Alkatra', system-ui, sans-serif;
      font-weight: 700;
      font-size: clamp(10rem, 22vw, 22rem);
      line-height: 0.82;
      letter-spacing: -0.04em;
      background: linear-gradient(180deg, rgba(0, 158, 245, 0.22) 0%, rgba(0, 158, 245, 0.05) 70%, transparent 100%);
      -webkit-background-clip: text;
              background-clip: text;
      -webkit-text-fill-color: transparent;
      pointer-events: none;
      user-select: none;
      z-index: 0;
    }

    /* LOGIN button — bigger, brand glow, animated chevron */
    .btn-login {
      background: #009EF5;
      color: #fff;
      padding: 14px 28px;
      border-radius: 12px;
      font-weight: 600;
      font-size: 15px;
      letter-spacing: 0.02em;
      display: inline-flex;
      align-items: center;
      gap: 14px;
      box-shadow:
        0 10px 28px -8px rgba(0, 158, 245, 0.55),
        0 2px 6px rgba(0, 158, 245, 0.18);
      transition: transform 0.25s ease, box-shadow 0.25s ease, background 0.25s ease;
    }
    .btn-login:hover {
      transform: translateY(-2px);
      background: #0091e0;
      box-shadow:
        0 16px 36px -8px rgba(0, 158, 245, 0.72),
        0 4px 10px rgba(0, 158, 245, 0.25);
    }
  </style>
</head>
@if(app()->environment('staging'))
   <div class="fixed top-0 left-0 w-full bg-red-600 text-white text-center py-1 text-sm font-semibold z-50 animate-pulse">
        STAGING ENVIRONMENT
    </div>

    <div style="height: 20px;"></div> {{-- spacer to avoid overlap --}}
@endif

<body class="font-sans antialiased overflow-hidden">

  <div class="homi-watermark" aria-hidden="true">HOMI</div>
  <div class="bg-white">
    <header class="absolute inset-x-0 top-0 z-50">
      <nav class="mx-auto flex max-w-7xl items-center justify-between p-6 lg:px-8" aria-label="Global">
        <div class="flex lg:flex-1">
          <a href="#" class="-m-1.5 p-1.5">
            <span class="sr-only">Your Company</span>
            <img class="h-12 w-auto" src="{{ asset('images/homiLogo.png') }}"
              alt="">
          </a>
        </div>
        <div class="flex lg:hidden">
          <button type="button" class="-m-2.5 inline-flex items-center justify-center rounded-md p-2.5 text-gray-700">
            <span class="sr-only">Open main menu</span>
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
              aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
            </svg>
          </button>
        </div>

      </nav>
      <!-- Mobile menu, show/hide based on menu open state. -->
      {{-- <div class="lg:hidden" role="dialog" aria-modal="true">
        <!-- Background backdrop, show/hide based on slide-over state. -->
        <div class="fixed inset-0 z-50"></div>
        <div
          class="fixed inset-y-0 right-0 z-50 w-full overflow-y-auto bg-white px-6 py-6 sm:max-w-sm sm:ring-1 sm:ring-gray-900/10">
          <div class="flex items-center justify-between">
            <a href="#" class="-m-1.5 p-1.5">
              <span class="sr-only">Your Company</span>
              <img class="h-8 w-auto" src="https://tailwindui.com/img/logos/mark.svg?color=indigo&shade=600"
                alt="">
            </a>
            <button type="button" class="-m-2.5 rounded-md p-2.5 text-gray-700">
              <span class="sr-only">Close menu</span>
              <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
  </div>
  </div> --}}
    </header>
    <main>
      <div class="relative isolate">
        <svg
          class="absolute inset-x-0 top-0 -z-10 h-[64rem] w-full stroke-gray-200 [mask-image:radial-gradient(32rem_32rem_at_center,white,transparent)]"
          aria-hidden="true">
          <defs>
            <pattern id="1f932ae7-37de-4c0a-a8b0-a6e3b4d44b84" width="200" height="200" x="50%"
              y="-1" patternUnits="userSpaceOnUse">
              <path d="M.5 200V.5H200" fill="none" />
            </pattern>
          </defs>
          <svg x="50%" y="-1" class="overflow-visible fill-gray-50">
            <path d="M-200 0h201v201h-201Z M600 0h201v201h-201Z M-400 600h201v201h-201Z M200 800h201v201h-201Z"
              stroke-width="0" />
          </svg>
          <rect width="100%" height="100%" stroke-width="0" fill="url(#1f932ae7-37de-4c0a-a8b0-a6e3b4d44b84)" />
        </svg>
        <div
          class="absolute left-1/2 right-0 top-0 -z-10 -ml-24 transform-gpu overflow-hidden blur-3xl lg:ml-24 xl:ml-48"
          aria-hidden="true">
          <div class="polygon-drift aspect-[801/1036] w-[50.0625rem] bg-gradient-to-tr from-[#009EF5] to-gray-300 opacity-30"
            style="clip-path: polygon(63.1% 29.5%, 100% 17.1%, 76.6% 3%, 48.4% 0%, 44.6% 4.7%, 54.5% 25.3%, 59.8% 49%, 55.2% 57.8%, 44.4% 57.2%, 27.8% 47.9%, 35.1% 81.5%, 0% 97.7%, 39.2% 100%, 35.2% 81.4%, 97.2% 52.8%, 63.1% 29.5%)">
          </div>
        </div>

        <div class="overflow-hidden">
          <div class="mx-auto max-w-7xl px-6 pb-32 pt-36 sm:pt-60 lg:px-8 lg:pt-32">
            <div class="mx-auto max-w-2xl gap-x-14 lg:mx-0 lg:flex lg:max-w-none lg:items-center">
              <div class="w-full max-w-xl lg:shrink-0 xl:max-w-2xl">
                <h1
                  class="animate__animated animate__slideInDown font-alkatra text-4xl font-bold tracking-tight  text-gray-700 sm:text-6xl">
                  Revolutionizing Hospitality with <span class="font-sans  text-blue-600 font-bold">HOMI</span></h1>
                <p
                  class="animate__animated animate__slideInDown relative mt-6 text-lg leading-8 text-gray-600 sm:max-w-md lg:max-w-none">
                  Your Ultimate Hotel Management Solution.</p>
                {{-- <p
                  class="animate__animated animate__slideInDown relative mt-1 text-sm leading-8 text-gray-500 sm:max-w-md lg:max-w-none">
                  Powered By: J7 IT SOLUTION & SERVICES</p> --}}
                <div class="mt-10 flex items-center gap-x-6">
                  <a href="{{ route('login') }}"
                    class="animate__animated animate__slideInUp btn-login group">
                    <span>LOGIN</span>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                      class="fill-white h-5 w-5 rotate-180 transition-transform duration-300 group-hover:translate-x-1">
                      <path
                        d="M11 20L1 12L11 4V9C16.5228 9 21 13.4772 21 19C21 19.2729 20.9891 19.5433 20.9676 19.8107C19.4605 16.9502 16.458 15 13 15H11V20Z">
                      </path>
                    </svg>
                  </a>
                </div>
              </div>

              <div class=" mt-14 flex justify-end gap-8 sm:-mt-44 sm:justify-start sm:pl-20 lg:mt-0 lg:pl-0">
                <div
                  class="ml-auto w-44 flex-none space-y-8 pt-32 sm:ml-0 sm:pt-80 lg:order-last lg:pt-36 xl:order-none xl:pt-80">
                  <div class="relative">
                    <img
                      src="https://images.unsplash.com/photo-1541971875076-8f970d573be6?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=1074&q=80"
                      src="" alt=""
                      class="aspect-[2/3] animate__animated animate__fadeInUp  w-full rounded-xl bg-gray-900/5 object-cover shadow-lg">
                    <div class="pointer-events-none absolute inset-0 rounded-xl ring-1 ring-inset ring-gray-900/10">
                    </div>
                  </div>
                </div>
                <div class="mr-auto w-44 flex-none space-y-8 sm:mr-0 sm:pt-52 lg:pt-36 ">
                  <div class="relative ">
                    <img
                      src="https://images.unsplash.com/photo-1518733057094-95b53143d2a7?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=665&q=80"
                      alt=""
                      class="aspect-[2/3] animate__animated animate__fadeInRight w-full rounded-xl bg-gray-900/5 object-cover shadow-lg">
                    <div class="pointer-events-none absolute inset-0 rounded-xl ring-1 ring-inset ring-gray-900/10">
                    </div>
                  </div>
                  <div class="relative">
                    <img
                      src="https://images.unsplash.com/photo-1629140727571-9b5c6f6267b4?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=627&q=80"
                      alt=""
                      class="aspect-[2/3] animate__animated animate__fadeInLeft w-full rounded-xl bg-gray-900/5 object-cover shadow-lg">
                    <div class="pointer-events-none absolute inset-0 rounded-xl ring-1 ring-inset ring-gray-900/10">
                    </div>
                  </div>
                </div>
                <div class="w-44 flex-none space-y-8 pt-32 sm:pt-0">
                  <div class="relative">
                    <img
                      src="https://images.unsplash.com/photo-1585551897231-77fe523c288a?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=687&q=80"
                      alt=""
                      class="aspect-[2/3] animate__animated animate__fadeInTopRight w-full rounded-xl bg-gray-900/5 object-cover shadow-lg">
                    <div class="pointer-events-none absolute inset-0 rounded-xl ring-1 ring-inset ring-gray-900/10">
                    </div>
                  </div>
                  <div class="relative">
                    <img
                      src="https://images.unsplash.com/photo-1562778612-e1e0cda9915c?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=687&q=80"
                      alt=""
                      class="aspect-[2/3] animate__animated animate__fadeInBottomRight w-full rounded-xl bg-gray-900/5 object-cover shadow-lg">
                    <div class="pointer-events-none absolute inset-0 rounded-xl ring-1 ring-inset ring-gray-900/10">
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>


  @livewireScripts
</body>

</html>
