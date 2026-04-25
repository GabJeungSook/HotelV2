<?php

namespace Tests\Feature\Pos;

use App\Models\FrontdeskInventory;
use App\Models\Inventory;
use App\Models\PubInventory;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillOpeningBalancesTest extends TestCase
{
    use RefreshDatabase;

    private function backfillMigration(): object
    {
        return require database_path(
            'migrations/2026_04_25_120003_backfill_stock_movements_opening_balances.php'
        );
    }

    public function test_backfill_creates_opening_movement_per_inventory_row(): void
    {
        FrontdeskInventory::create(['branch_id' => 1, 'frontdesk_menu_id' => 1, 'number_of_serving' => 12]);
        Inventory::create(['branch_id' => 1, 'menu_id' => 2, 'number_of_serving' => 7]);
        PubInventory::create(['branch_id' => 1, 'pub_menu_id' => 3, 'number_of_serving' => 4]);

        $this->backfillMigration()->up();

        $this->assertSame(3, StockMovement::where('type', StockMovement::TYPE_OPENING)->count());

        $this->assertEquals(12, StockMovement::where('source_type', 'frontdesk')->first()->balance_after);
        $this->assertEquals(7,  StockMovement::where('source_type', 'kitchen')->first()->balance_after);
        $this->assertEquals(4,  StockMovement::where('source_type', 'pub')->first()->balance_after);
    }

    public function test_backfill_skips_zero_balance_rows(): void
    {
        FrontdeskInventory::create(['branch_id' => 1, 'frontdesk_menu_id' => 50, 'number_of_serving' => 0]);

        $this->backfillMigration()->up();

        $this->assertSame(0, StockMovement::count());
    }

    public function test_backfill_is_idempotent_on_rerun(): void
    {
        FrontdeskInventory::create(['branch_id' => 1, 'frontdesk_menu_id' => 50, 'number_of_serving' => 8]);

        $m = $this->backfillMigration();
        $m->up();
        $m->up();
        $m->up();

        $this->assertSame(
            1,
            StockMovement::where('source_type', 'frontdesk')
                ->where('menu_id', 50)
                ->where('type', StockMovement::TYPE_OPENING)
                ->count(),
            'backfill must not duplicate OPENING rows'
        );
    }
}
