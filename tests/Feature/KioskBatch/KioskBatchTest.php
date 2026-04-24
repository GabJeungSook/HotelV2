<?php

namespace Tests\Feature\KioskBatch;

use App\Models\Branch;
use App\Models\Floor;
use App\Models\KioskCurrentBatch;
use App\Models\Rate;
use App\Models\Room;
use App\Models\StayingHour;
use App\Models\Type;
use App\Services\KioskBatchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class KioskBatchTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function first_batch_is_thrown_when_table_is_empty()
    {
        [$branch, $floors, $rooms] = $this->seedBranchWithFloorsAndRooms(
            floors: 3,
            roomsPerFloor: 2,
        );

        $this->assertTrue(KioskBatchService::isEmpty($branch->id));

        KioskBatchService::throwNextBatch($branch->id);

        // One active slot per floor (3 floors total).
        $active = KioskCurrentBatch::where('branch_id', $branch->id)
            ->where('slot_status', 'active')
            ->count();
        $this->assertEquals(3, $active);
    }

    /** @test */
    public function throw_picks_lowest_numbered_room_per_floor()
    {
        [$branch, $floors, $rooms] = $this->seedBranchWithFloorsAndRooms(
            floors: 2,
            roomsPerFloor: 3,
        );
        // Floor 1 rooms: numbers 1001, 1002, 1003
        // Floor 2 rooms: numbers 2001, 2002, 2003 (seeding uses floor*1000+room)

        KioskBatchService::throwNextBatch($branch->id);

        $active = KioskCurrentBatch::where('branch_id', $branch->id)
            ->where('slot_status', 'active')
            ->pluck('room_id')
            ->toArray();

        $activeRoomNumbers = Room::whereIn('id', $active)
            ->orderBy('number')
            ->pluck('number')
            ->toArray();

        $this->assertEquals(['1001', '2001'], $activeRoomNumbers);
    }

    /** @test */
    public function floor_goes_blank_after_mark_picked()
    {
        [$branch, $floors, $rooms] = $this->seedBranchWithFloorsAndRooms(
            floors: 2,
            roomsPerFloor: 2,
        );

        KioskBatchService::throwNextBatch($branch->id);

        // Pick the Floor 1 active room.
        $floor1ActiveRoomId = KioskCurrentBatch::where('branch_id', $branch->id)
            ->where('floor_id', $floors[0]->id)
            ->where('slot_status', 'active')
            ->value('room_id');

        // Simulate the room becoming Occupied (as in real check-in flow).
        Room::where('id', $floor1ActiveRoomId)->update(['status' => 'Occupied']);

        KioskBatchService::markPicked($branch->id, $floor1ActiveRoomId);

        // Floor 1 row should now be 'picked'.
        $floor1Row = KioskCurrentBatch::where('branch_id', $branch->id)
            ->where('floor_id', $floors[0]->id)
            ->first();
        $this->assertEquals('picked', $floor1Row->slot_status);

        // Floor 2 should still be 'active'.
        $activeOnFloor2 = KioskCurrentBatch::where('branch_id', $branch->id)
            ->where('floor_id', $floors[1]->id)
            ->where('slot_status', 'active')
            ->exists();
        $this->assertTrue($activeOnFloor2);
    }

    /** @test */
    public function next_batch_is_thrown_when_all_floors_are_picked()
    {
        [$branch, $floors, $rooms] = $this->seedBranchWithFloorsAndRooms(
            floors: 2,
            roomsPerFloor: 2,
        );

        KioskBatchService::throwNextBatch($branch->id);

        $initialRoomIds = KioskCurrentBatch::where('branch_id', $branch->id)
            ->pluck('room_id')
            ->sort()
            ->values()
            ->toArray();

        // Pick both active rooms (simulate check-ins).
        foreach ($initialRoomIds as $roomId) {
            Room::where('id', $roomId)->update(['status' => 'Occupied']);
            KioskBatchService::markPicked($branch->id, $roomId);
        }

        // After draining, the next batch should have been auto-thrown.
        $newActive = KioskCurrentBatch::where('branch_id', $branch->id)
            ->where('slot_status', 'active')
            ->pluck('room_id')
            ->sort()
            ->values()
            ->toArray();

        // New batch must have rooms, and they must be DIFFERENT from first batch
        // (because first batch rooms are Occupied now → not eligible).
        $this->assertCount(2, $newActive);
        $this->assertNotEquals($initialRoomIds, $newActive);
    }

    /** @test */
    public function blank_floor_fills_mid_batch_when_room_cleaned()
    {
        [$branch, $floors, $rooms] = $this->seedBranchWithFloorsAndRooms(
            floors: 3,
            roomsPerFloor: 1,
        );

        // Make Floor 3's only room unavailable so batch starts with 2 floors filled.
        Room::where('floor_id', $floors[2]->id)->update(['status' => 'Occupied']);

        KioskBatchService::throwNextBatch($branch->id);

        // Floor 3 should have NO row in the batch (blank).
        $this->assertFalse(
            KioskCurrentBatch::where('branch_id', $branch->id)
                ->where('floor_id', $floors[2]->id)
                ->exists()
        );

        // Now Floor 3's room gets cleaned (status Available again).
        $floor3Room = Room::where('floor_id', $floors[2]->id)->first();
        $floor3Room->update(['status' => 'Available', 'is_priority' => 1]);
        KioskBatchService::maybeFillBlankFloor($floor3Room);

        // Floor 3 should now have an active row (filled the blank mid-batch).
        $this->assertTrue(
            KioskCurrentBatch::where('branch_id', $branch->id)
                ->where('floor_id', $floors[2]->id)
                ->where('slot_status', 'active')
                ->exists()
        );
    }

    /** @test */
    public function mid_batch_cleaning_on_filled_floor_waits_for_next_batch()
    {
        [$branch, $floors, $rooms] = $this->seedBranchWithFloorsAndRooms(
            floors: 2,
            roomsPerFloor: 3,
        );

        KioskBatchService::throwNextBatch($branch->id);

        // Floor 1's second room gets cleaned AFTER batch forms (already has active).
        $floor1SecondRoom = Room::where('floor_id', $floors[0]->id)
            ->orderBy('number')
            ->skip(1)
            ->first();

        KioskBatchService::maybeFillBlankFloor($floor1SecondRoom);

        // Floor 1 must still have only ONE row (the original active). No extra row
        // was added for the second cleaning — it waits for next batch.
        $floor1Rows = KioskCurrentBatch::where('branch_id', $branch->id)
            ->where('floor_id', $floors[0]->id)
            ->count();
        $this->assertEquals(1, $floor1Rows);

        // The room in the batch must be the LOWEST numbered room on Floor 1
        // (not the second).
        $floor1ActiveRoomId = KioskCurrentBatch::where('branch_id', $branch->id)
            ->where('floor_id', $floors[0]->id)
            ->value('room_id');
        $floor1LowestId = Room::where('floor_id', $floors[0]->id)
            ->orderBy('number')
            ->value('id');
        $this->assertEquals($floor1LowestId, $floor1ActiveRoomId);
    }

    /**
     * Seed a branch with the given number of floors, each having the given
     * number of rooms. Room numbers are "{floor*1000}+{room}" so they sort
     * naturally — Floor 1 rooms are 1001, 1002, ... / Floor 2 rooms 2001, ...
     *
     * @return array{0: Branch, 1: array<int, Floor>, 2: array<int, Room>}
     */
    private function seedBranchWithFloorsAndRooms(int $floors, int $roomsPerFloor): array
    {
        $branch = Branch::create([
            'name' => 'Batch Test Branch ' . uniqid(),
            'kiosk_time_limit' => 10,
        ]);

        $type = Type::create([
            'branch_id' => $branch->id,
            'name' => 'Test Type',
        ]);

        $stayingHour = StayingHour::create([
            'branch_id' => $branch->id,
            'number' => 12,
        ]);

        Rate::create([
            'branch_id' => $branch->id,
            'type_id' => $type->id,
            'staying_hour_id' => $stayingHour->id,
            'amount' => 300,
        ]);

        $floorModels = [];
        $rooms = [];

        for ($f = 1; $f <= $floors; $f++) {
            $floor = Floor::create([
                'branch_id' => $branch->id,
                'number' => $f,
            ]);
            $floorModels[] = $floor;

            for ($r = 1; $r <= $roomsPerFloor; $r++) {
                $number = ($f * 1000) + $r;
                $room = Room::create([
                    'branch_id' => $branch->id,
                    'floor_id' => $floor->id,
                    'type_id' => $type->id,
                    'number' => (string) $number,
                    'status' => 'Available',
                    'is_priority' => true,
                ]);
                $rooms[] = $room;
            }
        }

        return [$branch, $floorModels, $rooms];
    }
}
