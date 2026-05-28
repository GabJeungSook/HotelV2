<?php

namespace App\Http\Livewire\Frontdesk;

use Livewire\Component;
use App\Models\Frontdesk;
use App\Models\AssignedFrontdesk as assignFrontdeskModel;
use App\Models\ShiftLog;
use App\Models\CashDrawer;
use App\Models\User;
use App\Support\ShiftResolver;
use WireUi\Traits\Actions;
use Illuminate\Support\Facades\Auth;
use DB;
class AssignedFrontdesk extends Component
{
    use Actions;
    public $get_frontdesk = [];
    public $partner_modal = false;
    public $name;
    public $cash_drawers;
    public $drawer;
    public $shift;

    // Shift capacity check
    public $showExistingSessionModal = false;
    public $shiftUsers = [];

    // Cross-day shift confirmation
    public bool $crossDayConfirmModal = false;
    public ?string $crossDayTargetDate = null;
    public bool $crossDayAcknowledged = false;

    // Shift override warning
    public bool $shiftOverrideWarning = false;
    public bool $shiftOverrideAcknowledged = false;
    public string $autoDetectedShift = 'AM';

    public function mount()
    {
        $this->shift = ShiftResolver::fromClock(now());
        $this->autoDetectedShift = $this->shift;

        $assigned = Frontdesk::where('branch_id', auth()->user()->branch_id)
            ->where('user_id', auth()->user()->id)->get();
        foreach ($assigned as $item) {
            array_push($this->get_frontdesk, $item->id);
        }
        $shifts = ShiftLog::whereHas('frontdesk', function($query){
            $query->where('branch_id', auth()->user()->branch_id);
        })->get();

        $this->cash_drawers = CashDrawer::where('branch_id', $assigned->first()->branch_id)
            ->where('is_active', 0)
            ->get();

        // Check if this shift already has 2 frontdesk users
        $this->checkShiftCapacity();
    }

    public function updatedShift($value)
    {
        if (! in_array($value, ['AM', 'PM'], true)) {
            $this->shift = 'AM';
        }

        $this->showExistingSessionModal = false;
        $this->shiftUsers = [];
        $this->crossDayAcknowledged = false;
        $this->crossDayConfirmModal = false;
        $this->crossDayTargetDate = null;
        $this->shiftOverrideWarning = false;
        $this->shiftOverrideAcknowledged = false;
        $this->checkShiftCapacity();
    }

    public function confirmCrossDay()
    {
        $this->crossDayAcknowledged = true;
        $this->crossDayConfirmModal = false;
        $this->saveFrontdesk();
    }

    public function cancelCrossDay()
    {
        $this->crossDayConfirmModal = false;
        $this->crossDayTargetDate = null;
        $this->crossDayAcknowledged = false;
    }

    public function confirmShiftOverride()
    {
        $this->shiftOverrideAcknowledged = true;
        $this->shiftOverrideWarning = false;
        $this->saveFrontdesk();
    }

    public function cancelShiftOverride()
    {
        $this->shift = $this->autoDetectedShift;
        $this->shiftOverrideWarning = false;
        $this->shiftOverrideAcknowledged = false;
    }

    public function checkShiftCapacity()
    {
        $now = now();
        $currentHour = $now->hour;

        // Shift boundaries: AM = 8AM-8PM, PM = 8PM-8AM
        // Transition window: 1 hour before shift end, new logins count as next shift
        // But the query checks against actual shift boundaries
        if ($this->shift === 'AM') {
            // AM shift: 8AM - 8PM today
            $shiftStart = $now->copy()->setTime(8, 0, 0);
            $shiftEnd = $now->copy()->setTime(20, 0, 0);
        } else {
            // PM shift: 8PM - 8AM
            if ($currentHour < 8) {
                // After midnight: shift started yesterday at 8PM
                $shiftStart = $now->copy()->subDay()->setTime(20, 0, 0);
                $shiftEnd = $now->copy()->setTime(8, 0, 0);
            } else {
                // Before midnight: shift starts today at 8PM
                $shiftStart = $now->copy()->setTime(20, 0, 0);
                $shiftEnd = $now->copy()->addDay()->setTime(8, 0, 0);
            }
        }

        // Get ALL logins for this shift period (including those who logged out early)
        $shiftLogs = ShiftLog::where('branch_id', auth()->user()->branch_id)
            ->where('shift', $this->shift)
            ->where('frontdesk_id', '!=', auth()->user()->id)
            ->whereBetween('time_in', [$shiftStart, $shiftEnd])
            ->with('frontdesk:id,name')
            ->get();

        $activeLogs = $shiftLogs->filter(fn ($log) => is_null($log->time_out));

        // Block only when 2 frontdesk users are currently active
        if ($activeLogs->count() >= 2) {
            $this->shiftUsers = $activeLogs
                ->map(fn ($log) => ['name' => $log->frontdesk?->name ?? 'Unknown'])
                ->values()
                ->toArray();
            $this->showExistingSessionModal = true;
        }
    }

    public function logoutCurrentUser()
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function render()
    {
        return view('livewire.frontdesk.assigned-frontdesk', [
            'frontdesks' => Frontdesk::where(
                'branch_id',
                auth()->user()->branch_id
            )
                ->WhereNotIn('id', $this->get_frontdesk)
                ->get(),
        ]);
    }

