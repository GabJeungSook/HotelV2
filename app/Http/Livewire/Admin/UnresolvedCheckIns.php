<?php

namespace App\Http\Livewire\Admin;

use App\Models\CheckInDetail;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use WireUi\Traits\Actions;

class UnresolvedCheckIns extends Component
{
    use Actions;

    public $showConfirmModal = false;
    public $guardsEnabled = false;

    public function mount()
    {
        $this->checkGuardsStatus();
    }

    public function checkGuardsStatus()
    {
        // Check if guards are enabled by reading the file content
        $kioskFile = app_path('Http/Livewire/Kiosk/CheckIn.php');
        if (file_exists($kioskFile)) {
            $content = file_get_contents($kioskFile);
            // If the guard code is NOT commented out, guards are enabled
            $this->guardsEnabled = strpos($content, '/* TEMPORARILY DISABLED') === false
                || strpos($content, '$openCheckin = CheckinDetail::where') !== false
                && strpos($content, '// $openCheckin = CheckinDetail::where') === false
                && strpos($content, '/* TEMPORARILY DISABLED') === false;

            // More accurate check: look for uncommented guard code
            $this->guardsEnabled = preg_match('/^\s*\$openCheckin\s*=\s*CheckinDetail::where/m', $content) === 1;
        }
    }

    public function getGhostRecordsProperty()
    {
        $cutoff1day = now()->subDays(1);

        return CheckInDetail::where('is_check_out', 0)
            ->whereRaw('DATE_ADD(check_in_at, INTERVAL number_of_hours HOUR) < ?', [$cutoff1day])
            ->with(['room:id,number,status,type_id,floor_id', 'room.type:id,name', 'room.floor:id,number', 'guest:id,name'])
            ->orderBy('check_in_at', 'asc')
            ->get()
            ->map(function ($record) {
                $expectedOut = Carbon::parse($record->check_in_at)->addHours($record->number_of_hours);
                $daysOverdue = round(now()->diffInHours($expectedOut) / 24, 1);

                return [
                    'id' => $record->id,
                    'room_number' => $record->room->number ?? 'N/A',
                    'floor_number' => $record->room->floor->number ?? 'N/A',
                    'room_status' => $record->room->status ?? 'N/A',
                    'room_type' => $record->room->type->name ?? 'N/A',
                    'guest_name' => $record->guest->name ?? 'Unknown',
                    'check_in_at' => Carbon::parse($record->check_in_at)->format('M d, Y H:i'),
                    'expected_out' => $expectedOut->format('M d, Y H:i'),
                    'days_overdue' => $daysOverdue,
                    'deposit' => $record->total_deposit ?? 0,
                    'will_block' => in_array($record->room->status ?? '', ['Available', 'Uncleaned', 'Cleaning']),
                ];
            });
    }

    public function getBlockedRoomsProperty()
    {
        return collect($this->ghostRecords)->filter(fn($r) => $r['will_block']);
    }

    public function getSummaryProperty()
    {
        $records = $this->ghostRecords;

        return [
            'total_ghosts' => $records->count(),
            'total_deposits' => $records->sum('deposit'),
            'blocked_count' => $records->where('will_block', true)->count(),
            'occupied_count' => $records->where('room_status', 'Occupied')->count(),
        ];
    }

    public function confirmFix()
    {
        $this->showConfirmModal = true;
    }

    public function fixAllGhostRecords()
    {
        $cutoff1day = now()->subDays(1);

        $ghosts = CheckInDetail::where('is_check_out', 0)
            ->whereRaw('DATE_ADD(check_in_at, INTERVAL number_of_hours HOUR) < ?', [$cutoff1day])
            ->get();

        $fixedCount = 0;

        DB::beginTransaction();
        try {
            foreach ($ghosts as $record) {
                // Calculate expected checkout time
                $expectedOut = Carbon::parse($record->check_in_at)->addHours($record->number_of_hours);
                // Add 30 minutes buffer for checkout time
                $checkoutTime = $expectedOut->copy()->addMinutes(30);

                // Update the checkin_detail record
                $record->update([
                    'is_check_out' => true,
                    'check_out_at' => $checkoutTime,
                ]);

                $fixedCount++;
            }

            DB::commit();

            $this->showConfirmModal = false;

            $this->dialog()->success(
                'Ghost Records Fixed',
                "Successfully resolved {$fixedCount} ghost check-in records. Checkout times have been backdated to their expected checkout time + 30 minutes."
            );

        } catch (\Exception $e) {
            DB::rollBack();

            $this->dialog()->error(
                'Error',
                'Failed to fix ghost records: ' . $e->getMessage()
            );
        }
    }

    public function render()
    {
        return view('livewire.admin.unresolved-check-ins', [
            'ghostRecords' => $this->ghostRecords,
            'blockedRooms' => $this->blockedRooms,
            'summary' => $this->summary,
        ])->layout('components.admin-layout');
    }
}
