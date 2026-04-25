<?php

namespace Tests\Feature\Pos;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TransactionsSnapshotColumnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_transactions_has_snapshot_columns(): void
    {
        foreach (['source_type', 'menu_id', 'item_name', 'unit_price', 'quantity'] as $col) {
            $this->assertTrue(
                Schema::hasColumn('transactions', $col),
                "transactions is missing snapshot column {$col}"
            );
        }
    }
}
