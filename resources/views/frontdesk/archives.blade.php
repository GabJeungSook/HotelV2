<x-frontdesk-layout>
    <div>
        {{-- Reuses the back-office archives Livewire component so frontdesk
             sees the same shift-scoped reports (including Big Boss POS
             Report) as supervisor and back office. The component is
             branch-scoped via auth()->user()->branch_id. --}}
        <livewire:back-office.archives />

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
</x-frontdesk-layout>
