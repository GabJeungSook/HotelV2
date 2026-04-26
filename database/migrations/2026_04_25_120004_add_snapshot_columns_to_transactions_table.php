<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('source_type', 20)->nullable()->after('transaction_type_id');
            $table->unsignedBigInteger('menu_id')->nullable()->after('source_type');
            $table->string('item_name', 255)->nullable()->after('menu_id');
            $table->integer('unit_price')->nullable()->after('item_name');
            $table->decimal('quantity', 10, 2)->nullable()->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['source_type', 'menu_id', 'item_name', 'unit_price', 'quantity']);
        });
    }
};
