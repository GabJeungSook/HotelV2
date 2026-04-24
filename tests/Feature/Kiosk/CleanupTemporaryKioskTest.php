<?php

namespace Tests\Feature\Kiosk;

use App\Models\Branch;
use App\Models\CheckinDetail;
use App\Models\Floor;
use App\Models\Guest;
use App\Models\KioskCurrentBatch;
use App\Models\Room;
use App\Models\TemporaryCheckInKiosk;
use App\Models\Transaction;
use App\Models\Type;
use App\Services\KioskBatchService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CleanupTemporaryKioskTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function cleanup_deletes_orphan_guest_when_hold_expires()
    {
        [$branch, $floor, $type, $room] = $this->seedScaffolding();

        // Simulate a kiosk check-in where the frontdesk NEVER confirmed.
        // The TemporaryCheckInKiosk row is old enough to be expired.
        $orphanGuest = Guest::create([
            'branch_id' => $branch->id,
            'name' => 'Orphan Test Guest',
            'qr_code' => 'TEST-ORPHAN-001',
            'room_id' => $room->id,
            'rate_id' => 1,
            'type_id' => $type->id,
            'static_amount' => 300,
        ]);

        $expiredHold = TemporaryCheckInKiosk::create([
            'branch_id' => $branch->id,
            'room_id' => $room->id,
            'guest_id' => $orphanGuest->id,
            'terminated_at' => Carbon::now()->subHours(1),
            'created_at' => Carbon::now()->subMinutes(30),
            'updated_at' => Carbon::now()->subMinutes(30),
        ]);

        $this->artisan('kiosk:cleanup')
            ->expectsOutputToContain('orphan guests')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('temporary_check_in_kiosks', ['id' => $expiredHold->id]);
        $this->assertDatabaseMissing('guests', ['id' => $orphanGuest->id]);
    }

    /** @test */
    public function cleanup_keeps_guest_that_already_has_checkin_detail()
    {
        [$branch, $floor, $type, $room] = $this->seedScaffolding();

        // Frontdesk already confirmed this one (it has a CheckinDetail).
        // Even if the hold row is old, the guest must NOT be deleted.
        $confirmedGuest = Guest::create([
            'branch_id' => $branch->id,
            'name' => 'Confirmed Test Guest',
            'qr_code' => 'TEST-CONFIRMED-001',
            'room_id' => $room->id,
            'rate_id' => 1,
            'type_id' => $type->id,
            'static_amount' => 300,
        ]);

        CheckinDetail::create([
            'guest_id' => $confirmedGuest->id,
            'type_id' => $type->id,
            'room_id' => $room->id,
            'rate_id' => 1,
            'static_amount' => 300,
            'hours_stayed' => 6,
            'check_in_at' => Carbon::now(),
            'check_out_at' => Carbon::now()->addHours(6),
            'is_long_stay' => false,
        ]);

        $expiredHold = TemporaryCheckInKiosk::create([
            'branch_id' => $branch->id,
            'room_id' => $room->id,
            'guest_id' => $confirmedGuest->id,
            'terminated_at' => Carbon::now()->subHours(1),
            'created_at' => Carbon::now()->subMinutes(30),
            'updated_at' => Carbon::now()->subMinutes(30),
        ]);

        $this->artisan('kiosk:cleanup')->assertExitCode(0);

        $this->assertDatabaseMissing('temporary_check_in_kiosks', ['id' => $expiredHold->id]);
        $this->assertDatabaseHas('guests', ['id' => $confirmedGuest->id]);
    }

    /** @test */
    public function cleanup_keeps_orphan_guest_that_has_transactions_attached()
    {
        // Past records may contain orphan guests (no CheckinDetail) that still
        // have real transactions tied to them — representing real money.
        // Cleanup must skip these so the guest/transaction link is preserved
        // for manual investigation. Deleting them would silently corrupt reports.
        [$branch, $floor, $type, $room] = $this->seedScaffolding();

        $moneyOrphan = Guest::create([
            'branch_id' => $branch->id,
            'name' => 'Orphan With Transactions',
            'qr_code' => 'TEST-MONEY-ORPHAN-001',
            'room_id' => $room->id,
            'rate_id' => 1,
            'type_id' => $type->id,
            'static_amount' => 300,
        ]);

        Transaction::create([
            'branch_id' => $branch->id,
            'room_id' => $room->id,
            'guest_id' => $moneyOrphan->id,
            'floor_id' => $floor->id,
            'transaction_type_id' => 1,
            'assigned_frontdesk_id' => json_encode([1]),
            'description' => 'Test charge',
            'payable_amount' => 300,
            'deposit_amount' => 0,
            'remarks' => 'Test transaction for orphan',
        ]);

        $expiredHold = TemporaryCheckInKiosk::create([
            'branch_id' => $branch->id,
            'room_id' => $room->id,
            'guest_id' => $moneyOrphan->id,
            'terminated_at' => Carbon::now()->subHours(1),
            'created_at' => Carbon::now()->subMinutes(30),
            'updated_at' => Carbon::now()->subMinutes(30),
        ]);

        $this->artisan('kiosk:cleanup')->assertExitCode(0);

        $this->assertDatabaseMissing('temporary_check_in_kiosks', ['id' => $expiredHold->id]);
        $this->assertDatabaseHas('guests', ['id' => $moneyOrphan->id]);
    }

    /** @test */
    public function cleanup_returns_picked_slot_to_batch_on_timeout()
    {
        // Seed TWO floors so picking one room does not drain the whole batch
        // and trigger an auto re-throw — we need the slot to stay 'picked'
        // to verify that cleanup flips it back to 'active'.
        [$branch, $floor, $type, $room] = $this->seedScaffolding();

        $secondFloor = Floor::create([
            'branch_id' => $branch->id,
            'number' => 2,
        ]);

        $secondRoom = Room::create([
            'branch_id' => $branch->id,
            'floor_id' => $secondFloor->id,
            'type_id' => $type->id,
            'number' => rand(9000, 9999),
            'status' => 'Available',
            'is_priority' => true,
        ]);

        // Simulate kiosk batch state: this room was picked by a guest that
        // then walked away without frontdesk confirmation.
        KioskBatchService::throwNextBatch($branch->id, $type->id);
        KioskBatchService::markPicked($branch->id, $room->id);

        $this->assertEquals(
            'picked',
            KioskCurrentBatch::where('room_id', $room->id)->value('slot_status'),
            'precondition: slot should be picked before cleanup runs',
        );

        $orphanGuest = Guest::create([
            'branch_id' => $branch->id,
            'name' => 'Orphan Timeout Guest',
            'qr_code' => 'TEST-ORPHAN-TIMEOUT-001',
            'room_id' => $room->id,
            'rate_id' => 1,
            'type_id' => $type->id,
            'static_amount' => 300,
        ]);

        $expiredHold = TemporaryCheckInKiosk::create([
            'branch_id' => $branch->id,
            'room_id' => $room->id,
            'guest_id' => $orphanGuest->id,
            'terminated_at' => Carbon::now()->subHours(1),
            'created_at' => Carbon::now()->subMinutes(30),
            'updated_at' => Carbon::now()->subMinutes(30),
        ]);

        $this->artisan('kiosk:cleanup')->assertExitCode(0);

        // Hold + orphan guest removed, AND slot is active again so the
        // floor reappears on the kiosk instead of staying blank.
        $this->assertDatabaseMissing('temporary_check_in_kiosks', ['id' => $expiredHold->id]);
        $this->assertDatabaseMissing('guests', ['id' => $orphanGuest->id]);
        $this->assertEquals(
            'active',
            KioskCurrentBatch::where('room_id', $room->id)->value('slot_status'),
            'cleanup must return the picked slot to active so the floor reappears',
        );
    }

    /** @test */
    public function cleanup_does_not_touch_non_expired_holds()
    {
        [$branch, $floor, $type, $room] = $this->seedScaffolding();

        $fresh_minutes = max(($branch->kiosk_time_limit ?? 10) - 2, 1);

        $guest = Guest::create([
            'branch_id' => $branch->id,
            'name' => 'Fresh Test Guest',
            'qr_code' => 'TEST-FRESH-001',
            'room_id' => $room->id,
            'rate_id' => 1,
            'type_id' => $type->id,
            'static_amount' => 300,
        ]);

        $freshHold = TemporaryCheckInKiosk::create([
            'branch_id' => $branch->id,
            'room_id' => $room->id,
            'guest_id' => $guest->id,
            'terminated_at' => Carbon::now()->addMinutes(20),
            'created_at' => Carbon::now()->subMinutes($fresh_minutes),
            'updated_at' => Carbon::now()->subMinutes($fresh_minutes),
        ]);

        $this->artisan('kiosk:cleanup')->assertExitCode(0);

        $this->assertDatabaseHas('temporary_check_in_kiosks', ['id' => $freshHold->id]);
        $this->assertDatabaseHas('guests', ['id' => $guest->id]);
    }

    /**
     * Create a minimal Branch/Floor/Type/Room scaffolding for a test.
     *
     * @return array{0: Branch, 1: Floor, 2: Type, 3: Room}
     */
    private function seedScaffolding(): array
    {
        $branch = Branch::create([
            'name' => 'Kiosk Test Branch ' . uniqid(),
            'kiosk_time_limit' => 10,
        ]);

        $type = Type::create([
            'branch_id' => $branch->id,
            'name' => 'Test Type',
        ]);

        $floor = Floor::create([
            'branch_id' => $branch->id,
            'number' => 1,
        ]);

        $room = Room::create([
            'branch_id' => $branch->id,
            'floor_id' => $floor->id,
            'type_id' => $type->id,
            'number' => rand(9000, 9999),
            'status' => 'Available',
            'is_priority' => true,
        ]);

        return [$branch, $floor, $type, $room];
    }
}
