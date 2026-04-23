<?php

namespace App\Console\Commands;

use App\Models\TemporaryCheckInKiosk;
use App\Models\Guest;
use App\Models\Branch;
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
            DB::transaction(function () use ($hold, &$totalGuestsDeleted) {
                // Delete the orphaned Guest so the room does not reappear in kiosk
                // with a pending (never-confirmed) guest record attached to it.
                if ($hold->guest_id) {
                    $guestDeleted = Guest::where('id', $hold->guest_id)
                        ->whereDoesntHave('checkInDetail')
                        ->delete();
                    $totalGuestsDeleted += $guestDeleted;
                }
                $hold->delete();
            });
            $totalDeleted++;
        }
    }

    $this->info("Deleted {$totalDeleted} expired kiosk entries and {$totalGuestsDeleted} orphan guests.");
}
}
