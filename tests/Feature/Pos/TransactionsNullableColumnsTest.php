<?php

namespace Tests\Feature\Pos;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TransactionsNullableColumnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_transactions_guest_room_floor_columns_are_nullable(): void
    {
        $columns = ['guest_id', 'room_id', 'floor_id'];

        foreach ($columns as $col) {
            $info = DB::selectOne(
                "SELECT IS_NULLABLE FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'transactions'
                   AND COLUMN_NAME = ?",
                [$col]
            );
            $this->assertNotNull($info, "transactions.{$col} not found");
            $this->assertSame('YES', $info->IS_NULLABLE, "transactions.{$col} must be nullable");
        }
    }
}
