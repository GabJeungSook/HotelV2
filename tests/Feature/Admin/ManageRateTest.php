<?php

namespace Tests\Feature\Admin;

use App\Http\Livewire\Admin\Manage\Rate as RateComponent;
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

class ManageRateTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Branch $branch;
    protected Floor $floor;
    protected Type $roomType;
    protected Room $room;
    protected Room $room2;
    protected StayingHour $stayingHour6;
    protected StayingHour $stayingHour12;

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

        $this->room2 = Room::create([
            'branch_id' => $this->branch->id,
            'floor_id' => $this->floor->id,
            'type_id' => $this->roomType->id,
            'number' => 102,
            'status' => 'available',
        ]);

        $this->stayingHour6 = StayingHour::create([
            'branch_id' => $this->branch->id,
            'number' => 6,
        ]);

        $this->stayingHour12 = StayingHour::create([
            'branch_id' => $this->branch->id,
            'number' => 12,
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
    public function bulk_set_rates_creates_rates_for_selected_rooms()
    {
        $this->actingAs($this->user);

        $component = Livewire::test(RateComponent::class)
            ->set('selected_rooms', [(string) $this->room->id, (string) $this->room2->id])
            ->call('openRateModal');

        // Set 6hr rate to 250
        $rateAmounts = $component->get('rate_amounts');
        $rateAmounts[$this->stayingHour6->id]['amount'] = 250;
        $rateAmounts[$this->stayingHour12->id]['amount'] = 400;

        $component->set('rate_amounts', $rateAmounts)
            ->call('applyRates');

        // Both rooms should have rates for both staying hours
        $this->assertDatabaseHas('rates', [
            'room_id' => $this->room->id,
            'staying_hour_id' => $this->stayingHour6->id,
            'amount' => 250,
        ]);
        $this->assertDatabaseHas('rates', [
            'room_id' => $this->room2->id,
            'staying_hour_id' => $this->stayingHour6->id,
            'amount' => 250,
        ]);
        $this->assertDatabaseHas('rates', [
            'room_id' => $this->room->id,
            'staying_hour_id' => $this->stayingHour12->id,
            'amount' => 400,
        ]);
        $this->assertDatabaseHas('rates', [
            'room_id' => $this->room2->id,
            'staying_hour_id' => $this->stayingHour12->id,
            'amount' => 400,
        ]);
    }

    /** @test */
    public function bulk_update_uses_update_or_create()
    {
        $this->actingAs($this->user);

        // Pre-create a rate for room 1
        Rate::create([
            'branch_id' => $this->branch->id,
            'room_id' => $this->room->id,
            'staying_hour_id' => $this->stayingHour6->id,
            'type_id' => $this->roomType->id,
            'amount' => 200,
            'is_available' => true,
        ]);

        // Bulk set: should UPDATE room 1's rate, CREATE room 2's rate
        $component = Livewire::test(RateComponent::class)
            ->set('selected_rooms', [(string) $this->room->id, (string) $this->room2->id])
            ->call('openRateModal');

        $rateAmounts = $component->get('rate_amounts');
        $rateAmounts[$this->stayingHour6->id]['amount'] = 300;

        $component->set('rate_amounts', $rateAmounts)
            ->call('applyRates');

        // Room 1: updated from 200 to 300 (not duplicated)
        $this->assertEquals(1, Rate::where('room_id', $this->room->id)
            ->where('staying_hour_id', $this->stayingHour6->id)->count());
        $this->assertDatabaseHas('rates', [
            'room_id' => $this->room->id,
            'staying_hour_id' => $this->stayingHour6->id,
            'amount' => 300,
        ]);

        // Room 2: created new
        $this->assertDatabaseHas('rates', [
            'room_id' => $this->room2->id,
            'staying_hour_id' => $this->stayingHour6->id,
            'amount' => 300,
        ]);
    }

    /** @test */
    public function cannot_set_rates_without_selecting_rooms()
    {
        $this->actingAs($this->user);

        Livewire::test(RateComponent::class)
            ->set('selected_rooms', [])
            ->call('openRateModal')
            ->assertSet('rate_modal', false);
    }

    /** @test */
    public function rates_are_filtered_by_branch()
    {
        $this->actingAs($this->user);

        Rate::create([
            'branch_id' => $this->branch->id,
            'room_id' => $this->room->id,
            'staying_hour_id' => $this->stayingHour6->id,
            'type_id' => $this->roomType->id,
            'amount' => 250,
            'is_available' => true,
        ]);

        // Create other branch data
        $otherBranch = Branch::create(['name' => 'Other Branch']);
        $otherFloor = Floor::create(['branch_id' => $otherBranch->id, 'number' => 2]);
        $otherType = Type::create(['branch_id' => $otherBranch->id, 'name' => 'Deluxe']);
        $otherRoom = Room::create([
            'branch_id' => $otherBranch->id,
            'floor_id' => $otherFloor->id,
            'type_id' => $otherType->id,
            'number' => 201,
            'status' => 'available',
        ]);

        // Component should only show current branch rooms
        $component = Livewire::test(RateComponent::class);
        $rooms = $component->viewData('rooms');

        $this->assertTrue($rooms->every(fn($r) => $r->branch_id === $this->branch->id));
        $this->assertFalse($rooms->contains(fn($r) => $r->id === $otherRoom->id));
    }

    /** @test */
    public function select_all_toggles_all_rooms()
    {
        $this->actingAs($this->user);

        $component = Livewire::test(RateComponent::class)
            ->set('select_all', true);

        $selected = $component->get('selected_rooms');
        $this->assertCount(2, $selected); // room and room2
        $this->assertContains((string) $this->room->id, $selected);
        $this->assertContains((string) $this->room2->id, $selected);

        // Deselect all
        $component->set('select_all', false);
        $this->assertEmpty($component->get('selected_rooms'));
    }
}
