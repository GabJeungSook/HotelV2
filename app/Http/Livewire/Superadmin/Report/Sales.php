<?php

namespace App\Http\Livewire\Superadmin\Report;

use App\Models\Branch;
use Livewire\Component;
use App\Models\Transaction;

class Sales extends Component
{
    public $branch_id = '';
     public $type = 'Overall Sales';
     public $showExtend = true;
     public $showAmenities = true;
     public $showFood = true;
     public $showDamages = true;
     public $showDeposits = true;

     public $totalSales = 0;

    public $extendedTransactions;
    public $amenitiesTransactions;
    public $foodTransactions;
    public $damagesTransactions;
    public $depositTransactions;
     public $date_from;
    public $date_to;
    public $shift;

    public function render()
    {
        $transactions = Transaction::query()
        ->whereHas('room.latestCheckInDetail', function ($query) {
            if ($this->date_from) {
                $query->whereDate('check_out_at', '>=', $this->date_from);
            }
            if ($this->date_to) {
                $query->whereDate('check_out_at', '<=', $this->date_to);
            }
            })
            ->whereHas('room.checkOutGuestReports', function ($query) {
            if ($this->shift) {
                $query->where('shift', $this->shift);
            }
            });

        if (!empty($this->branch_id)) {
            $transactions->where('branch_id', $this->branch_id);
        }

        switch ($this->type) {
            case 'Daily':
            $transactions->whereDate('paid_at', now()->format('Y-m-d'));
            break;
            case 'Weekly':
            $transactions->whereDate('paid_at', '>=', now()->startOfWeek());
            $transactions->whereDate('paid_at', '<=', now()->endOfWeek());
            break;
            case 'Monthly':
            $transactions->whereMonth('paid_at', now()->month);
            $transactions->whereYear('paid_at', now()->year);
            break;
            default:
        }

        $transactions = $transactions
            ->whereNotIn('transaction_type_id', [5, 7])
            ->selectRaw('room_id, SUM(paid_amount) as total_paid')
            ->groupBy('room_id')
            ->get();


        // $transactions = $transactions
        //     ->whereNotIn('transaction_type_id', [2, 5, 7])
        //     ->selectRaw('room_id, SUM(paid_amount) as total_paid')
        //     ->groupBy('room_id')
        //     ->get();

        // Get all extended transactions as a Collection
        $roomIds = $transactions->pluck('room_id');

        $this->extendedTransactions = Transaction::where('transaction_type_id', 6)
            ->when(!empty($this->branch_id), function ($query) {
            $query->where('branch_id', $this->branch_id);
            })
            ->when($roomIds->isNotEmpty(), function ($query) use ($roomIds) {
            $query->whereIn('room_id', $roomIds);
            })
            ->selectRaw('room_id, SUM(paid_amount) as total_paid')
            ->groupBy('room_id')
            ->get();

        $this->amenitiesTransactions = Transaction::where('transaction_type_id', 8)
            ->when(!empty($this->branch_id), function ($query) {
            $query->where('branch_id', $this->branch_id);
            })
            ->when($roomIds->isNotEmpty(), function ($query) use ($roomIds) {
            $query->whereIn('room_id', $roomIds);
            })
            ->selectRaw('room_id, SUM(paid_amount) as total_paid')
            ->groupBy('room_id')
            ->get();

        // Exclude voided POS room-charges so the per-room food total
        // doesn't double-count cancelled sales.
        $this->foodTransactions = Transaction::where('transaction_type_id', 9)
            ->whereNull('voided_at')
            ->when(!empty($this->branch_id), function ($query) {
            $query->where('branch_id', $this->branch_id);
            })
            ->when($roomIds->isNotEmpty(), function ($query) use ($roomIds) {
            $query->whereIn('room_id', $roomIds);
            })
            ->selectRaw('room_id, SUM(paid_amount) as total_paid')
            ->groupBy('room_id')
            ->get();

        $this->damagesTransactions = Transaction::where('transaction_type_id', 4)
            ->when(!empty($this->branch_id), function ($query) {
            $query->where('branch_id', $this->branch_id);
            })
            ->when($roomIds->isNotEmpty(), function ($query) use ($roomIds) {
            $query->whereIn('room_id', $roomIds);
            })
            ->selectRaw('room_id, SUM(paid_amount) as total_paid')
            ->groupBy('room_id')
            ->get();

        $this->depositTransactions = Transaction::where('transaction_type_id', 2)
            ->when(!empty($this->branch_id), function ($query) {
            $query->where('branch_id', $this->branch_id);
            })
            ->when($roomIds->isNotEmpty(), function ($query) use ($roomIds) {
            $query->whereIn('room_id', $roomIds);
            })
            ->selectRaw('room_id, SUM(paid_amount) as total_paid')
            ->groupBy('room_id')
            ->get();

         $roomAmount = 0;

        foreach ($transactions as $transaction) {
            $roomAmount += $transaction->room->latestCheckInDetail?->rate->amount;
        }

        $this->totalSales =
                ($this->showExtend ? ($this->extendedTransactions->sum('total_paid') ?? 0) : 0) +
                ($this->showAmenities ? ($this->amenitiesTransactions->sum('total_paid') ?? 0) : 0) +
                ($this->showFood ? ($this->foodTransactions->sum('total_paid') ?? 0) : 0) +
                ($this->showDamages ? ($this->damagesTransactions->sum('total_paid') ?? 0) : 0) +
                ($this->showDeposits ? ($this->depositTransactions->sum('total_paid') ?? 0) : 0) +
                $roomAmount;

        return view('livewire.superadmin.report.sales', [
            'branches' => Branch::all(),
            'transactions' => $transactions,
            'totalSales' => $this->totalSales,
        ]);
    }
}
