<x-supervisor-layout>
    @section('breadcrumbs')
        Reports
    @endsection

    {{-- HOMI Logo --}}
    <div class="flex justify-start px-4 sm:px-8 py-4">
        <img src="{{ asset('images/homiLogo.png') }}" class="h-8 sm:h-10" alt="HOMI">
    </div>

    <div class="px-4 sm:px-8 pb-8">
        <livewire:back-office.report-hub />

        <script>
            function printOut(data) {
                var mywindow = window.open('', '', 'height=1000,width=1000');
                mywindow.document.write('<html><head>');
                mywindow.document.write('<title></title>');
                mywindow.document.write(`<link rel="stylesheet" href="{{ Vite::asset('resources/css/app.css') }}" />`);
                mywindow.document.write('</head><body >');
                mywindow.document.write(data);
                mywindow.document.write('</body></html>');

                mywindow.document.close();
                mywindow.focus();
                setTimeout(() => {
                    mywindow.print();
                    return true;
                }, 1000)
            }
        </script>
    </div>
</x-supervisor-layout>
