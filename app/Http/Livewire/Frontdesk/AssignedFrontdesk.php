<?php

namespace App\Http\Livewire\Frontdesk;

use Livewire\Component;
use App\Models\Frontdesk;
use App\Models\AssignedFrontdesk as assignFrontdeskModel;
use App\Models\ShiftLog;
use App\Models\CashDrawer;
use WireUi\Traits\Actions;
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

    // Existing session detection
    public $existingShift = null;
    public $existingUserName = '';
    public $showExistingSessionModal = false;

    public function mount()
    {
        $currentHour = now()->hour;
        $this->shift = ($currentHour >= 8 && $currentHour < 20) ? 'AM' : 'PM';

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

        // Check for existing open session from another user in the same branch
        $this->checkExistingSession();
    }

    public function checkExistingSession()
    {
        $openShift = ShiftLog::where('branch_id', auth()->user()->branch_id)
            ->whereNull('time_out')
            ->where('frontdesk_id', '!=', auth()->user()->id)
            ->with('frontdesk')
            ->orderByDesc('time_in')
            ->first();

        if ($openShift) {
            $this->existingShift = $openShift;
            $this->existingUserName = $openShift->frontdesk?->name ?? 'Unknown';
            $this->showExistingSessionModal = true;
        }
    }

    public function closeExistingSession()
    {
        if ($this->existingShift) {
            DB::beginTransaction();

            $shift = ShiftLog::find($this->existingShift->id);
            if ($shift) {
                $shift->update([
                    'time_out' => now(),
                    'end_cash' => $shift->end_cash ?: 0,
                ]);

                // Deactivate their cash drawer
                if ($shift->cash_drawer) {
                    $shift->cash_drawer->update(['is_active' => false]);
                }
            }

            DB::commit();

            $this->existingShift = null;
            $this->existingUserName = '';
            $this->showExistingSessionModal = false;

            // Reload available cash drawers (the closed one may now be available)
            $this->cash_drawers = CashDrawer::where('branch_id', auth()->user()->branch_id)
                ->where('is_active', 0)
                ->get();

            $this->notification()->success(
                $title = 'Session Closed',
                $description = 'The previous session has been closed. You may now proceed.'
            );
        }
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

        if($this->drawer)
        {
         DB::beginTransaction();

            // Auto-close any stale shifts open longer than 14 hours (forgotten clock-outs)
            ShiftLog::whereNull('time_out')
                ->where('time_in', '<', now()->subHours(14))
                ->update([
                    'time_out' => DB::raw('DATE_ADD(time_in, INTERVAL 14 HOUR)'),
                    'end_cash' => DB::raw('COALESCE(NULLIF(end_cash, 0), 1.00)'),
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
