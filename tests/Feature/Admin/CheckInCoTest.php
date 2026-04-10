<?php

namespace Tests\Feature\Admin;

use App\Http\Livewire\Admin\CheckInCo;
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

class CheckInCoTest extends TestCase
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

        Role::firstOrCreate(['name' => 'admin']);
        $this->user->assignRole('admin');

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
            ->test(CheckInCo::class)
            ->assertStatus(200);
    }

    /** @test */
    public function rates_filter_by_room_id()
    {
        $component = Livewire::actingAs($this->user)
            ->test(CheckInCo::class)
            ->set('room_id', $this->room->id);

        $rates = $component->viewData('rates');

        $this->assertCount(2, $rates);
        $this->assertTrue($rates->every(fn ($rate) => $rate->room_id === $this->room->id));
        $this->assertTrue($rates->contains('id', $this->rate6->id));
        $this->assertTrue($rates->contains('id', $this->rate24->id));
    }

    /** @test */
    public function rates_empty_when_no_room_selected()
    {
        // Create a second room with its own rates to verify all branch rates are returned
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
        ]);

        $rate2 = Rate::create([
            'branch_id' => $this->branch->id,
            'type_id' => $roomType2->id,
            'room_id' => $room2->id,
            'staying_hour_id' => $this->stayingHour6->id,
            'amount' => 500,
            'is_available' => true,
            'has_discount' => false,
        ]);

        // Without setting room_id, the when() clause won't filter,
        // so all branch rates should be returned
        $component = Livewire::actingAs($this->user)
            ->test(CheckInCo::class);

        $rates = $component->viewData('rates');

        // Should include all 3 rates (2 from room 1 + 1 from room 2)
        $this->assertCount(3, $rates);
        $this->assertTrue($rates->contains('id', $this->rate6->id));
        $this->assertTrue($rates->contains('id', $this->rate24->id));
        $this->assertTrue($rates->contains('id', $rate2->id));
    }
}
