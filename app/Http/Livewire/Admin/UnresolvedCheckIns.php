<?php

namespace App\Http\Livewire\Admin;

use App\Models\ActivityLog;
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

    /**
     * 2026-04-28 — INCIDENT MITIGATION FLAG
     *
     * The Fix-All workflow caused 20 active guests to be force-closed on
     * 2026-04-27 23:19. The detection query and action below have since
     * been corrected (use check_out_at instead of number_of_hours, preserve
     * check_out_at on close, add per-record safety guard, add ActivityLog).
     *
     * The action remains disabled at the application level until a per-row
     * confirmation UI is built that requires admin to acknowledge each
     * record before the bulk action proceeds. This prevents another
     * single-click bulk-mistake of the same shape.
     *
     * To re-enable when the per-row confirmation UI ships:
     *   1. Set $fixActionEnabled to true here.
     *   2. Restore the active "Fix All Records" button in the view (lines
     *      78-89 of unresolved-check-ins.blade.php — currently shows a
     *      disabled gray button).
     *   3. Restore the desktop sidebar item in admin-layout.blade.php
     *      (lines 924-960 — currently commented out).
     *   4. Test on staging with multiple long-stay guests before deploying.
     */
    public bool $fixActionEnabled = false;

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
        if (! $this->fixActionEnabled) {
            $this->dialog()->error(
                'Action Disabled',
                'The Fix All Records feature is under maintenance. Resolve ghost records manually with frontdesk verification. See docs/bugs/2026-04-28-fixall-unresolved-flips-active-extension-checkins.md.'
            );
            return;
        }

        $this->showConfirmModal = true;
    }

    /**
     * Force-close ghost check-in records.
     *
     * Detection criteria (corrected 2026-04-28):
     *   - is_check_out = 0 (record still flagged active)
     *   - check_out_at IS NOT NULL
     *   - check_out_at < now() - 2 hours (real planned-checkout is past)
     *
     * Action behaviour (corrected 2026-04-28):
     *   - is_check_out: set to TRUE
     *   - check_out_at: PRESERVED (do not overwrite — preserves the audit
     *     trail of when the guest was supposed to leave)
     *   - Per-record safety guard: refuses to close any record whose
     *     check_out_at is in the future (defense in depth even if the
     *     query has a regression)
     *   - ActivityLog entry created per force-close (incident-recoverable
     *     audit trail without needing a database backup)
     *
     * Disabled at application level until the per-row confirmation UI
     * ships. See $fixActionEnabled docblock above.
     */
    public function fixAllGhostRecords()
    {
        if (! $this->fixActionEnabled) {
            $this->dialog()->error(
                'Action Disabled',
                'The Fix All Records feature is under maintenance. This action cannot run until a per-row confirmation UI is added. See docs/bugs/2026-04-28-fixall-unresolved-flips-active-extension-checkins.md.'
            );
            return;
        }

        $cutoff = now()->subHours(2);

        $ghosts = CheckinDetail::where('is_check_out', 0)
            ->whereNotNull('check_out_at')
            ->where('check_out_at', '<', $cutoff)
            ->with(['room:id,number,branch_id', 'guest:id,name'])
            ->get();

        $fixedCount = 0;
        $skippedCount = 0;

        DB::beginTransaction();
        try {
            foreach ($ghosts as $record) {
                // Per-record safety guard — never close a record whose
                // check_out_at is in the future. This protects against any
                // future regression in the detection query.
                if ($record->check_out_at && Carbon::parse($record->check_out_at)->isFuture()) {
                    \Log::warning("UnresolvedCheckIns: skipped cid={$record->id} — check_out_at is in the future ({$record->check_out_at})");
                    $skippedCount++;
                    continue;
                }

                // Close the record. PRESERVE check_out_at (do not overwrite —
                // the original is the only authoritative record of when the
                // guest was supposed to leave).
                $record->update([
                    'is_check_out' => true,
                ]);

                ActivityLog::create([
                    'branch_id'   => $record->room->branch_id ?? auth()->user()->branch_id,
                    'user_id'     => auth()->id(),
                    'activity'    => 'Force-Close Ghost Record',
                    'description' => 'Force-closed cid=' . $record->id
                        . ' room=#' . ($record->room->number ?? 'N/A')
                        . ' guest=' . ($record->guest->name ?? 'Unknown')
                        . ' (check_out_at=' . $record->check_out_at . ')',
                ]);

                $fixedCount++;
            }

            DB::commit();
            $this->showConfirmModal = false;

            $this->dialog()->success(
                'Ghost Records Fixed',
                "Closed {$fixedCount} ghost record(s)."
                    . ($skippedCount > 0 ? " Skipped {$skippedCount} record(s) with future check_out_at." : '')
                    . ' Please verify Room Monitoring before continuing.'
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
