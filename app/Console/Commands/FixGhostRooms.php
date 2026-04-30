<?php

namespace App\Console\Commands;

use App\Models\Room;
use App\Models\CheckinDetail;
use App\Services\KioskBatchService;
use Illuminate\Console\Command;

class FixGhostRooms extends Command
{
    /**
     * The name and signature of the console command.
     *
     * --fix flag actually fixes the issues, otherwise just reports
     */
    protected $signature = 'rooms:fix-ghost {--fix : Actually fix the ghost rooms} {--branch= : Filter by branch_id}';

    /**
     * The console command description.
     */
    protected $description = 'Find and fix ghost rooms (Occupied status but no active guest)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $shouldFix = $this->option('fix');
        $branchId = $this->option('branch');

        $this->info('Scanning for ghost rooms...');
        $this->newLine();

        // Find all rooms with Occupied status
        $query = Room::where('status', 'Occupied');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $occupiedRooms = $query->get();

        $ghostRooms = [];

        foreach ($occupiedRooms as $room) {
            // Check if there's an active guest
            $hasActiveGuest = CheckinDetail::where('room_id', $room->id)
                ->where('is_check_out', false)
                ->exists();

            if (!$hasActiveGuest) {
                $ghostRooms[] = $room;
            }
        }

        if (count($ghostRooms) === 0) {
            $this->info('✅ No ghost rooms found! All Occupied rooms have active guests.');
            return 0;
        }

        $this->warn('⚠️  Found ' . count($ghostRooms) . ' ghost room(s):');
        $this->newLine();

        $tableData = [];
        foreach ($ghostRooms as $room) {
            $tableData[] = [
                $room->id,
                $room->number,
                $room->branch_id,
                $room->status,
                'No active guest',
            ];
        }

        $this->table(
            ['Room ID', 'Room Number', 'Branch ID', 'Status', 'Issue'],
            $tableData
        );

        $this->newLine();

        if ($shouldFix) {
            $this->info('Fixing ghost rooms...');

            foreach ($ghostRooms as $room) {
                $room->update(['status' => 'Available']);
                // Notify the kiosk batch — same hook as the GhostRooms UI.
                $room->refresh();
                if ($room->is_priority) {
                    KioskBatchService::maybeFillBlankFloor($room);
                }
                $this->line("  ✅ Room #{$room->number} (ID: {$room->id}) → Available");
            }

            $this->newLine();
            $this->info('✅ Fixed ' . count($ghostRooms) . ' ghost room(s)!');
        } else {
            $this->newLine();
            $this->comment('Run with --fix flag to fix these rooms:');
            $this->comment('  php artisan rooms:fix-ghost --fix');
        }

        return 0;
    }
}
