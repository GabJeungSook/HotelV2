<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * B.3 — Void flow support.
     *
     *  - pos_orders.void_reason: free-text reason captured at void time.
     *  - transactions.voided_at / voided_by_user_id: lets folio queries
     *    filter out voided POS line items so a voided room-charge does
     *    not still appear on the guest's checkout bill.
     */
    public function up(): void
    {
        // Idempotent: staging may have a partially-applied state where
        // pos_orders.void_reason already exists. Only add what's missing.
        if (! Schema::hasColumn('pos_orders', 'void_reason')) {
            Schema::table('pos_orders', function (Blueprint $table) {
                $table->string('void_reason', 255)->nullable()->after('voided_by_user_id');
            });
        }

        Schema::table('transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('transactions', 'voided_at')) {
                $table->timestamp('voided_at')->nullable()->after('quantity');
            }
            if (! Schema::hasColumn('transactions', 'voided_by_user_id')) {
                $table->unsignedBigInteger('voided_by_user_id')->nullable()->after('voided_at');
            }
        });

        // Index added separately so we can guard it without touching the
        // column-add closure above.
        $indexes = collect(DB::select("SHOW INDEX FROM transactions WHERE Key_name = 'transactions_voided_at_index'"));
        if ($indexes->isEmpty()) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->index('voided_at');
            });
        }
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['voided_at']);
            $table->dropColumn(['voided_at', 'voided_by_user_id']);
        });

        Schema::table('pos_orders', function (Blueprint $table) {
            $table->dropColumn('void_reason');
        });
    }
};
