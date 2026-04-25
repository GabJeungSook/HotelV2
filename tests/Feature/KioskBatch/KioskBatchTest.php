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
    public function return_to_batch_flips_picked_slot_back_to_active()
    {
        [$branch, $type, $floors, $rooms] = $this->seedBranchWithFloorsAndRooms(
            floors: 2,
            roomsPerFloor: 1,
        );

        KioskBatchService::throwNextBatch($branch->id, $type->id);

        $floor1RoomId = KioskCurrentBatch::where('branch_id', $branch->id)
            ->where('type_id', $type->id)
            ->where('floor_id', $floors[0]->id)
            ->value('room_id');

        // Pick it — floor goes blank.
        KioskBatchService::markPicked($branch->id, $floor1RoomId);
        $this->assertEquals(
            'picked',
            KioskCurrentBatch::where('room_id', $floor1RoomId)->value('slot_status'),
        );

        // Simulate the guest walking away — cancel/timeout path.
        KioskBatchService::returnToBatch($branch->id, $floor1RoomId);

        $this->assertEquals(
            'active',
            KioskCurrentBatch::where('room_id', $floor1RoomId)->value('slot_status'),
            'Cancelled / timed-out rooms must reappear on the kiosk, not stay blank.',
        );

        // Floor should now be listed among active room ids again.
        $activeIds = KioskBatchService::activeRoomIds($branch->id, $type->id);
        $this->assertContains($floor1RoomId, $activeIds);
    }

    /** @test */
    public function return_to_batch_is_noop_when_slot_is_not_picked()
    {
        [$branch, $type, $floors, $rooms] = $this->seedBranchWithFloorsAndRooms(
            floors: 1,
            roomsPerFloor: 1,
        );

        KioskBatchService::throwNextBatch($branch->id, $type->id);

        $activeRoomId = KioskCurrentBatch::where('branch_id', $branch->id)
            ->where('type_id', $type->id)
            ->value('room_id');

        // Not picked — still active. Return should leave it alone.
        KioskBatchService::returnToBatch($branch->id, $activeRoomId);

        $this->assertEquals(
            'active',
            KioskCurrentBatch::where('room_id', $activeRoomId)->value('slot_status'),
        );
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

    /** @test */
    public function next_batch_picks_numerically_lowest_room_per_floor()
    {
        // Floor 1 with mixed-length numeric room numbers. SQL string sort
        // would pick "21" first (because "2" < "3" lexicographically). The
        // service must use natural sort and pick "3".
        $branch = Branch::create(['name' => 'Numeric Sort ' . uniqid(), 'kiosk_time_limit' => 10]);
        $type = Type::create(['branch_id' => $branch->id, 'name' => 'Test']);
        $stayingHour = StayingHour::create(['branch_id' => $branch->id, 'number' => 12]);
        Rate::create(['branch_id' => $branch->id, 'type_id' => $type->id, 'staying_hour_id' => $stayingHour->id, 'amount' => 300]);

        $floor1 = Floor::create(['branch_id' => $branch->id, 'number' => 1]);

        foreach (['3', '21', '52', '9', '7'] as $num) {
            Room::create([
                'branch_id' => $branch->id,
                'floor_id' => $floor1->id,
                'type_id' => $type->id,
                'number' => $num,
                'status' => 'Available',
                'is_priority' => true,
            ]);
        }

        KioskBatchService::throwNextBatch($branch->id, $type->id);

        $pickedRoomId = KioskCurrentBatch::where('branch_id', $branch->id)
            ->where('type_id', $type->id)
            ->where('floor_id', $floor1->id)
            ->value('room_id');
        $pickedNumber = Room::where('id', $pickedRoomId)->value('number');

        $this->assertEquals('3', $pickedNumber, 'Expected room "3" (numerically lowest), not "21" (lexicographically lowest).');
    }

    /** @test */
    public function alphanumeric_room_numbers_sort_naturally()
    {
        // Mixed alphanumeric: "5A", "5C", "256", "293". Natural sort order is
        // 5A < 5C < 256 < 293 (the "5x" series has a smaller numeric prefix
        // than 256/293). Plain string sort would give 256 first.
        $branch = Branch::create(['name' => 'Alphanum Sort ' . uniqid(), 'kiosk_time_limit' => 10]);
        $type = Type::create(['branch_id' => $branch->id, 'name' => 'Test']);
        $stayingHour = StayingHour::create(['branch_id' => $branch->id, 'number' => 12]);
        Rate::create(['branch_id' => $branch->id, 'type_id' => $type->id, 'staying_hour_id' => $stayingHour->id, 'amount' => 300]);

        $floor5 = Floor::create(['branch_id' => $branch->id, 'number' => 5]);

        foreach (['293', '5C', '5A', '256'] as $num) {
            Room::create([
                'branch_id' => $branch->id,
                'floor_id' => $floor5->id,
                'type_id' => $type->id,
                'number' => $num,
                'status' => 'Available',
                'is_priority' => true,
            ]);
        }

        KioskBatchService::throwNextBatch($branch->id, $type->id);

        $pickedRoomId = KioskCurrentBatch::where('branch_id', $branch->id)
            ->where('type_id', $type->id)
            ->where('floor_id', $floor5->id)
            ->value('room_id');
        $pickedNumber = Room::where('id', $pickedRoomId)->value('number');

        $this->assertEquals('5A', $pickedNumber, 'Natural sort should pick "5A" before "256" (5 < 256 numerically).');
    }

    /** @test */
    public function refresh_if_stale_throws_new_batch_when_active_slots_are_unusable()
    {
        // Simulate the production bug: batch slot points to a room that
        // became Occupied via a non-kiosk path (frontdesk direct check-in).
        // refreshIfStale must detect this and throw a fresh batch from the
        // remaining available rooms.
        [$branch, $type, $floors, $rooms] = $this->seedBranchWithFloorsAndRooms(
            floors: 2,
            roomsPerFloor: 2,
        );

        KioskBatchService::throwNextBatch($branch->id, $type->id);

        // Sanity: every active slot points to an Available room.
        $beforeIds = KioskBatchService::activeRoomIds($branch->id, $type->id);
        $this->assertCount(2, $beforeIds);

        // Simulate non-kiosk occupation of EVERY active slot's room.
        foreach ($beforeIds as $roomId) {
            Room::where('id', $roomId)->update(['status' => 'Occupied']);
        }

        // Slots are now stale (still 'active' but rooms are Occupied).
        $refreshed = KioskBatchService::refreshIfStale($branch->id, $type->id);

        $this->assertTrue($refreshed, 'refreshIfStale should signal it threw a new batch.');

        // The new batch should point to different (still-available) rooms.
        $afterIds = KioskBatchService::activeRoomIds($branch->id, $type->id);
        $this->assertCount(2, $afterIds, 'Expected new batch with one room per floor.');
        $this->assertEmpty(array_intersect($beforeIds, $afterIds), 'New batch must not reuse the now-Occupied rooms.');

        // Each new slot must point to a still-available room.
        foreach ($afterIds as $roomId) {
            $status = Room::where('id', $roomId)->value('status');
            $this->assertContains($status, ['Available', 'Cleaned']);
        }
    }

    /** @test */
    public function refresh_if_stale_is_noop_when_active_slots_are_still_usable()
    {
        [$branch, $type, $floors, $rooms] = $this->seedBranchWithFloorsAndRooms(
            floors: 2,
            roomsPerFloor: 2,
        );

        KioskBatchService::throwNextBatch($branch->id, $type->id);

        $beforeIds = KioskBatchService::activeRoomIds($branch->id, $type->id);

        // No status changes — slots still valid.
        $refreshed = KioskBatchService::refreshIfStale($branch->id, $type->id);

        $this->assertFalse($refreshed, 'refreshIfStale should be a no-op when slots are still usable.');

        $afterIds = KioskBatchService::activeRoomIds($branch->id, $type->id);
        $this->assertEqualsCanonicalizing($beforeIds, $afterIds);
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
