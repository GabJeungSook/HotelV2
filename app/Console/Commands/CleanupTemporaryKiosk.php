<?php

namespace App\Console\Commands;

use App\Models\CheckinDetail;
use App\Models\Transaction;
use App\Models\TemporaryCheckInKiosk;
use App\Models\Guest;
use App\Models\Branch;
use App\Services\KioskBatchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupTemporaryKiosk extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kiosk:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete temporary kiosk entries depending on kiosk timeout';

    /**
     * Execute the console command.
     *
     * @return int
     */
public function handle()
{
    $totalDeleted = 0;
    $totalGuestsDeleted = 0;

    $branches = Branch::all();

    foreach ($branches as $branch) {

        $minutes = $branch->kiosk_time_limit ?? 10;

        $expired = TemporaryCheckInKiosk::where('branch_id', $branch->id)
            ->where('created_at', '<=', now()->subMinutes($minutes))
            ->get();

        foreach ($expired as $hold) {
            $branchId = $hold->branch_id;
            $roomId = $hold->room_id;

            // Wrap in transaction + lockForUpdate to serialize against
            // CheckInFromKiosk::saveCheckIn which also locks the same TCK.
            // Race we are protecting against: cron sees a TCK at the timeout
            // boundary; a frontdesk simultaneously confirms it. Without locks,
            // the cron could delete the Guest row that the frontdesk's open
            // transaction is about to attach a CheckinDetail to → FK orphan
            // (or worse, a deleted-but-paid-for guest).
            $deletedThisHold = DB::transaction(function () use ($hold, &$totalGuestsDeleted) {
                // Re-fetch the TCK with a write lock. If a frontdesk already
                // holds the lock (mid-confirm), we block until they commit;
                // afterwards the row is gone and we skip cleanly.
                $lockedHold = TemporaryCheckInKiosk::where('id', $hold->id)
                    ->lockForUpdate()
                    ->first();

                if (! $lockedHold) {
                    return false;
                }

                if ($lockedHold->guest_id) {
                    // Lock the Guest row before re-checking + deleting so a
                    // frontdesk save in flight cannot slip a CheckinDetail or
                    // Transaction in between our read and our delete.
                    $lockedGuest = Guest::where('id', $lockedHold->guest_id)
                        ->lockForUpdate()
                        ->first();

                    if ($lockedGuest) {
                        $hasCheckin = CheckinDetail::where('guest_id', $lockedGuest->id)->exists();
                        $hasTxn = Transaction::where('guest_id', $lockedGuest->id)->exists();

                        if (! $hasCheckin && ! $hasTxn) {
                            $lockedGuest->delete();
                            $totalGuestsDeleted++;
                        }
                    }
                }

                $lockedHold->delete();
                return true;
            });

            if (! $deletedThisHold) {
                continue;
            }

            // The guest never made it to frontdesk — return the floor slot
            // to the kiosk so it does not stay blank until the next batch.
            KioskBatchService::returnToBatch($branchId, $roomId);

            $totalDeleted++;
        }
    }

    $this->info("Deleted {$totalDeleted} expired kiosk entries and {$totalGuestsDeleted} orphan guests.");
}
}
