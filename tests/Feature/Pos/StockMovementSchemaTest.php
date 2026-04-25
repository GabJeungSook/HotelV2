<?php

namespace Tests\Feature\Pos;

use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StockMovementSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_movements_table_exists_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('stock_movements'));

        $expected = [
            'id', 'branch_id',
            'source_type', 'menu_id', 'inventory_id',
            'type', 'quantity', 'balance_after',
            'reason', 'ref_type', 'ref_id',
            'user_id', 'shift_log_id',
            'created_at', 'updated_at',
        ];

        foreach ($expected as $col) {
            $this->assertTrue(
                Schema::hasColumn('stock_movements', $col),
                "stock_movements is missing column {$col}"
            );
        }
    }

    public function test_stock_movement_model_can_persist_and_read_back(): void
    {
        $movement = StockMovement::create([
            'branch_id'    => 1,
            'source_type'  => StockMovement::SOURCE_FRONTDESK,
            'menu_id'      => 1,
            'inventory_id' => 1,
            'type'         => StockMovement::TYPE_OPENING,
            'quantity'     => 10,
            'balance_after'=> 10,
        ]);

        $this->assertNotNull($movement->id);
        $this->assertSame(StockMovement::TYPE_OPENING, $movement->fresh()->type);
        $this->assertEquals(10, $movement->fresh()->balance_after);
    }
}
