<?php

namespace Tests\Feature\BackOffice;

use App\Http\Livewire\BackOffice\SalesReportV2;
use App\Models\Branch;
use App\Models\CheckinDetail;
use App\Models\Floor;
use App\Models\Frontdesk;
use App\Models\Guest;
use App\Models\Rate;
use App\Models\Room;
use App\Models\ShiftLog;
use App\Models\StayingHour;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\Type;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SalesReportV2Test extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Branch $branch;
    protected Floor $floor;
    protected Type $roomType;
    protected Room $room;
    protected Rate $rate;
    protected Frontdesk $frontdesk;
    protected ShiftLog $shiftLog;
    protected StayingHour $stayingHour;

    protected function setUp(): void
    {
        parent::setUp();

        // Create branch
        $this->branch = Branch::create([
            'name' => 'Test Branch',
        ]);

        // Create user with branch
        $this->user = User::factory()->create([
            'branch_id' => $this->branch->id,
            'branch_name' => $this->branch->name,
        ]);

        // Create floor
        $this->floor = Floor::create([
            'branch_id' => $this->branch->id,
            'number' => 1,
        ]);

        // Create room type
        $this->roomType = Type::create([
            'branch_id' => $this->branch->id,
            'name' => 'Standard',
        ]);

        // Create room
        $this->room = Room::create([
            'branch_id' => $this->branch->id,
            'floor_id' => $this->floor->id,
            'type_id' => $this->roomType->id,
            'number' => 101,
            'status' => 'available',
        ]);

        // Create staying hour
        $this->stayingHour = StayingHour::create([
            'branch_id' => $this->branch->id,
            'number' => 3,
        ]);

        // Create rate
        $this->rate = Rate::create([
            'branch_id' => $this->branch->id,
            'type_id' => $this->roomType->id,
            'room_id' => $this->room->id,
            'staying_hour_id' => $this->stayingHour->id,
            'amount' => 500,
            'is_available' => true,
        ]);

        // Create frontdesk
        $this->frontdesk = Frontdesk::create([
            'branch_id' => $this->branch->id,
            'user_id' => $this->user->id,
            'name' => 'Test Frontdesk',
            'number' => 1,
        ]);

        // Create shift log
        $this->shiftLog = ShiftLog::create([
            'branch_id' => $this->user->branch_id,
            'frontdesk_id' => $this->user->id,
            'frontdesk_ids' => json_encode([$this->user->id]),
            'time_in' => now()->setTime(8, 0),
            'shift' => 'AM',
        ]);

        // Seed transaction types if not exists
        $this->seedTransactionTypes();
    }

    protected function seedTransactionTypes(): void
    {
        $types = [
            ['id' => 1, 'name' => 'Check In'],
            ['id' => 2, 'name' => 'Deposit'],
            ['id' => 4, 'name' => 'Damage Charges'],
            ['id' => 5, 'name' => 'Cashout'],
            ['id' => 6, 'name' => 'Extend'],
            ['id' => 7, 'name' => 'Transfer Room'],
            ['id' => 8, 'name' => 'Amenities'],
            ['id' => 9, 'name' => 'Food and Beverages'],
        ];

        foreach ($types as $type) {
            TransactionType::firstOrCreate(['id' => $type['id']], $type);
        }
    }

    /**
     * Create a guest with all required fields.
     */
    protected function createGuest(array $overrides = []): Guest
    {
        return Guest::create(array_merge([
            'branch_id' => $this->branch->id,
            'room_id' => $this->room->id,
            'rate_id' => $this->rate->id,
            'type_id' => $this->roomType->id,
            'static_amount' => 500,
            'name' => 'Test Guest',
            'qr_code' => 'QR' . uniqid(),
        ], $overrides));
    }

    /**
     * Create a checkin detail with all required fields.
     */
    protected function createCheckinDetail(Guest $guest, array $overrides = []): CheckinDetail
    {
        return CheckinDetail::create(array_merge([
            'guest_id' => $guest->id,
            'type_id' => $this->roomType->id,
            'room_id' => $this->room->id,
            'rate_id' => $this->rate->id,
            'static_amount' => 500,
            'hours_stayed' => 3,
            'check_in_at' => now()->setTime(10, 0),
            'check_out_at' => now()->addHours(3),
            'is_long_stay' => false,
        ], $overrides));
    }

    /**
     * Create a transaction with all required fields.
     */
    protected function createTransaction(Guest $guest, CheckinDetail $checkinDetail, array $overrides = []): Transaction
    {
        return Transaction::create(array_merge([
            'branch_id' => $this->branch->id,
            'room_id' => $this->room->id,
            'guest_id' => $guest->id,
            'floor_id' => $this->floor->id,
            'transaction_type_id' => 1,
            'assigned_frontdesk_id' => json_encode([$this->user->id]),
            'description' => 'Room Charge',
            'remarks' => '',
            'shift_log_id' => $this->shiftLog->id,
            'checkin_detail_id' => $checkinDetail->id,
            'payable_amount' => 500,
            'paid_amount' => 500,
        ], $overrides));
    }

    /** @test */
    public function component_can_render()
    {
        $this->actingAs($this->user);

        Livewire::test(SalesReportV2::class)
            ->assertStatus(200)
            ->assertSee('SALES REPORT');
    }

    /** @test */
    public function shows_transactions_for_guests_occupying_rooms_during_date_range()
    {
        $this->actingAs($this->user);

        // Create guest who checked in YESTERDAY but is still staying TODAY
        $guest = $this->createGuest(['name' => 'Test Guest Forward']);

        $checkinDetail = $this->createCheckinDetail($guest, [
            'check_in_at' => now()->subDay()->setTime(22, 0), // Yesterday 10 PM
            'check_out_at' => now()->addHours(4), // Today + 4 hours (still staying)
        ]);

        // Create extension transaction TODAY for guest who checked in yesterday
        // This tests that forward guests' transactions appear when created within date range
        $this->createTransaction($guest, $checkinDetail, [
            'transaction_type_id' => 6, // Extension
            'description' => 'Extension',
            'created_at' => now()->setTime(8, 0), // Today morning
        ]);

        // Test: Filter for TODAY only
        // The guest checked in yesterday (forward guest), but has transaction today
        // SalesReportV2 should show the transaction and mark guest as FORWARDED
        $component = Livewire::test(SalesReportV2::class)
            ->set('filterMode', 'date_range')
            ->set('date_from', now()->toDateString())
            ->set('date_to', now()->toDateString())
            ->call('generateReport');

        // The guest should appear with their TODAY transaction
        $component->assertSee('TEST GUEST FORWARD');

        // Guest should be marked as forwarded since check-in was yesterday
        $salesRows = $component->get('salesRows');
        $this->assertTrue($salesRows[0]['is_forwarded']);
    }

    /** @test */
    public function filters_by_frontdesk_who_processed_transaction()
    {
        $this->actingAs($this->user);

        // Create second frontdesk user
        $user2 = User::factory()->create([
            'branch_id' => $this->branch->id,
            'branch_name' => $this->branch->name,
        ]);
        $frontdesk2 = Frontdesk::create([
            'branch_id' => $this->branch->id,
            'user_id' => $user2->id,
            'name' => 'Second Frontdesk',
            'number' => 2,
        ]);
        $shiftLog2 = ShiftLog::create([
            'branch_id' => $this->user->branch_id,
            'frontdesk_id' => $user2->id,
            'frontdesk_ids' => json_encode([$user2->id]),
            'time_in' => now()->setTime(20, 0),
            'shift' => 'PM',
        ]);

        // Create guest
        $guest = $this->createGuest(['name' => 'Filter Test Guest']);
        $checkinDetail = $this->createCheckinDetail($guest);

        // Transaction processed by first frontdesk
        $this->createTransaction($guest, $checkinDetail, [
            'shift_log_id' => $this->shiftLog->id, // First frontdesk
        ]);

        // Transaction processed by second frontdesk
        $this->createTransaction($guest, $checkinDetail, [
            'transaction_type_id' => 6, // Extension
            'shift_log_id' => $shiftLog2->id, // Second frontdesk
            'payable_amount' => 200,
            'paid_amount' => 200,
            'description' => 'Extension',
        ]);

        // Filter by second frontdesk - should only see extension
        $component = Livewire::test(SalesReportV2::class)
            ->set('filterMode', 'date_range')
            ->set('date_from', now()->toDateString())
            ->set('date_to', now()->toDateString())
            ->set('frontdesk', $user2->id)
            ->call('generateReport');

        // Should only show the extension (200), not the room charge (500)
        $this->assertEquals(200, $component->get('totalSales'));
    }

    /** @test */
    public function calculates_summary_by_transaction_type()
    {
        $this->actingAs($this->user);

        $guest = $this->createGuest(['name' => 'Summary Test Guest']);
        $checkinDetail = $this->createCheckinDetail($guest);

        // Room charge
        $this->createTransaction($guest, $checkinDetail, [
            'transaction_type_id' => 1,
            'payable_amount' => 500,
            'paid_amount' => 500,
        ]);

        // Extension
        $this->createTransaction($guest, $checkinDetail, [
            'transaction_type_id' => 6,
            'payable_amount' => 200,
            'paid_amount' => 200,
            'description' => 'Extension',
        ]);

        // Food
        $this->createTransaction($guest, $checkinDetail, [
            'transaction_type_id' => 9,
            'payable_amount' => 150,
            'paid_amount' => 150,
            'description' => 'Food',
        ]);

        $component = Livewire::test(SalesReportV2::class)
            ->set('filterMode', 'date_range')
            ->set('date_from', now()->toDateString())
            ->set('date_to', now()->toDateString())
            ->call('generateReport');

        $summary = $component->get('summaryByType');

        $this->assertEquals(500, $summary['room_charges']);
        $this->assertEquals(200, $summary['extensions']);
        $this->assertEquals(150, $summary['food']);
        $this->assertEquals(850, $summary['grand_total']); // 500 + 200 + 150
    }

    /** @test */
    public function excludes_deposits_and_cashouts_from_total()
    {
        $this->actingAs($this->user);

        $guest = $this->createGuest(['name' => 'Deposit Test Guest']);
        $checkinDetail = $this->createCheckinDetail($guest);

        // Room charge (should count)
        $this->createTransaction($guest, $checkinDetail, [
            'transaction_type_id' => 1,
            'payable_amount' => 500,
            'paid_amount' => 500,
        ]);

        // Deposit (should NOT count in total)
        $this->createTransaction($guest, $checkinDetail, [
            'transaction_type_id' => 2,
            'payable_amount' => 300,
            'paid_amount' => 300,
            'description' => 'Deposit',
        ]);

        $component = Livewire::test(SalesReportV2::class)
            ->set('filterMode', 'date_range')
            ->set('date_from', now()->toDateString())
            ->set('date_to', now()->toDateString())
            ->call('generateReport');

        // Total should be 500 (room charge only), not 800
        $this->assertEquals(500, $component->get('totalSales'));
    }

    /** @test */
    public function reset_filters_works()
    {
        $this->actingAs($this->user);

        $component = Livewire::test(SalesReportV2::class)
            ->set('date_from', '2024-01-01')
            ->set('date_to', '2024-01-31')
            ->set('frontdesk', 999)
            ->call('resetFilters');

        $this->assertEquals(now()->toDateString(), $component->get('date_from'));
        $this->assertEquals(now()->toDateString(), $component->get('date_to'));
        $this->assertNull($component->get('frontdesk'));
    }

    /** @test */
    public function marks_guest_as_forwarded_when_checked_in_during_different_shift()
    {
        $this->actingAs($this->user);

        // Create AM shift log (completed - has time_out)
        $amShiftLog = ShiftLog::create([
            'branch_id' => $this->user->branch_id,
            'frontdesk_id' => $this->user->id,
            'frontdesk_ids' => json_encode([$this->user->id]),
            'time_in' => now()->setTime(8, 0),
            'time_out' => now()->setTime(16, 0),
            'shift' => 'AM',
        ]);

        // Create PM shift log (completed - has time_out)
        $pmShiftLog = ShiftLog::create([
            'branch_id' => $this->user->branch_id,
            'frontdesk_id' => $this->user->id,
            'frontdesk_ids' => json_encode([$this->user->id]),
            'time_in' => now()->setTime(20, 0),
            'time_out' => now()->addDay()->setTime(7, 59),
            'shift' => 'PM',
        ]);

        // Create guest who checked in during AM shift
        $guest = $this->createGuest(['name' => 'AM Guest Forward']);
        $checkinDetail = $this->createCheckinDetail($guest, [
            'check_in_at' => now()->setTime(9, 0),
            'check_out_at' => now()->addDay(), // Still occupying (future checkout)
        ]);

        // Check-in transaction processed during AM shift
        $this->createTransaction($guest, $checkinDetail, [
            'transaction_type_id' => 1, // Check In
            'shift_log_id' => $amShiftLog->id,
            'created_at' => now()->setTime(9, 0),
        ]);

        // Extension transaction processed during PM shift
        $this->createTransaction($guest, $checkinDetail, [
            'transaction_type_id' => 6, // Extension
            'shift_log_id' => $pmShiftLog->id,
            'payable_amount' => 200,
            'paid_amount' => 200,
            'description' => 'Extension',
            'created_at' => now()->setTime(21, 0),
        ]);

        // Filter for PM shift using shift mode - guest should be marked as FORWARDED
        $component = Livewire::test(SalesReportV2::class)
            ->set('filterMode', 'shift')
            ->set('selectedShiftLogId', $pmShiftLog->id)
            ->call('generateReport');

        $salesRows = $component->get('salesRows');

        // Should have 2 rows: FWD ROOM + extension transaction
        $this->assertCount(2, $salesRows);

        // FWD ROOM row should exist
        $fwdRoomRow = collect($salesRows)->firstWhere('transaction_type', 'FWD ROOM');
        $this->assertNotNull($fwdRoomRow);
        $this->assertTrue($fwdRoomRow['is_forwarded_guest_row']);

        // Extension row should be marked as forwarded
        $extensionRow = collect($salesRows)->first(fn($r) => $r['transaction_type'] !== 'FWD ROOM');
        $this->assertTrue($extensionRow['is_forwarded']);

        // Forwarded count should be 1
        $this->assertEquals(1, $component->get('forwardedCount'));

        // Forwarded room should show ORIGINAL room charge (500) from previous shift
        $this->assertEquals(500, $component->get('forwardedRoom'));
    }

    /** @test */
    public function does_not_mark_guest_as_forwarded_when_checked_in_during_same_shift()
    {
        $this->actingAs($this->user);

        // Create PM shift log (completed - has time_out)
        $pmShiftLog = ShiftLog::create([
            'branch_id' => $this->user->branch_id,
            'frontdesk_id' => $this->user->id,
            'frontdesk_ids' => json_encode([$this->user->id]),
            'time_in' => now()->setTime(20, 0),
            'time_out' => now()->addDay()->setTime(7, 59),
            'shift' => 'PM',
        ]);

        // Create guest who checked in during PM shift
        $guest = $this->createGuest(['name' => 'PM Guest Same Shift']);
        $checkinDetail = $this->createCheckinDetail($guest, [
            'check_in_at' => now()->setTime(21, 0),
            'check_out_at' => now()->addDay(), // Still occupying (future checkout)
        ]);

        // Check-in transaction processed during PM shift (within time_in to time_out)
        $this->createTransaction($guest, $checkinDetail, [
            'transaction_type_id' => 1,
            'shift_log_id' => $pmShiftLog->id,
            'created_at' => now()->setTime(21, 0),
        ]);

        // Filter for PM shift using shift mode - guest should NOT be forwarded
        $component = Livewire::test(SalesReportV2::class)
            ->set('filterMode', 'shift')
            ->set('selectedShiftLogId', $pmShiftLog->id)
            ->call('generateReport');

        $salesRows = $component->get('salesRows');

        // Guest should NOT be marked as forwarded
        $this->assertFalse($salesRows[0]['is_forwarded']);

        // Forwarded count should be 0
        $this->assertEquals(0, $component->get('forwardedCount'));
    }

    /** @test */
    public function calculates_forwarded_room_and_deposit_totals()
    {
        $this->actingAs($this->user);

        // Create AM shift log (completed)
        $amShiftLog = ShiftLog::create([
            'branch_id' => $this->user->branch_id,
            'frontdesk_id' => $this->user->id,
            'frontdesk_ids' => json_encode([$this->user->id]),
            'time_in' => now()->setTime(8, 0),
            'time_out' => now()->setTime(16, 0),
            'shift' => 'AM',
        ]);

        // Create PM shift log (completed)
        $pmShiftLog = ShiftLog::create([
            'branch_id' => $this->user->branch_id,
            'frontdesk_id' => $this->user->id,
            'frontdesk_ids' => json_encode([$this->user->id]),
            'time_in' => now()->setTime(20, 0),
            'time_out' => now()->addDay()->setTime(7, 59),
            'shift' => 'PM',
        ]);

        // Create guest who checked in during AM shift
        $guest = $this->createGuest(['name' => 'Forwarded Guest']);
        $checkinDetail = $this->createCheckinDetail($guest, [
            'check_in_at' => now()->setTime(9, 0),
            'check_out_at' => now()->addDay(),
        ]);

        // Check-in transaction (room charge) during AM shift
        $this->createTransaction($guest, $checkinDetail, [
            'transaction_type_id' => 1,
            'shift_log_id' => $amShiftLog->id,
            'payable_amount' => 500,
            'paid_amount' => 500,
            'created_at' => now()->setTime(9, 0),
        ]);

        // Deposit during AM shift
        $this->createTransaction($guest, $checkinDetail, [
            'transaction_type_id' => 2, // Deposit
            'shift_log_id' => $amShiftLog->id,
            'payable_amount' => 300,
            'paid_amount' => 300,
            'description' => 'Guest Deposit',
            'created_at' => now()->setTime(9, 5),
        ]);

        // Filter for AM shift - both transactions should show with forwarded totals = 0
        $component = Livewire::test(SalesReportV2::class)
            ->set('filterMode', 'shift')
            ->set('selectedShiftLogId', $amShiftLog->id)
            ->call('generateReport');

        // Guest checked in during AM, viewing AM report - NOT forwarded
        $this->assertEquals(0, $component->get('forwardedRoom'));
        $this->assertEquals(0, $component->get('forwardedRoomDeposit'));
        $this->assertEquals(0, $component->get('forwardedGuestDeposit'));

        // Now filter for PM shift - guest should be forwarded with ORIGINAL amounts
        $component = Livewire::test(SalesReportV2::class)
            ->set('filterMode', 'shift')
            ->set('selectedShiftLogId', $pmShiftLog->id)
            ->call('generateReport');

        // Forwarded guest rows should show (no transactions but still occupying)
        // 2 rows: FWD ROOM + FWD GUEST DEPOSIT
        $salesRows = $component->get('salesRows');
        $this->assertCount(2, $salesRows);

        // The forwarded room row
        $fwdRoomRow = collect($salesRows)->firstWhere('transaction_type', 'FWD ROOM');
        $this->assertNotNull($fwdRoomRow);
        $this->assertTrue($fwdRoomRow['is_forwarded_guest_row']);
        $this->assertEquals('FORWARDED GUEST', $fwdRoomRow['guest_name']);
        $this->assertEquals(500, $fwdRoomRow['amount']);
        $this->assertEquals(0, $fwdRoomRow['total']); // No sales credit for this shift

        // The forwarded guest deposit row
        $fwdDepositRow = collect($salesRows)->firstWhere('transaction_type', 'FWD GUEST DEPOSIT');
        $this->assertNotNull($fwdDepositRow);
        $this->assertTrue($fwdDepositRow['is_forwarded_guest_row']);
        $this->assertEquals(300, $fwdDepositRow['amount']);
        $this->assertEquals(0, $fwdDepositRow['total']);

        // Forwarded totals: room from FWD rows, room deposit = forwardedCount × 200
        $this->assertEquals(500, $component->get('forwardedRoom'));
        $this->assertEquals(200, $component->get('forwardedRoomDeposit')); // 1 forwarded guest × 200
        $this->assertEquals(300, $component->get('forwardedGuestDeposit'));
    }

    /** @test */
    public function shows_forwarded_room_when_check_in_transaction_is_in_viewed_shift_but_guest_checked_in_earlier()
    {
        $this->actingAs($this->user);

        // Create AM shift log
        $amShiftLog = ShiftLog::create([
            'branch_id' => $this->user->branch_id,
            'frontdesk_id' => $this->user->id,
            'frontdesk_ids' => json_encode([$this->user->id]),
            'time_in' => now()->setTime(8, 0),
            'time_out' => now()->setTime(16, 0),
            'shift' => 'AM',
        ]);

        // Create PM shift log
        $pmShiftLog = ShiftLog::create([
            'branch_id' => $this->user->branch_id,
            'frontdesk_id' => $this->user->id,
            'frontdesk_ids' => json_encode([$this->user->id]),
            'time_in' => now()->setTime(20, 0),
            'time_out' => now()->addDay()->setTime(7, 59),
            'shift' => 'PM',
        ]);

        // Guest 1: Checked in during AM, still occupying
        $guest1 = $this->createGuest(['name' => 'AM Guest']);
        $checkinDetail1 = $this->createCheckinDetail($guest1, [
            'check_in_at' => now()->setTime(9, 0),
            'check_out_at' => now()->addDay(),
        ]);

        // Check-in transaction during AM
        $this->createTransaction($guest1, $checkinDetail1, [
            'transaction_type_id' => 1,
            'shift_log_id' => $amShiftLog->id,
            'payable_amount' => 500,
            'paid_amount' => 500,
            'created_at' => now()->setTime(9, 0),
        ]);

        // Extension for AM guest during PM shift
        $this->createTransaction($guest1, $checkinDetail1, [
            'transaction_type_id' => 6,
            'shift_log_id' => $pmShiftLog->id,
            'payable_amount' => 200,
            'paid_amount' => 200,
            'description' => 'Extension',
            'created_at' => now()->setTime(20, 0),
        ]);

        // Guest 2: Checked in during PM
        $guest2 = $this->createGuest(['name' => 'PM Guest']);
        $checkinDetail2 = $this->createCheckinDetail($guest2, [
            'check_in_at' => now()->setTime(21, 0),
            'check_out_at' => now()->addDay(),
        ]);

        // Check-in transaction during PM
        $this->createTransaction($guest2, $checkinDetail2, [
            'transaction_type_id' => 1,
            'shift_log_id' => $pmShiftLog->id,
            'payable_amount' => 600,
            'paid_amount' => 600,
            'created_at' => now()->setTime(21, 0),
        ]);

        // Filter for PM shift
        $component = Livewire::test(SalesReportV2::class)
            ->set('filterMode', 'shift')
            ->set('selectedShiftLogId', $pmShiftLog->id)
            ->call('generateReport');

        $salesRows = $component->get('salesRows');

        // Should have 3 rows: FWD ROOM for AM guest, extension for AM guest, check-in for PM guest
        $this->assertCount(3, $salesRows);

        // AM guest should have a FWD ROOM row
        $fwdRoomRow = collect($salesRows)->firstWhere('transaction_type', 'FWD ROOM');
        $this->assertNotNull($fwdRoomRow);
        $this->assertTrue($fwdRoomRow['is_forwarded_guest_row']);

        // AM guest's extension should be marked as forwarded
        $amGuestRow = collect($salesRows)->firstWhere('guest_name', 'AM GUEST');
        $this->assertTrue($amGuestRow['is_forwarded']);

        // PM guest should NOT be forwarded
        $pmGuestRow = collect($salesRows)->firstWhere('guest_name', 'PM GUEST');
        $this->assertFalse($pmGuestRow['is_forwarded']);

        // Forwarded count = 1 (AM guest)
        $this->assertEquals(1, $component->get('forwardedCount'));

        // Forwarded room = 500 (AM guest's ORIGINAL room charge from AM shift)
        $this->assertEquals(500, $component->get('forwardedRoom'));
    }

    /** @test */
    public function forwarded_guest_deposit_subtracts_cashouts()
    {
        $this->actingAs($this->user);

        // Shift 1 (AM)
        $amShiftLog = ShiftLog::create([
            'branch_id' => $this->user->branch_id,
            'frontdesk_id' => $this->user->id,
            'frontdesk_ids' => json_encode([$this->user->id]),
            'time_in' => now()->setTime(8, 0),
            'time_out' => now()->setTime(16, 0),
            'shift' => 'AM',
        ]);

        // Shift 2 (PM) - cashout happens here
        $pmShiftLog = ShiftLog::create([
            'branch_id' => $this->user->branch_id,
            'frontdesk_id' => $this->user->id,
            'frontdesk_ids' => json_encode([$this->user->id]),
            'time_in' => now()->setTime(20, 0),
            'time_out' => now()->addDay()->setTime(7, 59),
            'shift' => 'PM',
        ]);

        // Shift 3 (next day AM) - should see reduced forwarded guest deposit
        $nextAmShiftLog = ShiftLog::create([
            'branch_id' => $this->user->branch_id,
            'frontdesk_id' => $this->user->id,
            'frontdesk_ids' => json_encode([$this->user->id]),
            'time_in' => now()->addDay()->setTime(8, 0),
            'time_out' => now()->addDay()->setTime(16, 0),
            'shift' => 'AM',
        ]);

        // Guest checks in during AM with room charge + guest deposit
        $guest = $this->createGuest(['name' => 'Cashout Guest']);
        $checkinDetail = $this->createCheckinDetail($guest, [
            'check_in_at' => now()->setTime(9, 0),
            'check_out_at' => now()->addDays(2),
        ]);

        // Room charge (type 1): 200
        $this->createTransaction($guest, $checkinDetail, [
            'transaction_type_id' => 1,
            'shift_log_id' => $amShiftLog->id,
            'payable_amount' => 200,
            'paid_amount' => 200,
            'created_at' => now()->setTime(9, 0),
        ]);

        // Room key deposit (type 2): 200
        $this->createTransaction($guest, $checkinDetail, [
            'transaction_type_id' => 2,
            'shift_log_id' => $amShiftLog->id,
            'payable_amount' => 200,
            'paid_amount' => 200,
            'remarks' => 'Deposit From Check In (Room Key & TV Remote)',
            'description' => 'Deposit',
            'created_at' => now()->setTime(9, 1),
        ]);

        // Guest deposit (type 2): 184
        $this->createTransaction($guest, $checkinDetail, [
            'transaction_type_id' => 2,
            'shift_log_id' => $amShiftLog->id,
            'payable_amount' => 184,
            'paid_amount' => 184,
            'remarks' => 'Guest Deposit',
            'description' => 'Deposit',
            'created_at' => now()->setTime(9, 2),
        ]);

        // Cashout (type 5) during PM shift: 184
        $this->createTransaction($guest, $checkinDetail, [
            'transaction_type_id' => 5,
            'shift_log_id' => $pmShiftLog->id,
            'payable_amount' => 184,
            'paid_amount' => 184,
            'description' => 'Cashout',
            'created_at' => now()->setTime(20, 0),
        ]);

        // View the PM shift - cashout happens DURING PM (at 20:00),
        // but PM starts at 16:00, so the cashout is NOT before PM's time_in.
        // Forwarded guest deposit total should still be 184 (no cashouts before PM start).
        $pmComponent = Livewire::test(SalesReportV2::class)
            ->set('filterMode', 'shift')
            ->set('selectedShiftLogId', $pmShiftLog->id)
            ->call('generateReport');

        $this->assertEquals(184, $pmComponent->get('forwardedGuestDeposit'));

        // View the next day AM shift - guest deposit should be 0 (184 - 184)
        // because the cashout at 20:00 is before next AM's time_in
        $component = Livewire::test(SalesReportV2::class)
            ->set('filterMode', 'shift')
            ->set('selectedShiftLogId', $nextAmShiftLog->id)
            ->call('generateReport');

        $salesRows = $component->get('salesRows');

        // Should have FWD ROOM and FWD ROOM DEPOSIT rows but NO FWD GUEST DEPOSIT
        // (because 184 - 184 = 0, so it shouldn't show)
        $fwdGuestDeposit = collect($salesRows)->firstWhere('transaction_type', 'FWD GUEST DEPOSIT');
        $this->assertNull($fwdGuestDeposit, 'FWD GUEST DEPOSIT should not appear when fully cashed out');

        // Forwarded totals: room deposit unchanged, guest deposit = 0
        $this->assertEquals(200, $component->get('forwardedRoom'));
        $this->assertEquals(200, $component->get('forwardedRoomDeposit'));
        $this->assertEquals(0, $component->get('forwardedGuestDeposit'));
    }

    /** @test */
    public function forwarded_guest_deposit_shows_reduced_amount_after_partial_cashout()
    {
        $this->actingAs($this->user);

        $amShiftLog = ShiftLog::create([
            'branch_id' => $this->user->branch_id,
            'frontdesk_id' => $this->user->id,
            'frontdesk_ids' => json_encode([$this->user->id]),
            'time_in' => now()->setTime(8, 0),
            'time_out' => now()->setTime(16, 0),
            'shift' => 'AM',
        ]);

        $pmShiftLog = ShiftLog::create([
            'branch_id' => $this->user->branch_id,
            'frontdesk_id' => $this->user->id,
            'frontdesk_ids' => json_encode([$this->user->id]),
            'time_in' => now()->setTime(20, 0),
            'time_out' => now()->addDay()->setTime(7, 59),
            'shift' => 'PM',
        ]);

        $guest = $this->createGuest(['name' => 'Partial Cashout Guest']);
        $checkinDetail = $this->createCheckinDetail($guest, [
            'check_in_at' => now()->setTime(9, 0),
            'check_out_at' => now()->addDay(),
        ]);

        // Room charge
        $this->createTransaction($guest, $checkinDetail, [
            'transaction_type_id' => 1,
            'shift_log_id' => $amShiftLog->id,
            'payable_amount' => 500,
            'paid_amount' => 500,
            'created_at' => now()->setTime(9, 0),
        ]);

        // Guest deposit: 300
        $this->createTransaction($guest, $checkinDetail, [
            'transaction_type_id' => 2,
            'shift_log_id' => $amShiftLog->id,
            'payable_amount' => 300,
            'paid_amount' => 300,
            'remarks' => 'Guest Deposit',
            'description' => 'Deposit',
            'created_at' => now()->setTime(9, 5),
        ]);

        // Partial cashout: 100 (during AM)
        $this->createTransaction($guest, $checkinDetail, [
            'transaction_type_id' => 5,
            'shift_log_id' => $amShiftLog->id,
            'payable_amount' => 100,
            'paid_amount' => 100,
            'description' => 'Cashout',
            'created_at' => now()->setTime(14, 0),
        ]);

        // View PM shift - forwarded guest deposit should be 200 (300 - 100)
        $component = Livewire::test(SalesReportV2::class)
            ->set('filterMode', 'shift')
            ->set('selectedShiftLogId', $pmShiftLog->id)
            ->call('generateReport');

        $salesRows = $component->get('salesRows');

        // FWD GUEST DEPOSIT should show reduced amount
        $fwdGuestDeposit = collect($salesRows)->firstWhere('transaction_type', 'FWD GUEST DEPOSIT');
        $this->assertNotNull($fwdGuestDeposit);
        $this->assertEquals(200, $fwdGuestDeposit['amount']);

        // Forwarded totals
        $this->assertEquals(500, $component->get('forwardedRoom'));
        $this->assertEquals(200, $component->get('forwardedGuestDeposit'));
    }

    /** @test */
    public function forwarded_guest_shows_fwd_rows_even_with_transactions_in_current_shift()
    {
        $this->actingAs($this->user);

        // Shift 1 (AM): Guest checks in
        $amShiftLog = ShiftLog::create([
            'branch_id' => $this->user->branch_id,
            'frontdesk_id' => $this->user->id,
            'frontdesk_ids' => json_encode([$this->user->id]),
            'time_in' => now()->setTime(8, 0),
            'time_out' => now()->setTime(16, 0),
            'shift' => 'AM',
        ]);

        // Shift 2 (PM): Guest has extension transaction
        $pmShiftLog = ShiftLog::create([
            'branch_id' => $this->user->branch_id,
            'frontdesk_id' => $this->user->id,
            'frontdesk_ids' => json_encode([$this->user->id]),
            'time_in' => now()->setTime(20, 0),
            'time_out' => now()->addDay()->setTime(7, 59),
            'shift' => 'PM',
        ]);

        $guest = $this->createGuest(['name' => 'FWD With Txn Guest']);
        $checkinDetail = $this->createCheckinDetail($guest, [
            'check_in_at' => now()->setTime(9, 0),
            'check_out_at' => now()->addDay(),
        ]);

        // Room charge during AM (type 1): 200
        $this->createTransaction($guest, $checkinDetail, [
            'transaction_type_id' => 1,
            'shift_log_id' => $amShiftLog->id,
            'payable_amount' => 200,
            'paid_amount' => 200,
            'created_at' => now()->setTime(9, 0),
        ]);

        // Room key deposit during AM (type 2): 200
        $this->createTransaction($guest, $checkinDetail, [
            'transaction_type_id' => 2,
            'shift_log_id' => $amShiftLog->id,
            'payable_amount' => 200,
            'paid_amount' => 200,
            'remarks' => 'Deposit From Check In (Room Key & TV Remote)',
            'description' => 'Deposit',
            'created_at' => now()->setTime(9, 1),
        ]);

        // Guest deposit during AM (type 2): 184
        $this->createTransaction($guest, $checkinDetail, [
            'transaction_type_id' => 2,
            'shift_log_id' => $amShiftLog->id,
            'payable_amount' => 184,
            'paid_amount' => 184,
            'remarks' => 'Guest Deposit',
            'description' => 'Deposit',
            'created_at' => now()->setTime(9, 2),
        ]);

        // Extension during PM shift (type 6): 200
        $this->createTransaction($guest, $checkinDetail, [
            'transaction_type_id' => 6,
            'shift_log_id' => $pmShiftLog->id,
            'payable_amount' => 200,
            'paid_amount' => 200,
            'description' => 'Extension',
            'created_at' => now()->setTime(20, 0),
        ]);

        // View PM shift — should show FWD rows AND extension transaction
        $component = Livewire::test(SalesReportV2::class)
            ->set('filterMode', 'shift')
            ->set('selectedShiftLogId', $pmShiftLog->id)
            ->call('generateReport');

        $salesRows = $component->get('salesRows');

        // Should have 4 rows: FWD ROOM + FWD ROOM DEPOSIT + FWD GUEST DEPOSIT + extension
        $this->assertCount(4, $salesRows);

        // FWD ROOM row
        $fwdRoom = collect($salesRows)->firstWhere('transaction_type', 'FWD ROOM');
        $this->assertNotNull($fwdRoom);
        $this->assertTrue($fwdRoom['is_forwarded_guest_row']);
        $this->assertEquals(200, $fwdRoom['amount']);
        $this->assertEquals(0, $fwdRoom['total']);

        // FWD ROOM DEPOSIT row
        $fwdRoomDeposit = collect($salesRows)->firstWhere('transaction_type', 'FWD ROOM DEPOSIT');
        $this->assertNotNull($fwdRoomDeposit);
        $this->assertTrue($fwdRoomDeposit['is_forwarded_guest_row']);
        $this->assertEquals(200, $fwdRoomDeposit['amount']);
        $this->assertEquals(0, $fwdRoomDeposit['total']);

        // FWD GUEST DEPOSIT row (full amount, no cashouts before PM)
        $fwdGuestDeposit = collect($salesRows)->firstWhere('transaction_type', 'FWD GUEST DEPOSIT');
        $this->assertNotNull($fwdGuestDeposit);
        $this->assertTrue($fwdGuestDeposit['is_forwarded_guest_row']);
        $this->assertEquals(184, $fwdGuestDeposit['amount']);
        $this->assertEquals(0, $fwdGuestDeposit['total']);

        // Extension transaction row
        $extensionRow = collect($salesRows)->first(fn($r) => !($r['is_forwarded_guest_row'] ?? false));
        $this->assertNotNull($extensionRow);
        $this->assertTrue($extensionRow['is_forwarded']);
        $this->assertEquals(200, $extensionRow['amount']);

        // Forwarded totals
        $this->assertEquals(200, $component->get('forwardedRoom'));
        $this->assertEquals(200, $component->get('forwardedRoomDeposit'));
        $this->assertEquals(184, $component->get('forwardedGuestDeposit'));
    }

    /** @test */
    public function forwarded_guest_with_cashout_in_current_shift_shows_full_deposit()
    {
        $this->actingAs($this->user);

        // Shift 1 (AM): Guest checks in
        $amShiftLog = ShiftLog::create([
            'branch_id' => $this->user->branch_id,
            'frontdesk_id' => $this->user->id,
            'frontdesk_ids' => json_encode([$this->user->id]),
            'time_in' => now()->setTime(8, 0),
            'time_out' => now()->setTime(16, 0),
            'shift' => 'AM',
        ]);

        // Shift 2 (PM): Cashout happens here
        $pmShiftLog = ShiftLog::create([
            'branch_id' => $this->user->branch_id,
            'frontdesk_id' => $this->user->id,
            'frontdesk_ids' => json_encode([$this->user->id]),
            'time_in' => now()->setTime(20, 0),
            'time_out' => now()->addDay()->setTime(7, 59),
            'shift' => 'PM',
        ]);

        $guest = $this->createGuest(['name' => 'Cashout In Shift Guest']);
        $checkinDetail = $this->createCheckinDetail($guest, [
            'check_in_at' => now()->setTime(9, 0),
            'check_out_at' => now()->addDay(),
        ]);

        // Room charge during AM: 200
        $this->createTransaction($guest, $checkinDetail, [
            'transaction_type_id' => 1,
            'shift_log_id' => $amShiftLog->id,
            'payable_amount' => 200,
            'paid_amount' => 200,
            'created_at' => now()->setTime(9, 0),
        ]);

        // Room key deposit during AM: 200
        $this->createTransaction($guest, $checkinDetail, [
            'transaction_type_id' => 2,
            'shift_log_id' => $amShiftLog->id,
            'payable_amount' => 200,
            'paid_amount' => 200,
            'remarks' => 'Deposit From Check In (Room Key & TV Remote)',
            'description' => 'Deposit',
            'created_at' => now()->setTime(9, 1),
        ]);

        // Guest deposit during AM: 184
        $this->createTransaction($guest, $checkinDetail, [
            'transaction_type_id' => 2,
            'shift_log_id' => $amShiftLog->id,
            'payable_amount' => 184,
            'paid_amount' => 184,
            'remarks' => 'Guest Deposit',
            'description' => 'Deposit',
            'created_at' => now()->setTime(9, 2),
        ]);

        // Cashout during PM shift: 184
        $this->createTransaction($guest, $checkinDetail, [
            'transaction_type_id' => 5,
            'shift_log_id' => $pmShiftLog->id,
            'payable_amount' => 184,
            'paid_amount' => 184,
            'description' => 'Cashout',
            'created_at' => now()->setTime(20, 0),
        ]);

        // View PM shift — FWD rows should still show alongside cashout transaction
        $component = Livewire::test(SalesReportV2::class)
            ->set('filterMode', 'shift')
            ->set('selectedShiftLogId', $pmShiftLog->id)
            ->call('generateReport');

        $salesRows = $component->get('salesRows');

        // FWD ROOM should still appear
        $fwdRoom = collect($salesRows)->firstWhere('transaction_type', 'FWD ROOM');
        $this->assertNotNull($fwdRoom, 'FWD ROOM should appear even when guest has transactions in this shift');
        $this->assertEquals(200, $fwdRoom['amount']);

        // FWD ROOM DEPOSIT should still appear
        $fwdRoomDeposit = collect($salesRows)->firstWhere('transaction_type', 'FWD ROOM DEPOSIT');
        $this->assertNotNull($fwdRoomDeposit, 'FWD ROOM DEPOSIT should appear even when guest has transactions in this shift');
        $this->assertEquals(200, $fwdRoomDeposit['amount']);

        // FWD GUEST DEPOSIT should show FULL amount (cashout is during this shift, not before)
        $fwdGuestDeposit = collect($salesRows)->firstWhere('transaction_type', 'FWD GUEST DEPOSIT');
        $this->assertNotNull($fwdGuestDeposit, 'FWD GUEST DEPOSIT should show full amount when cashout is in current shift');
        $this->assertEquals(184, $fwdGuestDeposit['amount']);

        // Cashout should also appear as a regular transaction row
        $cashoutRow = collect($salesRows)->first(fn($r) => ($r['transaction_type_id'] ?? 0) == 5);
        $this->assertNotNull($cashoutRow, 'Cashout transaction should appear as a regular row');
        $this->assertEquals(184, $cashoutRow['amount']);

        // Forwarded totals: guest deposit = 184 (no cashouts before PM's time_in)
        $this->assertEquals(200, $component->get('forwardedRoom'));
        $this->assertEquals(200, $component->get('forwardedRoomDeposit'));
        $this->assertEquals(184, $component->get('forwardedGuestDeposit'));
    }

    /** @test */
    public function forwarded_guest_deposit_sums_all_deposits_not_just_first()
    {
        $this->actingAs($this->user);

        $amShiftLog = ShiftLog::create([
            'branch_id' => $this->user->branch_id,
            'frontdesk_id' => $this->user->id,
            'frontdesk_ids' => json_encode([$this->user->id]),
            'time_in' => now()->setTime(8, 0),
            'time_out' => now()->setTime(16, 0),
            'shift' => 'AM',
        ]);

        $pmShiftLog = ShiftLog::create([
            'branch_id' => $this->user->branch_id,
            'frontdesk_id' => $this->user->id,
            'frontdesk_ids' => json_encode([$this->user->id]),
            'time_in' => now()->setTime(20, 0),
            'time_out' => now()->addDay()->setTime(7, 59),
            'shift' => 'PM',
        ]);

        $guest = $this->createGuest(['name' => 'Multi Deposit Guest']);
        $checkinDetail = $this->createCheckinDetail($guest, [
            'check_in_at' => now()->setTime(9, 0),
            'check_out_at' => now()->addDay(),
        ]);

        // Room charge during AM
        $this->createTransaction($guest, $checkinDetail, [
            'transaction_type_id' => 1,
            'shift_log_id' => $amShiftLog->id,
            'payable_amount' => 500,
            'paid_amount' => 500,
            'created_at' => now()->setTime(9, 0),
        ]);

        // First guest deposit during AM: 200
        $this->createTransaction($guest, $checkinDetail, [
            'transaction_type_id' => 2,
            'shift_log_id' => $amShiftLog->id,
            'payable_amount' => 200,
            'paid_amount' => 200,
            'remarks' => 'Guest Deposit',
            'description' => 'Deposit',
            'created_at' => now()->setTime(9, 5),
        ]);

        // Second guest deposit during AM: 150
        $this->createTransaction($guest, $checkinDetail, [
            'transaction_type_id' => 2,
            'shift_log_id' => $amShiftLog->id,
            'payable_amount' => 150,
            'paid_amount' => 150,
            'remarks' => 'Guest Deposit',
            'description' => 'Deposit',
            'created_at' => now()->setTime(10, 0),
        ]);

        // View PM shift — FWD GUEST DEPOSIT should show 350 (200 + 150), not just 200
        $component = Livewire::test(SalesReportV2::class)
            ->set('filterMode', 'shift')
            ->set('selectedShiftLogId', $pmShiftLog->id)
            ->call('generateReport');

        $salesRows = $component->get('salesRows');

        // FWD GUEST DEPOSIT row should sum all deposits
        $fwdGuestDeposit = collect($salesRows)->firstWhere('transaction_type', 'FWD GUEST DEPOSIT');
        $this->assertNotNull($fwdGuestDeposit);
        $this->assertEquals(350, $fwdGuestDeposit['amount']);

        // Summary should match
        $this->assertEquals(350, $component->get('forwardedGuestDeposit'));
    }

    /** @test */
    public function forwarded_summary_matches_fwd_row_totals_across_shifts()
    {
        $this->actingAs($this->user);

        // Create a second room for guest B
        $room2 = Room::create([
            'branch_id' => $this->branch->id,
            'floor_id' => $this->floor->id,
            'type_id' => $this->roomType->id,
            'number' => 102,
            'status' => 'available',
        ]);

        // Shift 0 (previous day PM): Guest A checks in
        $prevPmShiftLog = ShiftLog::create([
            'branch_id' => $this->user->branch_id,
            'frontdesk_id' => $this->user->id,
            'frontdesk_ids' => json_encode([$this->user->id]),
            'time_in' => now()->subDay()->setTime(16, 0),
            'time_out' => now()->subDay()->setTime(23, 59),
            'shift' => 'PM',
        ]);

        // Shift 1 (AM): Guest B checks in, Guest B has cashout
        $amShiftLog = ShiftLog::create([
            'branch_id' => $this->user->branch_id,
            'frontdesk_id' => $this->user->id,
            'frontdesk_ids' => json_encode([$this->user->id]),
            'time_in' => now()->setTime(8, 0),
            'time_out' => now()->setTime(16, 0),
            'shift' => 'AM',
        ]);

        // Shift 2 (PM): Both guests forwarded
        $pmShiftLog = ShiftLog::create([
            'branch_id' => $this->user->branch_id,
            'frontdesk_id' => $this->user->id,
            'frontdesk_ids' => json_encode([$this->user->id]),
            'time_in' => now()->setTime(20, 0),
            'time_out' => now()->addDay()->setTime(7, 59),
            'shift' => 'PM',
        ]);

        // Guest A: checked in during previous day PM with deposit 500
        $guestA = $this->createGuest(['name' => 'Guest A']);
        $checkinDetailA = $this->createCheckinDetail($guestA, [
            'check_in_at' => now()->subDay()->setTime(17, 0),
            'check_out_at' => now()->addDay(),
        ]);

        $this->createTransaction($guestA, $checkinDetailA, [
            'transaction_type_id' => 1,
            'shift_log_id' => $prevPmShiftLog->id,
            'payable_amount' => 400,
            'paid_amount' => 400,
            'created_at' => now()->subDay()->setTime(17, 0),
        ]);

        $this->createTransaction($guestA, $checkinDetailA, [
            'transaction_type_id' => 2,
            'shift_log_id' => $prevPmShiftLog->id,
            'payable_amount' => 500,
            'paid_amount' => 500,
            'remarks' => 'Guest Deposit',
            'description' => 'Deposit',
            'created_at' => now()->subDay()->setTime(17, 5),
        ]);

        // Guest B: checked in during AM with deposit 300
        $guestB = $this->createGuest(['name' => 'Guest B', 'room_id' => $room2->id]);
        $checkinDetailB = $this->createCheckinDetail($guestB, [
            'room_id' => $room2->id,
            'check_in_at' => now()->setTime(9, 0),
            'check_out_at' => now()->addDay(),
        ]);

        $this->createTransaction($guestB, $checkinDetailB, [
            'transaction_type_id' => 1,
            'shift_log_id' => $amShiftLog->id,
            'payable_amount' => 300,
            'paid_amount' => 300,
            'room_id' => $room2->id,
            'created_at' => now()->setTime(9, 0),
        ]);

        $this->createTransaction($guestB, $checkinDetailB, [
            'transaction_type_id' => 2,
            'shift_log_id' => $amShiftLog->id,
            'payable_amount' => 300,
            'paid_amount' => 300,
            'remarks' => 'Guest Deposit',
            'description' => 'Deposit',
            'room_id' => $room2->id,
            'created_at' => now()->setTime(9, 5),
        ]);

        // Guest B cashout during AM: 100
        $this->createTransaction($guestB, $checkinDetailB, [
            'transaction_type_id' => 5,
            'shift_log_id' => $amShiftLog->id,
            'payable_amount' => 100,
            'paid_amount' => 100,
            'description' => 'Cashout',
            'room_id' => $room2->id,
            'created_at' => now()->setTime(14, 0),
        ]);

        // View PM shift (shift 2)
        $component = Livewire::test(SalesReportV2::class)
            ->set('filterMode', 'shift')
            ->set('selectedShiftLogId', $pmShiftLog->id)
            ->call('generateReport');

        $salesRows = $component->get('salesRows');
        $fwdRows = collect($salesRows)->filter(fn($r) => !empty($r['is_forwarded_guest_row']));

        // Guest A FWD GUEST DEPOSIT = 500 (no cashouts)
        $guestADeposit = $fwdRows
            ->where('transaction_type', 'FWD GUEST DEPOSIT')
            ->firstWhere('guest_name', 'GUEST A');
        $this->assertNotNull($guestADeposit);
        $this->assertEquals(500, $guestADeposit['amount']);

        // Guest B FWD GUEST DEPOSIT = 200 (300 - 100 cashout before PM)
        $guestBDeposit = $fwdRows
            ->where('transaction_type', 'FWD GUEST DEPOSIT')
            ->firstWhere('guest_name', 'GUEST B');
        $this->assertNotNull($guestBDeposit);
        $this->assertEquals(200, $guestBDeposit['amount']);

        // Summary should equal sum of FWD rows: 500 + 200 = 700
        $this->assertEquals(700, $component->get('forwardedGuestDeposit'));
    }

    /** @test */
    public function forwarded_guest_deposit_excludes_deposits_made_during_current_shift()
    {
        $this->actingAs($this->user);

        $amShiftLog = ShiftLog::create([
            'branch_id' => $this->user->branch_id,
            'frontdesk_id' => $this->user->id,
            'frontdesk_ids' => json_encode([$this->user->id]),
            'time_in' => now()->setTime(8, 0),
            'time_out' => now()->setTime(16, 0),
            'shift' => 'AM',
        ]);

        $pmShiftLog = ShiftLog::create([
            'branch_id' => $this->user->branch_id,
            'frontdesk_id' => $this->user->id,
            'frontdesk_ids' => json_encode([$this->user->id]),
            'time_in' => now()->setTime(20, 0),
            'time_out' => now()->addDay()->setTime(7, 59),
            'shift' => 'PM',
        ]);

        $guest = $this->createGuest(['name' => 'New Deposit Guest']);
        $checkinDetail = $this->createCheckinDetail($guest, [
            'check_in_at' => now()->setTime(9, 0),
            'check_out_at' => now()->addDay(),
        ]);

        // Room charge during AM
        $this->createTransaction($guest, $checkinDetail, [
            'transaction_type_id' => 1,
            'shift_log_id' => $amShiftLog->id,
            'payable_amount' => 500,
            'paid_amount' => 500,
            'created_at' => now()->setTime(9, 0),
        ]);

        // Guest deposit during AM: 300
        $this->createTransaction($guest, $checkinDetail, [
            'transaction_type_id' => 2,
            'shift_log_id' => $amShiftLog->id,
            'payable_amount' => 300,
            'paid_amount' => 300,
            'remarks' => 'Guest Deposit',
            'description' => 'Deposit',
            'created_at' => now()->setTime(9, 5),
        ]);

        // Additional guest deposit during PM shift: 200 (created at 21:00, during PM)
        $this->createTransaction($guest, $checkinDetail, [
            'transaction_type_id' => 2,
            'shift_log_id' => $pmShiftLog->id,
            'payable_amount' => 200,
            'paid_amount' => 200,
            'remarks' => 'Guest Deposit',
            'description' => 'Deposit',
            'created_at' => now()->setTime(21, 0),
        ]);

        // View PM shift — FWD GUEST DEPOSIT should show 300 (only AM deposit),
        // because the 200 deposit at 21:00 is during PM shift (after time_in 20:00)
        $component = Livewire::test(SalesReportV2::class)
            ->set('filterMode', 'shift')
            ->set('selectedShiftLogId', $pmShiftLog->id)
            ->call('generateReport');

        $salesRows = $component->get('salesRows');

        $fwdGuestDeposit = collect($salesRows)->firstWhere('transaction_type', 'FWD GUEST DEPOSIT');
        $this->assertNotNull($fwdGuestDeposit);
        $this->assertEquals(300, $fwdGuestDeposit['amount']);

        // Summary should match — cumulative chain: AM has 300 guest dep, 0 cashouts = remaining 300
        $this->assertEquals(300, $component->get('forwardedGuestDeposit'));
    }

    /** @test */
    public function includes_unclaimed_guest_deposit_from_checked_out_guest()
    {
        $this->actingAs($this->user);

        // AM shift: guest checks in and checks out, leaving unclaimed deposit
        $amShiftLog = ShiftLog::create([
            'branch_id' => $this->user->branch_id,
            'frontdesk_id' => $this->user->id,
            'frontdesk_ids' => json_encode([$this->user->id]),
            'time_in' => now()->setTime(8, 0),
            'time_out' => now()->setTime(16, 0),
            'shift' => 'AM',
        ]);

        // PM shift: should see unclaimed deposit forwarded
        $pmShiftLog = ShiftLog::create([
            'branch_id' => $this->user->branch_id,
            'frontdesk_id' => $this->user->id,
            'frontdesk_ids' => json_encode([$this->user->id]),
            'time_in' => now()->setTime(20, 0),
            'time_out' => now()->addDay()->setTime(7, 59),
            'shift' => 'PM',
        ]);

        // Guest checks in during AM and checks out during AM (before PM starts)
        $guest = $this->createGuest(['name' => 'Checked Out Guest']);
        $checkinDetail = $this->createCheckinDetail($guest, [
            'check_in_at' => now()->setTime(9, 0),
            'check_out_at' => now()->setTime(14, 0), // Checked out during AM
        ]);

        // Room charge during AM
        $this->createTransaction($guest, $checkinDetail, [
            'transaction_type_id' => 1,
            'shift_log_id' => $amShiftLog->id,
            'payable_amount' => 500,
            'paid_amount' => 500,
            'created_at' => now()->setTime(9, 0),
        ]);

        // Guest deposit during AM: 300 (unclaimed — no cashout)
        $this->createTransaction($guest, $checkinDetail, [
            'transaction_type_id' => 2,
            'shift_log_id' => $amShiftLog->id,
            'payable_amount' => 300,
            'paid_amount' => 300,
            'remarks' => 'Guest Deposit',
            'description' => 'Deposit',
            'created_at' => now()->setTime(9, 5),
        ]);

        // View PM shift — should see unclaimed FWD GUEST DEPOSIT of 300
        $component = Livewire::test(SalesReportV2::class)
            ->set('filterMode', 'shift')
            ->set('selectedShiftLogId', $pmShiftLog->id)
            ->call('generateReport');

        $salesRows = $component->get('salesRows');

        $fwdGuestDeposit = collect($salesRows)->firstWhere('transaction_type', 'FWD GUEST DEPOSIT');
        $this->assertNotNull($fwdGuestDeposit, 'Unclaimed guest deposit should appear as FWD GUEST DEPOSIT');
        $this->assertEquals(300, $fwdGuestDeposit['amount']);
        $this->assertStringContainsString('Unclaimed', $fwdGuestDeposit['remarks']);

        // Summary should include it
        $this->assertEquals(300, $component->get('forwardedGuestDeposit'));
    }

    /** @test */
    public function unclaimed_deposit_not_shown_when_fully_cashed_out()
    {
        $this->actingAs($this->user);

        $amShiftLog = ShiftLog::create([
            'branch_id' => $this->user->branch_id,
            'frontdesk_id' => $this->user->id,
            'frontdesk_ids' => json_encode([$this->user->id]),
            'time_in' => now()->setTime(8, 0),
            'time_out' => now()->setTime(16, 0),
            'shift' => 'AM',
        ]);

        $pmShiftLog = ShiftLog::create([
            'branch_id' => $this->user->branch_id,
            'frontdesk_id' => $this->user->id,
            'frontdesk_ids' => json_encode([$this->user->id]),
            'time_in' => now()->setTime(20, 0),
            'time_out' => now()->addDay()->setTime(7, 59),
            'shift' => 'PM',
        ]);

        $guest = $this->createGuest(['name' => 'Fully Cashed Out Guest']);
        $checkinDetail = $this->createCheckinDetail($guest, [
            'check_in_at' => now()->setTime(9, 0),
            'check_out_at' => now()->setTime(14, 0),
        ]);

        $this->createTransaction($guest, $checkinDetail, [
            'transaction_type_id' => 1,
            'shift_log_id' => $amShiftLog->id,
            'payable_amount' => 500,
            'paid_amount' => 500,
            'created_at' => now()->setTime(9, 0),
        ]);

        // Guest deposit: 300
        $this->createTransaction($guest, $checkinDetail, [
            'transaction_type_id' => 2,
            'shift_log_id' => $amShiftLog->id,
            'payable_amount' => 300,
            'paid_amount' => 300,
            'remarks' => 'Guest Deposit',
            'description' => 'Deposit',
            'created_at' => now()->setTime(9, 5),
        ]);

        // Full cashout: 300
        $this->createTransaction($guest, $checkinDetail, [
            'transaction_type_id' => 5,
            'shift_log_id' => $amShiftLog->id,
            'payable_amount' => 300,
            'paid_amount' => 300,
            'description' => 'Cashout',
            'created_at' => now()->setTime(13, 0),
        ]);

        // View PM shift — no unclaimed deposit (fully cashed out)
        $component = Livewire::test(SalesReportV2::class)
            ->set('filterMode', 'shift')
            ->set('selectedShiftLogId', $pmShiftLog->id)
            ->call('generateReport');

        $salesRows = $component->get('salesRows');
        $fwdGuestDeposit = collect($salesRows)->firstWhere('transaction_type', 'FWD GUEST DEPOSIT');
        $this->assertNull($fwdGuestDeposit, 'No FWD GUEST DEPOSIT when fully cashed out');
        $this->assertEquals(0, $component->get('forwardedGuestDeposit'));
    }

    /** @test */
    public function overlap_guests_room_charges_appear_in_sales_rows()
    {
        $this->actingAs($this->user);

        // Delete the default shift log from setUp to avoid interfering with overlap detection
        $this->shiftLog->delete();

        // Shift 1 (AM): 8:00 AM - 4:30 PM (ends AFTER shift 2 starts = overlap)
        $amShiftLog = ShiftLog::create([
            'branch_id' => $this->user->branch_id,
            'frontdesk_id' => $this->user->id,
            'frontdesk_ids' => json_encode([$this->user->id]),
            'time_in' => now()->setTime(8, 0),
            'time_out' => now()->setTime(16, 30), // Ends 30 min after shift 2 starts
            'shift' => 'AM',
        ]);

        // Shift 2 (PM): 4:00 PM - 11:59 PM (starts at 4 PM, overlaps with AM until 4:30 PM)
        $pmShiftLog = ShiftLog::create([
            'branch_id' => $this->user->branch_id,
            'frontdesk_id' => $this->user->id,
            'frontdesk_ids' => json_encode([$this->user->id]),
            'time_in' => now()->setTime(16, 0),
            'time_out' => now()->setTime(23, 59),
            'shift' => 'PM',
        ]);

        // --- Guest 1: Normal check-in during PM shift (not overlap) ---
        $guest1 = $this->createGuest(['name' => 'Normal PM Guest']);
        $checkin1 = $this->createCheckinDetail($guest1, [
            'check_in_at' => now()->setTime(17, 0),
            'check_out_at' => now()->addDay(),
        ]);
        $this->createTransaction($guest1, $checkin1, [
            'transaction_type_id' => 1,
            'shift_log_id' => $pmShiftLog->id,
            'payable_amount' => 500,
            'paid_amount' => 500,
            'created_at' => now()->setTime(17, 0),
        ]);

        // --- Guest 2: Overlap guest — checked in during AM, checked out during overlap ---
        $guest2 = $this->createGuest(['name' => 'Overlap Guest']);
        $room2 = Room::create([
            'branch_id' => $this->branch->id,
            'floor_id' => $this->floor->id,
            'type_id' => $this->roomType->id,
            'number' => 102,
            'status' => 'available',
        ]);
        $checkin2 = $this->createCheckinDetail($guest2, [
            'room_id' => $room2->id,
            'check_in_at' => now()->setTime(10, 0),  // AM shift
            'check_out_at' => now()->setTime(16, 15), // During overlap (between 16:00 and 16:30)
        ]);
        $this->createTransaction($guest2, $checkin2, [
            'room_id' => $room2->id,
            'transaction_type_id' => 1,
            'shift_log_id' => $amShiftLog->id,
            'payable_amount' => 400,
            'paid_amount' => 400,
            'created_at' => now()->setTime(10, 0), // Created during AM shift
        ]);

        // View PM shift
        $component = Livewire::test(SalesReportV2::class)
            ->set('filterMode', 'shift')
            ->set('selectedShiftLogId', $pmShiftLog->id)
            ->call('generateReport');

        // Check-in count: normal PM check-ins + overlap guests added by overlap detection
        $shiftCheckins = $component->get('shiftCheckins');
        $this->assertGreaterThanOrEqual(2, $shiftCheckins, 'Check-in count should include overlap guest');

        // Room charges should also include the overlap guest's type 1 transaction
        $salesRows = $component->get('salesRows');
        $roomChargeRows = collect($salesRows)->where('transaction_type_id', 1);

        $this->assertCount(2, $roomChargeRows, 'Room charges should include overlap guest');

        // Verify both guests are present
        $guestNames = $roomChargeRows->pluck('guest_name')->toArray();
        $this->assertContains('NORMAL PM GUEST', $guestNames);
        $this->assertContains('OVERLAP GUEST', $guestNames);

        // Verify the overlap guest's room charge amount
        $overlapRow = $roomChargeRows->firstWhere('guest_name', 'OVERLAP GUEST');
        $this->assertEquals(400, $overlapRow['amount']);
    }
}
