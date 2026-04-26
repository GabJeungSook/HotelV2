<?php

namespace Tests\Feature\Pos;

use App\Models\PosOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PosOrdersSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_pos_orders_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('pos_orders'));

        $expected = [
            'id', 'branch_id', 'user_id', 'shift_log_id',
            'guest_id', 'room_id',
            'payment_method', 'subtotal',
            'discount_amount', 'discount_reason',
            'total', 'paid_amount', 'change_amount',
            'voided_at', 'voided_by_user_id',
            'created_at', 'updated_at',
        ];

        foreach ($expected as $col) {
            $this->assertTrue(Schema::hasColumn('pos_orders', $col), "pos_orders missing {$col}");
        }
    }

    public function test_transactions_has_order_id_column(): void
    {
        $this->assertTrue(Schema::hasColumn('transactions', 'order_id'));
    }

    public function test_pos_order_can_be_created_and_read_back(): void
    {
        $order = PosOrder::create([
            'branch_id'      => 1,
            'user_id'        => 1,
            'shift_log_id'   => null,
            'guest_id'       => null,
            'room_id'        => null,
            'payment_method' => 'cash',
            'subtotal'       => 200,
            'discount_amount'=> 0,
            'discount_reason'=> null,
            'total'          => 200,
            'paid_amount'    => 200,
            'change_amount'  => 0,
        ]);

        $this->assertNotNull($order->id);
        $this->assertSame('cash', $order->fresh()->payment_method);
        $this->assertEquals(200, $order->fresh()->total);
    }
}
