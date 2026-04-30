<?php

namespace App\Jobs;

use App\Models\Room;
use App\Models\TemporaryCheckInKiosk;
use App\Services\KioskBatchService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TerminationInKiosk implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $room_id;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($room_id)
    {
        $this->room_id = $room_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $room = Room::find($this->room_id);

        TemporaryCheckInKiosk::where(
            'room_id',
            $this->room_id
        )->delete();

        // Flip the kiosk picked slot for this room back to active so the
        // floor doesn't end up with an orphan slot. Mirrors the cleanup
        // that CleanupTemporaryKiosk console command already does.
        if ($room) {
            KioskBatchService::returnToBatch($room->branch_id, $this->room_id);
        }
    }
}
