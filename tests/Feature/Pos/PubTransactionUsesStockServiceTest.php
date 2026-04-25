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
            'use App\\Models\\StockMovement;',
            $code,
            'Pub PubTransaction must import StockMovement'
        );
    }

    public function test_pub_addFood_includes_shadow_stock_service_call_with_pub_source(): void
    {
        $code = file_get_contents(app_path('Http/Livewire/Pub/PubTransaction.php'));

        $this->assertMatchesRegularExpression(
            '/StockService::class\)->out\(\s*StockMovement::SOURCE_PUB/',
            $code,
            'Pub addFood must call StockService::out with SOURCE_PUB'
        );
        $this->assertStringContainsString(
            "'shadow'    => true,",
            $code,
            'Pub StockService call must use shadow=true during shadow-write phase'
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

    public function test_pub_shadow_failure_does_not_block_legacy_path(): void
    {
        $code = file_get_contents(app_path('Http/Livewire/Pub/PubTransaction.php'));

        $this->assertMatchesRegularExpression(
            '/try\s*{[^}]*StockService::class[^}]*}\s*catch\s*\(\s*\\\\Throwable/s',
            $code,
            'Pub shadow-write must be wrapped in try/catch on \\Throwable'
        );
    }
}
