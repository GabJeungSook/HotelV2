<?php

namespace Tests\Feature\KioskBatch;

use App\Models\Branch;
use App\Models\Floor;
use App\Models\Guest;
use App\Models\KioskCurrentBatch;
use App\Models\Rate;
use App\Models\Room;
use App\Models\StayingHour;
use App\Models\TemporaryCheckInKiosk;
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
    public function mark_picked_does_not_auto_throw_on_last_pick()
    {
        // Wait-for-confirm rule: markPicked alone never throws the next
        // batch. The throw waits until frontdesk processes the picks via
        // saveCheckIn (which calls maybeThrowNextBatch).
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

        // Simulate guest reservations: temp records exist, slots flip to picked.
        foreach ($initialRoomIds as $roomId) {
            TemporaryCheckInKiosk::create([
                'guest_id' => Guest::create([
                    'branch_id' => $branch->id, 'name' => 'G' . $roomId, 'contact' => 'N/A',
                    'qr_code' => 'Q' . uniqid(), 'room_id' => $roomId,
                    'rate_id' => Rate::first()->id, 'type_id' => $type->id, 'static_amount' => 300,
                ])->id,
                'room_id' => $roomId,
                'branch_id' => $branch->id,
                'terminated_at' => now()->addMinutes(20),
            ]);
            KioskBatchService::markPicked($branch->id, $roomId);
        }

        // Despite all picks, batch should NOT have been thrown — same rooms
        // still in batch (now all 'picked').
        $stillSameSlots = KioskCurrentBatch::where('branch_id', $branch->id)
            ->where('type_id', $type->id)
            ->pluck('room_id')
            ->sort()
            ->values()
            ->toArray();
        $this->assertEquals($initialRoomIds, $stillSameSlots, 'markPicked must not auto-throw — same slots remain.');

        // Batch has one slot per floor (2 floors → 2 slots), not one per room.
        $allPicked = KioskCurrentBatch::where('branch_id', $branch->id)
            ->where('slot_status', 'picked')
            ->count();
        $this->assertEquals(2, $allPicked);
    }

    /** @test */
    public function maybe_throw_next_batch_fires_only_when_all_holds_are_cleared()
    {
        [$branch, $type, $floors, $rooms] = $this->seedBranchWithFloorsAndRooms(
            floors: 2,
            roomsPerFloor: 2,
        );

        KioskBatchService::throwNextBatch($branch->id, $type->id);
        $initialRoomIds = KioskCurrentBatch::where('branch_id', $branch->id)
            ->pluck('room_id')
            ->sort()
            ->values()
            ->toArray();

        // Reserve every batch room and mark them picked.
        foreach ($initialRoomIds as $roomId) {
            $guest = Guest::create([
                'branch_id' => $branch->id, 'name' => 'G' . $roomId, 'contact' => 'N/A',
                'qr_code' => 'Q' . uniqid(), 'room_id' => $roomId,
                'rate_id' => Rate::first()->id, 'type_id' => $type->id, 'static_amount' => 300,
            ]);
            TemporaryCheckInKiosk::create([
                'guest_id' => $guest->id, 'room_id' => $roomId,
                'branch_id' => $branch->id, 'terminated_at' => now()->addMinutes(20),
            ]);
            KioskBatchService::markPicked($branch->id, $roomId);
        }

        // While any temp hold exists, the throw should NOT fire.
        $this->assertFalse(KioskBatchService::maybeThrowNextBatch($branch->id, $type->id));

        // Clear all but one hold — still a wait.
        TemporaryCheckInKiosk::where('branch_id', $branch->id)
            ->where('room_id', '!=', $initialRoomIds[0])
            ->delete();
        $this->assertFalse(KioskBatchService::maybeThrowNextBatch($branch->id, $type->id));

        // Clear the last hold — now it should fire.
        TemporaryCheckInKiosk::where('branch_id', $branch->id)->delete();

        // Mark the rooms Occupied so they don't get re-picked by the new batch.
        Room::whereIn('id', $initialRoomIds)->update(['status' => 'Occupied']);

        $threw = KioskBatchService::maybeThrowNextBatch($branch->id, $type->id);
        $this->assertTrue($threw);

        $newRoomIds = KioskCurrentBatch::where('branch_id', $branch->id)
            ->pluck('room_id')
            ->sort()
            ->values()
            ->toArray();
        $this->assertNotEquals($initialRoomIds, $newRoomIds, 'New batch must contain different rooms.');
    }

    /** @test */
    public function cancel_mid_batch_keeps_old_slots_visible()
    {
        // Spec: when frontdesk cancels (returnToBatch), the cancelled room
        // should reappear as 'active' in the SAME batch — not be lost to
        // the next batch's waiting stack.
        [$branch, $type, $floors, $rooms] = $this->seedBranchWithFloorsAndRooms(
            floors: 2,
            roomsPerFloor: 2,
        );

        KioskBatchService::throwNextBatch($branch->id, $type->id);
        $initialRoomIds = KioskCurrentBatch::where('branch_id', $branch->id)
            ->pluck('room_id')
            ->sort()
            ->values()
            ->toArray();

        foreach ($initialRoomIds as $roomId) {
            $guest = Guest::create([
                'branch_id' => $branch->id, 'name' => 'G' . $roomId, 'contact' => 'N/A',
                'qr_code' => 'Q' . uniqid(), 'room_id' => $roomId,
                'rate_id' => Rate::first()->id, 'type_id' => $type->id, 'static_amount' => 300,
            ]);
            TemporaryCheckInKiosk::create([
                'guest_id' => $guest->id, 'room_id' => $roomId,
                'branch_id' => $branch->id, 'terminated_at' => now()->addMinutes(20),
            ]);
            KioskBatchService::markPicked($branch->id, $roomId);
        }

        // Frontdesk cancels the LAST one — should restore its slot to active.
        $lastRoom = end($initialRoomIds);
        KioskBatchService::returnToBatch($branch->id, $lastRoom);

        // Same batch — same slot rooms — last one is active again.
        $sameSlots = KioskCurrentBatch::where('branch_id', $branch->id)
            ->pluck('room_id')
            ->sort()
            ->values()
            ->toArray();
        $this->assertEquals($initialRoomIds, $sameSlots);

        $lastSlotStatus = KioskCurrentBatch::where('branch_id', $branch->id)
            ->where('room_id', $lastRoom)
            ->value('slot_status');
        $this->assertEquals('active', $lastSlotStatus, 'Cancelled room must reappear as active in the same batch.');
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
        KioskBatchService::maybeFillEmptySlot($floor3Room);

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

        KioskBatchService::maybeFillEmptySlot($floor1SecondRoom);

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
    public function preview_batches_returns_upcoming_rooms_in_natural_order()
    {
        // Floor 1 has 6 available Doubles — current batch will take 1, then
        // Batch +1 takes next lowest, Batch +2 the one after that.
        $branch = Branch::create(['name' => 'Preview Test ' . uniqid(), 'kiosk_time_limit' => 10]);
        $type = Type::create(['branch_id' => $branch->id, 'name' => 'Test']);
        $stayingHour = StayingHour::create(['branch_id' => $branch->id, 'number' => 12]);
        Rate::create(['branch_id' => $branch->id, 'type_id' => $type->id, 'staying_hour_id' => $stayingHour->id, 'amount' => 300]);

        $floor1 = Floor::create(['branch_id' => $branch->id, 'number' => 1]);

        foreach (['3', '21', '7', '9', '34', '52'] as $num) {
            Room::create([
                'branch_id' => $branch->id,
                'floor_id' => $floor1->id,
                'type_id' => $type->id,
                'number' => $num,
                'status' => 'Available',
                'is_priority' => true,
            ]);
        }

        // Throw the current batch — should pick "3".
        KioskBatchService::throwNextBatch($branch->id, $type->id);

        // Preview the next 2 batches (excluding current).
        $upcoming = KioskBatchService::previewBatches($branch->id, $type->id, 2);

        $this->assertCount(2, $upcoming, 'Should return 2 batches.');
        // Batch +1: next-lowest after 3 is 7.
        $this->assertEquals('7', $upcoming[0][0]['room_number']);
        // Batch +2: after 3, 7 is 9.
        $this->assertEquals('9', $upcoming[1][0]['room_number']);
    }

    /** @test */
    public function preview_batches_returns_blank_when_floor_runs_out()
    {
        $branch = Branch::create(['name' => 'Blank Preview ' . uniqid(), 'kiosk_time_limit' => 10]);
        $type = Type::create(['branch_id' => $branch->id, 'name' => 'Test']);
        $stayingHour = StayingHour::create(['branch_id' => $branch->id, 'number' => 12]);
        Rate::create(['branch_id' => $branch->id, 'type_id' => $type->id, 'staying_hour_id' => $stayingHour->id, 'amount' => 300]);

        $floor1 = Floor::create(['branch_id' => $branch->id, 'number' => 1]);

        // Only 2 Available rooms on this floor. Current batch takes 1 → only
        // 1 left for Batch +1 → Batch +2 has nothing.
        Room::create(['branch_id' => $branch->id, 'floor_id' => $floor1->id, 'type_id' => $type->id, 'number' => '5', 'status' => 'Available', 'is_priority' => true]);
        Room::create(['branch_id' => $branch->id, 'floor_id' => $floor1->id, 'type_id' => $type->id, 'number' => '8', 'status' => 'Available', 'is_priority' => true]);

        KioskBatchService::throwNextBatch($branch->id, $type->id);
        $upcoming = KioskBatchService::previewBatches($branch->id, $type->id, 2);

        $this->assertEquals('8', $upcoming[0][0]['room_number'], 'Batch +1 should pick the remaining Room 8.');
        $this->assertNull($upcoming[1][0]['room_number'], 'Batch +2 should be blank — nothing left.');
    }

    /** @test */
    public function throw_skips_rooms_with_active_kiosk_holds()
    {
        // The kiosk's render() filters out rooms in temporary_check_in_kiosks,
        // temporary_reserveds, and recent orphan-Guest holds. throwNextBatch
        // must apply the same exclusion or it can re-pick rooms whose status
        // is still "Available" but are already reserved — leaving the slot
        // "active" while the kiosk shows SORRY.
        $branch = Branch::create(['name' => 'Skip held ' . uniqid(), 'kiosk_time_limit' => 10]);
        $type = Type::create(['branch_id' => $branch->id, 'name' => 'Test']);
        $stayingHour = StayingHour::create(['branch_id' => $branch->id, 'number' => 12]);
        Rate::create(['branch_id' => $branch->id, 'type_id' => $type->id, 'staying_hour_id' => $stayingHour->id, 'amount' => 300]);

        $f1 = Floor::create(['branch_id' => $branch->id, 'number' => 1]);

        $heldRoom = Room::create(['branch_id' => $branch->id, 'floor_id' => $f1->id, 'type_id' => $type->id, 'number' => '1', 'status' => 'Available', 'is_priority' => true]);
        $freeRoom = Room::create(['branch_id' => $branch->id, 'floor_id' => $f1->id, 'type_id' => $type->id, 'number' => '5', 'status' => 'Available', 'is_priority' => true]);

        // Simulate an active kiosk hold on Room "1" (would be lowest natsort).
        $heldGuest = Guest::create([
            'branch_id' => $branch->id,
            'name' => 'Held Guest',
            'contact' => 'N/A',
            'qr_code' => 'TEST' . uniqid(),
            'room_id' => $heldRoom->id,
            'rate_id' => Rate::first()->id,
            'type_id' => $type->id,
            'static_amount' => 300,
        ]);
        TemporaryCheckInKiosk::create([
            'guest_id' => $heldGuest->id,
            'room_id' => $heldRoom->id,
            'branch_id' => $branch->id,
            'terminated_at' => now()->addMinutes(20),
        ]);

        KioskBatchService::throwNextBatch($branch->id, $type->id);

        $picked = Room::find(KioskCurrentBatch::where('branch_id', $branch->id)->where('floor_id', $f1->id)->value('room_id'))->number;
        $this->assertEquals('5', $picked, 'Should skip Room "1" (held in temporary_check_in_kiosks) and pick "5" instead.');
    }

    /** @test */
    public function throw_prefers_never_used_rooms_then_earliest_cleaned()
    {
        // Goal of the round-robin spec: don't keep picking the same low-
        // numbered room while higher-numbered ones sit idle. Tier 1 is
        // never-used (last_checkin_at IS NULL); Tier 2 is earliest-cleaned
        // (FIFO from the roomboy entry). Within each tier, natsort lowest wins.
        $branch = Branch::create(['name' => 'RR ' . uniqid(), 'kiosk_time_limit' => 10]);
        $type = Type::create(['branch_id' => $branch->id, 'name' => 'Test']);
        $stayingHour = StayingHour::create(['branch_id' => $branch->id, 'number' => 12]);
        Rate::create(['branch_id' => $branch->id, 'type_id' => $type->id, 'staying_hour_id' => $stayingHour->id, 'amount' => 300]);

        $f1 = Floor::create(['branch_id' => $branch->id, 'number' => 1]);

        // Room "3" used yesterday but cleaned 1 hour ago. Room "5" never used.
        // Room "10" used last week but cleaned 3 hours ago (earliest of the
        // used set). Tier 1 must pick "5"; once "5" is gone, Tier 2 must pick
        // "10" (earliest cleaning entry, NOT "3" which would have won under
        // the old last_checkin_at rule).
        Room::create(['branch_id' => $branch->id, 'floor_id' => $f1->id, 'type_id' => $type->id, 'number' => '3', 'status' => 'Available', 'is_priority' => true, 'last_checkin_at' => now()->subDay(), 'last_cleaned_at' => now()->subHour()]);
        Room::create(['branch_id' => $branch->id, 'floor_id' => $f1->id, 'type_id' => $type->id, 'number' => '5', 'status' => 'Available', 'is_priority' => true, 'last_checkin_at' => null, 'last_cleaned_at' => null]);
        Room::create(['branch_id' => $branch->id, 'floor_id' => $f1->id, 'type_id' => $type->id, 'number' => '10', 'status' => 'Available', 'is_priority' => true, 'last_checkin_at' => now()->subWeek(), 'last_cleaned_at' => now()->subHours(3)]);

        KioskBatchService::throwNextBatch($branch->id, $type->id);

        $picked = Room::find(KioskCurrentBatch::where('branch_id', $branch->id)->where('floor_id', $f1->id)->value('room_id'))->number;
        $this->assertEquals('5', $picked, 'Should pick the never-used room "5" before previously-used ones.');

        // With "5" newly used (and now also cleaned), reset and re-throw.
        // Tier 2 should pick "10" because it was cleaned earliest among used
        // rooms — even though "3" was checked-in more recently than "10".
        Room::where('floor_id', $f1->id)->where('number', '5')->update(['last_checkin_at' => now(), 'last_cleaned_at' => now()]);
        KioskCurrentBatch::where('branch_id', $branch->id)->delete();
        KioskBatchService::throwNextBatch($branch->id, $type->id);

        $picked2 = Room::find(KioskCurrentBatch::where('branch_id', $branch->id)->where('floor_id', $f1->id)->value('room_id'))->number;
        $this->assertEquals('10', $picked2, 'No never-used left; Tier 2 should pick "10" (earliest cleaned, FIFO).');
    }

    /** @test */
    public function refresh_floor_slot_replaces_picked_with_next_priority_room_on_same_floor()
    {
        // After frontdesk confirms a kiosk pick, the picked slot is replaced
        // immediately by the next-priority clean room on the same floor.
        // Other floors must not be touched.
        [$branch, $type, $floors, $rooms] = $this->seedBranchWithFloorsAndRooms(
            floors: 2,
            roomsPerFloor: 3,
        );

        KioskBatchService::throwNextBatch($branch->id, $type->id);

        $f1 = $floors[0];
        $f2 = $floors[1];

        $f1PickedRoomId = KioskCurrentBatch::where('branch_id', $branch->id)
            ->where('type_id', $type->id)
            ->where('floor_id', $f1->id)
            ->value('room_id');
        $f2BeforeRoomId = KioskCurrentBatch::where('branch_id', $branch->id)
            ->where('type_id', $type->id)
            ->where('floor_id', $f2->id)
            ->value('room_id');

        // Simulate the kiosk pick + frontdesk confirm path on Floor 1.
        Room::where('id', $f1PickedRoomId)->update(['status' => 'Occupied']);
        KioskBatchService::markPicked($branch->id, $f1PickedRoomId);

        KioskBatchService::refreshSlot($branch->id, $type->id);

        $f1Slot = KioskCurrentBatch::where('branch_id', $branch->id)
            ->where('type_id', $type->id)
            ->where('floor_id', $f1->id)
            ->first();
        $f2Slot = KioskCurrentBatch::where('branch_id', $branch->id)
            ->where('type_id', $type->id)
            ->where('floor_id', $f2->id)
            ->first();

        $this->assertNotNull($f1Slot, 'F1 should have a fresh active slot, not be blank.');
        $this->assertEquals('active', $f1Slot->slot_status);
        $this->assertNotEquals($f1PickedRoomId, $f1Slot->room_id, 'F1 slot must point at a different (next-priority) room.');
        $this->assertEquals($f2BeforeRoomId, $f2Slot->room_id, 'F2 slot must be untouched.');
        $this->assertEquals('active', $f2Slot->slot_status);
    }

    /** @test */
    public function refresh_floor_slot_leaves_floor_blank_when_no_replacement()
    {
        // No spare clean room → slot stays empty so a future
        // roomboy `maybeFillEmptySlot` can promote the next cleaning.
        $branch = Branch::create(['name' => 'No spare refresh ' . uniqid(), 'kiosk_time_limit' => 10]);
        $type = Type::create(['branch_id' => $branch->id, 'name' => 'Test']);
        $stayingHour = StayingHour::create(['branch_id' => $branch->id, 'number' => 12]);
        Rate::create(['branch_id' => $branch->id, 'type_id' => $type->id, 'staying_hour_id' => $stayingHour->id, 'amount' => 300]);

        $f1 = Floor::create(['branch_id' => $branch->id, 'number' => 1]);

        $onlyRoom = Room::create(['branch_id' => $branch->id, 'floor_id' => $f1->id, 'type_id' => $type->id, 'number' => '1', 'status' => 'Available', 'is_priority' => true]);

        KioskBatchService::throwNextBatch($branch->id, $type->id);

        Room::where('id', $onlyRoom->id)->update(['status' => 'Occupied']);
        KioskBatchService::markPicked($branch->id, $onlyRoom->id);

        KioskBatchService::refreshSlot($branch->id, $type->id);

        $slot = KioskCurrentBatch::where('branch_id', $branch->id)
            ->where('type_id', $type->id)
            ->where('floor_id', $f1->id)
            ->first();

        $this->assertNull($slot, 'Floor should be blank — no replacement available.');
    }

    /** @test */
    public function refresh_if_stale_repairs_individual_stale_slots_in_place()
    {
        // Floor 1 has 1 active room + 2 spares, Floor 2 has 1 active room + 1 spare.
        // Mark Floor 1's active room Occupied. The other slot (F2) is still usable, so the
        // OLD logic returned false (only refreshes when ALL stale). New logic must replace
        // F1's slot with the next available room without touching F2.
        $branch = Branch::create(['name' => 'In-place repair ' . uniqid(), 'kiosk_time_limit' => 10]);
        $type = Type::create(['branch_id' => $branch->id, 'name' => 'Test']);
        $stayingHour = StayingHour::create(['branch_id' => $branch->id, 'number' => 12]);
        Rate::create(['branch_id' => $branch->id, 'type_id' => $type->id, 'staying_hour_id' => $stayingHour->id, 'amount' => 300]);

        $f1 = Floor::create(['branch_id' => $branch->id, 'number' => 1]);
        $f2 = Floor::create(['branch_id' => $branch->id, 'number' => 2]);

        // F1: rooms "1", "2", "3" (current batch will pick "1"; spares "2","3")
        foreach (['1', '2', '3'] as $n) {
            Room::create(['branch_id' => $branch->id, 'floor_id' => $f1->id, 'type_id' => $type->id, 'number' => $n, 'status' => 'Available', 'is_priority' => true]);
        }
        // F2: rooms "10", "11" (current batch picks "10"; spare "11")
        foreach (['10', '11'] as $n) {
            Room::create(['branch_id' => $branch->id, 'floor_id' => $f2->id, 'type_id' => $type->id, 'number' => $n, 'status' => 'Available', 'is_priority' => true]);
        }

        KioskBatchService::throwNextBatch($branch->id, $type->id);

        // Sanity: F1 picked "1", F2 picked "10".
        $f1Active = Room::find(KioskCurrentBatch::where('branch_id', $branch->id)->where('floor_id', $f1->id)->value('room_id'))->number;
        $f2Active = Room::find(KioskCurrentBatch::where('branch_id', $branch->id)->where('floor_id', $f2->id)->value('room_id'))->number;
        $this->assertEquals('1', $f1Active);
        $this->assertEquals('10', $f2Active);

        // Make F1's batch room Occupied (non-kiosk path).
        Room::where('floor_id', $f1->id)->where('number', '1')->update(['status' => 'Occupied']);

        // Refresh — should fix F1 only, leave F2 alone.
        $changed = KioskBatchService::refreshIfStale($branch->id, $type->id);
        $this->assertTrue($changed);

        $f1NewActive = Room::find(KioskCurrentBatch::where('branch_id', $branch->id)->where('floor_id', $f1->id)->value('room_id'))->number;
        $f2NewActive = Room::find(KioskCurrentBatch::where('branch_id', $branch->id)->where('floor_id', $f2->id)->value('room_id'))->number;
        $this->assertEquals('2', $f1NewActive, 'F1 should swap to the next available room "2".');
        $this->assertEquals('10', $f2NewActive, 'F2 must stay untouched (was not stale).');
    }

    /** @test */
    public function refresh_if_stale_deletes_slot_when_no_replacement_on_that_floor()
    {
        // Floor 1 has only 1 room. Mark it Occupied — no replacement possible.
        // F2 is still good. Result: F1 slot deleted (floor blank), F2 unchanged.
        $branch = Branch::create(['name' => 'No spare ' . uniqid(), 'kiosk_time_limit' => 10]);
        $type = Type::create(['branch_id' => $branch->id, 'name' => 'Test']);
        $stayingHour = StayingHour::create(['branch_id' => $branch->id, 'number' => 12]);
        Rate::create(['branch_id' => $branch->id, 'type_id' => $type->id, 'staying_hour_id' => $stayingHour->id, 'amount' => 300]);

        $f1 = Floor::create(['branch_id' => $branch->id, 'number' => 1]);
        $f2 = Floor::create(['branch_id' => $branch->id, 'number' => 2]);

        Room::create(['branch_id' => $branch->id, 'floor_id' => $f1->id, 'type_id' => $type->id, 'number' => '1', 'status' => 'Available', 'is_priority' => true]);
        Room::create(['branch_id' => $branch->id, 'floor_id' => $f2->id, 'type_id' => $type->id, 'number' => '10', 'status' => 'Available', 'is_priority' => true]);

        KioskBatchService::throwNextBatch($branch->id, $type->id);
        Room::where('floor_id', $f1->id)->update(['status' => 'Occupied']);

        $changed = KioskBatchService::refreshIfStale($branch->id, $type->id);
        $this->assertTrue($changed);

        $f1Slot = KioskCurrentBatch::where('branch_id', $branch->id)->where('floor_id', $f1->id)->first();
        $f2Slot = KioskCurrentBatch::where('branch_id', $branch->id)->where('floor_id', $f2->id)->first();

        $this->assertNull($f1Slot, 'F1 slot should be deleted (no replacement room).');
        $this->assertNotNull($f2Slot, 'F2 slot should still exist.');
    }

    /** @test */
    public function refresh_if_stale_clears_stale_picked_slot_when_room_no_longer_occupied()
    {
        // A picked slot is "stale" if the room it points to is no longer
        // Occupied AND there's no temp_check_in_kiosks hold for it.
        // This happens when the kiosk-checked-in guest gets transferred or
        // checked out but the picked slot was never cleaned up.
        $branch = Branch::create(['name' => 'Stale picked ' . uniqid(), 'kiosk_time_limit' => 10]);
        $type = Type::create(['branch_id' => $branch->id, 'name' => 'Test']);
        $stayingHour = StayingHour::create(['branch_id' => $branch->id, 'number' => 12]);
        Rate::create(['branch_id' => $branch->id, 'type_id' => $type->id, 'staying_hour_id' => $stayingHour->id, 'amount' => 300]);

        $f1 = Floor::create(['branch_id' => $branch->id, 'number' => 1]);

        $picked = Room::create(['branch_id' => $branch->id, 'floor_id' => $f1->id, 'type_id' => $type->id, 'number' => '1', 'status' => 'Available', 'is_priority' => true]);
        $spare = Room::create(['branch_id' => $branch->id, 'floor_id' => $f1->id, 'type_id' => $type->id, 'number' => '2', 'status' => 'Available', 'is_priority' => true]);

        // Simulate the stuck state: a picked slot whose room cycled back to
        // Available without the slot being cleaned up.
        KioskCurrentBatch::create([
            'branch_id' => $branch->id,
            'type_id' => $type->id,
            'floor_id' => $f1->id,
            'room_id' => $picked->id,
            'slot_status' => KioskCurrentBatch::STATUS_PICKED,
        ]);

        $changed = KioskBatchService::refreshIfStale($branch->id, $type->id);
        $this->assertTrue($changed);

        // Stale picked slot deleted; a fresh active slot was thrown for the floor.
        $remaining = KioskCurrentBatch::where('branch_id', $branch->id)->where('type_id', $type->id)->get();
        $this->assertCount(1, $remaining, 'After cleanup, there should be exactly one fresh slot (the throw).');
        $this->assertEquals('active', $remaining->first()->slot_status);
    }

    /** @test */
    public function refresh_if_stale_clears_picked_orphan_when_room_occupied_and_no_tck()
    {
        // 4F regression (2026-04-30): a kiosk-pick → frontdesk-confirm finished
        // (room Occupied, TCK deleted) but the post-commit refreshSlot
        // never ran — leaving a 'picked' batch row pointing at an Occupied
        // room with NO live TCK. Old rule treated this as "in flight" and kept
        // it forever. New rule: no TCK = stale, regardless of room status.
        $branch = Branch::create(['name' => 'Orphan picked ' . uniqid(), 'kiosk_time_limit' => 10]);
        $type = Type::create(['branch_id' => $branch->id, 'name' => 'Test']);
        $stayingHour = StayingHour::create(['branch_id' => $branch->id, 'number' => 12]);
        Rate::create(['branch_id' => $branch->id, 'type_id' => $type->id, 'staying_hour_id' => $stayingHour->id, 'amount' => 300]);

        $f1 = Floor::create(['branch_id' => $branch->id, 'number' => 1]);

        // Room is Occupied (the original picker is now a real guest), but the
        // TCK was already deleted by the successful frontdesk confirm.
        $picked = Room::create(['branch_id' => $branch->id, 'floor_id' => $f1->id, 'type_id' => $type->id, 'number' => '1', 'status' => 'Occupied', 'is_priority' => true]);
        $spare  = Room::create(['branch_id' => $branch->id, 'floor_id' => $f1->id, 'type_id' => $type->id, 'number' => '2', 'status' => 'Available', 'is_priority' => true]);

        $slot = KioskCurrentBatch::create([
            'branch_id' => $branch->id,
            'type_id' => $type->id,
            'floor_id' => $f1->id,
            'room_id' => $picked->id,
            'slot_status' => KioskCurrentBatch::STATUS_PICKED,
        ]);

        $changed = KioskBatchService::refreshIfStale($branch->id, $type->id);
        $this->assertTrue($changed, 'Orphan picked slot (Occupied + no TCK) must be cleaned.');

        $this->assertNull(KioskCurrentBatch::find($slot->id), 'Orphan row must be deleted.');

        // After cleanup, the floor refills via throwNextBatch (since the batch
        // became empty) — the spare available room should now be the active
        // slot for floor 1.
        $remaining = KioskCurrentBatch::where('branch_id', $branch->id)
            ->where('type_id', $type->id)
            ->get();
        $this->assertCount(1, $remaining);
        $this->assertEquals('active', $remaining->first()->slot_status);
        $this->assertEquals($spare->id, $remaining->first()->room_id);
    }

    /** @test */
    public function refresh_if_stale_keeps_picked_slot_when_temp_hold_exists()
    {
        // If a temp_check_in_kiosks hold still exists for the room (kiosk pick
        // is in flight, frontdesk hasn't confirmed yet), the picked slot must
        // be preserved — the guest could still cancel and have the slot
        // returned to active via returnToBatch.
        $branch = Branch::create(['name' => 'Held picked ' . uniqid(), 'kiosk_time_limit' => 10]);
        $type = Type::create(['branch_id' => $branch->id, 'name' => 'Test']);
        $stayingHour = StayingHour::create(['branch_id' => $branch->id, 'number' => 12]);
        Rate::create(['branch_id' => $branch->id, 'type_id' => $type->id, 'staying_hour_id' => $stayingHour->id, 'amount' => 300]);

        $f1 = Floor::create(['branch_id' => $branch->id, 'number' => 1]);

        $picked = Room::create(['branch_id' => $branch->id, 'floor_id' => $f1->id, 'type_id' => $type->id, 'number' => '1', 'status' => 'Available', 'is_priority' => true]);

        $slot = KioskCurrentBatch::create([
            'branch_id' => $branch->id,
            'type_id' => $type->id,
            'floor_id' => $f1->id,
            'room_id' => $picked->id,
            'slot_status' => KioskCurrentBatch::STATUS_PICKED,
        ]);

        // Active kiosk hold — guest hasn't been confirmed yet.
        $heldGuest = Guest::create([
            'branch_id' => $branch->id,
            'name' => 'Held Guest',
            'contact' => 'N/A',
            'qr_code' => 'TEST' . uniqid(),
            'room_id' => $picked->id,
            'rate_id' => Rate::first()->id,
            'type_id' => $type->id,
            'static_amount' => 300,
        ]);
        TemporaryCheckInKiosk::create([
            'guest_id' => $heldGuest->id,
            'room_id' => $picked->id,
            'branch_id' => $branch->id,
            'terminated_at' => now()->addMinutes(20),
        ]);

        $changed = KioskBatchService::refreshIfStale($branch->id, $type->id);
        $this->assertFalse($changed, 'Picked slot with active kiosk hold should be kept.');

        $this->assertNotNull(KioskCurrentBatch::find($slot->id));
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
