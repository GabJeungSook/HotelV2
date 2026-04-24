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
    public function first_batch_is_thrown_when_table_is_empty_for_type()
    {
        [$branch, $type, $floors, $rooms] = $this->seedBranchWithFloorsAndRooms(
            floors: 3,
            roomsPerFloor: 2,
        );

        $this->assertTrue(KioskBatchService::isEmpty($branch->id, $type->id));

        KioskBatchService::throwNextBatch($branch->id, $type->id);

        $active = KioskCurrentBatch::where('branch_id', $branch->id)
            ->where('type_id', $type->id)
            ->where('slot_status', 'active')
            ->count();
        $this->assertEquals(3, $active);
    }

    /** @test */
    public function throw_picks_lowest_numbered_room_per_floor_of_the_type()
    {
        [$branch, $type, $floors, $rooms] = $this->seedBranchWithFloorsAndRooms(
            floors: 2,
            roomsPerFloor: 3,
        );

        KioskBatchService::throwNextBatch($branch->id, $type->id);

        $active = KioskCurrentBatch::where('branch_id', $branch->id)
            ->where('type_id', $type->id)
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
        [$branch, $type, $floors, $rooms] = $this->seedBranchWithFloorsAndRooms(
            floors: 2,
            roomsPerFloor: 2,
        );

        KioskBatchService::throwNextBatch($branch->id, $type->id);

        $floor1ActiveRoomId = KioskCurrentBatch::where('branch_id', $branch->id)
            ->where('type_id', $type->id)
            ->where('floor_id', $floors[0]->id)
            ->where('slot_status', 'active')
            ->value('room_id');

        Room::where('id', $floor1ActiveRoomId)->update(['status' => 'Occupied']);

        KioskBatchService::markPicked($branch->id, $floor1ActiveRoomId);

        $floor1Row = KioskCurrentBatch::where('branch_id', $branch->id)
            ->where('type_id', $type->id)
            ->where('floor_id', $floors[0]->id)
            ->first();
        $this->assertEquals('picked', $floor1Row->slot_status);

        $activeOnFloor2 = KioskCurrentBatch::where('branch_id', $branch->id)
            ->where('type_id', $type->id)
            ->where('floor_id', $floors[1]->id)
            ->where('slot_status', 'active')
            ->exists();
        $this->assertTrue($activeOnFloor2);
    }

    /** @test */
    public function next_batch_is_thrown_when_all_floors_are_picked()
    {
        [$branch, $type, $floors, $rooms] = $this->seedBranchWithFloorsAndRooms(
            floors: 2,
            roomsPerFloor: 2,
        );

        KioskBatchService::throwNextBatch($branch->id, $type->id);

        $initialRoomIds = KioskCurrentBatch::where('branch_id', $branch->id)
            ->where('type_id', $type->id)
            ->pluck('room_id')
            ->sort()
            ->values()
            ->toArray();

        foreach ($initialRoomIds as $roomId) {
            Room::where('id', $roomId)->update(['status' => 'Occupied']);
            KioskBatchService::markPicked($branch->id, $roomId);
        }

        $newActive = KioskCurrentBatch::where('branch_id', $branch->id)
            ->where('type_id', $type->id)
            ->where('slot_status', 'active')
            ->pluck('room_id')
            ->sort()
            ->values()
            ->toArray();

        $this->assertCount(2, $newActive);
        $this->assertNotEquals($initialRoomIds, $newActive);
    }

    /** @test */
    public function blank_floor_fills_mid_batch_when_room_cleaned()
    {
        [$branch, $type, $floors, $rooms] = $this->seedBranchWithFloorsAndRooms(
            floors: 3,
            roomsPerFloor: 1,
        );

        Room::where('floor_id', $floors[2]->id)->update(['status' => 'Occupied']);

        KioskBatchService::throwNextBatch($branch->id, $type->id);

        $this->assertFalse(
            KioskCurrentBatch::where('branch_id', $branch->id)
                ->where('type_id', $type->id)
                ->where('floor_id', $floors[2]->id)
                ->exists()
        );

        $floor3Room = Room::where('floor_id', $floors[2]->id)->first();
        $floor3Room->update(['status' => 'Available', 'is_priority' => 1]);
        KioskBatchService::maybeFillBlankFloor($floor3Room);

        $this->assertTrue(
            KioskCurrentBatch::where('branch_id', $branch->id)
                ->where('type_id', $type->id)
                ->where('floor_id', $floors[2]->id)
                ->where('slot_status', 'active')
                ->exists()
        );
    }

    /** @test */
    public function mid_batch_cleaning_on_filled_floor_waits_for_next_batch()
    {
        [$branch, $type, $floors, $rooms] = $this->seedBranchWithFloorsAndRooms(
            floors: 2,
            roomsPerFloor: 3,
        );

        KioskBatchService::throwNextBatch($branch->id, $type->id);

        $floor1SecondRoom = Room::where('floor_id', $floors[0]->id)
            ->orderBy('number')
            ->skip(1)
            ->first();

        KioskBatchService::maybeFillBlankFloor($floor1SecondRoom);

        $floor1Rows = KioskCurrentBatch::where('branch_id', $branch->id)
            ->where('type_id', $type->id)
            ->where('floor_id', $floors[0]->id)
            ->count();
        $this->assertEquals(1, $floor1Rows);

        $floor1ActiveRoomId = KioskCurrentBatch::where('branch_id', $branch->id)
            ->where('type_id', $type->id)
            ->where('floor_id', $floors[0]->id)
            ->value('room_id');
        $floor1LowestId = Room::where('floor_id', $floors[0]->id)
            ->orderBy('number')
            ->value('id');
        $this->assertEquals($floor1LowestId, $floor1ActiveRoomId);
    }

    /** @test */
    public function batches_are_independent_per_type()
    {
        // Branch with 2 floors. Each floor has ONE Double room and ONE Single.
        $branch = Branch::create(['name' => 'Multi Type ' . uniqid(), 'kiosk_time_limit' => 10]);
        $doubleType = Type::create(['branch_id' => $branch->id, 'name' => 'Double']);
        $singleType = Type::create(['branch_id' => $branch->id, 'name' => 'Single']);

        $stayingHour = StayingHour::create(['branch_id' => $branch->id, 'number' => 12]);
        Rate::create(['branch_id' => $branch->id, 'type_id' => $doubleType->id, 'staying_hour_id' => $stayingHour->id, 'amount' => 400]);
        Rate::create(['branch_id' => $branch->id, 'type_id' => $singleType->id, 'staying_hour_id' => $stayingHour->id, 'amount' => 200]);

        $floor1 = Floor::create(['branch_id' => $branch->id, 'number' => 1]);
        $floor2 = Floor::create(['branch_id' => $branch->id, 'number' => 2]);

        $doubleFloor1 = Room::create(['branch_id' => $branch->id, 'floor_id' => $floor1->id, 'type_id' => $doubleType->id, 'number' => '1D1', 'status' => 'Available', 'is_priority' => true]);
        $doubleFloor2 = Room::create(['branch_id' => $branch->id, 'floor_id' => $floor2->id, 'type_id' => $doubleType->id, 'number' => '2D1', 'status' => 'Available', 'is_priority' => true]);
        $singleFloor1 = Room::create(['branch_id' => $branch->id, 'floor_id' => $floor1->id, 'type_id' => $singleType->id, 'number' => '1S1', 'status' => 'Available', 'is_priority' => true]);
        $singleFloor2 = Room::create(['branch_id' => $branch->id, 'floor_id' => $floor2->id, 'type_id' => $singleType->id, 'number' => '2S1', 'status' => 'Available', 'is_priority' => true]);

        KioskBatchService::throwNextBatch($branch->id, $doubleType->id);
        KioskBatchService::throwNextBatch($branch->id, $singleType->id);

        // Double batch must have both Double rooms.
        $doubleActiveIds = KioskBatchService::activeRoomIds($branch->id, $doubleType->id);
        $this->assertEqualsCanonicalizing([$doubleFloor1->id, $doubleFloor2->id], $doubleActiveIds);

        // Single batch must have both Single rooms.
        $singleActiveIds = KioskBatchService::activeRoomIds($branch->id, $singleType->id);
        $this->assertEqualsCanonicalizing([$singleFloor1->id, $singleFloor2->id], $singleActiveIds);

        // Pick a Double room — must NOT affect the Single batch.
        $doubleFloor1->update(['status' => 'Occupied']);
        KioskBatchService::markPicked($branch->id, $doubleFloor1->id);

        $singleActiveIdsAfter = KioskBatchService::activeRoomIds($branch->id, $singleType->id);
        $this->assertEqualsCanonicalizing([$singleFloor1->id, $singleFloor2->id], $singleActiveIdsAfter);
    }

    /**
     * Seed a branch with the given number of floors, each having the given
     * number of rooms, all of a single test type. Returns [branch, type, floors, rooms].
     *
     * @return array{0: Branch, 1: Type, 2: array<int, Floor>, 3: array<int, Room>}
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

        return [$branch, $type, $floorModels, $rooms];
    }
}
