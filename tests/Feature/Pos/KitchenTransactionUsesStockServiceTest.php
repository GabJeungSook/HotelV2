<?php

namespace Tests\Feature\Pos;

use App\Http\Livewire\Kitchen\Transaction as KitchenTransaction;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KitchenTransactionUsesStockServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_kitchen_transaction_class_imports_stock_service_and_movement(): void
    {
        $code = file_get_contents(app_path('Http/Livewire/Kitchen/Transaction.php'));

        $this->assertStringContainsString(
            'use App\\Services\\Pos\\StockService;',
            $code,
            'Kitchen Transaction must import StockService for shadow-write phase'
        );
        $this->assertStringContainsString(
            'use App\\Models\\StockMovement;',
            $code,
            'Kitchen Transaction must import StockMovement (for SOURCE constants)'
        );
    }

    public function test_kitchen_addFood_includes_shadow_stock_service_call(): void
    {
        $code = file_get_contents(app_path('Http/Livewire/Kitchen/Transaction.php'));

        $this->assertMatchesRegularExpression(
            '/StockService::class\)->out\(\s*StockMovement::SOURCE_KITCHEN/',
            $code,
            'Kitchen addFood must call StockService::out with SOURCE_KITCHEN'
        );
        $this->assertStringContainsString(
            "'shadow'    => true,",
            $code,
            'Kitchen StockService call must use shadow=true during shadow-write phase'
        );
        $this->assertStringContainsString(
            "'ref_type'  => 'transaction',",
            $code,
            'Kitchen shadow movement must reference the transaction'
        );
    }

    public function test_kitchen_transaction_create_includes_snapshot_fields(): void
    {
        $code = file_get_contents(app_path('Http/Livewire/Kitchen/Transaction.php'));

        foreach ([
            "'source_type' => StockMovement::SOURCE_KITCHEN",
            "'menu_id'     => \$food->id",
            "'item_name'   => \$food->name",
            "'unit_price'  => (int) \$food->price",
            "'quantity'    => \$this->food_quantity",
        ] as $snippet) {
            $this->assertStringContainsString(
                $snippet,
                $code,
                "Kitchen Transaction must include snapshot field: {$snippet}"
            );
        }
    }

    public function test_kitchen_shadow_failure_does_not_block_legacy_path(): void
    {
        $code = file_get_contents(app_path('Http/Livewire/Kitchen/Transaction.php'));

        $this->assertMatchesRegularExpression(
            '/try\s*{[^}]*StockService::class[^}]*}\s*catch\s*\(\s*\\\\Throwable/s',
            $code,
            'Kitchen shadow-write must be wrapped in try/catch on \\Throwable so audit cannot break the live flow'
        );
    }
}
