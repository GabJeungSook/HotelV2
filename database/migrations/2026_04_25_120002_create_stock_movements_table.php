<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable();

            $table->string('source_type', 20);
            $table->unsignedBigInteger('menu_id');
            $table->unsignedBigInteger('inventory_id');

            $table->enum('type', ['IN', 'OUT', 'ADJUST', 'VOID', 'OPENING']);
            $table->decimal('quantity', 10, 2);
            $table->decimal('balance_after', 10, 2);

            $table->string('reason', 255)->nullable();
            $table->string('ref_type', 50)->nullable();
            $table->unsignedBigInteger('ref_id')->nullable();

            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('shift_log_id')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'menu_id']);
            $table->index('shift_log_id');
            $table->index(['branch_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
