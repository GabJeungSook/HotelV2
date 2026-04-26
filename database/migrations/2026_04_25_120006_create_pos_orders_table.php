<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('pos_orders')) {
            return;
        }
        Schema::create('pos_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('shift_log_id')->nullable();
            $table->unsignedBigInteger('guest_id')->nullable();
            $table->unsignedBigInteger('room_id')->nullable();

            $table->string('payment_method', 20)->nullable();
            $table->integer('subtotal')->default(0);
            $table->integer('discount_amount')->default(0);
            $table->string('discount_reason', 255)->nullable();
            $table->integer('total')->default(0);
            $table->integer('paid_amount')->default(0);
            $table->integer('change_amount')->default(0);

            $table->timestamp('voided_at')->nullable();
            $table->unsignedBigInteger('voided_by_user_id')->nullable();

            $table->timestamps();

            $table->index(['branch_id', 'created_at']);
            $table->index('shift_log_id');
            $table->index('guest_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_orders');
    }
};
