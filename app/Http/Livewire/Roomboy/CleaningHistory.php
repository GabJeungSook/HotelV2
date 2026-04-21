<?php

namespace App\Http\Livewire\Roomboy;

use Livewire\Component;

class CleaningHistory extends Component
{
    public $histories;
    public $date_from;
    public $date_to;

    public function render()
    {
        $query = auth()->user()->cleaningHistories()
            ->orderBy('created_at', 'desc');

        if ($this->date_from) {
            $query->whereDate('start_time', '>=', $this->date_from);
        }

        if ($this->date_to) {
            $query->whereDate('end_time', '<=', $this->date_to);
        }

        $this->histories = $query->get();

        return view('livewire.roomboy.cleaning-history', [
            'histories' => $this->histories
        ]);
    }
}
