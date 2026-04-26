<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable()->after('id');
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['order_id']);
            $table->dropColumn('order_id');
        });
    }
};
