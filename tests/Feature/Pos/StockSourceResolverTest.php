<?php

namespace Tests\Feature\Pos;

use App\Models\FrontdeskInventory;
use App\Models\Inventory;
use App\Models\PubInventory;
use App\Models\StockMovement;
use App\Services\Pos\StockSourceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockSourceResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_frontdesk_to_frontdesk_inventory_model(): void
    {
        $resolver = new StockSourceResolver();
        $this->assertSame(FrontdeskInventory::class, $resolver->modelFor(StockMovement::SOURCE_FRONTDESK));
    }

    public function test_resolves_kitchen_to_inventory_model(): void
    {
        $resolver = new StockSourceResolver();
        $this->assertSame(Inventory::class, $resolver->modelFor(StockMovement::SOURCE_KITCHEN));
    }

    public function test_resolves_pub_to_pub_inventory_model(): void
    {
        $resolver = new StockSourceResolver();
        $this->assertSame(PubInventory::class, $resolver->modelFor(StockMovement::SOURCE_PUB));
    }

    public function test_unknown_source_type_throws(): void
    {
        $resolver = new StockSourceResolver();
        $this->expectException(\InvalidArgumentException::class);
        $resolver->modelFor('mystery');
    }

    public function test_menu_foreign_key_for_each_source(): void
    {
        $resolver = new StockSourceResolver();
        $this->assertSame('frontdesk_menu_id', $resolver->menuForeignKey(StockMovement::SOURCE_FRONTDESK));
        $this->assertSame('menu_id',           $resolver->menuForeignKey(StockMovement::SOURCE_KITCHEN));
        $this->assertSame('pub_menu_id',       $resolver->menuForeignKey(StockMovement::SOURCE_PUB));
    }

    public function test_findInventory_returns_correct_row(): void
    {
        $fdi = FrontdeskInventory::create([
            'branch_id' => 1,
            'frontdesk_menu_id' => 99,
            'number_of_serving' => 5,
        ]);

        $resolver = new StockSourceResolver();
        $found = $resolver->findInventory(StockMovement::SOURCE_FRONTDESK, 99, 1);
        $this->assertNotNull($found);
        $this->assertEquals($fdi->id, $found->id);
    }

    public function test_findInventory_returns_null_when_missing(): void
    {
        $resolver = new StockSourceResolver();
        $this->assertNull($resolver->findInventory(StockMovement::SOURCE_KITCHEN, 999, 1));
    }
}
