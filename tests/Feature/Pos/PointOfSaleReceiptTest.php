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
use App\Models\Rate;
use App\Models\Room;
use App\Models\ShiftLog;
use App\Models\StayingHour;
use App\Models\Type;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PointOfSaleReceiptTest extends TestCase
{
    use RefreshDatabase;

    private function seedShift(): array
    {
        $branch = Branch::create([
            'name' => 'Receipt Test Branch ' . uniqid(),
        ]);

        $drawer = CashDrawer::create([
            'branch_id' => $branch->id,
            'name'      => 'Drawer A',
            'is_active' => true,
        ]);

        $user = User::create([
            'name'           => 'Cashier ' . uniqid(),
            'email'          => 'receipt-' . uniqid() . '@example.com',
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

    public function test_v2_cash_checkout_opens_receipt_modal_for_the_new_order(): void
    {
        $context = $this->seedShift();

        $component = Livewire::actingAs($context['user'])
            ->test(PointOfSale::class)
            ->call('addToCart', $context['menu']->id)
            ->call('addToCart', $context['menu']->id) // qty 2 = 120
            ->call('reviewCheckout')
            ->call('checkout');

        $order = PosOrder::orderByDesc('id')->first();
        $this->assertNotNull($order);

        $component
            ->assertSet('showReceiptModal', true)
            ->assertSet('receiptOrderId', $order->id);
    }

    public function test_v2_receipt_html_contains_branch_total_and_cash_payment_line(): void
    {
        $context = $this->seedShift();

        $component = Livewire::actingAs($context['user'])->test(PointOfSale::class);
        $component->call('addToCart', $context['menu']->id);
        $component->call('addToCart', $context['menu']->id);
        $component->call('reviewCheckout');
        $component->call('checkout');

        $component
            ->assertSee(strtoupper($context['branch']->name), false)
            ->assertSee('Coke')
            ->assertSee('120.00')
            ->assertSee('CASH')
            ->assertSee('Order:')
            ->assertSee('id="pos-receipt-printable"', false);
    }

    public function test_v2_room_charge_receipt_shows_room_charge_line_not_cash(): void
    {
        $context = $this->seedShift();

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
            'number'      => '210',
            'status'      => 'Occupied',
            'is_priority' => true,
        ]);
        $guest = Guest::create([
            'branch_id'     => $context['branch']->id,
            'name'          => 'Pedro Alvarez',
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
            'check_in_at'   => Carbon::now()->subHour(),
            'check_out_at'  => Carbon::now()->addHours(11),
            'is_long_stay'  => false,
            'is_check_out'  => false,
        ]);

        $component = Livewire::actingAs($context['user'])->test(PointOfSale::class);
        $component->call('addToCart', $context['menu']->id);
        $component->call('toggleAttachToRoom');
        $component->call('selectGuest', $guest->id);
        $component->call('reviewCheckout');
        $component->call('checkout');

        $component
            ->assertSee('ROOM CHARGE')
            ->assertSee('RM 210')
            ->assertSee('Pedro Alvarez')
            ->assertSee('Will be settled at guest checkout.')
            ->assertDontSee('>CASH<', false);
    }

    public function test_close_receipt_modal_clears_state(): void
    {
        $context = $this->seedShift();

        Livewire::actingAs($context['user'])
            ->test(PointOfSale::class)
            ->call('addToCart', $context['menu']->id)
            ->call('reviewCheckout')
            ->call('checkout')
            ->assertSet('showReceiptModal', true)
            ->call('closeReceiptModal')
            ->assertSet('showReceiptModal', false)
            ->assertSet('receiptOrderId', null);
    }
}
