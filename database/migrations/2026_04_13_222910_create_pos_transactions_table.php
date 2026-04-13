<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('pos_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_log_id')->nullable()->constrained('shift_logs')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('frontdesk_menu_id')->constrained('frontdesk_menus')->cascadeOnDelete();
            $table->string('item_name');
            $table->decimal('price', 15, 2);
            $table->integer('quantity')->default(1);
            $table->decimal('total', 15, 2);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('pos_transactions');
    }
};
