<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('transactions', 'order_id')) {
                $table->unsignedBigInteger('order_id')->nullable()->after('id');
            }
        });

        $hasIndex = collect(DB::select("SHOW INDEX FROM transactions WHERE Key_name = 'transactions_order_id_index'"))->isNotEmpty();
        if (! $hasIndex) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->index('order_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['order_id']);
            $table->dropColumn('order_id');
        });
    }
};
