<?php

namespace Tests\Feature\Kiosk;

use App\Http\Livewire\Kiosk\CheckIn;
use App\Models\Branch;
use App\Models\Floor;
use App\Models\Rate;
use App\Models\Room;
use App\Models\StayingHour;
use App\Models\Type;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CheckInTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Branch $branch;
    protected Floor $floor;
    protected Type $roomType;
    protected Room $room;
    protected StayingHour $stayingHour6;
    protected StayingHour $stayingHour24;
    protected Rate $rate6;
    protected Rate $rate24;

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

        Role::firstOrCreate(['name' => 'kiosk']);
        $this->user->assignRole('kiosk');

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
            'status' => 'Available',
            'is_priority' => true,
        ]);

        $this->stayingHour6 = StayingHour::create([
            'branch_id' => $this->branch->id,
            'number' => 6,
        ]);

        $this->stayingHour24 = StayingHour::create([
            'branch_id' => $this->branch->id,
            'number' => 24,
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

        $this->rate24 = Rate::create([
            'branch_id' => $this->branch->id,
            'type_id' => $this->roomType->id,
            'room_id' => $this->room->id,
            'staying_hour_id' => $this->stayingHour24->id,
            'amount' => 700,
            'is_available' => true,
            'has_discount' => false,
        ]);
    }

    /** @test */
    public function component_can_render()
    {
        Livewire::actingAs($this->user)
            ->test(CheckIn::class)
            ->assertStatus(200);
    }

    /** @test */
    public function rates_load_for_selected_room()
    {
        $component = Livewire::actingAs($this->user)
            ->test(CheckIn::class)
            ->set('type_id', $this->roomType->id)
            ->call('selectRoom', $this->room->id);

        $rates = $component->get('rates');

        $this->assertCount(2, $rates);
        $this->assertTrue($rates->contains('id', $this->rate6->id));
        $this->assertTrue($rates->contains('id', $this->rate24->id));
    }

    /** @test */
    public function rates_are_room_specific()
    {
        // Create a second room type and room with different rates
        $roomType2 = Type::create([
            'branch_id' => $this->branch->id,
            'name' => 'Deluxe',
        ]);

        $room2 = Room::create([
            'branch_id' => $this->branch->id,
            'floor_id' => $this->floor->id,
            'type_id' => $roomType2->id,
            'number' => 202,
            'status' => 'Available',
            'is_priority' => true,
        ]);

        $rate2_6 = Rate::create([
            'branch_id' => $this->branch->id,
            'type_id' => $roomType2->id,
            'room_id' => $room2->id,
            'staying_hour_id' => $this->stayingHour6->id,
            'amount' => 500,
            'is_available' => true,
            'has_discount' => false,
        ]);

        $rate2_24 = Rate::create([
            'branch_id' => $this->branch->id,
            'type_id' => $roomType2->id,
            'room_id' => $room2->id,
            'staying_hour_id' => $this->stayingHour24->id,
            'amount' => 1200,
            'is_available' => true,
            'has_discount' => false,
        ]);

        // Select room 1 and verify its rates
        $component = Livewire::actingAs($this->user)
            ->test(CheckIn::class)
            ->set('type_id', $this->roomType->id)
            ->call('selectRoom', $this->room->id);

        $rates1 = $component->get('rates');

        $this->assertCount(2, $rates1);
        $this->assertTrue($rates1->contains('id', $this->rate6->id));
        $this->assertTrue($rates1->contains('id', $this->rate24->id));
        $this->assertFalse($rates1->contains('id', $rate2_6->id));
        $this->assertFalse($rates1->contains('id', $rate2_24->id));

        // Select room 2 and verify its rates
        $component->set('type_id', $roomType2->id)
            ->call('selectRoom', $room2->id);

        $rates2 = $component->get('rates');

        $this->assertCount(2, $rates2);
        $this->assertTrue($rates2->contains('id', $rate2_6->id));
        $this->assertTrue($rates2->contains('id', $rate2_24->id));
        $this->assertFalse($rates2->contains('id', $this->rate6->id));
        $this->assertFalse($rates2->contains('id', $this->rate24->id));
    }
}
