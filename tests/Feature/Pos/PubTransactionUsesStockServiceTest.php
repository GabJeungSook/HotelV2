<?php

namespace Tests\Feature\Pos;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PubTransactionUsesStockServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_pub_transaction_class_imports_stock_service_and_movement(): void
    {
        $code = file_get_contents(app_path('Http/Livewire/Pub/PubTransaction.php'));

        $this->assertStringContainsString(
            'use App\\Services\\Pos\\StockService;',
            $code,
            'Pub PubTransaction must import StockService'
        );
        $this->assertStringContainsString(
            'use App\\Services\\Pos\\InsufficientStockException;',
            $code,
            'Pub PubTransaction must import InsufficientStockException to handle stock-out errors'
        );
        $this->assertStringContainsString(
            'use App\\Models\\StockMovement;',
            $code,
            'Pub PubTransaction must import StockMovement'
        );
    }

    public function test_pub_addFood_calls_stock_service_in_real_mode(): void
    {
        $code = file_get_contents(app_path('Http/Livewire/Pub/PubTransaction.php'));

        $this->assertMatchesRegularExpression(
            '/StockService::class\)->out\(\s*StockMovement::SOURCE_PUB/',
            $code,
            'Pub addFood must call StockService::out with SOURCE_PUB'
        );
        $this->assertStringNotContainsString(
            "'shadow'    => true",
            $code,
            'Pub StockService call must no longer use shadow mode (legacy dual-write removed)'
        );
        $this->assertStringNotContainsString(
            "'shadow' => true",
            $code,
            'Pub StockService call must no longer use shadow mode (legacy dual-write removed)'
        );
        $this->assertStringContainsString(
            "'ref_type'  => 'transaction',",
            $code,
            'Pub stock movement must reference the transaction'
        );
    }

    public function test_pub_legacy_direct_inventory_update_is_removed(): void
    {
        $code = file_get_contents(app_path('Http/Livewire/Pub/PubTransaction.php'));

        $this->assertStringNotContainsString(
            "\$inventory->update([",
            $code,
            'Pub legacy direct inventory->update() must be removed; StockService is the single writer'
        );
        $this->assertStringNotContainsString(
            'number_of_serving' . "' => \$new_stock",
            $code,
            'Pub legacy $new_stock decrement assignment must be removed'
        );
    }

    public function test_pub_transaction_create_includes_snapshot_fields(): void
    {
        $code = file_get_contents(app_path('Http/Livewire/Pub/PubTransaction.php'));

        foreach ([
            "'source_type' => StockMovement::SOURCE_PUB",
            "'menu_id'     => \$food->id",
            "'item_name'   => \$food->name",
            "'unit_price'  => (int) \$food->price",
            "'quantity'    => \$this->food_quantity",
        ] as $snippet) {
            $this->assertStringContainsString(
                $snippet,
                $code,
                "Pub PubTransaction must include snapshot field: {$snippet}"
            );
        }
    }

    public function test_pub_addFood_handles_insufficient_stock_with_dialog_and_rollback(): void
    {
        $code = file_get_contents(app_path('Http/Livewire/Pub/PubTransaction.php'));

        $this->assertMatchesRegularExpression(
            '/catch\s*\(\s*InsufficientStockException\s+\$e\s*\)\s*{[^}]*DB::rollBack\(\)[^}]*Out Of Stock/s',
            $code,
            'Pub addFood must catch InsufficientStockException, rollback, and show the Out Of Stock dialog'
        );
        $this->assertMatchesRegularExpression(
            '/catch\s*\(\s*\\\\Throwable\s+\$e\s*\)\s*{[^}]*DB::rollBack\(\)/s',
            $code,
            'Pub addFood must defensively rollback on any other Throwable'
        );
    }

    public function test_pub_addFood_pre_checks_stock_before_opening_transaction(): void
    {
        $code = file_get_contents(app_path('Http/Livewire/Pub/PubTransaction.php'));

        $this->assertMatchesRegularExpression(
            '/\$inventory\s*===\s*null\s*\|\|.*number_of_serving.*<.*food_quantity/s',
            $code,
            'Pub addFood must pre-check (inventory null OR insufficient quantity) before DB::beginTransaction'
        );
    }
}
