<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Drop the pilot rollout flag — POS v2 becomes the only register flow.
     * The legacy pos_transactions table is preserved (historical data + model
     * stay readable); only the toggle and the v1 write path are removed.
     */
    public function up(): void
    {
        if (Schema::hasColumn('branches', 'pos_v2_enabled')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->dropColumn('pos_v2_enabled');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('branches', 'pos_v2_enabled')) {
            Schema::table('branches', function (Blueprint $table) {
                // Re-add as TRUE by default if rolled back — v2 is the system now.
                $table->boolean('pos_v2_enabled')->default(true)->after('force_auto_override');
            });
        }
    }
};
