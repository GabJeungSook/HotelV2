<?php

namespace Tests\Feature\Pos;

use App\Models\FrontdeskInventory;
use App\Models\StockMovement;
use App\Services\Pos\InsufficientStockException;
use App\Services\Pos\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    private function inv(float $qty = 10): FrontdeskInventory
    {
        return FrontdeskInventory::create([
            'branch_id' => 1,
            'frontdesk_menu_id' => 100,
            'number_of_serving' => $qty,
        ]);
    }

    public function test_in_increases_balance_and_writes_movement(): void
    {
        $i = $this->inv(5);
        app(StockService::class)->in(StockMovement::SOURCE_FRONTDESK, 100, 3, [
            'branch_id' => 1, 'reason' => 'delivery',
        ]);

        $this->assertEquals(8, $i->fresh()->number_of_serving);
        $m = StockMovement::latest('id')->first();
        $this->assertSame(StockMovement::TYPE_IN, $m->type);
        $this->assertEquals(3, $m->quantity);
        $this->assertEquals(8, $m->balance_after);
    }

    public function test_out_decreases_balance_and_writes_movement(): void
    {
        $i = $this->inv(5);
        app(StockService::class)->out(StockMovement::SOURCE_FRONTDESK, 100, 2, [
            'branch_id' => 1, 'ref_type' => 'transaction', 'ref_id' => 999,
        ]);

        $this->assertEquals(3, $i->fresh()->number_of_serving);
        $m = StockMovement::latest('id')->first();
        $this->assertSame(StockMovement::TYPE_OUT, $m->type);
        $this->assertEquals(2, $m->quantity);
        $this->assertEquals(3, $m->balance_after);
        $this->assertSame('transaction', $m->ref_type);
        $this->assertSame(999, $m->ref_id);
    }

    public function test_out_throws_when_insufficient_and_writes_no_movement(): void
    {
        $i = $this->inv(2);

        try {
            app(StockService::class)->out(StockMovement::SOURCE_FRONTDESK, 100, 5, ['branch_id' => 1]);
            $this->fail('Expected InsufficientStockException');
        } catch (InsufficientStockException $e) {
            $this->assertEquals(2, $e->available);
            $this->assertEquals(5, $e->requested);
        }

        $this->assertEquals(2, $i->fresh()->number_of_serving);
        $this->assertSame(0, StockMovement::count());
    }

    public function test_out_throws_when_inventory_row_missing(): void
    {
        $this->expectException(InsufficientStockException::class);
        app(StockService::class)->out(StockMovement::SOURCE_FRONTDESK, 9999, 1, ['branch_id' => 1]);
    }

    public function test_void_reverses_a_previous_out_via_ref(): void
    {
        $i = $this->inv(10);
        $svc = app(StockService::class);

        $svc->out(StockMovement::SOURCE_FRONTDESK, 100, 4, [
            'branch_id' => 1, 'ref_type' => 'transaction', 'ref_id' => 555,
        ]);
        $this->assertEquals(6, $i->fresh()->number_of_serving);

        $svc->void(StockMovement::SOURCE_FRONTDESK, 100, 4, [
            'branch_id' => 1, 'ref_type' => 'transaction', 'ref_id' => 555, 'reason' => 'mistake',
        ]);
        $this->assertEquals(10, $i->fresh()->number_of_serving);

        $voidM = StockMovement::where('type', StockMovement::TYPE_VOID)->first();
        $this->assertNotNull($voidM);
        $this->assertEquals(10, $voidM->balance_after);
        $this->assertSame(555, $voidM->ref_id);
    }

    public function test_adjust_sets_absolute_balance_and_writes_movement(): void
    {
        $i = $this->inv(5);
        app(StockService::class)->adjust(StockMovement::SOURCE_FRONTDESK, 100, 12, [
            'branch_id' => 1, 'reason' => 'physical count',
        ]);

        $this->assertEquals(12, $i->fresh()->number_of_serving);
        $m = StockMovement::latest('id')->first();
        $this->assertSame(StockMovement::TYPE_ADJUST, $m->type);
        $this->assertEquals(12, $m->balance_after);
    }

    public function test_sequential_outs_block_oversell(): void
    {
        $this->inv(3);
        $svc = app(StockService::class);

        $svc->out(StockMovement::SOURCE_FRONTDESK, 100, 2, ['branch_id' => 1]);
        $this->expectException(InsufficientStockException::class);
        $svc->out(StockMovement::SOURCE_FRONTDESK, 100, 2, ['branch_id' => 1]);
    }

    public function test_shadow_mode_writes_movement_without_touching_inventory(): void
    {
        $i = $this->inv(3);
        app(StockService::class)->out(StockMovement::SOURCE_FRONTDESK, 100, 2, [
            'branch_id' => 1, 'shadow' => true,
        ]);

        $this->assertEquals(3, $i->fresh()->number_of_serving, 'shadow must NOT change inventory');
        $this->assertSame(1, StockMovement::count());
        $this->assertEquals(3, StockMovement::first()->balance_after, 'balance_after snapshots current');
    }

    public function test_shadow_mode_does_not_throw_when_insufficient(): void
    {
        $this->inv(0);
        app(StockService::class)->out(StockMovement::SOURCE_FRONTDESK, 100, 5, [
            'branch_id' => 1, 'shadow' => true,
        ]);

        $this->assertSame(1, StockMovement::count(), 'shadow must always log');
    }
}
