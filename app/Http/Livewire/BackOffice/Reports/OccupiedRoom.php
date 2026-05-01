<?php

namespace App\Http\Livewire\BackOffice\Reports;

use Livewire\Component;
use App\Models\Transaction;

class OccupiedRoom extends Component
{
    public $date;
    public function render()
    {
        return view('livewire.back-office.reports.occupied-room', [
            'occupieds' => Transaction::where('branch_id', auth()->user()->branch_id)
                ->where('transaction_type_id', 1)
                ->when($this->date, function ($query) {
                    $query->whereDate('created_at', $this->date);
                })
                ->whereHas('guest.room', function ($query) {
                    $query
                        ->where('branch_id', auth()->user()->branch_id)
                        ->where('status', 'Occupied');
                })
                ->get(),
        ]);
    }
}
