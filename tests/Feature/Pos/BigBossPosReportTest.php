<?php

namespace Tests\Feature\Pos;

use App\Http\Livewire\BackOffice\Reports\BigBossPosReport;
use App\Http\Livewire\Frontdesk\PointOfSale;
use App\Models\Branch;
use App\Models\CashDrawer;
use App\Models\FrontdeskCategory;
use App\Models\FrontdeskInventory;
use App\Models\FrontdeskMenu;
use App\Models\PosOrder;
use App\Models\ShiftLog;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Pos\StockService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BigBossPosReportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build a closed shift session (time_out set) so it shows up in the
     * report's per-shift selector.
     */
    private function seedClosedShift(): array
    {
        $branch = Branch::create([
            'name' => 'BigBoss POS Test Branch ' . uniqid(),
        ]);

        $drawer = CashDrawer::create([
            'branch_id' => $branch->id,
            'name'      => 'Drawer A',
            'is_active' => true,
        ]);

        $user = User::create([
            'name'           => 'Cashier ' . uniqid(),
            'email'          => 'bigboss-pos-' . uniqid() . '@example.com',
            'password'       => bcrypt('secret'),
            'branch_id'      => $branch->id,
            'branch_name'    => $branch->name,
            'cash_drawer_id' => $drawer->id,
        ]);

        // CLOSED shift (time_out set) — required for it to appear in the selector.
        $shift = ShiftLog::create([
            'branch_id'      => $branch->id,
            'frontdesk_id'   => $user->id,
            'cash_drawer_id' => $drawer->id,
            'time_in'        => Carbon::now()->subHours(8),
            'time_out'       => Carbon::now()->subHour(),
            'frontdesk_ids'  => json_encode([$user->id]),
        ]);

        $category = FrontdeskCategory::create([
            'branch_id' => $branch->id,
            'name'      => 'Drinks',
        ]);

        return compact('branch', 'user', 'drawer', 'shift', 'category');
    }

    private function seedMenuItem(int $branchId, int $categoryId, string $name, int $price, float $stock): FrontdeskMenu
    {
        $menu = FrontdeskMenu::create([
            'branch_id'             => $branchId,
            'frontdesk_category_id' => $categoryId,
            'name'                  => $name,
            'price'                 => (string) $price,
        ]);

        FrontdeskInventory::create([
            'branch_id'         => $branchId,
            'frontdesk_menu_id' => $menu->id,
            'number_of_serving' => $stock,
        ]);

        return $menu;
    }

    /**
     * Insert a PosOrder + Transaction + StockMovement-out trio directly,
     * dated within the shift window. Faster than driving the Livewire UI.
     */
    private function ringSale(array $context, FrontdeskMenu $menu, int $price, float $qty, ?Carbon $createdAt = null): PosOrder
    {
        $createdAt = $createdAt ?? $context['shift']->time_in->copy()->addHour();
        $lineTotal = (int) round($price * $qty);

        $order = PosOrder::create([
            'branch_id'      => $context['branch']->id,
            'user_id'        => $context['user']->id,
            'shift_log_id'   => $context['shift']->id,
            'payment_method' => 'cash',
            'subtotal'       => $lineTotal,
            'discount_amount'=> 0,
            'total'          => $lineTotal,
            'paid_amount'    => $lineTotal,
            'change_amount'  => 0,
            'created_at'     => $createdAt,
            'updated_at'     => $createdAt,
        ]);

        \App\Models\Transaction::create([
            'order_id'              => $order->id,
            'branch_id'             => $context['branch']->id,
            'shift_log_id'          => $context['shift']->id,
            'transaction_type_id'   => 9,
            'assigned_frontdesk_id' => json_encode(null),
            'description'           => 'Food and Beverages',
            'payable_amount'        => $lineTotal,
            'paid_amount'           => 0,
            'change_amount'         => 0,
            'deposit_amount'        => 0,
            'remarks'               => "POS ({$menu->name} x{$qty})",
            'source_type'           => StockMovement::SOURCE_FRONTDESK,
            'menu_id'               => $menu->id,
            'item_name'             => $menu->name,
            'unit_price'            => $price,
            'quantity'              => $qty,
            'created_at'            => $createdAt,
            'updated_at'            => $createdAt,
        ]);

        // Real stock OUT through the service so balance_after is correct.
        app(StockService::class)->out(
            StockMovement::SOURCE_FRONTDESK,
            $menu->id,
            $qty,
            [
                'branch_id'    => $context['branch']->id,
                'ref_type'     => 'transaction',
                'user_id'      => $context['user']->id,
                'shift_log_id' => $context['shift']->id,
            ]
        );

        // Backdate the stock movement to land in the shift window.
        StockMovement::latest('id')->first()->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        return $order->fresh();
    }

    public function test_pos_sales_rows_includes_orders_and_correct_totals(): void
    {
        $context = $this->seedClosedShift();
        $coke = $this->seedMenuItem($context['branch']->id, $context['category']->id, 'Coke', 60, 10);
        $chips = $this->seedMenuItem($context['branch']->id, $context['category']->id, 'Chips', 50, 10);

        $first = $this->ringSale($context, $coke, 60, 2);   // 120 cash
        $second = $this->ringSale($context, $chips, 50, 1); // 50  cash

        $component = Livewire::actingAs($context['user'])->test(BigBossPosReport::class);

        $report = $component->instance()->generateReport(
            collect($component->get('availableShiftSessions'))->firstWhere('id', $context['shift']->id)
        );

        $this->assertCount(2, $report['posSales']['orders']);
        $this->assertSame(170, $report['posSales']['totals']['cash']);
        $this->assertSame(0, $report['posSales']['totals']['room']);
        $this->assertSame(170, $report['posSales']['totals']['gross']);
        $this->assertSame(2, $report['posSales']['totals']['live_count']);
        $this->assertSame(0, $report['posSales']['totals']['voided_count']);
    }

    public function test_voided_orders_are_excluded_from_totals_but_listed(): void
    {
        $context = $this->seedClosedShift();
        $coke = $this->seedMenuItem($context['branch']->id, $context['category']->id, 'Coke', 60, 10);

        $live = $this->ringSale($context, $coke, 60, 1);   // 60 cash, live
        $void = $this->ringSale($context, $coke, 60, 2);   // 120 cash, then voided

        // Mark second order voided manually (bypassing the same-shift gate
        // since the shift is already closed in this test).
        $void->forceFill([
            'voided_at'         => Carbon::now()->subHours(2),
            'voided_by_user_id' => $context['user']->id,
        ])->save();

        $component = Livewire::actingAs($context['user'])->test(BigBossPosReport::class);
        $report = $component->instance()->generateReport(
            collect($component->get('availableShiftSessions'))->firstWhere('id', $context['shift']->id)
        );

        $this->assertCount(2, $report['posSales']['orders'], 'Both orders listed (with voided indicator)');
        $this->assertSame(60, $report['posSales']['totals']['cash'], 'Only the live order counts toward cash total');
        $this->assertSame(1, $report['posSales']['totals']['live_count']);
        $this->assertSame(1, $report['posSales']['totals']['voided_count']);
    }

    public function test_inventory_rows_show_opening_in_out_closing(): void
    {
        $context = $this->seedClosedShift();
        $coke = $this->seedMenuItem($context['branch']->id, $context['category']->id, 'Coke', 60, 10);

        // Pre-shift OPENING balance: insert a movement BEFORE shift time_in.
        // created_at/updated_at aren't fillable on StockMovement, so set
        // them via forceFill after create.
        $opening = StockMovement::create([
            'branch_id'    => $context['branch']->id,
            'source_type'  => StockMovement::SOURCE_FRONTDESK,
            'menu_id'      => $coke->id,
            'inventory_id' => FrontdeskInventory::where('frontdesk_menu_id', $coke->id)->first()->id,
            'type'         => StockMovement::TYPE_OPENING,
            'quantity'     => 10,
            'balance_after'=> 10,
        ]);
        $openingAt = $context['shift']->time_in->copy()->subHour();
        $opening->forceFill(['created_at' => $openingAt, 'updated_at' => $openingAt])->save();

        // Now ring 3 sales = OUT 3 within the shift.
        $this->ringSale($context, $coke, 60, 1);
        $this->ringSale($context, $coke, 60, 2);

        $component = Livewire::actingAs($context['user'])->test(BigBossPosReport::class);
        $report = $component->instance()->generateReport(
            collect($component->get('availableShiftSessions'))->firstWhere('id', $context['shift']->id)
        );

        $this->assertCount(1, $report['inventoryRows']);
        $row = $report['inventoryRows'][0];
        $this->assertSame('Coke', $row['item_name']);
        $this->assertEquals(10.0, $row['opening']);
        $this->assertEquals(0.0, $row['in']);
        $this->assertEquals(3.0, $row['out']);
        $this->assertEquals(7.0, $row['closing']);
    }

    public function test_inventory_rows_omit_items_with_no_shift_movement(): void
    {
        $context = $this->seedClosedShift();
        $coke = $this->seedMenuItem($context['branch']->id, $context['category']->id, 'Coke', 60, 5);
        $chips = $this->seedMenuItem($context['branch']->id, $context['category']->id, 'Chips', 50, 5);

        // Only ring Coke this shift — Chips should NOT appear in inventory rows.
        $this->ringSale($context, $coke, 60, 1);

        $component = Livewire::actingAs($context['user'])->test(BigBossPosReport::class);
        $report = $component->instance()->generateReport(
            collect($component->get('availableShiftSessions'))->firstWhere('id', $context['shift']->id)
        );

        $this->assertCount(1, $report['inventoryRows']);
        $this->assertSame('Coke', $report['inventoryRows'][0]['item_name']);
    }

    public function test_empty_session_returns_empty_report(): void
    {
        $context = $this->seedClosedShift();

        $component = Livewire::actingAs($context['user'])->test(BigBossPosReport::class);
        $report = $component->instance()->generateReport(null);

        $this->assertEmpty($report['posSales']['orders']);
        $this->assertSame(0, $report['posSales']['totals']['gross']);
        $this->assertEmpty($report['inventoryRows']);
    }
}
