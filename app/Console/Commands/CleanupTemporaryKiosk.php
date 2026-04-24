<?php

namespace App\Console\Commands;

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

            DB::transaction(function () use ($hold, &$totalGuestsDeleted) {
                // Delete the orphaned Guest so the room does not reappear in kiosk
                // with a pending (never-confirmed) guest record attached to it.
                // Skip any guest that already has transactions attached — those
                // represent real money and must be investigated manually.
                if ($hold->guest_id) {
                    $guestDeleted = Guest::where('id', $hold->guest_id)
                        ->whereDoesntHave('checkInDetail')
                        ->whereDoesntHave('transactions')
                        ->delete();
                    $totalGuestsDeleted += $guestDeleted;
                }
                $hold->delete();
            });

            // The guest never made it to frontdesk — return the floor slot
            // to the kiosk so it does not stay blank until the next batch.
            KioskBatchService::returnToBatch($branchId, $roomId);

            $totalDeleted++;
        }
    }

    $this->info("Deleted {$totalDeleted} expired kiosk entries and {$totalGuestsDeleted} orphan guests.");
}
}
