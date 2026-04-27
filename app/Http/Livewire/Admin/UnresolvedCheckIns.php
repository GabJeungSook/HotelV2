<?php

namespace App\Http\Livewire\Admin;

use App\Models\CheckinDetail;
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
        // 2026-04-28 — query rewritten. Old query used `number_of_hours` which
        // is 0 for long-stay/extension guests, falsely flagging active guests
        // (with future check_out_at) as ghosts. New query uses check_out_at
        // (the authoritative planned-checkout column) with a 2 h grace window.
        $cutoff = now()->subHours(2);

        return CheckinDetail::where('is_check_out', 0)
            ->whereNotNull('check_out_at')
            ->where('check_out_at', '<', $cutoff)
            ->with(['room:id,number,status,type_id,floor_id', 'room.type:id,name', 'room.floor:id,number', 'guest:id,name'])
            ->orderBy('check_out_at', 'asc')
            ->get()
            ->map(function ($record) {
                $expectedOut = Carbon::parse($record->check_out_at);
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
        // 2026-04-28 — disabled. The Fix-All workflow caused an incident on
        // 2026-04-27 23:19 where 20 active guests were force-closed. UI button
        // is hidden; this guard blocks any direct/programmatic invocation.
        $this->dialog()->error(
            'Action Disabled',
            'The Fix All Records feature is under maintenance. Resolve ghost records manually with frontdesk verification. See docs/bugs/2026-04-28-fixall-unresolved-flips-active-extension-checkins.md.'
        );
    }

    public function fixAllGhostRecords()
    {
        // 2026-04-28 — disabled. Hard guard at the top so even direct Livewire
        // calls (bypassing the hidden UI button) cannot trigger the action.
        $this->dialog()->error(
            'Action Disabled',
            'The Fix All Records feature is under maintenance. This action cannot run until the detection query is corrected and per-record safety guards are added. See docs/bugs/2026-04-28-fixall-unresolved-flips-active-extension-checkins.md.'
        );
        return;
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
