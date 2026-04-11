<?php

namespace App\Http\Livewire\Frontdesk;
use App\Models\CashOnDrawer;
use App\Models\Expense;
use App\Models\Remittance;
use App\Models\ShiftLog;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use WireUi\Traits\Actions;
use DB;

class CashOnHand extends Component
{
    use Actions;
    public $total_transactions = 0;
    public $total_expenses = 0;
    public $total_remittances = 0;
    public $total_deposits = 0;
    public $logout_modal = false;
    public $withdraw_modal = false;
    public $remittance;
    public $description;
    public $authorization_modal = false;
    public $code;
    public $current_shift;

    public function mount()
    {
        $this->current_shift = ShiftLog::where('frontdesk_id', auth()->user()->id)
                                ->where('cash_drawer_id', auth()->user()->cash_drawer_id)
                                ->whereNull('time_out')
                                ->orderBy('created_at', 'desc')
                                ->first();
        $this->total_transactions = CashOnDrawer::where('branch_id', auth()->user()->branch_id)
            ->where('cash_drawer_id', auth()->user()->cash_drawer_id)
            ->where('transaction_date', now()->toDateString())
            ->whereNot('transaction_type', 'deposit')
            ->sum('amount');

        $this->total_expenses = $this->current_shift
            ? Expense::where('branch_id', auth()->user()->branch_id)
                ->where('shift_log_id', $this->current_shift->id)
                ->where('user_id', auth()->user()->id)
                ->sum('amount')
            : 0;

        $this->total_remittances = $this->current_shift
            ? Remittance::where('branch_id', auth()->user()->branch_id)
                ->where('shift_log_id', $this->current_shift->id)
                ->where('user_id', auth()->user()->id)
                ->sum('total_remittance')
            : 0;

        $deposits = CashOnDrawer::where('branch_id', auth()->user()->branch_id)
            ->where('cash_drawer_id', auth()->user()->cash_drawer_id)
            ->where('transaction_date', now()->toDateString())
            ->where('transaction_type', 'deposit')
            ->sum('amount');
        $deductions = CashOnDrawer::where('branch_id', auth()->user()->branch_id)
            ->where('cash_drawer_id', auth()->user()->cash_drawer_id)
            ->where('transaction_date', now()->toDateString())
            ->sum('deduction');

        $this->total_deposits = $deposits - $deductions;

    }

    public function confirmLogout()
    {
        $this->validate([
            'remittance' => 'required|numeric|min:0',
        ], [
            'remittance.required' => 'Please enter the ending cash amount.',
            'remittance.numeric' => 'The ending cash must be a valid number.',
            'remittance.min' => 'The ending cash cannot be negative.',
        ]);

        $this->logout_modal = true;
    }

    public function enterPasscode()
    {
       $this->logout_modal = false;
       $this->authorization_modal = true;

    }

    public function endShiftConfirm()
    {
        $this->validate([
             'code' => 'required|numeric',
        ], [
            'code.required' => 'Please enter your passcode.',
            'code.numeric' => 'The passcode must be a number.',
        ]);

        if(auth()->user()->frontdesk->passcode !== $this->code) {
            $this->dialog()->error(
                $title = 'Unauthorized',
                $description = 'The passcode you entered is incorrect. Please try again.'
            );
            return;

        }else{
            DB::beginTransaction();
            $shift = ShiftLog::where('frontdesk_id', auth()->user()->id)
                ->where('cash_drawer_id', auth()->user()->cash_drawer_id)
                ->whereNull('time_out')
                ->first();
            $shift->end_cash = $this->remittance;
            $shift->description = $this->description;
            $shift->total_expenses = $this->total_expenses;
            $shift->total_remittances = $this->total_remittances;
            $shift->save();

            //deactivate drawer
            $shift->cash_drawer->update([
                'is_active' => false,
            ]);
            DB::commit();

            Auth::logout();

            request()->session()->invalidate();
            request()->session()->regenerateToken();

            return redirect()->route('login');
        }
      
    }

    public function render()
    {
        return view('livewire.frontdesk.cash-on-hand');
    }


}
