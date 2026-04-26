<?php

namespace Tests\Feature\Pos;

use App\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PosV2FeatureFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_branches_table_has_pos_v2_enabled_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('branches', 'pos_v2_enabled'),
            'branches.pos_v2_enabled column must exist for pilot rollout'
        );
    }

    public function test_pos_v2_enabled_defaults_to_false(): void
    {
        $row = DB::selectOne(
            "SELECT IS_NULLABLE, COLUMN_DEFAULT FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'branches'
               AND COLUMN_NAME = 'pos_v2_enabled'"
        );

        $this->assertNotNull($row, 'pos_v2_enabled column must be queryable from information_schema');
        // Default in MySQL is stored as the string '0' for boolean false.
        $this->assertContains((string) $row->COLUMN_DEFAULT, ['0', 'false', '0\b'], 'pos_v2_enabled must default to false');
    }

    public function test_pos_v2_enabled_casts_to_boolean(): void
    {
        $branch = Branch::create([
            'name' => 'Cast Test Branch',
            'address' => 'somewhere',
        ]);

        // Refresh to pick up the DB default (Branch::create only carries
        // attributes we pass; the default comes from the schema).
        $branch->refresh();

        $this->assertIsBool($branch->pos_v2_enabled, 'pos_v2_enabled must be cast to bool');
        $this->assertFalse($branch->pos_v2_enabled, 'New branch must have pos_v2_enabled=false by default');

        $branch->update(['pos_v2_enabled' => true]);
        $branch->refresh();
        $this->assertTrue($branch->pos_v2_enabled, 'pos_v2_enabled must round-trip true');
    }
}
