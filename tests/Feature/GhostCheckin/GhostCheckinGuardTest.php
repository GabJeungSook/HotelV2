<?php

namespace Tests\Feature\GhostCheckin;

use App\Http\Livewire\Kiosk\CheckIn;
use App\Http\Livewire\Roomboy\Index as RoomboyIndex;
use App\Http\Livewire\Roomboy\Main as RoomboyMain;
use App\Models\Branch;
use App\Models\CheckinDetail;
use App\Models\Floor;
use App\Models\Guest;
use App\Models\Rate;
use App\Models\Room;
use App\Models\StayingHour;
use App\Models\Type;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class GhostCheckinGuardTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function kiosk_confirm_checkin_blocks_when_ghost_checkin_exists()
    {
        $this->markTestSkipped('Ghost guard temporarily disabled — see plan 2026-04-24. Re-enable when guards are uncommented.');

        [$branch, $floor, $type, $rate, $room, $user] = $this->seedScaffolding();

        // Simulate the bug pre-condition: a previous guest's record is still
        // open (is_check_out = 0) but the room status got flipped to Available.
        $ghostGuest = Guest::create([
            'branch_id' => $branch->id,
            'name' => 'Ghost Guest Vee',
            'qr_code' => 'TEST-GHOST-001',
            'room_id' => $room->id,
            'rate_id' => $rate->id,
            'type_id' => $type->id,
            'static_amount' => 300,
        ]);

        CheckinDetail::create([
            'guest_id' => $ghostGuest->id,
            'type_id' => $type->id,
            'room_id' => $room->id,
            'rate_id' => $rate->id,
            'static_amount' => 300,
            'hours_stayed' => 12,
            'check_in_at' => Carbon::now()->subHours(20),
            'check_out_at' => Carbon::now()->subHours(8),
            'is_long_stay' => false,
            'is_check_out' => false,
        ]);

        $room->update(['status' => 'Available']);

        Livewire::actingAs($user)
            ->test(CheckIn::class)
            ->set('room_id', $room->id)
            ->set('type_id', $type->id)
            ->set('rate_id', $rate->id)
            ->set('name', 'New Kiosk Guest')
            ->set('room_pay', 300)
            ->call('confirmCheckIn');

        // Guard should block: no new Guest or hold created for the new customer
        $this->assertDatabaseMissing('guests', ['name' => 'New Kiosk Guest']);
        $this->assertDatabaseMissing('temporary_check_in_kiosks', ['room_id' => $room->id]);
    }

    /** @test */
    public function roomboy_index_finish_cleaning_blocks_when_ghost_checkin_exists()
    {
        $this->markTestSkipped('Ghost guard temporarily disabled — see plan 2026-04-24. Re-enable when guards are uncommented.');

        [$branch, $floor, $type, $rate, $room, $user] = $this->seedScaffolding();

        $ghostGuest = Guest::create([
            'branch_id' => $branch->id,
            'name' => 'Ghost Roomboy Test',
            'qr_code' => 'TEST-GHOST-002',
            'room_id' => $room->id,
            'rate_id' => $rate->id,
            'type_id' => $type->id,
            'static_amount' => 300,
        ]);

        CheckinDetail::create([
            'guest_id' => $ghostGuest->id,
            'type_id' => $type->id,
            'room_id' => $room->id,
            'rate_id' => $rate->id,
            'static_amount' => 300,
            'hours_stayed' => 12,
            'check_in_at' => Carbon::now()->subHours(20),
            'check_out_at' => Carbon::now()->subHours(8),
            'is_long_stay' => false,
            'is_check_out' => false,
        ]);

        $room->update([
            'status' => 'Uncleaned',
            'started_cleaning_at' => Carbon::now()->subMinutes(10),
            'time_to_clean' => Carbon::now()->addMinutes(5),
        ]);

        Livewire::actingAs($user)
            ->test(RoomboyIndex::class)
            ->call('finishCleaning', $room->id);

        // Guard should block: room status must NOT flip to Available
        $room->refresh();
        $this->assertEquals('Uncleaned', $room->status);
    }

    /** @test */
    public function roomboy_main_finish_cleaning_blocks_when_ghost_checkin_exists()
    {
        $this->markTestSkipped('Ghost guard temporarily disabled — see plan 2026-04-24. Re-enable when guards are uncommented.');

        [$branch, $floor, $type, $rate, $room, $user] = $this->seedScaffolding();

        $ghostGuest = Guest::create([
            'branch_id' => $branch->id,
            'name' => 'Ghost Main Test',
            'qr_code' => 'TEST-GHOST-003',
            'room_id' => $room->id,
            'rate_id' => $rate->id,
            'type_id' => $type->id,
            'static_amount' => 300,
        ]);

        CheckinDetail::create([
            'guest_id' => $ghostGuest->id,
            'type_id' => $type->id,
            'room_id' => $room->id,
            'rate_id' => $rate->id,
            'static_amount' => 300,
            'hours_stayed' => 12,
            'check_in_at' => Carbon::now()->subHours(20),
            'check_out_at' => Carbon::now()->subHours(8),
            'is_long_stay' => false,
            'is_check_out' => false,
        ]);

        $room->update([
            'status' => 'Uncleaned',
            'started_cleaning_at' => Carbon::now()->subMinutes(10),
            'time_to_clean' => Carbon::now()->addMinutes(5),
        ]);

        Livewire::actingAs($user)
            ->test(RoomboyMain::class)
            ->call('finishCleaning', $room->id);

        $room->refresh();
        $this->assertEquals('Uncleaned', $room->status);
    }

    /** @test */
    public function roomboy_finish_cleaning_works_when_no_ghost_exists()
    {
        // Happy path: the previous guest was properly checked out. Roomboy
        // should be able to finish cleaning normally.
        [$branch, $floor, $type, $rate, $room, $user] = $this->seedScaffolding();

        $checkedOutGuest = Guest::create([
            'branch_id' => $branch->id,
            'name' => 'Properly Checked Out Guest',
            'qr_code' => 'TEST-HAPPY-001',
            'room_id' => $room->id,
            'rate_id' => $rate->id,
            'type_id' => $type->id,
            'static_amount' => 300,
        ]);

        CheckinDetail::create([
            'guest_id' => $checkedOutGuest->id,
            'type_id' => $type->id,
            'room_id' => $room->id,
            'rate_id' => $rate->id,
            'static_amount' => 300,
            'hours_stayed' => 12,
            'check_in_at' => Carbon::now()->subHours(15),
            'check_out_at' => Carbon::now()->subHours(3),
            'is_long_stay' => false,
            'is_check_out' => true,
        ]);

        $room->update([
            'status' => 'Uncleaned',
            'started_cleaning_at' => Carbon::now()->subMinutes(10),
            'time_to_clean' => Carbon::now()->addMinutes(5),
        ]);

        Livewire::actingAs($user)
            ->test(RoomboyIndex::class)
            ->call('finishCleaning', $room->id);

        $room->refresh();
        $this->assertEquals('Available', $room->status);
    }

    /**
     * Create a minimal Branch/Floor/Type/Rate/Room/User scaffolding.
     *
     * @return array{0: Branch, 1: Floor, 2: Type, 3: Rate, 4: Room, 5: User}
     */
    private function seedScaffolding(): array
    {
        $branch = Branch::create([
            'name' => 'Ghost Test Branch ' . uniqid(),
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

        $stayingHour = StayingHour::create([
            'branch_id' => $branch->id,
            'number' => 12,
        ]);

        $rate = Rate::create([
            'branch_id' => $branch->id,
            'type_id' => $type->id,
            'staying_hour_id' => $stayingHour->id,
            'amount' => 300,
        ]);

        $room = Room::create([
            'branch_id' => $branch->id,
            'floor_id' => $floor->id,
            'type_id' => $type->id,
            'number' => rand(9000, 9999),
            'status' => 'Available',
            'is_priority' => true,
        ]);

        $user = User::create([
            'name' => 'Test User ' . uniqid(),
            'email' => 'ghost-test-' . uniqid() . '@example.com',
            'password' => bcrypt('secret'),
            'branch_id' => $branch->id,
            'branch_name' => $branch->name,
            'roomboy_assigned_floor_id' => $floor->id,
        ]);

        return [$branch, $floor, $type, $rate, $room, $user];
    }
}
