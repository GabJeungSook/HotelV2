<?php

namespace App\Http\Livewire\Admin;

use Livewire\Component;
use App\Models\ShiftLog;
use App\Models\Transaction;
use App\Models\NewGuestReport;
use App\Models\ExtendedGuestReport;
use App\Models\CheckOutGuestReport;
use App\Models\CashOnDrawer;
use App\Models\Expense;
use App\Models\ActivityLog;
use WireUi\Traits\Actions;
use DB;

class ShiftLogCorrection extends Component
{
    use Actions;

    public $date;
    public $editingShiftLogId = null;
    public $newShift = null;
    public $reason = '';
    public bool $editModal = false;

    public function mount()
    {
        $this->date = now()->toDateString();
    }

    public function openEdit($shiftLogId)
    {
        $log = ShiftLog::find($shiftLogId);
        if (! $log) return;

        $this->editingShiftLogId = $log->id;
        $this->newShift = $log->shift === 'AM' ? 'PM' : 'AM';
        $this->reason = '';
        $this->editModal = true;
    }

    public function closeEdit()
    {
        $this->editModal = false;
        $this->editingShiftLogId = null;
        $this->newShift = null;
        $this->reason = '';
    }

    public function updateShift()
    {
        $this->validate([
            'reason' => 'required|string|min:3',
            'newShift' => 'required|in:AM,PM',
        ]);

        $log = ShiftLog::with('frontdesk')->find($this->editingShiftLogId);
        if (! $log) {
            $this->notification()->error('Shift log not found.');
            return;
        }

        $oldShift = $log->shift;
        if ($oldShift === $this->newShift) {
            $this->notification()->info('Shift is already ' . $this->newShift . '.');
            $this->closeEdit();
            return;
        }

        $frontdeskName = $log->frontdesk?->name ?? 'Unknown';

        DB::beginTransaction();

        // 1. Update shift_logs
        $log->update(['shift' => $this->newShift]);

        // 2. Update transactions
        Transaction::where('shift_log_id', $log->id)
            ->update(['shift' => $this->newShift]);

        // 3. Update new_guest_reports via checkin_detail_id
        $checkinDetailIds = Transaction::where('shift_log_id', $log->id)
            ->where('transaction_type_id', 1)
            ->pluck('checkin_detail_id')
            ->filter()
            ->toArray();

        if (! empty($checkinDetailIds)) {
            NewGuestReport::whereIn('checkin_details_id', $checkinDetailIds)
                ->update(['shift' => $this->newShift]);
        }

        // 4. Update extended_guest_reports
        $extendCheckinIds = Transaction::where('shift_log_id', $log->id)
            ->where('transaction_type_id', 6)
            ->pluck('checkin_detail_id')
            ->filter()
            ->toArray();

        if (! empty($extendCheckinIds)) {
            ExtendedGuestReport::whereIn('checkin_details_id', $extendCheckinIds)
                ->update(['shift' => $this->newShift]);
        }

        // 5. Update check_out_guest_reports
        $allCheckinIds = Transaction::where('shift_log_id', $log->id)
            ->pluck('checkin_detail_id')
            ->filter()
            ->unique()
            ->toArray();

        if (! empty($allCheckinIds)) {
            CheckOutGuestReport::whereIn('checkin_details_id', $allCheckinIds)
                ->update(['shift' => $this->newShift]);
        }

        // 6. Update cash_on_drawers
        if ($log->time_in && $log->time_out) {
            CashOnDrawer::where('frontdesk_id', $log->frontdesk?->id)
                ->where('branch_id', $log->branch_id)
                ->whereBetween('created_at', [$log->time_in, $log->time_out])
                ->update(['shift' => $this->newShift]);
        }

        // 7. Update expenses
        Expense::where('shift_log_id', $log->id)
            ->update(['shift' => $this->newShift]);

        // 8. Activity log
        ActivityLog::create([
            'branch_id' => auth()->user()->branch_id,
            'user_id' => auth()->user()->id,
            'activity' => 'Shift Correction',
            'description' => "Changed shift log #{$log->id} ({$frontdeskName}) from {$oldShift} to {$this->newShift}. Reason: {$this->reason}",
        ]);

        DB::commit();

        $this->closeEdit();
        $this->notification()->success('Shift corrected successfully. All related records have been updated.');
    }

    public function render()
    {
        $branchId = auth()->user()->branch_id;

        $shiftLogs = ShiftLog::where('branch_id', $branchId)
            ->whereNotNull('time_out')
            ->when($this->date, fn($q) => $q->whereDate('time_in', $this->date))
            ->with('frontdesk:id,name')
            ->withCount('transactions')
            ->orderByDesc('time_in')
            ->get();

        return view('livewire.admin.shift-log-correction', [
            'shiftLogs' => $shiftLogs,
        ]);
    }
}
