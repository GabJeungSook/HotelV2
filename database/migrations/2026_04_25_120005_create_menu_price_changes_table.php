<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('menu_price_changes')) {
            return;
        }
        Schema::create('menu_price_changes', function (Blueprint $table) {
            $table->id();
            $table->string('source_type', 20);
            $table->unsignedBigInteger('menu_id');
            $table->string('field', 50);
            $table->string('old_value', 255)->nullable();
            $table->string('new_value', 255)->nullable();
            $table->unsignedBigInteger('changed_by_user_id')->nullable();
            $table->string('reason', 255)->nullable();
            $table->timestamps();

            $table->index(['source_type', 'menu_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_price_changes');
    }
};
