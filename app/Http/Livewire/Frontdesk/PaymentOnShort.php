<?php

namespace App\Http\Livewire\Frontdesk;

use App\Models\PaymentOnShort as PaymentOnShortModel;
use App\Models\ShiftLog;
use Livewire\Component;
use WireUi\Traits\Actions;

class PaymentOnShort extends Component
{
    use Actions;

    public $add_modal = false;
    public $view_modal = false;
    public $total;
    public $user_id;
    public $current_shift;

    public $name;
    public $description;
    public $amount;

    public function mount()
    {
        $this->user_id = auth()->user()->id;
        $this->current_shift = ShiftLog::where('frontdesk_id', $this->user_id)
            ->where('cash_drawer_id', auth()->user()->cash_drawer_id)
            ->whereNull('time_out')
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$this->current_shift) {
            auth()->user()->update(['cash_drawer_id' => null]);
            return redirect()->route('frontdesk.dashboard');
        }
    }

    public function render()
    {
        $this->total = PaymentOnShortModel::where('branch_id', auth()->user()->branch_id)
            ->where('user_id', $this->user_id)
            ->where('shift_log_id', $this->current_shift->id)
            ->sum('amount');

        return view('livewire.frontdesk.payment-on-short', [
            'total' => $this->total,
            'payments' => PaymentOnShortModel::where('branch_id', auth()->user()->branch_id)
                ->where('user_id', $this->user_id)
                ->where('shift_log_id', $this->current_shift->id)
                ->orderBy('created_at', 'desc')
                ->get(),
        ]);
    }

    public function savePaymentOnShort()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:1',
        ], [
            'name.required' => 'The name is required.',
            'name.max' => 'The name may not be greater than 255 characters.',
            'amount.required' => 'The amount is required.',
            'amount.numeric' => 'The amount must be a number.',
            'amount.min' => 'The amount must be at least 1.',
        ]);

        PaymentOnShortModel::create([
            'user_id' => $this->user_id,
            'shift_log_id' => $this->current_shift->id,
            'branch_id' => auth()->user()->branch_id,
            'name' => $this->name,
            'description' => $this->description,
            'amount' => $this->amount,
        ]);

        $this->add_modal = false;
        $this->reset(['name', 'description', 'amount']);

        $this->notification()->success(
            $title = 'Payment on Short Added',
            $description = 'The payment on short has been successfully added.'
        );
    }

    public function openAddModal()
    {
        $this->reset(['name', 'description', 'amount']);
        $this->add_modal = true;
    }

    public function openViewModal()
    {
        $this->view_modal = true;
    }
}
