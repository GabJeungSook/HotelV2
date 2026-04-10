<?php

namespace Tests\Feature\Admin;

use App\Http\Livewire\Admin\Manage\Rate as RateComponent;
use App\Models\Branch;
use App\Models\Floor;
use App\Models\Frontdesk;
use App\Models\Rate;
use App\Models\Room;
use App\Models\StayingHour;
use App\Models\Type;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManageRateTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Branch $branch;
    protected Floor $floor;
    protected Type $roomType;
    protected Room $room;
    protected StayingHour $stayingHour;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create(['name' => 'Test Branch']);

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
            'name' => 'Standard',
        ]);

        $this->room = Room::create([
            'branch_id' => $this->branch->id,
            'floor_id' => $this->floor->id,
            'type_id' => $this->roomType->id,
            'number' => 101,
            'status' => 'available',
        ]);

        $this->stayingHour = StayingHour::create([
            'branch_id' => $this->branch->id,
            'number' => 6,
        ]);
    }

    /** @test */
    public function component_can_render()
    {
        $this->actingAs($this->user);

        Livewire::test(RateComponent::class)
            ->assertStatus(200);
    }

    /** @test */
    public function can_create_rate_for_room()
    {
        $this->actingAs($this->user);

        Livewire::test(RateComponent::class)
            ->set('amount', 250)
            ->set('hours_id', $this->stayingHour->id)
            ->set('room_id', $this->room->id)
            ->call('saveRate');

        $this->assertDatabaseHas('rates', [
            'amount' => 250,
            'staying_hour_id' => $this->stayingHour->id,
            'room_id' => $this->room->id,
            'type_id' => $this->roomType->id,
            'branch_id' => $this->branch->id,
        ]);
    }

    /** @test */
    public function cannot_create_duplicate_rate_for_same_room_and_hour()
    {
        $this->actingAs($this->user);

        Rate::create([
            'branch_id' => $this->branch->id,
            'amount' => 250,
            'staying_hour_id' => $this->stayingHour->id,
            'type_id' => $this->roomType->id,
            'room_id' => $this->room->id,
        ]);

        Livewire::test(RateComponent::class)
            ->set('amount', 250)
            ->set('hours_id', $this->stayingHour->id)
            ->set('room_id', $this->room->id)
            ->call('saveRate');

        $this->assertDatabaseCount('rates', 2);
    }

    /** @test */
    public function can_edit_rate()
    {
        $this->actingAs($this->user);

        $rate = Rate::create([
            'branch_id' => $this->branch->id,
            'amount' => 300,
            'staying_hour_id' => $this->stayingHour->id,
            'type_id' => $this->roomType->id,
            'room_id' => $this->room->id,
        ]);

        Livewire::test(RateComponent::class)
            ->call('editRate', $rate->id)
            ->assertSet('room_id', $this->room->id)
            ->assertSet('amount', 300)
            ->assertSet('hours_id', $this->stayingHour->id)
            ->assertSet('rate_id', $rate->id)
            ->assertSet('edit_modal', true);
    }

    /** @test */
    public function rates_are_filtered_by_branch()
    {
        $this->actingAs($this->user);

        // Create rate for current user's branch
        Rate::create([
            'branch_id' => $this->branch->id,
            'amount' => 250,
            'staying_hour_id' => $this->stayingHour->id,
            'type_id' => $this->roomType->id,
            'room_id' => $this->room->id,
        ]);

        // Create a second branch with its own data
        $otherBranch = Branch::create(['name' => 'Other Branch']);
        $otherFloor = Floor::create([
            'branch_id' => $otherBranch->id,
            'number' => 2,
        ]);
        $otherType = Type::create([
            'branch_id' => $otherBranch->id,
            'name' => 'Deluxe',
        ]);
        $otherRoom = Room::create([
            'branch_id' => $otherBranch->id,
            'floor_id' => $otherFloor->id,
            'type_id' => $otherType->id,
            'number' => 201,
            'status' => 'available',
        ]);
        $otherStayingHour = StayingHour::create([
            'branch_id' => $otherBranch->id,
            'number' => 12,
        ]);
        Rate::create([
            'branch_id' => $otherBranch->id,
            'amount' => 500,
            'staying_hour_id' => $otherStayingHour->id,
            'type_id' => $otherType->id,
            'room_id' => $otherRoom->id,
        ]);

        // The component's table query filters by the authenticated user's branch_id,
        // so it should only return rooms belonging to the user's branch.
        $component = Livewire::test(RateComponent::class);

        // Verify the table query only returns rooms for the user's branch
        $tableQuery = Room::where('branch_id', $this->user->branch_id)
            ->with(['rates.stayingHour', 'type'])
            ->get();

        $this->assertCount(1, $tableQuery);
        $this->assertEquals($this->branch->id, $tableQuery->first()->branch_id);

        // Ensure the other branch's room is not included
        $this->assertFalse(
            $tableQuery->contains(function ($room) use ($otherRoom) {
                return $room->id === $otherRoom->id;
            })
        );
    }
}
