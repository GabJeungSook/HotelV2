<?php

namespace Tests\Feature\Pos;

use App\Models\FrontdeskInventory;
use App\Models\FrontdeskMenu;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StockInTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $user = User::create([
            'name'        => 'Test Frontdesk',
            'email'       => 'fd_'.uniqid().'@example.com',
            'password'    => bcrypt('secret'),
            'branch_id'   => 1,
            'branch_name' => 'Test Branch',
        ]);
        $this->actingAs($user);
        return $user;
    }

    public function test_stock_in_records_an_IN_movement_and_increments_balance(): void
    {
        $this->actingUser();

        $menu = FrontdeskMenu::create([
            'branch_id' => 1, 'frontdesk_category_id' => 1,
            'name' => 'Coke', 'price' => '60',
        ]);

        FrontdeskInventory::create([
            'branch_id' => 1, 'frontdesk_menu_id' => $menu->id, 'number_of_serving' => 4,
        ]);

        Livewire::test(\App\Http\Livewire\Frontdesk\StockIn::class)
            ->set('source_type', StockMovement::SOURCE_FRONTDESK)
            ->set('menu_id', $menu->id)
            ->set('quantity', 12)
            ->set('reason', 'delivery — supplier ABC')
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertEquals(16, FrontdeskInventory::first()->number_of_serving);

        $movement = StockMovement::where('type', StockMovement::TYPE_IN)->first();
        $this->assertNotNull($movement);
        $this->assertEquals(12, $movement->quantity);
        $this->assertEquals(16, $movement->balance_after);
        $this->assertSame('delivery — supplier ABC', $movement->reason);
        $this->assertSame('stock_in_form', $movement->ref_type);
    }

    public function test_stock_in_creates_inventory_row_when_missing(): void
    {
        $this->actingUser();

        $menu = FrontdeskMenu::create([
            'branch_id' => 1, 'frontdesk_category_id' => 1,
            'name' => 'New Item', 'price' => '20',
        ]);

        $this->assertSame(0, FrontdeskInventory::count());

        Livewire::test(\App\Http\Livewire\Frontdesk\StockIn::class)
            ->set('source_type', StockMovement::SOURCE_FRONTDESK)
            ->set('menu_id', $menu->id)
            ->set('quantity', 5)
            ->call('submit');

        $this->assertSame(1, FrontdeskInventory::count());
        $this->assertEquals(5, FrontdeskInventory::first()->number_of_serving);
    }

    public function test_quantity_must_be_positive(): void
    {
        $this->actingUser();

        Livewire::test(\App\Http\Livewire\Frontdesk\StockIn::class)
            ->set('source_type', StockMovement::SOURCE_FRONTDESK)
            ->set('menu_id', 1)
            ->set('quantity', 0)
            ->call('submit')
            ->assertHasErrors(['quantity']);

        $this->assertSame(0, StockMovement::count());
    }

    public function test_menu_id_required(): void
    {
        $this->actingUser();

        Livewire::test(\App\Http\Livewire\Frontdesk\StockIn::class)
            ->set('source_type', StockMovement::SOURCE_FRONTDESK)
            ->set('menu_id', null)
            ->set('quantity', 5)
            ->call('submit')
            ->assertHasErrors(['menu_id']);
    }
}
