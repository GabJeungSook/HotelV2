<?php

namespace App\Http\Livewire\BackOffice\Reports;

use Livewire\Component;
use App\Models\Guest as guestModel;

class Guest extends Component
{
    public $type;
    public $date;
    public function render()
    {
        return view('livewire.back-office.reports.guest', [
            'guests' => $this->loadQuery(),
        ]);
    }

    public function loadQuery()
    {
        switch ($this->type) {
            case 1:
                return guestModel::where('branch_id', auth()->user()->branch_id)
                    ->with('transactions')
                    ->when($this->date, function ($query) {
                        $query->whereDate('created_at', $this->date);
                    })
                    ->get();
            case 2:
                return guestModel::where('branch_id', auth()->user()->branch_id)
                    ->whereDate('created_at', now())
                    ->get();
            case 3:
                return guestModel::where('branch_id', auth()->user()->branch_id)
                    ->whereHas('transactions', function ($query) {
                        $query->where('transaction_type_id', 6);
                    })
                    ->when($this->date, function ($query) {
                        $query->whereDate('created_at', $this->date);
                    })
                    ->get();
        }
    }

    public function updatedType()
    {
        $this->date = null;
    }
}