    public function assignFrontdesk($frontdesk_id)
    {
        if (count($this->get_frontdesk) >= 1) {
            $this->dialog()->error(
                $title = 'Sorry',
                $description = 'you have already assigned a frontdesk'
            );
        } else {
            array_push($this->get_frontdesk, $frontdesk_id);
        }
    }

    public function removeFrontdesk($frontdesk_id)
    {
        $this->get_frontdesk = array_diff($this->get_frontdesk, [
            $frontdesk_id,
        ]);
    }

    public function saveFrontdesk()
    {
        // Block if existing session is still open
        if ($this->showExistingSessionModal) {
            return;
        }

        // Warn when the selected shift differs from the auto-detected shift
        if ($this->shift !== $this->autoDetectedShift && ! $this->shiftOverrideAcknowledged) {
            $this->shiftOverrideWarning = true;
            return;
        }

        // Confirm with the frontdesk when the chosen shift implies a different calendar day
        $targetDate = ShiftResolver::deriveShiftDate(now(), $this->shift);
        if (! $targetDate->isToday() && ! $this->crossDayAcknowledged) {
            $this->crossDayTargetDate = $targetDate->format('F j, Y');
            $this->crossDayConfirmModal = true;
            return;
        }

        if($this->drawer)
        {
         DB::beginTransaction();

            // Auto-close any stale shifts open longer than 14 hours (forgotten clock-outs)
            $staleLogs = ShiftLog::whereNull('time_out')
                ->where('time_in', '<', now()->subHours(14))
                ->get(['id', 'frontdesk_id', 'cash_drawer_id']);

            if ($staleLogs->isNotEmpty()) {
                ShiftLog::whereIn('id', $staleLogs->pluck('id'))->update([
                    'time_out' => DB::raw('DATE_ADD(time_in, INTERVAL 14 HOUR)'),
                    'end_cash' => DB::raw('COALESCE(NULLIF(end_cash, 0), 1.00)'),
                ]);

                $staleDrawerIds = $staleLogs->pluck('cash_drawer_id')->filter()->unique();
                if ($staleDrawerIds->isNotEmpty()) {
                    CashDrawer::whereIn('id', $staleDrawerIds)->update(['is_active' => false]);
                }

                $staleUserIds = $staleLogs->pluck('frontdesk_id')->filter()->unique();
                if ($staleUserIds->isNotEmpty()) {
                    User::whereIn('id', $staleUserIds)->update(['cash_drawer_id' => null]);
                }
            }

            // Auto-close any existing open shift for this user
            $openShift = ShiftLog::where('frontdesk_id', auth()->user()->id)
                ->whereNull('time_out')
                ->first();

            if ($openShift) {
                // Release the prior drawer unless the user is re-selecting the same one
                if ($openShift->cash_drawer_id && $openShift->cash_drawer_id != $this->drawer) {
                    CashDrawer::where('id', $openShift->cash_drawer_id)
                        ->update(['is_active' => false]);
                }
                $openShift->update([
                    'time_out' => now(),
                    'end_cash' => $openShift->end_cash ?: 1.00,
                ]);
            }

             array_push($this->get_frontdesk, 'N/A');
            $frontdesk_ids = json_encode($this->get_frontdesk);
            ShiftLog::create([
                'branch_id' => auth()->user()->branch_id,
                'frontdesk_id' => auth()->user()->id,
                'time_in' => \Carbon\Carbon::now(),
                'frontdesk_ids' => $frontdesk_ids,
                'cash_drawer_id' => $this->drawer,
                'shift' => $this->shift,
            ]);

            auth()
                ->user()
                ->update([
                    'time_in' => \Carbon\Carbon::now(),
                    'assigned_frontdesks' => $frontdesk_ids,
                    'cash_drawer_id' => $this->drawer,
                    'shift' => $this->shift,
                ]);

                CashDrawer::where('id', $this->drawer)->update([
                    'is_active' => true,
                ]);

         DB::commit();
          $this->dialog()->success(
            $title = 'Success',
            $description = 'Cash drawer selected successfully'
        );

        return redirect()->route('frontdesk.beginning-cash');
        }else{
            $this->dialog()->error(
            $title = 'Oops!',
            $description = 'Please select a cash drawer to proceed.'
        );
        }
    }

    public function savePartner()
    {
        $this->validate([
            'name' => 'required',
        ]);

        // Auto-close any existing open shift for this user
        $openShift = ShiftLog::where('frontdesk_id', auth()->user()->id)
            ->whereNull('time_out')
            ->first();

        if ($openShift) {
            $openShift->update([
                'time_out' => now(),
                'end_cash' => $openShift->end_cash ?: 1.00,
            ]);
        }

        array_push($this->get_frontdesk, $this->name);
        $haha = json_encode($this->get_frontdesk);
        DB::beginTransaction();
        ShiftLog::create([
            'branch_id' => auth()->user()->branch_id,
            'frontdesk_id' => auth()->user()->id,
            'time_in' => \Carbon\Carbon::now(),
            'frontdesk_ids' => json_encode($this->get_frontdesk),
            'cash_drawer_id' => auth()->user()->cash_drawer_id,
            'shift' => $this->shift,
        ]);

        auth()
            ->user()
            ->update([
                'time_in' => \Carbon\Carbon::now(),
                'assigned_frontdesks' => $haha,
            ]);

        DB::commit();
        $this->dialog()->success(
            $title = 'Success',
            $description = 'Frontdesk assigned successfully'
        );
        return redirect()->route('frontdesk.dashboard');
    }
}
