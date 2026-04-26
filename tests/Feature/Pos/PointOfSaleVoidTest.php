<?php

namespace Tests\Feature\Pos;

use App\Http\Livewire\Frontdesk\PointOfSale;
use App\Models\Branch;
use App\Models\CashDrawer;
use App\Models\FrontdeskCategory;
use App\Models\FrontdeskInventory;
use App\Models\FrontdeskMenu;
use App\Models\PosOrder;
use App\Models\ShiftLog;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Pos\CheckoutService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PointOfSaleVoidTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{branch: Branch, user: User, drawer: CashDrawer, shift: ShiftLog, category: FrontdeskCategory, menu: FrontdeskMenu}
     */
    private function seedShiftWithCart(): array
    {
        $branch = Branch::create([
            'name' => 'POS Void Test ' . uniqid(),
        ]);

        $drawer = CashDrawer::create([
            'branch_id' => $branch->id,
            'name'      => 'Drawer A',
            'is_active' => true,
        ]);

        $user = User::create([
            'name'           => 'Frontdesk Void Test ' . uniqid(),
            'email'          => 'pos-void-' . uniqid() . '@example.com',
            'password'       => bcrypt('secret'),
            'branch_id'      => $branch->id,
            'branch_name'    => $branch->name,
            'cash_drawer_id' => $drawer->id,
        ]);

        $shift = ShiftLog::create([
            'branch_id'      => $branch->id,
            'frontdesk_id'   => $user->id,
            'cash_drawer_id' => $drawer->id,
            'time_in'        => Carbon::now()->subHour(),
            'time_out'       => null,
            'frontdesk_ids'  => json_encode([$user->id]),
        ]);

        $category = FrontdeskCategory::create([
            'branch_id' => $branch->id,
            'name'      => 'Drinks',
        ]);

        $menu = FrontdeskMenu::create([
            'branch_id'             => $branch->id,
            'frontdesk_category_id' => $category->id,
            'name'                  => 'Coke',
            'price'                 => '60',
        ]);

        FrontdeskInventory::create([
            'branch_id'         => $branch->id,
            'frontdesk_menu_id' => $menu->id,
            'number_of_serving' => 10,
        ]);

        return compact('branch', 'user', 'drawer', 'shift', 'category', 'menu');
    }

    private function ringSale(array $context, int $qty = 2): PosOrder
    {
        $component = Livewire::actingAs($context['user'])->test(PointOfSale::class);
        for ($i = 0; $i < $qty; $i++) {
            $component->call('addToCart', $context['menu']->id);
        }
        $component->call('reviewCheckout')->call('checkout');

        return PosOrder::orderByDesc('id')->first();
    }

    public function test_void_reverses_order_transactions_and_restores_stock(): void
    {
        $context = $this->seedShiftWithCart();
        $order = $this->ringSale($context, qty: 3); // 3 Coke @ 60 = 180; stock 10 -> 7

        $this->assertEquals(7, FrontdeskInventory::where('frontdesk_menu_id', $context['menu']->id)->first()->number_of_serving);
        $this->assertNull($order->voided_at);

        Livewire::actingAs($context['user'])
            ->test(PointOfSale::class)
            ->call('voidOrder', $order->id);

        $order->refresh();
        $this->assertNotNull($order->voided_at, 'Order header must be marked voided');
        $this->assertSame($context['user']->id, (int) $order->voided_by_user_id);

        // Every line transaction also marked voided
        $lineTx = Transaction::where('order_id', $order->id)->get();
        $this->assertCount(1, $lineTx, 'Single cart line (Coke x3) = one transaction row');
        foreach ($lineTx as $tx) {
            $this->assertNotNull($tx->voided_at, 'Each line transaction must be marked voided');
            $this->assertSame($context['user']->id, (int) $tx->voided_by_user_id);
        }

        // Stock restored to original
        $this->assertEquals(10, FrontdeskInventory::where('frontdesk_menu_id', $context['menu']->id)->first()->number_of_serving);

        // A VOID stock movement was written referencing the original transaction
        $voidMovement = StockMovement::where('type', StockMovement::TYPE_VOID)->first();
        $this->assertNotNull($voidMovement);
        $this->assertSame('transaction_void', $voidMovement->ref_type);
    }

    public function test_void_excludes_order_from_cash_total(): void
    {
        $context = $this->seedShiftWithCart();
        $first = $this->ringSale($context, qty: 2);  // 120
        $second = $this->ringSale($context, qty: 1); // 60

        $component = Livewire::actingAs($context['user'])->test(PointOfSale::class);
        $this->assertSame(180, (int) $component->get('total_pos'), '2 sales totalling 180 before void');

        $component->call('voidOrder', $first->id);

        $component2 = Livewire::actingAs($context['user'])->test(PointOfSale::class);
        $this->assertSame(60, (int) $component2->get('total_pos'), 'Voided order must drop out of the cash total');
    }

    public function test_void_is_idempotent(): void
    {
        $context = $this->seedShiftWithCart();
        $order = $this->ringSale($context, qty: 1);

        $stockBefore = FrontdeskInventory::where('frontdesk_menu_id', $context['menu']->id)->first()->number_of_serving;

        // First void
        Livewire::actingAs($context['user'])->test(PointOfSale::class)->call('voidOrder', $order->id);
        $stockAfterFirstVoid = FrontdeskInventory::where('frontdesk_menu_id', $context['menu']->id)->first()->number_of_serving;
        $this->assertEquals($stockBefore + 1, $stockAfterFirstVoid, 'Stock should be restored once');

        // Second void (via service directly to bypass the UI guard)
        app(CheckoutService::class)->void($order->fresh(), $context['user']->id, null);
        $stockAfterSecondVoid = FrontdeskInventory::where('frontdesk_menu_id', $context['menu']->id)->first()->number_of_serving;
        $this->assertEquals($stockAfterFirstVoid, $stockAfterSecondVoid, 'Second void must NOT double-restore stock');
    }

    public function test_void_blocked_when_attempted_by_different_user(): void
    {
        $context = $this->seedShiftWithCart();
        $order = $this->ringSale($context, qty: 1);

        $other = User::create([
            'name'           => 'Other Cashier ' . uniqid(),
            'email'          => 'other-' . uniqid() . '@example.com',
            'password'       => bcrypt('secret'),
            'branch_id'      => $context['branch']->id,
            'branch_name'    => $context['branch']->name,
            'cash_drawer_id' => $context['drawer']->id,
        ]);
        // Other cashier needs a shift to even mount the component.
        ShiftLog::create([
            'branch_id'      => $context['branch']->id,
            'frontdesk_id'   => $other->id,
            'cash_drawer_id' => $context['drawer']->id,
            'time_in'        => Carbon::now()->subMinutes(30),
            'time_out'       => null,
            'frontdesk_ids'  => json_encode([$other->id]),
        ]);

        Livewire::actingAs($other)
            ->test(PointOfSale::class)
            ->call('voidOrder', $order->id);

        $order->refresh();
        $this->assertNull($order->voided_at, 'Different user must not be able to void the order');
    }
}
