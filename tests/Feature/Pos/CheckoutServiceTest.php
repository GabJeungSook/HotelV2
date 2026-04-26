<?php

namespace Tests\Feature\Pos;

use App\Models\FrontdeskInventory;
use App\Models\PosOrder;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Services\Pos\CheckoutService;
use App\Services\Pos\InsufficientStockException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CheckoutServiceTest extends TestCase
{
    use RefreshDatabase;

    private function inv(int $menuId, float $qty): FrontdeskInventory
    {
        return FrontdeskInventory::create([
            'branch_id'         => 1,
            'frontdesk_menu_id' => $menuId,
            'number_of_serving' => $qty,
        ]);
    }

    private function line(int $menuId, string $name, int $unitPrice, float $qty): array
    {
        return [
            'menu_id'    => $menuId,
            'name'       => $name,
            'unit_price' => $unitPrice,
            'quantity'   => $qty,
        ];
    }

    public function test_walk_in_cash_sale_creates_pos_order_transactions_and_stock_out(): void
    {
        $this->inv(101, 10);
        $this->inv(102, 5);

        $cart = [
            $this->line(101, 'Coke', 60, 2),
            $this->line(102, 'Chips', 50, 1),
        ];

        $order = app(CheckoutService::class)->checkout($cart, [
            'branch_id'   => 1,
            'user_id'     => 7,
            'shift_log_id'=> 42,
            'paid_amount' => 200,
            'change_amount' => 30,
        ]);

        $this->assertSame(170, $order->subtotal); // 60*2 + 50*1
        $this->assertSame(170, $order->total);
        $this->assertSame(0, $order->discount_amount);
        $this->assertSame('cash', $order->payment_method);
        $this->assertNull($order->guest_id);
        $this->assertNull($order->room_id);
        $this->assertSame(200, $order->paid_amount);
        $this->assertSame(30, $order->change_amount);

        $this->assertSame(2, Transaction::where('order_id', $order->id)->count());
        $coke = Transaction::where('order_id', $order->id)->where('menu_id', 101)->first();
        $this->assertSame(60, (int) $coke->unit_price);
        $this->assertEquals(2, (float) $coke->quantity);
        $this->assertSame(120, (int) $coke->payable_amount);
        $this->assertSame('Coke', $coke->item_name);
        $this->assertSame(StockMovement::SOURCE_FRONTDESK, $coke->source_type);
        $this->assertSame(9, (int) $coke->transaction_type_id);

        // Stock decremented
        $this->assertEquals(8, FrontdeskInventory::where('frontdesk_menu_id', 101)->first()->number_of_serving);
        $this->assertEquals(4, FrontdeskInventory::where('frontdesk_menu_id', 102)->first()->number_of_serving);

        // Stock movement audit rows
        $this->assertSame(2, StockMovement::where('source_type', StockMovement::SOURCE_FRONTDESK)->count());
    }

    public function test_room_charge_sale_attaches_guest_and_omits_payment_method(): void
    {
        $this->inv(201, 5);

        $order = app(CheckoutService::class)->checkout(
            [$this->line(201, 'Beer', 80, 1)],
            [
                'branch_id'    => 1,
                'user_id'      => 7,
                'guest_id'     => 333,
                'room_id'      => 12,
                'floor_id'     => 1,
                'paid_amount'  => 999, // ignored on room-charge
                'change_amount'=> 999,
            ]
        );

        $this->assertNull($order->payment_method);
        $this->assertSame(333, $order->guest_id);
        $this->assertSame(12, $order->room_id);
        $this->assertSame(0, $order->paid_amount, 'Room-charge sale should not capture cash');
        $this->assertSame(0, $order->change_amount, 'Room-charge sale should not capture change');

        $tx = Transaction::where('order_id', $order->id)->first();
        $this->assertSame(333, (int) $tx->guest_id);
        $this->assertSame(12, (int) $tx->room_id);
        $this->assertSame(1, (int) $tx->floor_id);
    }

    public function test_discount_is_applied_to_total_and_persisted(): void
    {
        $this->inv(301, 5);

        $order = app(CheckoutService::class)->checkout(
            [$this->line(301, 'Snack', 100, 3)],
            [
                'branch_id'       => 1,
                'user_id'         => 7,
                'discount_amount' => 50,
                'discount_reason' => 'regular customer',
                'paid_amount'     => 250,
                'change_amount'   => 0,
            ]
        );

        $this->assertSame(300, $order->subtotal);
        $this->assertSame(50, $order->discount_amount);
        $this->assertSame('regular customer', $order->discount_reason);
        $this->assertSame(250, $order->total);
    }

    public function test_discount_exceeding_subtotal_throws(): void
    {
        $this->inv(401, 5);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Discount.*exceed/');

        app(CheckoutService::class)->checkout(
            [$this->line(401, 'Item', 100, 1)],
            [
                'branch_id'       => 1,
                'user_id'         => 7,
                'discount_amount' => 200,
                'paid_amount'     => 0,
            ]
        );

        // Nothing should have been written.
        $this->assertSame(0, PosOrder::count());
        $this->assertSame(0, Transaction::count());
        $this->assertSame(0, StockMovement::count());
    }

    public function test_empty_cart_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cart is empty');

        app(CheckoutService::class)->checkout([], [
            'branch_id' => 1,
            'user_id'   => 7,
        ]);
    }

    public function test_insufficient_stock_throws_and_writes_no_partial_state(): void
    {
        $this->inv(501, 5); // enough
        $this->inv(502, 1); // NOT enough — line wants 3

        $cart = [
            $this->line(501, 'Item A', 10, 2),
            $this->line(502, 'Item B', 20, 3),
        ];

        try {
            app(CheckoutService::class)->checkout($cart, [
                'branch_id'   => 1,
                'user_id'     => 7,
                'paid_amount' => 200,
            ]);
            $this->fail('Expected InsufficientStockException');
        } catch (InsufficientStockException $e) {
            // Expected.
        }

        // Critical safety: NO partial state.
        $this->assertSame(0, PosOrder::count(), 'Order header must not persist on failure');
        $this->assertSame(0, Transaction::count(), 'No line transactions must persist on failure');
        $this->assertSame(0, StockMovement::count(), 'No stock movements must persist on failure');
        $this->assertEquals(5, FrontdeskInventory::where('frontdesk_menu_id', 501)->first()->number_of_serving, 'First-line inventory must NOT decrement when later line fails');
        $this->assertEquals(1, FrontdeskInventory::where('frontdesk_menu_id', 502)->first()->number_of_serving);
    }

    public function test_cash_sale_paid_less_than_total_throws(): void
    {
        $this->inv(601, 5);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Paid amount.*less than total/');

        app(CheckoutService::class)->checkout(
            [$this->line(601, 'Item', 100, 2)],
            [
                'branch_id'   => 1,
                'user_id'     => 7,
                'paid_amount' => 50, // total is 200
            ]
        );
    }

    public function test_negative_quantity_throws(): void
    {
        $this->inv(701, 5);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/quantity must be > 0/');

        app(CheckoutService::class)->checkout(
            [$this->line(701, 'Item', 100, 0)],
            [
                'branch_id'   => 1,
                'user_id'     => 7,
                'paid_amount' => 100,
            ]
        );
    }

    public function test_missing_required_context_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('branch_id required');

        app(CheckoutService::class)->checkout(
            [$this->line(801, 'X', 1, 1)],
            ['user_id' => 7]
        );
    }
}
