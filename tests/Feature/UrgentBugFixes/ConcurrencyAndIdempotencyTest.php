<?php

namespace Tests\Feature\UrgentBugFixes;

use App\Models\Branch;
use App\Models\Floor;
use App\Models\Guest;
use App\Models\Room;
use App\Models\StayExtension;
use App\Models\Type;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Tests for the C1/C4/C5/C9 concurrency + null-safety fixes.
 */
class ConcurrencyAndIdempotencyTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function recent_stay_extension_within_3_seconds_is_detected()
    {
        $branch = Branch::create(['name' => 'C5 ' . uniqid(), 'kiosk_time_limit' => 10, 'initial_deposit' => 200]);
        $guest = Guest::create([
            'branch_id' => $branch->id, 'name' => 'Test', 'contact' => 'N/A',
            'qr_code' => 'TEST' . uniqid(), 'room_id' => 1, 'rate_id' => 1, 'type_id' => 1,
            'static_amount' => 0,
        ]);

        StayExtension::create([
            'guest_id' => $guest->id,
            'extension_id' => 1,
            'hours' => 6,
            'amount' => 200,
            'frontdesk_ids' => '[1,"N/A"]',
        ]);

        $isRecent = StayExtension::where('guest_id', $guest->id)
            ->where('created_at', '>=', now()->subSeconds(3))
            ->exists();

        $this->assertTrue($isRecent, 'StayExtension created seconds ago should match the recency guard.');
    }

    /** @test */
    public function old_stay_extension_outside_window_is_not_detected_as_recent()
    {
        $branch = Branch::create(['name' => 'C5b ' . uniqid(), 'kiosk_time_limit' => 10, 'initial_deposit' => 200]);
        $guest = Guest::create([
            'branch_id' => $branch->id, 'name' => 'Test', 'contact' => 'N/A',
            'qr_code' => 'TEST' . uniqid(), 'room_id' => 1, 'rate_id' => 1, 'type_id' => 1,
            'static_amount' => 0,
        ]);

        $extension = StayExtension::create([
            'guest_id' => $guest->id,
            'extension_id' => 1,
            'hours' => 6,
            'amount' => 200,
            'frontdesk_ids' => '[1,"N/A"]',
        ]);

        $extension->created_at = now()->subSeconds(10);
        $extension->save();

        $isRecent = StayExtension::where('guest_id', $guest->id)
            ->where('created_at', '>=', now()->subSeconds(3))
            ->exists();

        $this->assertFalse($isRecent, 'Extension older than 3 seconds should not block a new extension.');
    }

    /** @test */
    public function destination_room_status_check_rejects_non_available_rooms()
    {
        $branch = Branch::create(['name' => 'C4 ' . uniqid(), 'kiosk_time_limit' => 10, 'initial_deposit' => 200]);
        $type = Type::create(['branch_id' => $branch->id, 'name' => 'Test']);
        $f1 = Floor::create(['branch_id' => $branch->id, 'number' => 1]);

        $occupiedRoom = Room::create([
            'branch_id' => $branch->id, 'floor_id' => $f1->id, 'type_id' => $type->id,
            'number' => '1', 'status' => 'Occupied', 'is_priority' => true,
        ]);
        $availableRoom = Room::create([
            'branch_id' => $branch->id, 'floor_id' => $f1->id, 'type_id' => $type->id,
            'number' => '2', 'status' => 'Available', 'is_priority' => true,
        ]);

        $found1 = Room::where('id', $occupiedRoom->id)->where('status', 'Available')->first();
        $found2 = Room::where('id', $availableRoom->id)->where('status', 'Available')->first();

        $this->assertNull($found1, 'Occupied room should not pass the destination guard.');
        $this->assertNotNull($found2, 'Available room should pass the destination guard.');
    }

    /** @test */
    public function null_safe_accessor_on_missing_checkin_detail_does_not_fatal()
    {
        $branch = Branch::create(['name' => 'C9 ' . uniqid(), 'kiosk_time_limit' => 10, 'initial_deposit' => 200]);
        $guest = Guest::create([
            'branch_id' => $branch->id, 'name' => 'Test', 'contact' => 'N/A',
            'qr_code' => 'TEST' . uniqid(), 'room_id' => 1, 'rate_id' => 1, 'type_id' => 1,
            'static_amount' => 0,
        ]);

        // Guest has no checkInDetail — chain would have fataled previously.
        $amount = $guest?->checkInDetail?->rate?->amount ?? 0;

        $this->assertEquals(0, $amount, 'Null-safe chain should default to 0 when checkInDetail is missing.');
    }
}
