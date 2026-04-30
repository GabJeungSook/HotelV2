<?php

namespace Tests\Feature\UrgentBugFixes;

use App\Models\Branch;
use App\Models\Floor;
use App\Models\KioskCurrentBatch;
use App\Models\Rate;
use App\Models\Room;
use App\Models\StayingHour;
use App\Models\TemporaryCheckInKiosk;
use App\Models\Type;
use App\Services\KioskBatchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Tests for the batch-sync hook fixes shipped on 2026-05-01:
 *
 *   B2  RoomMonitoring::saveReserveCheckInDetails — refreshIfStale call
 *   B3  GuestTransaction + ManageGuestTransaction::checkoutGuest — refreshIfStale
 *   B4  TerminationInKiosk job — returnToBatch
 *   B6  Roomboy startCleaning — defensive refreshIfStale
 *   B7  PriorityRoom::removePriority — refreshIfStale
 *   B8  RoomMonitoring::saveCheckIn — refreshFloorSlot
 *
 * The actual UI flows (Livewire components / queued jobs) are large with
 * many dependencies. These tests verify the underlying behaviour the
 * hooks rely on:
 *
 *   • refreshIfStale clears active slot pointing to non-eligible room
 *   • refreshFloorSlot clears picked slot and refills with next FIFO
 *   • returnToBatch flips picked back to active
 *
 * Because the hook calls themselves are 1-line additions, these tests
 * exercise the underlying service primitives the hooks use, providing
 * regression coverage for the fixes as a class.
 */
class BatchSyncHooksTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function refresh_if_stale_clears_active_slot_pointing_to_uncleaned_room()
    {
        // After checkout (Bug B3), the room is set Uncleaned. Any kiosk
        // active slot pointing at that room should be repaired by
        // refreshIfStale on the next call. Without the hook, the slot
        // stays active until the next render — causing brief "ghost room"
        // on the kiosk.
        [$branch, $type, $f1] = $this->seedSmall();

        $room = Room::create([
            'branch_id' => $branch->id, 'floor_id' => $f1->id, 'type_id' => $type->id,
            'number' => '1', 'status' => 'Available', 'is_priority' => true,
        ]);
        $spare = Room::create([
            'branch_id' => $branch->id, 'floor_id' => $f1->id, 'type_id' => $type->id,
            'number' => '2', 'status' => 'Available', 'is_priority' => true,
        ]);

        // Pretend the kiosk batch had room #1 as an active slot.
        KioskCurrentBatch::create([
            'branch_id' => $branch->id, 'type_id' => $type->id, 'floor_id' => $f1->id,
            'room_id' => $room->id, 'slot_status' => 'active',
        ]);

        // Simulate the room going Uncleaned (B3 checkout flow result).
        $room->update(['status' => 'Uncleaned']);

        // The new B3 hook would call this immediately after commit.
        $changed = KioskBatchService::refreshIfStale($branch->id, $type->id);

        $this->assertTrue($changed, 'Active slot pointing to Uncleaned should be detected as stale.');
        $this->assertEquals(
            $spare->id,
            KioskCurrentBatch::where('floor_id', $f1->id)->value('room_id'),
            'Slot should be repaired to point at the next available room (#2).',
        );
    }

    /** @test */
    public function refresh_if_stale_clears_active_slot_pointing_to_non_priority_room()
    {
        // After PriorityRoom::removePriority (Bug B7), the room loses
        // is_priority=1 — kiosk shouldn't show it. The active slot
        // pointing at it should be repaired.
        [$branch, $type, $f1] = $this->seedSmall();

        $picked = Room::create([
            'branch_id' => $branch->id, 'floor_id' => $f1->id, 'type_id' => $type->id,
            'number' => '5', 'status' => 'Available', 'is_priority' => true,
        ]);
        $spare = Room::create([
            'branch_id' => $branch->id, 'floor_id' => $f1->id, 'type_id' => $type->id,
            'number' => '7', 'status' => 'Available', 'is_priority' => true,
        ]);

        KioskCurrentBatch::create([
            'branch_id' => $branch->id, 'type_id' => $type->id, 'floor_id' => $f1->id,
            'room_id' => $picked->id, 'slot_status' => 'active',
        ]);

        $picked->update(['is_priority' => false]);

        $changed = KioskBatchService::refreshIfStale($branch->id, $type->id);

        $this->assertTrue($changed);
        $this->assertEquals(
            $spare->id,
            KioskCurrentBatch::where('floor_id', $f1->id)->value('room_id'),
        );
    }

    /** @test */
    public function refresh_floor_slot_clears_picked_after_kiosk_walk_in_confirm()
    {
        // After RoomMonitoring::saveCheckIn (Bug B8) commits the kiosk
        // walk-in confirmation, refreshFloorSlot replaces the picked slot
        // with the next-FIFO clean room — same as CheckInFromKiosk does.
        [$branch, $type, $f1] = $this->seedSmall();

        $picked = Room::create([
            'branch_id' => $branch->id, 'floor_id' => $f1->id, 'type_id' => $type->id,
            'number' => '1', 'status' => 'Occupied', 'is_priority' => true,
        ]);
        $next = Room::create([
            'branch_id' => $branch->id, 'floor_id' => $f1->id, 'type_id' => $type->id,
            'number' => '2', 'status' => 'Available', 'is_priority' => true,
        ]);

        KioskCurrentBatch::create([
            'branch_id' => $branch->id, 'type_id' => $type->id, 'floor_id' => $f1->id,
            'room_id' => $picked->id, 'slot_status' => 'picked',
        ]);

        KioskBatchService::refreshFloorSlot($branch->id, $type->id, $f1->id);

        $slot = KioskCurrentBatch::where('floor_id', $f1->id)->first();
        $this->assertNotNull($slot, 'A new slot should be created.');
        $this->assertEquals('active', $slot->slot_status);
        $this->assertEquals($next->id, $slot->room_id);
    }

    /** @test */
    public function return_to_batch_flips_picked_to_active_after_termination_job()
    {
        // TerminationInKiosk (Bug B4) deletes the temp hold and then calls
        // returnToBatch, restoring the picked slot to active.
        [$branch, $type, $f1] = $this->seedSmall();

        $room = Room::create([
            'branch_id' => $branch->id, 'floor_id' => $f1->id, 'type_id' => $type->id,
            'number' => '1', 'status' => 'Available', 'is_priority' => true,
        ]);

        $slot = KioskCurrentBatch::create([
            'branch_id' => $branch->id, 'type_id' => $type->id, 'floor_id' => $f1->id,
            'room_id' => $room->id, 'slot_status' => 'picked',
        ]);

        KioskBatchService::returnToBatch($branch->id, $room->id);

        $slot->refresh();
        $this->assertEquals('active', $slot->slot_status, 'Picked slot should be flipped back to active.');
    }

    /**
     * Seed a small branch/type/floor + 24h staying_hour and one rate.
     */
    private function seedSmall(): array
    {
        $branch = Branch::create(['name' => 'BatchSync ' . uniqid(), 'kiosk_time_limit' => 10, 'initial_deposit' => 200]);
        $type = Type::create(['branch_id' => $branch->id, 'name' => 'Test Type']);
        $hour = StayingHour::create(['branch_id' => $branch->id, 'number' => 24]);
        Rate::create(['branch_id' => $branch->id, 'type_id' => $type->id, 'staying_hour_id' => $hour->id, 'amount' => 700]);
        $f1 = Floor::create(['branch_id' => $branch->id, 'number' => 1]);

        return [$branch, $type, $f1];
    }
}
