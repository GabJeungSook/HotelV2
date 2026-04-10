<?php

namespace Tests\Feature\Frontdesk;

use App\Http\Livewire\Frontdesk\Monitoring\TransferRoom;
use App\Models\Branch;
use App\Models\CheckinDetail;
use App\Models\ExtensionRate;
use App\Models\Floor;
use App\Models\Frontdesk;
use App\Models\Guest;
use App\Models\Rate;
use App\Models\Room;
use App\Models\ShiftLog;
use App\Models\StayingHour;
use App\Models\TransferReason;
use App\Models\Type;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TransferRoomTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;
    protected User $user;
    protected Floor $floor;
    protected Type $roomType;
    protected Type $roomType2;
    protected Room $room;
    protected Room $room2;
    protected StayingHour $stayingHour6;
    protected StayingHour $stayingHour12;
    protected Rate $rate6;
    protected Rate $rate12;
    protected Rate $rate2_12;
    protected ExtensionRate $extRate6;
    protected ExtensionRate $extRate12;
    protected Frontdesk $frontdesk;
    protected ShiftLog $shiftLog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name' => 'Test Branch',
            'extension_time_reset' => 24,
        ]);

        $this->user = User::factory()->create([
            'branch_id' => $this->branch->id,
            'branch_name' => $this->branch->name,
        ]);

        Role::firstOrCreate(['name' => 'frontdesk']);
        $this->user->assignRole('frontdesk');

        $this->floor = Floor::create([
            'branch_id' => $this->branch->id,
            'number' => 1,
        ]);

        $this->roomType = Type::create([
            'branch_id' => $this->branch->id,
            'name' => 'Single',
        ]);

        $this->room = Room::create([
            'branch_id' => $this->branch->id,
            'floor_id' => $this->floor->id,
            'type_id' => $this->roomType->id,
            'number' => 101,
            'status' => 'Occupied',
        ]);

        $this->stayingHour6 = StayingHour::create([
            'branch_id' => $this->branch->id,
            'number' => 6,
        ]);

        $this->stayingHour12 = StayingHour::create([
            'branch_id' => $this->branch->id,
            'number' => 12,
        ]);

        $this->rate6 = Rate::create([
            'branch_id' => $this->branch->id,
            'type_id' => $this->roomType->id,
            'room_id' => $this->room->id,
            'staying_hour_id' => $this->stayingHour6->id,
            'amount' => 250,
            'is_available' => true,
            'has_discount' => false,
        ]);

        $this->rate12 = Rate::create([
            'branch_id' => $this->branch->id,
            'type_id' => $this->roomType->id,
            'room_id' => $this->room->id,
            'staying_hour_id' => $this->stayingHour12->id,
            'amount' => 350,
            'is_available' => true,
            'has_discount' => false,
        ]);

        $this->roomType2 = Type::create([
            'branch_id' => $this->branch->id,
            'name' => 'Double',
        ]);

        $this->room2 = Room::create([
            'branch_id' => $this->branch->id,
            'floor_id' => $this->floor->id,
            'type_id' => $this->roomType2->id,
            'number' => 202,
            'status' => 'Available',
        ]);

        $this->rate2_12 = Rate::create([
            'branch_id' => $this->branch->id,
            'type_id' => $this->roomType2->id,
            'room_id' => $this->room2->id,
            'staying_hour_id' => $this->stayingHour12->id,
            'amount' => 500,
            'is_available' => true,
            'has_discount' => false,
        ]);

        $this->extRate6 = ExtensionRate::create([
            'branch_id' => $this->branch->id,
            'hour' => 6,
            'amount' => 100,
        ]);

        $this->extRate12 = ExtensionRate::create([
            'branch_id' => $this->branch->id,
            'hour' => 12,
            'amount' => 180,
        ]);

        $this->frontdesk = Frontdesk::create([
            'branch_id' => $this->branch->id,
            'user_id' => $this->user->id,
            'name' => 'FD1',
            'number' => 1,
        ]);

        $this->shiftLog = ShiftLog::create([
            'branch_id' => $this->branch->id,
            'frontdesk_id' => $this->user->id,
            'frontdesk_ids' => json_encode([$this->user->id]),
            'time_in' => now()->setTime(8, 0),
            'shift' => 'AM',
        ]);
    }

    protected function createGuestWithCheckin(array $checkinOverrides = []): Guest
    {
        $guest = Guest::create([
            'branch_id' => $this->branch->id,
            'room_id' => $this->room->id,
            'rate_id' => $this->rate12->id,
            'type_id' => $this->roomType->id,
            'static_amount' => 350,
            'name' => 'Test Guest',
            'qr_code' => 'QR' . uniqid(),
        ]);

        CheckinDetail::create(array_merge([
            'guest_id' => $guest->id,
            'type_id' => $this->roomType->id,
            'room_id' => $this->room->id,
            'rate_id' => $this->rate12->id,
            'static_amount' => 350,
            'hours_stayed' => 12,
            'check_in_at' => now()->subHours(12),
            'check_out_at' => now(),
            'is_long_stay' => false,
            'number_of_hours' => 12,
            'next_extension_is_original' => false,
        ], $checkinOverrides));

        return $guest;
    }

    /** @test */
    public function component_mounts_with_guest()
    {
        $guest = $this->createGuestWithCheckin();

        $component = Livewire::actingAs($this->user)
            ->test(TransferRoom::class, ['record' => $guest->id]);

        $component->assertSet('guest.id', $guest->id);
        $component->assertSet('room.id', $this->room->id);
        $component->assertSet('current_room_rate', 350);
    }

    /** @test */
    public function transfer_rate_lookup_uses_room_id()
    {
        $guest = $this->createGuestWithCheckin();

        $component = Livewire::actingAs($this->user)
            ->test(TransferRoom::class, ['record' => $guest->id]);

        // Select type first (resets room_id), then select room (triggers rate lookup)
        $component->set('selected_type_id', $this->roomType2->id)
            ->set('selected_room_id', $this->room2->id);

        // new_room should be loaded with room2's 12hr rate (500), not room1's (350)
        $component->assertSet('new_room.amount', 500);
        $component->assertSet('new_room_rate', 500);
    }
}
