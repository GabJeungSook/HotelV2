<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        Schema::table('pos_orders', function (Blueprint $table) {
            $table->string('void_reason', 255)->nullable()->after('voided_by_user_id');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->timestamp('voided_at')->nullable()->after('quantity');
            $table->unsignedBigInteger('voided_by_user_id')->nullable()->after('voided_at');
            $table->index('voided_at');
        });
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
