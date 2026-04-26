<?php

namespace Tests\Feature\Pos;

use App\Http\Livewire\Frontdesk\PointOfSale;
use App\Models\Branch;
use App\Models\CashDrawer;
use App\Models\CheckinDetail;
use App\Models\Floor;
use App\Models\FrontdeskCategory;
use App\Models\FrontdeskInventory;
use App\Models\FrontdeskMenu;
use App\Models\Guest;
use App\Models\PosOrder;
use App\Models\PosTransaction;
use App\Models\Rate;
use App\Models\Room;
use App\Models\ShiftLog;
use App\Models\StayingHour;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\Type;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PointOfSaleV2Test extends TestCase
{
    use RefreshDatabase;

    /**
     * Build the minimum scaffolding to operate a frontdesk POS shift.
     * @return array{branch: Branch, user: User, drawer: CashDrawer, shift: ShiftLog, category: FrontdeskCategory}
     */
    private function seedShift(): array
    {
        $branch = Branch::create([
            'name' => 'POS Test Branch ' . uniqid(),
        ]);

        $drawer = CashDrawer::create([
            'branch_id' => $branch->id,
            'name'      => 'Drawer A',
            'is_active' => true,
        ]);

        $user = User::create([
            'name'            => 'Frontdesk Test ' . uniqid(),
            'email'           => 'pos-test-' . uniqid() . '@example.com',
            'password'        => bcrypt('secret'),
            'branch_id'       => $branch->id,
            'branch_name'     => $branch->name,
            'cash_drawer_id'  => $drawer->id,
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

    public function test_pos_no_longer_writes_legacy_pos_transactions(): void
    {
        $context = $this->seedShift();
        $coke = $this->seedMenuItem($context['branch']->id, $context['category']->id, 'Coke', 60, 5);

        Livewire::actingAs($context['user'])
            ->test(PointOfSale::class)
            ->call('addToCart', $coke->id)
            ->call('reviewCheckout')
            ->call('checkout');

        $this->assertSame(0, PosTransaction::count(), 'POS rebuild must NEVER write to legacy pos_transactions');
        $this->assertSame(1, PosOrder::count(), 'POS sale must create a pos_order');
        $this->assertSame(1, Transaction::where('transaction_type_id', 9)->count(), 'POS sale must create a transactions row');
    }

    public function test_cash_walk_in_creates_pos_order_transactions_and_stock_movements(): void
    {
        $context = $this->seedShift();
        $coke = $this->seedMenuItem($context['branch']->id, $context['category']->id, 'Coke', 60, 10);
        $chips = $this->seedMenuItem($context['branch']->id, $context['category']->id, 'Chips', 50, 5);

        Livewire::actingAs($context['user'])
            ->test(PointOfSale::class)
            ->call('addToCart', $coke->id)
            ->call('addToCart', $coke->id) // qty 2
            ->call('addToCart', $chips->id)
            ->call('reviewCheckout')
            ->call('checkout');

        $this->assertSame(0, PosTransaction::count(), 'v2 must NOT write legacy PosTransaction rows');
        $this->assertSame(1, PosOrder::count(), 'v2 must write one pos_order header');

        $order = PosOrder::first();
        $this->assertSame('cash', $order->payment_method);
        $this->assertNull($order->guest_id);
        $this->assertSame(170, (int) $order->subtotal); // 60*2 + 50*1
        $this->assertSame(170, (int) $order->total);
        $this->assertSame(0, (int) $order->discount_amount);
        $this->assertSame($context['shift']->id, (int) $order->shift_log_id);

        $this->assertSame(2, Transaction::where('order_id', $order->id)->count(), 'one transaction per cart line');
        $this->assertSame(2, StockMovement::where('source_type', StockMovement::SOURCE_FRONTDESK)->where('type', StockMovement::TYPE_OUT)->count());

        // Stock decremented
        $this->assertEquals(8, FrontdeskInventory::where('frontdesk_menu_id', $coke->id)->first()->number_of_serving);
        $this->assertEquals(4, FrontdeskInventory::where('frontdesk_menu_id', $chips->id)->first()->number_of_serving);
    }

    public function test_v2_room_charge_attaches_guest_and_omits_payment_method(): void
    {
        $context = $this->seedShift();
        $beer = $this->seedMenuItem($context['branch']->id, $context['category']->id, 'Beer', 80, 5);

        // Guest scaffolding
        $type = Type::create(['branch_id' => $context['branch']->id, 'name' => 'Standard']);
        $floor = Floor::create(['branch_id' => $context['branch']->id, 'number' => 1]);
        $stayingHour = StayingHour::create(['branch_id' => $context['branch']->id, 'number' => 12]);
        $rate = Rate::create([
            'branch_id'       => $context['branch']->id,
            'type_id'         => $type->id,
            'staying_hour_id' => $stayingHour->id,
            'amount'          => 500,
        ]);
        $room = Room::create([
            'branch_id'   => $context['branch']->id,
            'floor_id'    => $floor->id,
            'type_id'     => $type->id,
            'number'      => '305',
            'status'      => 'Occupied',
            'is_priority' => true,
        ]);
        $guest = Guest::create([
            'branch_id'     => $context['branch']->id,
            'name'          => 'Maria Santos',
            'qr_code'       => 'TEST-' . uniqid(),
            'room_id'       => $room->id,
            'rate_id'       => $rate->id,
            'type_id'       => $type->id,
            'static_amount' => 500,
        ]);
        CheckinDetail::create([
            'guest_id'      => $guest->id,
            'type_id'       => $type->id,
            'room_id'       => $room->id,
            'rate_id'       => $rate->id,
            'static_amount' => 500,
            'hours_stayed'  => 12,
            'check_in_at'   => Carbon::now()->subHours(2),
            'check_out_at'  => Carbon::now()->addHours(10),
            'is_long_stay'  => false,
            'is_check_out'  => false,
        ]);

        Livewire::actingAs($context['user'])
            ->test(PointOfSale::class)
            ->call('addToCart', $beer->id)
            ->call('toggleAttachToRoom')
            ->call('selectGuest', $guest->id)
            ->call('reviewCheckout')
            ->call('checkout');

        $order = PosOrder::first();
        $this->assertNotNull($order, 'pos_order must be created');
        $this->assertSame($guest->id, (int) $order->guest_id);
        $this->assertSame($room->id, (int) $order->room_id);
        $this->assertNull($order->payment_method, 'Room-charge sale must have payment_method = NULL');
        $this->assertSame(0, (int) $order->paid_amount, 'Room-charge sale must not capture cash');
        $this->assertSame(80, (int) $order->total);

        $tx = Transaction::where('order_id', $order->id)->first();
        $this->assertSame($guest->id, (int) $tx->guest_id);
        $this->assertSame($room->id, (int) $tx->room_id);
    }

    public function test_v2_discount_persists_to_pos_order_and_total(): void
    {
        $context = $this->seedShift();
        $snack = $this->seedMenuItem($context['branch']->id, $context['category']->id, 'Snack', 100, 5);

        Livewire::actingAs($context['user'])
            ->test(PointOfSale::class)
            ->call('addToCart', $snack->id)
            ->call('addToCart', $snack->id)
            ->call('addToCart', $snack->id) // qty 3, subtotal 300
            ->set('discountAmount', 50)
            ->set('discountReason', 'regular customer')
            ->call('reviewCheckout')
            ->call('checkout');

        $order = PosOrder::first();
        $this->assertNotNull($order);
        $this->assertSame(300, (int) $order->subtotal);
        $this->assertSame(50, (int) $order->discount_amount);
        $this->assertSame('regular customer', $order->discount_reason);
        $this->assertSame(250, (int) $order->total);
        $this->assertSame(250, (int) $order->paid_amount, 'Cash sale paid amount equals discounted total');
    }

    public function test_v2_attach_to_room_without_selecting_guest_blocks_checkout(): void
    {
        $context = $this->seedShift();
        $coke = $this->seedMenuItem($context['branch']->id, $context['category']->id, 'Coke', 60, 5);

        Livewire::actingAs($context['user'])
            ->test(PointOfSale::class)
            ->call('addToCart', $coke->id)
            ->call('toggleAttachToRoom')      // ON, but no guest selected
            ->call('reviewCheckout');         // should block before showing modal

        $this->assertSame(0, PosOrder::count(), 'No order should be created when no guest is selected');
        $this->assertSame(0, Transaction::where('transaction_type_id', 9)->count());
    }
}
