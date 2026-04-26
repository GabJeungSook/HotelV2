@section('breadcrumbs')
  Stock Movements
@endsection
<x-back-office-layout>
    <div class="px-4">
        {{-- Reuses the same admin component so back office sees the same
             audit log of every inventory change with no code duplication. --}}
        <livewire:admin.manage.stock-movements />
    </div>
</x-back-office-layout>
