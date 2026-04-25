<?php

namespace Tests\Feature\Pos;

use App\Models\FrontdeskMenu;
use App\Models\Menu as KitchenMenu;
use App\Models\MenuPriceChange;
use App\Models\PubMenu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuPriceChangeAuditTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $user = User::create([
            'name'        => 'Test',
            'email'       => 'audit_'.uniqid().'@example.com',
            'password'    => bcrypt('secret'),
            'branch_id'   => 1,
            'branch_name' => 'Test Branch',
        ]);
        $this->actingAs($user);
        return $user;
    }

    public function test_frontdesk_menu_price_change_writes_audit_row(): void
    {
        $user = $this->actingUser();

        $menu = FrontdeskMenu::create([
            'branch_id' => 1, 'frontdesk_category_id' => 1,
            'name' => 'Coke', 'price' => '60',
        ]);

        $menu->update(['price' => '65']);

        $change = MenuPriceChange::where('source_type', 'frontdesk')
            ->where('menu_id', $menu->id)
            ->where('field', 'price')
            ->first();

        $this->assertNotNull($change);
        $this->assertSame('60', (string) $change->old_value);
        $this->assertSame('65', (string) $change->new_value);
        $this->assertSame($user->id, $change->changed_by_user_id);
    }

    public function test_kitchen_menu_price_change_writes_audit_row(): void
    {
        $this->actingUser();

        $menu = KitchenMenu::create([
            'branch_id' => 1, 'menu_category_id' => 1,
            'name' => 'Burger', 'price' => 100,
        ]);

        $menu->update(['price' => 110]);

        $this->assertSame(1, MenuPriceChange::where('source_type', 'kitchen')->where('field', 'price')->count());
    }

    public function test_pub_menu_price_change_writes_audit_row(): void
    {
        $this->actingUser();

        $menu = PubMenu::create([
            'branch_id' => 1, 'pub_category_id' => 1,
            'name' => 'Beer', 'price' => 75,
        ]);

        $menu->update(['price' => 80]);

        $this->assertSame(1, MenuPriceChange::where('source_type', 'pub')->where('field', 'price')->count());
    }

    public function test_name_change_is_audited(): void
    {
        $this->actingUser();

        $menu = FrontdeskMenu::create([
            'branch_id' => 1, 'frontdesk_category_id' => 1,
            'name' => 'Old', 'price' => '50',
        ]);

        $menu->update(['name' => 'New']);

        $this->assertSame(1, MenuPriceChange::where('field', 'name')->count());
        $this->assertSame(0, MenuPriceChange::where('field', 'price')->count());
    }

    public function test_no_audit_row_when_unrelated_field_changes(): void
    {
        $this->actingUser();

        $menu = FrontdeskMenu::create([
            'branch_id' => 1, 'frontdesk_category_id' => 1,
            'name' => 'X', 'price' => '50',
        ]);

        $menu->update(['frontdesk_category_id' => 2]);

        $this->assertSame(0, MenuPriceChange::count(), 'category change must not be audited');
    }
}
