<?php

namespace App\Http\Livewire\Frontdesk;


use App\Models\User;
use Livewire\Component;
use WireUi\Traits\Actions;
use App\Models\Remittance as RemittanceModel;
use App\Models\ShiftLog;

class Remittance extends Component
{
    use Actions;
    public $add_modal = false;
    public $total;
    public $user_id, $shift;
    public $current_shift;
    public $remittance_amount, $description;

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

        $this->shift = $this->current_shift->shift;
    }
    public function render()
    {
        $this->total = RemittanceModel::where('branch_id', auth()->user()->branch_id)
                                ->where('user_id', $this->user_id)
                                ->where('shift_log_id', $this->current_shift->id)
                                ->sum('total_remittance');
        return view('livewire.frontdesk.remittance', [
            'total' => $this->total,
            'remittances' => RemittanceModel::where('branch_id', auth()->user()->branch_id)
                                ->where('user_id', $this->user_id)
                                ->where('shift_log_id', $this->current_shift->id)
                                ->get(),
        ]);
    }

    public function saveRemittance()
    {
        $this->validate([
            'remittance_amount' => 'required|numeric|min:1',
            'description' => 'required|string|max:255',
        ],
        [
            'remittance_amount.required' => 'The remittance amount is required.',
            'remittance_amount.numeric' => 'The remittance amount must be a number.',
            'remittance_amount.min' => 'The remittance amount must be at least 1.',
            'description.required' => 'The description is required.',
            'description.max' => 'The description may not be greater than 255 characters.',
        ]);

        RemittanceModel::create([
            'user_id' => $this->user_id,
            'shift_log_id' => $this->current_shift->id,
            'branch_id' => auth()->user()->branch_id,
            'total_remittance' => $this->remittance_amount,
            'description' => $this->description,
        ]);

        $this->add_modal = false;
        $this->remittance_amount = null;
        $this->description = null;

        $this->notification()->success(
            $title = 'Remittance Added',
            $description = 'The remittance has been successfully added.'
        );
    }

        public function redirectReport()
    {
        return redirect()->route('frontdesk.remittance-report');
    }
}
